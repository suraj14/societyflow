<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\WhatsAppTemplate;
use App\Services\Fast2SmsAccount;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Message Templates -> WhatsApp, entered by hand: the admin copies an approved template from the provider portal and
 * pastes it in (name, Fast2SMS Message ID, language, text, which value fills each {{n}}). Create / read / edit /
 * switch on and off / delete, all against the database, without ever contacting the provider - and the template the
 * admin made active is the one the real notification uses.
 */
class WhatsAppTemplateCrudTest extends TestCase
{
    use RefreshDatabase;

    private $society;
    private $admin;
    private array $requests = [];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();   // any real HTTP call would be recorded below and fail the "no provider call" tests
        $this->society = $this->makeSociety('Alpha');
        $this->admin = $this->makeUserWithRole($this->society, 'Admin');
        WhatsAppService::$transportOverride = function (string $url) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
            $this->requests[] = $q + ['_path' => parse_url($url, PHP_URL_PATH)];

            return [['return' => true, 'message' => ['Whatsapp Message sent successfully.'], 'request_id' => 'r'], 200];
        };
        Fast2SmsAccount::$transportOverride = function () { $this->requests[] = ['_account_call' => true]; return [['success' => true], 200]; };
    }

    protected function tearDown(): void
    {
        WhatsAppService::$transportOverride = null;
        Fast2SmsAccount::$transportOverride = null;
        parent::tearDown();
    }

    private function wa(string $key, $value, string $type = 'string'): void
    {
        SystemSetting::set('whatsapp', $key, $value, $type, $this->society->id);
    }

    private function form(array $over = []): array
    {
        return $over + [
            'name' => 'Visitor approved', 'purpose' => 'visitor', 'template_name' => 'visitor_approved_v2', 'message_id' => '8340', 'language' => 'en', 'status' => 'active',
            'body_text' => 'Hello {{1}}, your visitor {{2}} was approved for {{3}}.', 'variables' => ['resident_name', 'visitor_name', 'visit_date'],
        ];
    }

    private function create(array $over = [], $as = null)
    {
        return $this->actingAs($as ?? $this->admin)->post(route('admin.message-templates.whatsapp.store'), $this->form($over));
    }

    private function makeOne(array $over = []): WhatsAppTemplate
    {
        $this->create($over)->assertRedirect(route('admin.message-templates.index', ['channel' => 'whatsapp']));

        return WhatsAppTemplate::orderByDesc('id')->firstOrFail();
    }

    private function loaded(WhatsAppTemplate $t): string
    {
        return (string) $t->fresh()->updated_at->timestamp;
    }

    // ── create / read ───────────────────────────────────────────────────────

    public function test_a_pasted_template_is_saved_with_every_field_and_listed_after_a_reload(): void
    {
        $t = $this->makeOne();

        $this->assertSame($this->society->id, $t->society_id);
        $this->assertSame('Visitor approved', $t->friendly_name);
        $this->assertSame('visitor', $t->purpose);
        $this->assertSame('visitor_approved_v2', $t->name);
        $this->assertSame('8340', $t->message_id);
        $this->assertSame('en', $t->language);
        $this->assertSame(3, $t->var_count);
        $this->assertSame(['resident_name', 'visitor_name', 'visit_date'], $t->variable_map);
        $this->assertSame('Hello {{1}}, your visitor {{2}} was approved for {{3}}.', $t->body_text, 'the pasted text is stored exactly');
        $this->assertTrue($t->is_active);
        $this->assertSame('manual', $t->source);

        $html = $this->actingAs($this->admin)->get(route('admin.message-templates.index', ['channel' => 'whatsapp']))->assertOk()->getContent();
        $this->assertStringContainsString('Visitor approved', $html);
        $this->assertStringContainsString('Message ID: 8340', $html);
        $this->assertStringContainsString('Hello {{1}}, your visitor {{2}}', $html);
        $this->assertStringContainsString('3 variables', $html);
        $this->assertStringContainsString('Active', $html);
    }

    public function test_a_saved_template_is_not_called_provider_approved(): void
    {
        $t = $this->makeOne();

        $this->assertFalse($t->isVerified(), 'typing a name in does not make it approved');
        $this->assertSame('LOCAL', $t->status);
        $html = $this->actingAs($this->admin)->get(route('admin.message-templates.index', ['channel' => 'whatsapp']))->getContent();
        $this->assertStringContainsString('Provider: Not checked', $html);
        $this->assertStringNotContainsString('Provider: Approved', $html);
    }

    public function test_the_edit_form_loads_the_selected_record_and_the_new_form_opens(): void
    {
        $t = $this->makeOne();

        $this->actingAs($this->admin)->get(route('admin.message-templates.whatsapp.create'))->assertOk()->assertSee('Approved Template Name')->assertSee('Fast2SMS WhatsApp Message ID');
        $this->actingAs($this->admin)->get(route('admin.message-templates.whatsapp.edit', $t))->assertOk()->assertSee('visitor_approved_v2')->assertSee('8340')
            ->assertSee('Hello {{1}}, your visitor', false)->assertSee('value="' . $t->updated_at->timestamp . '"', false);
    }

    // ── update ──────────────────────────────────────────────────────────────

    public function test_editing_updates_the_same_record(): void
    {
        $t = $this->makeOne();

        $this->actingAs($this->admin)->put(route('admin.message-templates.whatsapp.update', $t), $this->form([
            'name' => 'Visitor OK', 'message_id' => '9001', 'language' => 'en_US', 'body_text' => 'Hi {{1}}, {{2}} arrived.', 'variables' => ['resident_name', 'visitor_name'],
            'loaded' => $this->loaded($t),
        ]))->assertRedirect();

        $this->assertSame(1, WhatsAppTemplate::count(), 'updated in place, not duplicated');
        $t->refresh();
        $this->assertSame('Visitor OK', $t->friendly_name);
        $this->assertSame('9001', $t->message_id);
        $this->assertSame('en_US', $t->language);
        $this->assertSame(2, $t->var_count);
        $this->assertSame(['resident_name', 'visitor_name'], $t->variable_map);
    }

    public function test_a_stale_form_does_not_overwrite_a_newer_save(): void
    {
        $t = $this->makeOne();
        $stale = $this->loaded($t);
        \Carbon\Carbon::setTestNow(now()->addMinute());
        $t->forceFill(['friendly_name' => 'Changed by someone else'])->save();

        $this->actingAs($this->admin)->put(route('admin.message-templates.whatsapp.update', $t), $this->form(['name' => 'My old edit', 'loaded' => $stale]))
            ->assertSessionHasErrors('name');

        \Carbon\Carbon::setTestNow();
        $this->assertSame('Changed by someone else', $t->fresh()->friendly_name);
    }

    // ── on / off ────────────────────────────────────────────────────────────

    public function test_active_and_inactive_persist_and_one_template_per_type_is_active(): void
    {
        $a = $this->makeOne(['name' => 'First']);
        $b = $this->makeOne(['name' => 'Second', 'template_name' => 'visitor_second']);

        $this->assertFalse($a->fresh()->is_active, 'activating the second switched the first off');
        $this->assertTrue($b->fresh()->is_active);

        $this->actingAs($this->admin)->post(route('admin.message-templates.whatsapp.toggle', $b))->assertRedirect();
        $this->assertFalse($b->fresh()->is_active);
        $this->actingAs($this->admin)->post(route('admin.message-templates.whatsapp.toggle', $a))->assertRedirect();
        $this->assertTrue($a->fresh()->is_active);
        $this->assertFalse($b->fresh()->is_active);

        // a different type is independent
        $c = $this->makeOne(['name' => 'Bill', 'purpose' => 'bill', 'template_name' => 'new_bill', 'body_text' => 'Bill {{1}} due {{2}}', 'variables' => ['amount', 'due_date']]);
        $this->assertTrue($c->fresh()->is_active);
        $this->assertTrue($a->fresh()->is_active);
    }

    public function test_it_can_be_saved_inactive_and_stays_inactive(): void
    {
        $t = $this->makeOne(['status' => 'inactive']);

        $this->assertFalse($t->is_active);
        $this->assertNull(WhatsAppTemplate::forPurpose($this->society->id, 'visitor'));
    }

    // ── delete ──────────────────────────────────────────────────────────────

    public function test_an_inactive_template_is_deleted_but_an_active_one_must_be_switched_off_first(): void
    {
        $t = $this->makeOne();

        $this->actingAs($this->admin)->delete(route('admin.message-templates.whatsapp.destroy', $t))->assertRedirect()->assertSessionHas('error');
        $this->assertNotNull($t->fresh(), 'the template a notification is using is not deleted from under it');

        $this->actingAs($this->admin)->post(route('admin.message-templates.whatsapp.toggle', $t));
        $this->actingAs($this->admin)->delete(route('admin.message-templates.whatsapp.destroy', $t))->assertRedirect()->assertSessionHas('success');
        $this->assertNull(WhatsAppTemplate::find($t->id));
    }

    // ── validation ──────────────────────────────────────────────────────────

    public function test_required_fields_and_identifiers_are_validated(): void
    {
        $this->create(['name' => '', 'template_name' => '', 'body_text' => ''])->assertSessionHasErrors(['name', 'template_name', 'body_text']);
        $this->create(['template_name' => 'has spaces!'])->assertSessionHasErrors('template_name');
        $this->create(['message_id' => 'abc'])->assertSessionHasErrors('message_id');
        $this->create(['message_id' => '12345678901234567890'])->assertSessionHasErrors('message_id');
        $this->create(['language' => 'English'])->assertSessionHasErrors('language');
        $this->create(['purpose' => 'nonsense'])->assertSessionHasErrors('purpose');
        $this->create(['status' => 'maybe'])->assertSessionHasErrors('status');
        $this->create(['body_text' => str_repeat('x', 1025), 'variables' => []])->assertSessionHasErrors('body_text');
        $this->assertSame(0, WhatsAppTemplate::count());
    }

    public function test_the_variables_must_match_the_text_in_number_order_and_availability(): void
    {
        // a named placeholder is not a WhatsApp variable
        $this->create(['body_text' => 'Hello {{name}}', 'variables' => []])->assertSessionHasErrors('body_text');
        // numbered with a gap
        $this->create(['body_text' => 'Hello {{1}} and {{3}}', 'variables' => ['resident_name', 'visitor_name']])->assertSessionHasErrors('body_text');
        // a value must be chosen for every variable
        $this->create(['body_text' => 'Hello {{1}} {{2}}', 'variables' => ['resident_name']])->assertSessionHasErrors('variables');
        // only values this notification really has
        $this->create(['variables' => ['resident_name', 'visitor_name', 'amount']])->assertSessionHasErrors('variables');
        // more variables than the notification can fill (a visitor notification has 5 values: it gained the society phone)
        $this->create(['body_text' => '{{1}} {{2}} {{3}} {{4}} {{5}} {{6}}', 'variables' => ['resident_name', 'visitor_name', 'visit_date', 'society_name', 'society_phone', 'resident_name']])->assertSessionHasErrors('body_text');
        // a repeated number is one variable
        $t = $this->makeOne(['body_text' => 'Hi {{1}}, yes {{1}}. {{2}}', 'variables' => ['resident_name', 'visitor_name']]);
        $this->assertSame(2, $t->var_count);
    }

    public function test_pasting_a_template_without_choosing_variables_uses_the_notifications_own_order(): void
    {
        $this->enableSending();
        // the basic paste-and-save flow submits no variable choices at all
        $form = $this->form(['body_text' => 'Hi {{1}}, {{2}} on {{3}}, {{4}}', 'message_id' => '']);
        unset($form['variables']);
        $this->actingAs($this->admin)->post(route('admin.message-templates.whatsapp.store'), $form)->assertSessionHasNoErrors();

        $t = WhatsAppTemplate::firstOrFail();
        $this->assertSame('Hi {{1}}, {{2}} on {{3}}, {{4}}', $t->body_text, 'placeholders are not renumbered or rewritten');
        $this->assertSame(['resident_name', 'visitor_name', 'visit_date', 'society_name'], $t->variable_map);

        // no Message ID: sent by approved name + language, values in placeholder order from the real event
        $sent = [];
        WhatsAppService::$transportOverride = function (string $url, array $data) use (&$sent) {
            $sent[] = $data;

            return [['messages' => [['id' => 'wamid.X']]], 200];
        };
        $r = $this->send();
        $this->assertTrue($r['success']);
        $payload = $sent[0];
        $this->assertSame('visitor_approved_v2', $payload['template']['name'] ?? null);
        $this->assertSame('en', $payload['template']['language']['code'] ?? null);
        $this->assertSame(['Asha', 'Ravi', '12 Nov', 'Alpha'], array_column($payload['template']['components'][0]['parameters'], 'text'));
    }

    public function test_each_card_shows_the_approved_name_and_who_receives_it(): void
    {
        $this->makeOne();

        $html = $this->actingAs($this->admin)->get(route('admin.message-templates.index', ['channel' => 'whatsapp']))->assertOk()->getContent();
        $this->assertStringContainsString('visitor_approved_v2', $html);
        $this->assertStringContainsString('The resident being visited', $html);
        $this->assertStringContainsString('Provider: Not checked', $html);
    }

    public function test_a_template_with_no_variables_is_fine(): void
    {
        $t = $this->makeOne(['body_text' => 'Your visitor has arrived.', 'variables' => []]);

        $this->assertSame(0, $t->var_count);
        $this->assertSame([], $t->variableFields());
    }

    public function test_the_preview_fills_numbers_with_examples_without_changing_the_saved_text(): void
    {
        $t = $this->makeOne();

        $this->assertSame('Hello Asha Rao, your visitor Ravi (courier) was approved for 15 Oct, 10:00 AM.', $t->preview());
        $this->assertSame('Hello {{1}}, your visitor {{2}} was approved for {{3}}.', $t->fresh()->body_text);
    }

    // ── authorization and isolation ─────────────────────────────────────────

    public function test_only_an_admin_can_reach_the_actions(): void
    {
        $t = $this->makeOne();
        foreach (['Tenant', 'Staff', 'Guard', 'Accountant'] as $role) {
            $user = $this->makeUserWithRole($this->society, $role);
            $this->actingAs($user)->get(route('admin.message-templates.whatsapp.create'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.message-templates.whatsapp.store'), $this->form())->assertForbidden();
            $this->actingAs($user)->get(route('admin.message-templates.whatsapp.edit', $t))->assertForbidden();
            $this->actingAs($user)->put(route('admin.message-templates.whatsapp.update', $t), $this->form())->assertForbidden();
            $this->actingAs($user)->post(route('admin.message-templates.whatsapp.toggle', $t))->assertForbidden();
            $this->actingAs($user)->delete(route('admin.message-templates.whatsapp.destroy', $t))->assertForbidden();
        }
        $this->assertTrue($t->fresh()->is_active);
        $this->assertSame(1, WhatsAppTemplate::count());
    }

    public function test_another_societys_admin_cannot_see_edit_switch_or_delete_it(): void
    {
        $t = $this->makeOne(['name' => 'Zebra gate template']);
        $intruder = $this->makeUserWithRole($this->makeSociety('Beta'), 'Admin');

        $this->actingAs($intruder)->get(route('admin.message-templates.whatsapp.edit', $t))->assertForbidden();
        $this->actingAs($intruder)->put(route('admin.message-templates.whatsapp.update', $t), $this->form(['loaded' => $this->loaded($t)]))->assertForbidden();
        $this->actingAs($intruder)->post(route('admin.message-templates.whatsapp.toggle', $t))->assertForbidden();
        $this->actingAs($intruder)->delete(route('admin.message-templates.whatsapp.destroy', $t))->assertForbidden();
        $this->actingAs($intruder)->get(route('admin.message-templates.index', ['channel' => 'whatsapp']))->assertOk()->assertDontSee('Zebra gate template');

        // and their own save lands in their own society
        $this->create([], $intruder);
        $this->assertSame($intruder->society_id, WhatsAppTemplate::orderByDesc('id')->first()->society_id);
        $this->assertSame($this->society->id, $t->fresh()->society_id);
        $this->assertTrue($t->fresh()->is_active, 'their activation did not touch ours');
    }

    // ── no provider contact, ever, for template management ──────────────────

    public function test_template_management_never_contacts_the_provider(): void
    {
        $t = $this->makeOne();
        $this->actingAs($this->admin)->get(route('admin.message-templates.index', ['channel' => 'whatsapp']))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.message-templates.whatsapp.edit', $t))->assertOk();
        $this->actingAs($this->admin)->put(route('admin.message-templates.whatsapp.update', $t), $this->form(['loaded' => $this->loaded($t)]));
        $this->actingAs($this->admin)->post(route('admin.message-templates.whatsapp.toggle', $t));
        $this->actingAs($this->admin)->delete(route('admin.message-templates.whatsapp.destroy', $t));

        $this->assertSame([], $this->requests, 'no WhatsApp or account request was made');
        Http::assertNothingSent();
    }

    public function test_the_refresh_and_assign_workflow_is_gone_and_only_a_manual_sync_button_remains(): void
    {
        foreach (['settings.whatsapp.sync', 'settings.whatsapp.assign', 'settings.whatsapp.unassign'] as $name) {
            $this->assertFalse(\Illuminate\Support\Facades\Route::has($name), "$name was removed");
        }
        $html = $this->actingAs($this->admin)->get(route('admin.message-templates.index', ['channel' => 'whatsapp']))->getContent();
        $this->assertStringNotContainsString('Refresh from Fast2SMS', $html);
        // the one sync that exists is the button on this page (WhatsAppTemplateScopeTest covers what it does)
        $this->assertStringContainsString('Sync from Fast2SMS', $html);
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.message-templates.whatsapp.sync'));
        $settings = $this->actingAs($this->admin)->get('/settings?tab=whatsapp')->getContent();
        $this->assertStringNotContainsString('Refresh from Fast2SMS', $settings);
        $this->assertStringNotContainsString('Connect &amp; Verify', $settings);
    }

    // ── the saved template is the one the notification sends ────────────────

    private function enableSending(): void
    {
        $this->wa('whatsapp_enabled', true, 'boolean');
        $this->wa('whatsapp_api_key', 'wa-key-0123456789abcdefghij', 'encrypted');
        $this->wa('whatsapp_phone_number_id', '1209719742234591');
    }

    private function send(array $values = null)
    {
        return WhatsAppService::sendNotification('visitor', '9876543210', $values ?? ['resident_name' => 'Asha', 'visitor_name' => 'Ravi', 'visit_date' => '12 Nov', 'society_name' => 'Alpha'], $this->society->id);
    }

    public function test_the_active_template_is_the_one_sent_with_its_message_id_and_values_in_the_chosen_order(): void
    {
        $this->enableSending();
        $this->makeOne(['message_id' => '8340', 'body_text' => 'Hi {{1}}, {{2}} at {{3}}', 'variables' => ['visitor_name', 'resident_name', 'visit_date']]);

        $r = $this->send();

        $this->assertTrue($r['success']);
        $this->assertSame('/dev/whatsapp', $this->requests[0]['_path']);
        $this->assertSame('8340', $this->requests[0]['message_id']);
        $this->assertSame('Ravi|Asha|12 Nov', $this->requests[0]['variables_values'], 'the order the admin chose, not the default');
    }

    public function test_an_inactive_or_missing_template_sends_nothing_and_says_why(): void
    {
        $this->enableSending();

        $none = $this->send();
        $this->assertFalse($none['success']);
        $this->assertStringContainsString('No active WhatsApp template configured', $none['message']);

        $this->makeOne(['status' => 'inactive']);
        $off = $this->send();
        $this->assertFalse($off['success']);
        $this->assertSame([], $this->requests);
    }

    public function test_a_value_the_event_did_not_supply_fails_instead_of_sending_a_placeholder(): void
    {
        $this->enableSending();
        $this->makeOne();

        $r = $this->send(['resident_name' => 'Asha', 'visitor_name' => 'Ravi']);   // no visit_date

        $this->assertFalse($r['success']);
        $this->assertStringContainsString('visit_date', $r['message']);
        $this->assertSame([], $this->requests);
    }

    public function test_an_active_template_for_another_type_is_not_used(): void
    {
        $this->enableSending();
        $this->makeOne(['purpose' => 'bill', 'name' => 'Bill', 'template_name' => 'new_bill', 'body_text' => 'Bill {{1}}', 'variables' => ['amount']]);

        $this->assertFalse($this->send()['success']);
        $this->assertSame([], $this->requests);
    }

    public function test_another_societys_template_is_never_used(): void
    {
        $this->enableSending();
        $other = $this->makeSociety('Beta');
        WhatsAppTemplate::create(['society_id' => $other->id, 'external_id' => 'manual-x', 'name' => 'theirs', 'purpose' => 'visitor', 'is_active' => true, 'source' => 'manual',
            'status' => 'LOCAL', 'var_count' => 0, 'body_text' => 'x', 'message_id' => '1']);

        $this->assertFalse($this->send()['success']);
        $this->assertSame([], $this->requests);
    }

    public function test_the_kill_switch_still_wins_over_an_active_template(): void
    {
        $this->enableSending();
        $this->makeOne();
        WhatsAppService::$transportOverride = null;   // the real path, which the outbound guard and the kill switch protect
        $this->assertFalse(\App\Support\ExternalMessaging::enabled());

        $r = $this->send();

        $this->assertFalse($r['success']);
        $this->assertTrue($r['skipped'] ?? false);
        Http::assertNothingSent();
    }

    public function test_a_retried_event_does_not_message_the_resident_twice(): void
    {
        $this->enableSending();
        $this->makeOne();

        $first = $this->send();
        $second = $this->send();

        $this->assertTrue($first['success']);
        $this->assertSame('skipped_duplicate', $second['status'] ?? null);
        $this->assertCount(1, $this->requests);
    }

    // ── what existed before this feature is carried over, not lost ──────────

    public function test_templates_a_society_had_already_attached_become_the_active_template_of_their_type(): void
    {
        $legacy = WhatsAppTemplate::create(['society_id' => $this->society->id, 'external_id' => '2012', 'message_id' => '555', 'name' => 'bill_notice', 'status' => 'APPROVED',
            'language' => 'en', 'var_count' => 2, 'body_text' => 'Bill {{1}} due {{2}}']);
        $unrelated = WhatsAppTemplate::create(['society_id' => $this->society->id, 'external_id' => '2013', 'message_id' => '556', 'name' => 'unused', 'status' => 'APPROVED',
            'language' => 'en', 'var_count' => 0, 'body_text' => 'x']);
        $this->wa('whatsapp_bill_template_name', 'bill_notice');
        $this->wa('whatsapp_bill_variables', ['amount', 'due_date'], 'json');

        $migration = require base_path('database/migrations/2026_10_14_000001_make_whatsapp_templates_manual.php');
        (new \ReflectionMethod($migration, 'carryOverAssignments'))->invoke($migration);

        $legacy->refresh();
        $this->assertSame('bill', $legacy->purpose);
        $this->assertTrue($legacy->is_active);
        $this->assertSame(['amount', 'due_date'], $legacy->variable_map);
        $this->assertSame('synced', $legacy->source, 'still marked as provider-read: untouched otherwise');
        $this->assertNull($unrelated->fresh()->purpose);
        $this->assertFalse($unrelated->fresh()->is_active);
        $this->assertNotNull(WhatsAppTemplate::forPurpose($this->society->id, 'bill'));
    }

    /**
     * The carry-over above leaves the older `whatsapp_<type>_template_name` setting in place. It used to find the template by
     * name whatever its switch said, so "Switch off" on a carried-over template did not stop it.
     */
    public function test_switching_off_a_carried_over_template_stops_it_although_the_older_setting_still_names_it(): void
    {
        $this->enableSending();
        $t = $this->makeOne();                                                // active for visitor
        $this->wa('whatsapp_visitor_template_name', $t->name);                // the older setting the carry-over left behind
        $this->assertTrue($this->send()['success'], 'switched on: sent');

        $this->actingAs($this->admin)->post(route('admin.message-templates.whatsapp.toggle', $t))->assertSessionHas('success');
        $this->assertFalse($t->fresh()->is_active);
        $this->requests = [];

        $off = $this->send();
        $this->assertFalse($off['success']);
        $this->assertSame('skipped_not_configured', $off['status']);
        $this->assertStringContainsString('is switched off', $off['message']);
        $this->assertSame([], $this->requests, 'switched off: no provider request');

        // switched on for another notification: the older visitor setting does not borrow it
        $t->forceFill(['purpose' => 'bill', 'is_active' => true])->save();
        $elsewhere = $this->send();
        $this->assertFalse($elsewhere['success']);
        $this->assertStringContainsString('is used for', $elsewhere['message']);
        $this->assertSame([], $this->requests);

        // never assigned in Message Templates (the setup before templates were records): the older setting still sends it
        $t->forceFill(['purpose' => null, 'is_active' => false])->save();
        \Illuminate\Support\Facades\Cache::flush();   // the same values were accepted above; the duplicate guard is tested elsewhere
        $this->assertTrue($this->send()['success']);
        $this->assertCount(1, $this->requests);
    }

    public function test_saving_whatsapp_settings_does_not_touch_the_templates(): void
    {
        $t = $this->makeOne();

        $this->actingAs($this->admin)->post(route('settings.whatsapp.update'), ['whatsapp_enabled' => '1', 'whatsapp_phone_number_id' => '1209719742234591', 'whatsapp_country_code' => '91']);

        $this->assertTrue($t->fresh()->is_active);
        $this->assertSame(1, WhatsAppTemplate::count());
    }
}
