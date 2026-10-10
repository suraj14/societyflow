<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use App\Models\Event;
use App\Models\Society;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Event announcements must say what really happened, to the right people, once.
 *
 *  - an event is announced on creation only when it is actually scheduled;
 *  - cancelling / rescheduling / editing sends its OWN message (the old update
 *    path re-sent "A new event has been created", so a cancellation read as an
 *    announcement, and every save - even one that changed nothing - emailed
 *    everyone again);
 *  - owners of the event's society only; tenants, staff and other societies not;
 *  - the email goes through the real job and is logged `sent` (a faked dispatch
 *    would not have caught a missing template or queue).
 */
class EventNotificationTest extends TestCase
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
        $this->society = $this->makeSociety('Eden');
        $this->admin = $this->makeUserWithRole($this->society, 'Admin');
        $this->owner = $this->makeUserWithRole($this->society, 'Owner');
        $this->tenant = $this->makeUserWithRole($this->society, 'Tenant');
        $this->foreignOwner = $this->makeUserWithRole($this->makeSociety('Other'), 'Owner');
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

        return Event::where('event_name', $over['event_name'] ?? 'Diwali get-together')->latest('id')->firstOrFail();
    }

    private function update(Event $event, array $over): void
    {
        $this->actingAs($this->admin)->put(route('events.update', $event), $this->payload($over))->assertRedirect(route('events.index'));
    }

    private function titles(User $user): array
    {
        return $user->fresh()->notifications()->pluck('data')->map(fn ($d) => $d['title'])->sort()->values()->all();
    }

    private function mailsTo(User $user, string $action = null)
    {
        return EmailLog::where('recipient_email', $user->email)->where('status', 'sent')
            ->when($action, fn ($q) => $q->where('trigger_action', $action));
    }

    // ---------------------------------------------------------------- create

    public function test_a_scheduled_event_is_announced_to_owners_of_that_society_only(): void
    {
        $event = $this->create();

        $this->assertSame(1, $this->mailsTo($this->owner)->count(), 'owner emailed');
        $this->assertSame(['New society event'], $this->titles($this->owner));
        $this->assertSame(0, EmailLog::where('recipient_email', $this->tenant->email)->count(), 'tenant is not told');
        $this->assertSame(0, EmailLog::where('recipient_email', $this->foreignOwner->email)->count(), 'other society is not told');
        $this->assertSame([], $this->titles($this->foreignOwner));
        $this->assertNotNull($event);
    }

    /** @dataProvider unscheduledStatuses */
    public function test_an_event_created_already_cancelled_or_completed_is_not_announced(string $status): void
    {
        $this->create(['status' => $status, 'start_date' => '2020-01-01', 'end_date' => '2020-01-01']);

        $this->assertSame(0, EmailLog::count());
        $this->assertSame([], $this->titles($this->owner));
    }

    public static function unscheduledStatuses(): array
    {
        return [['cancelled'], ['completed']];
    }

    // ---------------------------------------------------------------- change

    public function test_rescheduling_sends_a_rescheduled_message_not_a_new_event_message(): void
    {
        $event = $this->create();
        EmailLog::query()->delete();
        $this->owner->notifications()->delete();

        $this->update($event, ['start_date' => '2030-11-02', 'end_date' => '2030-11-02']);

        $mail = $this->mailsTo($this->owner, 'event_changed')->sole();
        $this->assertStringContainsString('rescheduled', json_encode($mail->variables));
        $this->assertStringContainsString('02 Nov 2030', json_encode($mail->variables));
        $this->assertSame(['Event rescheduled'], $this->titles($this->owner));
        $this->assertSame(0, $this->mailsTo($this->owner, 'event_created')->count(), 'no second "new event" email');
        $this->assertSame(0, EmailLog::where('recipient_email', $this->tenant->email)->count());
        $this->assertSame(0, EmailLog::where('recipient_email', $this->foreignOwner->email)->count());
    }

    public function test_cancelling_sends_a_cancelled_message(): void
    {
        $event = $this->create();
        EmailLog::query()->delete();
        $this->owner->notifications()->delete();

        $this->update($event, ['status' => 'cancelled']);

        $mail = $this->mailsTo($this->owner, 'event_changed')->sole();
        $this->assertStringContainsString('has been cancelled', json_encode($mail->variables));
        $this->assertSame(['Event cancelled'], $this->titles($this->owner));
    }

    public function test_editing_the_location_sends_an_updated_message(): void
    {
        $event = $this->create();
        EmailLog::query()->delete();
        $this->owner->notifications()->delete();

        $this->update($event, ['location' => 'Garden lawn']);

        $this->assertSame(['Event updated'], $this->titles($this->owner));
        $this->assertSame(1, $this->mailsTo($this->owner, 'event_changed')->count());
    }

    public function test_saving_with_nothing_changed_notifies_nobody(): void
    {
        $event = $this->create();
        EmailLog::query()->delete();
        $this->owner->notifications()->delete();

        $this->update($event, []);
        $this->update($event, []);

        $this->assertSame(0, EmailLog::count());
        $this->assertSame([], $this->titles($this->owner));
    }

    public function test_the_same_change_is_never_emailed_twice(): void
    {
        $event = $this->create();
        EmailLog::query()->delete();

        $this->update($event, ['start_date' => '2030-11-02', 'end_date' => '2030-11-02']);
        $this->update($event, ['start_date' => '2030-11-02', 'end_date' => '2030-11-02']);   // identical re-save

        $this->assertSame(1, $this->mailsTo($this->owner, 'event_changed')->count());

        // A DIFFERENT second change still goes out.
        $this->update($event, ['start_date' => '2030-11-05', 'end_date' => '2030-11-05']);
        $this->assertSame(2, $this->mailsTo($this->owner, 'event_changed')->count());
    }

    public function test_two_different_edits_that_keep_the_same_start_time_are_both_emailed(): void
    {
        $event = $this->create();
        EmailLog::query()->delete();

        $this->travel(2)->seconds();
        $this->update($event, ['location' => 'Garden lawn']);
        $this->travel(2)->seconds();
        $this->update($event, ['location' => 'Terrace']);

        $this->assertSame(2, $this->mailsTo($this->owner, 'event_changed')->count(), 'a later edit is a new message, not a duplicate');
    }

    public function test_the_same_change_delivered_twice_is_emailed_once(): void
    {
        // e.g. a listener retried by the queue: the job key must make it a no-op.
        $event = $this->create();
        EmailLog::query()->delete();
        $event->update(['start_date' => '2030-11-02 18:00:00', 'end_date' => '2030-11-02 21:00:00']);
        $event = $event->fresh();

        \App\Events\EventChanged::dispatch($event, 'rescheduled');
        \App\Events\EventChanged::dispatch($event, 'rescheduled');

        $this->assertSame(1, $this->mailsTo($this->owner, 'event_changed')->count());
    }

    public function test_an_event_that_finished_or_is_already_cancelled_announces_nothing_more(): void
    {
        $event = $this->create(['start_date' => '2020-01-01', 'end_date' => '2020-01-01']);
        EmailLog::query()->delete();
        $this->owner->notifications()->delete();

        $this->update($event, ['start_date' => '2020-01-01', 'end_date' => '2020-01-01', 'status' => 'completed']);
        $this->assertSame(0, EmailLog::count(), 'finishing is not news');

        $other = $this->create(['event_name' => 'Cancelled already']);
        $this->update($other, ['event_name' => 'Cancelled already', 'status' => 'cancelled']);
        EmailLog::query()->delete();
        $this->owner->notifications()->delete();
        $this->update($other, ['event_name' => 'Cancelled already', 'status' => 'cancelled', 'location' => 'Elsewhere']);
        $this->assertSame(0, EmailLog::count(), 'editing a cancelled event does not re-announce it');
        $this->assertSame([], $this->titles($this->owner));
    }

    public function test_a_role_restricted_event_change_reaches_only_those_roles(): void
    {
        $other = $this->makeUserWithRole($this->society, 'Apartment Owner');
        $event = $this->create(['is_role_based' => 1, 'visible_roles' => ['Apartment Owner']]);
        EmailLog::query()->delete();

        $this->update($event, ['is_role_based' => 1, 'visible_roles' => ['Apartment Owner'], 'location' => 'Hall B']);

        $this->assertSame(1, $this->mailsTo($other, 'event_changed')->count());
        $this->assertSame(0, EmailLog::where('recipient_email', $this->owner->email)->count(), 'plain Owner is outside the audience');
    }

    public function test_the_change_template_exists_and_is_registered_so_it_can_never_be_dropped_silently(): void
    {
        $this->assertContains('event_changed', \App\Enums\EmailEvent::values());
        $this->assertTrue(\App\Models\EmailTemplate::where('trigger_event', 'event_changed')->whereNull('society_id')->where('status', 'active')->exists());
        $this->assertArrayHasKey('event.changed', \App\Support\NotificationEvents::all());
    }
}
