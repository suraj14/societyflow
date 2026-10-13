<?php

namespace Tests\Feature;

use App\Models\SmsTemplate;
use App\Models\Society;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Message Templates -> SMS: create, edit, switch on/off and delete real templates. Server-side validation is the
 * authority (the editor's live checks are only a convenience); one active template per type and society; a society's
 * admin can only ever touch its own templates.
 */
class SmsTemplateCrudTest extends TestCase
{
    use RefreshDatabase;

    private Society $a;
    private Society $b;
    private $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = $this->makeSociety('Alpha');
        $this->b = $this->makeSociety('Beta');
        $this->admin = $this->makeUserWithRole($this->a, 'Admin');
    }

    private function payload(array $over = []): array
    {
        return $over + ['name' => 'Notice alert', 'purpose' => 'notice', 'audience' => 'resident', 'status' => 'active',
            'body' => 'Notice: {{notice_title}} at {{society_name}}', 'dlt_message_id' => '223577'];
    }

    private function make(array $over = [], ?Society $s = null): SmsTemplate
    {
        return SmsTemplate::create($over + ['society_id' => ($s ?? $this->a)->id, 'name' => 'Existing', 'purpose' => 'notice', 'audience' => 'resident',
            'body' => 'Notice: {{notice_title}}', 'dlt_message_id' => '111111', 'is_active' => true]);
    }

    // ── create ──────────────────────────────────────────────────────────────

    public function test_the_create_form_offers_every_type_with_its_variables_and_the_dlt_help(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.message-templates.sms.create'))->assertOk()->getContent();

        foreach (['Login OTP', 'Notice published', 'Gate pass revoked', 'Society document published'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
        foreach (['otp', 'notice_title', 'society_name', 'person_name', 'document_title', 'resident_name'] as $variable) {
            $this->assertStringContainsString($variable, $html, "{$variable} is offered as a variable");
        }
        $this->assertStringContainsString('Paste the Message ID of your approved SMS template from Fast2SMS DLT Manager', $html);
        $this->assertStringContainsString('The message text and variable order must match the approved DLT template', $html);
        $this->assertStringContainsString('Fast2SMS Message ID (DLT)', $html);
        $this->assertStringContainsString('Preview', $html);
        $this->assertStringContainsString('/320', $html, 'the live character count');
    }

    public function test_creating_a_template_saves_it_for_the_admins_society(): void
    {
        $this->actingAs($this->admin)->post(route('admin.message-templates.sms.store'), $this->payload())
            ->assertRedirect(route('admin.message-templates.index', ['channel' => 'sms']))->assertSessionHas('success');

        $this->assertDatabaseHas('sms_message_templates', [
            'society_id' => $this->a->id, 'name' => 'Notice alert', 'purpose' => 'notice', 'audience' => 'resident',
            'body' => 'Notice: {{notice_title}} at {{society_name}}', 'dlt_message_id' => '223577', 'is_active' => 1,
        ]);
        $this->assertSame(1, SmsTemplate::count());
    }

    public function test_the_text_is_trimmed_and_a_blank_message_id_is_stored_as_null(): void
    {
        $this->actingAs($this->admin)->post(route('admin.message-templates.sms.store'), $this->payload(['name' => '  Spaced  ', 'body' => "  Notice: {{notice_title}}\n", 'dlt_message_id' => '']))
            ->assertSessionHasNoErrors();

        $t = SmsTemplate::first();
        $this->assertSame('Spaced', $t->name);
        $this->assertSame('Notice: {{notice_title}}', $t->body);
        $this->assertNull($t->dlt_message_id, 'empty means "use the default Message ID, or Quick SMS"');
    }

    public function test_making_a_template_active_switches_off_the_other_of_the_same_type_only(): void
    {
        $old = $this->make(['name' => 'Old notice', 'purpose' => 'notice']);
        $otherType = $this->make(['name' => 'Pass', 'purpose' => 'gate_pass', 'body' => 'Pass for {{person_name}}']);
        $otherSociety = $this->make(['name' => 'Theirs', 'purpose' => 'notice'], $this->b);

        $this->actingAs($this->admin)->post(route('admin.message-templates.sms.store'), $this->payload(['name' => 'New notice']))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Old notice') && str_contains($m, 'switched off'));

        $this->assertFalse($old->fresh()->is_active);
        $this->assertTrue($otherType->fresh()->is_active, 'a different type is left alone');
        $this->assertTrue($otherSociety->fresh()->is_active, 'another society is left alone');
        $this->assertSame('New notice', SmsTemplate::forPurpose($this->a->id, 'notice')->name);
    }

    public function test_an_inactive_template_is_saved_inactive_and_does_not_replace_the_active_one(): void
    {
        $active = $this->make(['name' => 'Live one']);

        $this->actingAs($this->admin)->post(route('admin.message-templates.sms.store'), $this->payload(['name' => 'Draft', 'status' => 'inactive']))->assertSessionHasNoErrors();

        $this->assertTrue($active->fresh()->is_active);
        $this->assertFalse(SmsTemplate::where('name', 'Draft')->first()->is_active);
        $this->assertSame('Live one', SmsTemplate::forPurpose($this->a->id, 'notice')->name);
    }

    /** @dataProvider invalidPayloads */
    public function test_invalid_templates_are_refused_with_a_reason_and_nothing_is_saved(array $override, string $field, string $message): void
    {
        $r = $this->actingAs($this->admin)->post(route('admin.message-templates.sms.store'), $this->payload($override));

        $r->assertSessionHasErrors($field);
        $this->assertStringContainsString($message, implode(' ', session('errors')->get($field)));
        $this->assertSame(0, SmsTemplate::count());
    }

    public static function invalidPayloads(): array
    {
        return [
            'name is required'                  => [['name' => ''], 'name', 'required'],
            'name is too long'                  => [['name' => str_repeat('x', 121)], 'name', '120'],
            'unknown type'                      => [['purpose' => 'new_bill'], 'purpose', 'invalid'],
            'unknown recipient'                 => [['audience' => 'everyone'], 'audience', 'invalid'],
            'unknown status'                    => [['status' => 'maybe'], 'status', 'invalid'],
            'body is required'                  => [['body' => ''], 'body', 'required'],
            'body is too long'                  => [['body' => str_repeat('a', 321)], 'body', '320'],
            'a variable this type cannot fill'  => [['body' => 'Bill {{amount}} for {{notice_title}}'], 'body', '{{amount}}'],
            'a half-written variable'           => [['body' => 'Notice: {{notice_title'], 'body', 'not written correctly'],
            'an empty variable'                 => [['body' => 'Notice: {{ }}'], 'body', 'not written correctly'],
            'a login OTP without the code'      => [['purpose' => 'login_otp', 'body' => 'Welcome to {{society_name}}'], 'body', 'must include {{otp}}'],
            'a message id with letters'         => [['dlt_message_id' => '22A577'], 'dlt_message_id', 'Message ID'],
            'a DLT template id from the portal' => [['dlt_message_id' => '1707168880000012345'], 'dlt_message_id', 'not the long DLT Template ID'],
            'a message id with spaces'          => [['dlt_message_id' => '22 3577'], 'dlt_message_id', 'Message ID'],
        ];
    }

    public function test_a_login_otp_template_must_carry_the_code_and_may_use_the_society_name(): void
    {
        $this->actingAs($this->admin)->post(route('admin.message-templates.sms.store'), $this->payload([
            'name' => 'Login OTP', 'purpose' => 'login_otp', 'body' => '{{otp}} is your {{society_name}} login code.', 'dlt_message_id' => '223577',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('login_otp', SmsTemplate::first()->purpose);
    }

    public function test_a_repeated_variable_is_allowed_because_each_use_is_its_own_dlt_slot(): void
    {
        $this->actingAs($this->admin)->post(route('admin.message-templates.sms.store'), $this->payload(['body' => '{{society_name}}: {{notice_title}} - {{society_name}}']))
            ->assertSessionHasNoErrors();

        $this->assertSame(3, SmsTemplate::first()->variableCount());
    }

    // ── edit ────────────────────────────────────────────────────────────────

    public function test_the_edit_form_is_filled_with_the_saved_values(): void
    {
        $t = $this->make(['name' => 'Water notice', 'audience' => 'guard', 'body' => 'Water: {{notice_title}}', 'dlt_message_id' => '445566']);

        $html = $this->actingAs($this->admin)->get(route('admin.message-templates.sms.edit', $t))->assertOk()->getContent();

        $this->assertStringContainsString('Water notice', $html);
        $this->assertStringContainsString('Water: {{notice_title}}', $html);
        $this->assertStringContainsString('445566', $html);
        $this->assertMatchesRegularExpression('/<option value="guard"\s+selected/', $html);
    }

    public function test_editing_updates_the_template_in_place(): void
    {
        $t = $this->make(['name' => 'Before', 'dlt_message_id' => '111111']);

        $this->actingAs($this->admin)->put(route('admin.message-templates.sms.update', $t), $this->payload([
            'name' => 'After', 'audience' => 'admin', 'body' => 'Updated: {{notice_title}}', 'dlt_message_id' => '',
        ]))->assertRedirect(route('admin.message-templates.index', ['channel' => 'sms']));

        $t->refresh();
        $this->assertSame('After', $t->name);
        $this->assertSame('admin', $t->audience);
        $this->assertSame('Updated: {{notice_title}}', $t->body);
        $this->assertNull($t->dlt_message_id);
        $this->assertSame(1, SmsTemplate::count(), 'edited in place, not duplicated');
    }

    public function test_an_invalid_edit_changes_nothing(): void
    {
        $t = $this->make(['name' => 'Keep me', 'body' => 'Notice: {{notice_title}}']);

        $this->actingAs($this->admin)->put(route('admin.message-templates.sms.update', $t), $this->payload(['name' => 'Changed', 'body' => 'Uses {{nope}}']))->assertSessionHasErrors('body');

        $this->assertSame('Keep me', $t->fresh()->name);
        $this->assertSame('Notice: {{notice_title}}', $t->fresh()->body);
    }

    public function test_changing_the_type_of_an_active_template_keeps_one_active_per_type(): void
    {
        $notice = $this->make(['name' => 'Notice', 'purpose' => 'notice']);
        $pass = $this->make(['name' => 'Pass', 'purpose' => 'gate_pass', 'body' => 'Pass for {{person_name}}']);

        $this->actingAs($this->admin)->put(route('admin.message-templates.sms.update', $pass), $this->payload(['name' => 'Pass now a notice']))->assertSessionHasNoErrors();

        $this->assertFalse($notice->fresh()->is_active, 'only one active notice template');
        $this->assertTrue($pass->fresh()->is_active);
        $this->assertSame('notice', $pass->fresh()->purpose);
    }

    // ── switch on / off, delete ─────────────────────────────────────────────

    public function test_a_template_can_be_switched_off_and_on_again(): void
    {
        $t = $this->make();

        $this->actingAs($this->admin)->post(route('admin.message-templates.sms.toggle', $t))->assertRedirect()->assertSessionHas('success');
        $this->assertFalse($t->fresh()->is_active);
        $this->assertNull(SmsTemplate::forPurpose($this->a->id, 'notice'), 'an inactive template is not used for sending');

        $this->actingAs($this->admin)->post(route('admin.message-templates.sms.toggle', $t));
        $this->assertTrue($t->fresh()->is_active);
        $this->assertSame($t->id, SmsTemplate::forPurpose($this->a->id, 'notice')->id);
    }

    public function test_switching_one_on_switches_off_the_other(): void
    {
        $first = $this->make(['name' => 'First']);
        $second = $this->make(['name' => 'Second', 'is_active' => false]);

        $this->actingAs($this->admin)->post(route('admin.message-templates.sms.toggle', $second))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'First') && str_contains($m, 'switched off'));

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
    }

    public function test_a_template_can_be_deleted(): void
    {
        $t = $this->make();

        $this->actingAs($this->admin)->delete(route('admin.message-templates.sms.destroy', $t))->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseMissing('sms_message_templates', ['id' => $t->id]);
    }

    // ── isolation ───────────────────────────────────────────────────────────

    public function test_another_societys_admin_cannot_view_edit_switch_or_delete_a_template(): void
    {
        $t = $this->make(['name' => 'Alpha only']);
        $intruder = $this->makeUserWithRole($this->b, 'Admin');

        $this->actingAs($intruder)->get(route('admin.message-templates.sms.edit', $t))->assertForbidden();
        $this->actingAs($intruder)->put(route('admin.message-templates.sms.update', $t), $this->payload(['name' => 'Hijacked']))->assertForbidden();
        $this->actingAs($intruder)->post(route('admin.message-templates.sms.toggle', $t))->assertForbidden();
        $this->actingAs($intruder)->delete(route('admin.message-templates.sms.destroy', $t))->assertForbidden();

        $t->refresh();
        $this->assertSame('Alpha only', $t->name);
        $this->assertTrue($t->is_active);
        $this->assertSame($this->a->id, $t->society_id);
    }

    public function test_a_society_admin_cannot_edit_a_platform_level_template(): void
    {
        $platform = SmsTemplate::create(['society_id' => null, 'name' => 'Platform', 'purpose' => 'notice', 'audience' => 'resident', 'body' => 'Notice: {{notice_title}}', 'is_active' => true]);

        $this->actingAs($this->admin)->get(route('admin.message-templates.sms.edit', $platform))->assertForbidden();
        $this->actingAs($this->admin)->delete(route('admin.message-templates.sms.destroy', $platform))->assertForbidden();
        $this->assertDatabaseHas('sms_message_templates', ['id' => $platform->id]);
    }

    public function test_the_society_comes_from_the_signed_in_user_never_from_the_request(): void
    {
        $this->actingAs($this->admin)->post(route('admin.message-templates.sms.store'), $this->payload(['society_id' => $this->b->id]))->assertSessionHasNoErrors();

        $this->assertSame($this->a->id, SmsTemplate::first()->society_id);
    }

    public function test_a_super_admin_creates_platform_level_templates(): void
    {
        $super = $this->makeUserWithRole($this->a, 'Super Admin', ['society_id' => null]);

        $this->actingAs($super)->post(route('admin.message-templates.sms.store'), $this->payload())->assertSessionHasNoErrors();

        $this->assertNull(SmsTemplate::first()->society_id);
        $this->assertNotNull(SmsTemplate::forPurpose(null, 'notice'));
        $this->assertNull(SmsTemplate::forPurpose($this->a->id, 'notice'), 'a platform template is not a society\'s template');
    }

    // ── manual entry: stale forms, exact wording, no provider contact ───────

    public function test_a_stale_edit_form_does_not_overwrite_a_newer_save(): void
    {
        $t = $this->make(['name' => 'Original']);
        $stale = (string) $t->fresh()->updated_at->timestamp;
        \Carbon\Carbon::setTestNow(now()->addMinute());
        $t->forceFill(['name' => 'Changed by someone else'])->save();

        $this->actingAs($this->admin)->put(route('admin.message-templates.sms.update', $t), $this->payload(['name' => 'My old edit', 'loaded' => $stale]))
            ->assertSessionHasErrors('name');

        \Carbon\Carbon::setTestNow();
        $this->assertSame('Changed by someone else', $t->fresh()->name);
    }

    public function test_the_edit_form_carries_the_version_it_loaded(): void
    {
        $t = $this->make();

        $this->actingAs($this->admin)->get(route('admin.message-templates.sms.edit', $t))->assertOk()
            ->assertSee('name="loaded" value="' . $t->updated_at->timestamp . '"', false);
    }

    public function test_the_pasted_approved_wording_is_stored_exactly_including_spacing_around_placeholders(): void
    {
        $text = 'Dear {{resident_name}} , notice: {{notice_title}} . Thank you,  {{society_name}}';

        $this->actingAs($this->admin)->post(route('admin.message-templates.sms.store'), $this->payload(['body' => $text]))->assertRedirect();

        $this->assertSame($text, SmsTemplate::latest('id')->firstOrFail()->body, 'no normalising of the approved text');
    }

    public function test_managing_sms_templates_never_contacts_a_provider(): void
    {
        \Illuminate\Support\Facades\Http::fake();
        $called = [];
        \App\Services\SmsService::$transportOverride = function () use (&$called) { $called[] = 'sms'; return [[], 200]; };
        \App\Services\Fast2SmsAccount::$transportOverride = function () use (&$called) { $called[] = 'account'; return [[], 200]; };

        $this->actingAs($this->admin)->post(route('admin.message-templates.sms.store'), $this->payload())->assertRedirect();
        $t = SmsTemplate::firstOrFail();
        $this->actingAs($this->admin)->get(route('admin.message-templates.index', ['channel' => 'sms']))->assertOk();
        $this->actingAs($this->admin)->put(route('admin.message-templates.sms.update', $t), $this->payload(['name' => 'Renamed']));
        $this->actingAs($this->admin)->post(route('admin.message-templates.sms.toggle', $t));
        $this->actingAs($this->admin)->delete(route('admin.message-templates.sms.destroy', $t));

        \App\Services\SmsService::$transportOverride = null;
        \App\Services\Fast2SmsAccount::$transportOverride = null;
        $this->assertSame([], $called);
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }
}
