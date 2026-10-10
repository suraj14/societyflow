<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppNotification;
use App\Models\Event;
use App\Models\Society;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * WhatsApp for events fits the existing product rule: it is sent only when the
 * society has attached an approved template to the `event` purpose (Settings ->
 * WhatsApp) AND WhatsApp is on; otherwise it is skipped silently. Same audience
 * as the email and in-app messages, each message queued once, and never any
 * network: the real send path is exercised through a captured fake transport.
 *
 * (SMS is deliberately not wired: it runs through one generic DLT template
 * behind toggles that societies already use for notices, so reusing it would
 * silently start texting owners about every event.)
 */
class EventWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;
    private User $admin;
    private User $owner;
    private User $tenant;
    private User $foreignOwner;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Cache::flush();
        $this->society = $this->makeSociety('Eden');
        $this->admin = $this->makeUserWithRole($this->society, 'Admin');
        $this->owner = $this->makeUserWithRole($this->society, 'Owner', ['name' => 'Suraj', 'phone' => '9000000011']);
        $this->tenant = $this->makeUserWithRole($this->society, 'Tenant', ['phone' => '9000000012']);
        $this->foreignOwner = $this->makeUserWithRole($this->makeSociety('Other'), 'Owner', ['phone' => '9000000013']);
    }

    protected function tearDown(): void
    {
        WhatsAppService::$transportOverride = null;
        parent::tearDown();
    }

    private function enableWhatsApp(Society $society, bool $withTemplate = true, bool $on = true): void
    {
        $set = fn ($k, $v, $t = 'string') => SystemSetting::set('whatsapp', $k, $v, $t, $society->id);
        $set('whatsapp_enabled', $on ? '1' : '0', 'boolean');
        $set('whatsapp_api_key', 'test-key-not-real');
        $set('whatsapp_phone_number_id', '1234567890');
        if ($withTemplate) {
            $set('whatsapp_event_template_name', 'event_update_v1');
        }
    }

    private function payload(array $over = []): array
    {
        return array_merge([
            'event_name' => 'Diwali get-together', 'location' => 'Clubhouse', 'description' => 'Sweets and lights',
            'start_date' => '2030-10-30', 'start_time' => '18:00', 'end_date' => '2030-10-30', 'end_time' => '21:00',
            'status' => 'pending', 'is_role_based' => 0,
        ], $over);
    }

    private function create(array $over = []): Event
    {
        $this->actingAs($this->admin)->post(route('events.store'), $this->payload($over))->assertRedirect(route('events.index'));

        return Event::latest('id')->firstOrFail();
    }

    private function update(Event $event, array $over): void
    {
        $this->actingAs($this->admin)->put(route('events.update', $event), $this->payload($over))->assertRedirect(route('events.index'));
    }

    private function pushed(): array
    {
        return Bus::dispatchedSync(SendWhatsAppNotification::class) ?: [];
    }

    // ---------------------------------------------------------------- gating

    public function test_nothing_is_queued_without_an_attached_template_or_when_whatsapp_is_off(): void
    {
        Bus::fake();

        $this->enableWhatsApp($this->society, withTemplate: false);
        $this->create();
        Bus::assertNotDispatched(SendWhatsAppNotification::class);

        $this->enableWhatsApp($this->society, withTemplate: true, on: false);
        $this->create(['event_name' => 'Second']);
        Bus::assertNotDispatched(SendWhatsAppNotification::class);
    }

    public function test_a_new_event_reaches_only_the_owners_of_that_society(): void
    {
        Bus::fake();
        $this->enableWhatsApp($this->society);

        $this->create();

        Bus::assertDispatchedTimes(SendWhatsAppNotification::class, 1);
        Bus::assertDispatched(SendWhatsAppNotification::class, fn ($j) => $j->purpose === 'event' && $j->phone === '9000000011'
            && $j->societyId === $this->society->id
            && $j->values['event_status'] === 'New event' && $j->values['event_name'] === 'Diwali get-together'
            && $j->values['resident_name'] === 'Suraj' && $j->values['society_name'] === 'Eden');
    }

    public function test_an_owner_without_a_phone_is_skipped_and_a_role_restricted_event_stays_restricted(): void
    {
        Bus::fake();
        $this->enableWhatsApp($this->society);
        $noPhone = $this->makeUserWithRole($this->society, 'Owner', ['phone' => null]);
        $apartment = $this->makeUserWithRole($this->society, 'Apartment Owner', ['phone' => '9000000014']);

        $this->create(['is_role_based' => 1, 'visible_roles' => ['Apartment Owner']]);

        Bus::assertDispatchedTimes(SendWhatsAppNotification::class, 1);
        Bus::assertDispatched(SendWhatsAppNotification::class, fn ($j) => $j->phone === '9000000014');
        $this->assertNotNull($noPhone);
        $this->assertNotNull($apartment);
    }

    public function test_an_event_created_already_cancelled_is_not_announced(): void
    {
        Bus::fake();
        $this->enableWhatsApp($this->society);

        $this->create(['status' => 'cancelled']);

        Bus::assertNotDispatched(SendWhatsAppNotification::class);
    }

    // ---------------------------------------------------------------- changes

    public function test_reschedule_cancel_and_update_each_say_what_happened(): void
    {
        Bus::fake();
        $this->enableWhatsApp($this->society);
        $event = $this->create();

        $statuses = [];
        $step = function (array $over) use (&$statuses, $event) {
            $this->travel(2)->seconds();
            Bus::fake();   // reset the recorder for this step
            $this->update($event, $over);
            $sent = Bus::dispatched(SendWhatsAppNotification::class);
            $statuses[] = $sent->map(fn ($j) => $j->values['event_status'])->all();
        };

        $step(['start_date' => '2030-11-02', 'end_date' => '2030-11-02']);                  // reschedule
        $step(['start_date' => '2030-11-02', 'end_date' => '2030-11-02', 'location' => 'Lawn']);   // update
        $step(['start_date' => '2030-11-02', 'end_date' => '2030-11-02', 'location' => 'Lawn']);   // no-op
        $step(['start_date' => '2030-11-02', 'end_date' => '2030-11-02', 'location' => 'Lawn', 'status' => 'cancelled']);

        $this->assertSame([['Rescheduled'], ['Updated'], [], ['Cancelled']], $statuses);
    }

    public function test_the_same_change_delivered_twice_is_queued_once_but_a_new_edit_is_queued_again(): void
    {
        Bus::fake();
        $this->enableWhatsApp($this->society);
        $event = $this->create();
        Bus::fake();

        $event->update(['start_date' => '2030-11-02 18:00:00', 'end_date' => '2030-11-02 21:00:00']);
        $event = $event->fresh();
        \App\Events\EventChanged::dispatch($event, 'rescheduled');
        \App\Events\EventChanged::dispatch($event, 'rescheduled');
        Bus::assertDispatchedTimes(SendWhatsAppNotification::class, 1);

        // A later, different save of the same kind is a new message.
        $this->travel(5)->seconds();
        $event->update(['location' => 'Lawn']);
        \App\Events\EventChanged::dispatch($event->fresh(), 'updated');
        Bus::assertDispatchedTimes(SendWhatsAppNotification::class, 2);
    }

    // ---------------------------------------------------------------- the real send path, fake transport

    public function test_the_queued_job_builds_the_template_message_and_never_touches_the_network(): void
    {
        $this->enableWhatsApp($this->society);
        $captured = [];
        WhatsAppService::$transportOverride = function ($url, $data, $headers) use (&$captured) {
            $captured[] = ['url' => $url, 'data' => $data];

            return [['messages' => [['id' => 'wamid.FAKE']]], 200];
        };

        // Real job, real service, fake transport (config('messaging.enabled') is false suite-wide).
        $this->create();

        $this->assertCount(1, $captured, 'one message, to the one owner');
        $this->assertStringContainsString('1234567890', $captured[0]['url']);
        $this->assertSame('template', $captured[0]['data']['type']);
        $this->assertSame('event_update_v1', $captured[0]['data']['template']['name']);
        $this->assertStringEndsWith('9000000011', (string) $captured[0]['data']['to']);
        $texts = collect($captured[0]['data']['template']['components'][0]['parameters'] ?? [])->pluck('text')->implode('|');
        $this->assertStringContainsString('Diwali get-together', $texts);
        $this->assertStringContainsString('New event', $texts);
    }

    public function test_a_provider_failure_never_breaks_the_events_screen(): void
    {
        $this->enableWhatsApp($this->society);
        WhatsAppService::$transportOverride = fn () => [['error' => ['message' => 'template not approved']], 400];

        $this->actingAs($this->admin)->post(route('events.store'), $this->payload())->assertRedirect(route('events.index'));

        $this->assertSame(1, Event::count(), 'the event is saved regardless of the provider');
    }

    public function test_another_society_with_its_own_template_hears_only_about_its_own_events(): void
    {
        Bus::fake();
        $other = $this->foreignOwner->society;
        $this->enableWhatsApp($this->society);
        $this->enableWhatsApp($other);

        $this->create();

        Bus::assertDispatchedTimes(SendWhatsAppNotification::class, 1);
        Bus::assertNotDispatched(SendWhatsAppNotification::class, fn ($j) => $j->societyId === $other->id || $j->phone === '9000000013');
    }
}
