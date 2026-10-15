<?php

namespace Tests\Feature;

use App\Events\VisitorApproved;
use App\Events\VisitorWorkflowEvent;
use App\Jobs\DispatchEventNotification;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\Society;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use App\Models\WhatsAppTemplate;
use App\Services\VisitorApprovalService;
use App\Services\VisitorWorkflowException;
use App\Services\WhatsAppService;
use App\Support\NotificationRules;
use App\Support\VisitorRecipients;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * MyGate-style gate approval: a guard logs a visitor for a unit, the unit's owner / tenant (or an administrator) allow or
 * turn them away, the guard checks them in and out, and an administrator can override with a reason.
 *
 * Every provider call is a captured fake; nothing here can send a real message (TestCase blocks and fails on any).
 */
class VisitorApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;
    private Society $other;
    private Flat $owned;      // 101: owner A (flats.owner_id) + a second household account
    private Flat $rented;     // 102: owner B and an active tenant
    private Flat $unlinked;   // 103: nobody linked
    private Flat $fallback;   // 104: owner reached only through the owner record's own address
    private Flat $villa;      // mixed society: a villa next to the apartments
    private Flat $foreign;    // a unit in the other society
    private User $guard;
    private User $admin;
    private User $ownerA;
    private User $ownerA2;
    private User $ownerB;
    private User $tenantB;
    private User $fallbackOwner;
    private User $villaOwner;
    private User $otherAdmin;
    private User $otherOwner;
    private array $wa = [];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Mail::fake();

        $this->society = $this->makeSociety('Royal Eden');
        $this->other = $this->makeSociety('Other Estate');
        $tower = $this->makeBuilding($this->society);

        $recA = $this->ownerRecord('Owner A');
        $recB = $this->ownerRecord('Owner B');
        $recFallback = $this->ownerRecord('Owner Fallback', ['property_type' => 'apartment', 'building_id' => $tower->id, 'flat_no' => '104']);
        $recVilla = $this->ownerRecord('Villa Owner');

        $this->owned = $this->makeFlat($this->society, $tower, '101');
        $this->owned->update(['owner_id' => $recA->id]);
        $this->rented = $this->makeFlat($this->society, $tower, '102');
        $this->rented->update(['owner_id' => $recB->id]);
        $this->unlinked = $this->makeFlat($this->society, $tower, '103');
        $this->fallback = $this->makeFlat($this->society, $tower, '104');   // flats.owner_id deliberately unset
        $this->fallback->update(['property_type' => 'apartment']);          // the address match needs the discriminator real data carries
        $this->villa = Flat::create(['society_id' => $this->society->id, 'property_type' => 'villa', 'owner_id' => $recVilla->id, 'flat_number' => '159', 'floor' => 0, 'status' => 'occupied']);

        $this->guard = $this->makeUserWithRole($this->society, 'Guard', ['name' => 'Gate Guard']);
        $this->admin = $this->makeUserWithRole($this->society, 'Admin', ['name' => 'Society Admin']);
        $this->ownerA = $this->makeUserWithRole($this->society, 'Apartment Owner', ['name' => 'Asha Owner', 'owner_id' => $recA->id]);
        $this->ownerA2 = $this->makeUserWithRole($this->society, 'Apartment Owner', ['name' => 'Asha Spouse', 'owner_id' => $recA->id]);
        $this->ownerB = $this->makeUserWithRole($this->society, 'Apartment Owner', ['name' => 'Bala Owner', 'owner_id' => $recB->id]);
        $this->fallbackOwner = $this->makeUserWithRole($this->society, 'Apartment Owner', ['name' => 'Farid Owner', 'owner_id' => $recFallback->id]);
        $this->villaOwner = $this->makeUserWithRole($this->society, 'Villa Owner', ['name' => 'Vijay Villa', 'owner_id' => $recVilla->id]);

        $this->tenantB = $this->makeUserWithRole($this->society, 'Tenant', ['name' => 'Tara Tenant']);
        $tenancy = Tenant::create([
            'society_id' => $this->society->id, 'flat_id' => $this->rented->id, 'user_id' => $this->tenantB->id,
            'name' => 'Tara Tenant', 'email' => $this->tenantB->email, 'phone' => '9000000102', 'move_in_date' => now(), 'status' => 'active',
        ]);
        $this->tenantB->update(['tenant_id' => $tenancy->id]);

        // the other society
        $this->foreign = $this->makeFlat($this->other, $this->makeBuilding($this->other), '101');
        $this->otherAdmin = $this->makeUserWithRole($this->other, 'Admin', ['name' => 'Other Admin']);
        $this->otherOwner = $this->makeUserWithRole($this->other, 'Apartment Owner', ['name' => 'Other Owner', 'owner_id' => $recA->id]);   // owners.id spaces overlap across societies
    }

    protected function tearDown(): void
    {
        WhatsAppService::$transportOverride = null;
        parent::tearDown();
    }

    // ── fixtures ────────────────────────────────────────────────────────────

    private function ownerRecord(string $name, array $over = []): Owner
    {
        return Owner::create($over + [
            'society_id' => $this->society->id, 'name' => $name, 'email' => 'o' . uniqid() . '@test.local',
            'phone' => '9' . random_int(100000000, 999999999), 'status' => 'active',
        ]);
    }

    /** The guard logs a visitor through the mobile API. */
    private function request(Flat $flat, array $over = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->guard)->postJson('/api/v1/visitors', $over + [
            'flat_id' => $flat->id, 'visitor_name' => 'Ravi Courier', 'visitor_phone' => '9876543210', 'visitor_type' => 'delivery', 'purpose' => 'Parcel',
        ]);
    }

    private function pending(Flat $flat, array $over = []): Visitor
    {
        return Visitor::findOrFail($this->request($flat, $over)->assertStatus(201)->json('data.id'));
    }

    /** @return list<string> titles in the user's bell, oldest first */
    private function bell(User $user): array
    {
        return $user->notifications()->orderBy('created_at')->orderBy('id')->get()->map(fn ($n) => $n->data['title'])->all();
    }

    private function logActions(Visitor $v): array
    {
        return $v->logs()->orderBy('id')->pluck('action')->all();
    }

    private function auditNotes(Visitor $v, string $kind): string
    {
        return (string) $v->logs()->where('action', 'notified')->where('notes', 'like', "{$kind}:%")->latest('id')->value('notes');
    }

    private function configureWhatsApp(string $status = 'APPROVED', bool $enabled = true): WhatsAppTemplate
    {
        SystemSetting::set('whatsapp', 'whatsapp_enabled', $enabled, 'boolean', $this->society->id);
        SystemSetting::set('whatsapp', 'whatsapp_api_key', 'wa-key-0123456789abcdefghijKLMN', 'encrypted', $this->society->id);
        SystemSetting::set('whatsapp', 'whatsapp_phone_number_id', '1209719742234591', 'string', $this->society->id);

        return WhatsAppTemplate::create([
            'society_id' => $this->society->id, 'external_id' => 'ext-arrived', 'message_id' => '37328', 'phone_number_id' => '1209719742234591',
            'name' => 'society_visitor_arrived', 'purpose' => 'visitor_arrival', 'is_active' => true, 'status' => $status, 'source' => 'synced', 'language' => 'en',
            'var_count' => 3, 'body_text' => "Hello {{1}},\n\nYour visitor {{2}} has arrived at {{3}}. Please contact the security gate to confirm entry.\n\nThank you.",
        ]);
    }

    private function captureWhatsApp(array $answer = ['return' => true, 'request_id' => 'wa-1', 'message' => ['Whatsapp Message sent successfully.']], int $code = 200): void
    {
        $this->wa = [];
        WhatsAppService::$transportOverride = function (string $url) use ($answer, $code) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
            $this->wa[] = $q;

            return [$answer, $code];
        };
    }

    // ── recipients ──────────────────────────────────────────────────────────

    public function test_the_owner_and_every_household_account_of_the_unit_are_found_through_the_property_link(): void
    {
        $ids = VisitorRecipients::residents($this->owned)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$this->ownerA->id, $this->ownerA2->id], $ids);
        $this->assertNotContains($this->ownerB->id, $ids, 'a neighbour is never a recipient');
    }

    public function test_a_rented_unit_tells_both_the_tenant_and_the_owner_and_both_may_decide(): void
    {
        $ids = VisitorRecipients::residents($this->rented)->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$this->tenantB->id, $this->ownerB->id], $ids);

        $first = $this->pending($this->rented);
        VisitorApprovalService::decide($first, $this->ownerB, 'allowed');
        $second = $this->pending($this->rented, ['visitor_phone' => '9811111111']);
        VisitorApprovalService::decide($second, $this->tenantB, 'allowed');

        $this->assertSame('resident', $first->fresh()->decision_role);
        $this->assertSame($this->tenantB->id, (int) $second->fresh()->decided_by);
    }

    public function test_an_owner_known_only_by_the_owner_records_address_is_still_resolved(): void
    {
        $this->assertNull($this->fallback->fresh()->owner_id);

        $this->assertSame([$this->fallbackOwner->id], VisitorRecipients::residents($this->fallback)->pluck('id')->all());
    }

    public function test_a_villa_in_a_mixed_society_resolves_its_own_owner(): void
    {
        $this->assertSame([$this->villaOwner->id], VisitorRecipients::residents($this->villa)->pluck('id')->all());
    }

    public function test_inactive_accounts_other_roles_and_another_societys_accounts_are_never_recipients(): void
    {
        $this->ownerA2->update(['status' => 'inactive']);
        $guardWithOwnerLink = $this->makeUserWithRole($this->society, 'Guard', ['owner_id' => $this->owned->owner_id]);

        $ids = VisitorRecipients::residents($this->owned)->pluck('id')->all();

        $this->assertSame([$this->ownerA->id], $ids);
        $this->assertNotContains($guardWithOwnerLink->id, $ids, 'a staff account is not a resident');
        $this->assertNotContains($this->otherOwner->id, $ids, 'an account of another society that happens to carry the same owners.id');
    }

    public function test_no_owner_is_invented_for_a_unit_nobody_is_linked_to(): void
    {
        $this->assertTrue(VisitorRecipients::residents($this->unlinked)->isEmpty());
        $this->assertFalse(VisitorRecipients::forVisitor($this->pending($this->unlinked))['linked']);
    }

    // ── the guard logs a visitor ────────────────────────────────────────────

    public function test_a_guard_request_is_stored_pending_with_who_asked_and_is_not_an_admission(): void
    {
        $r = $this->request($this->owned)->assertStatus(201)
            ->assertJsonPath('data.status', 'pending_approval')
            ->assertJsonPath('data.approval_status', 'pending')
            ->assertJsonPath('data.entry_status', 'pending')
            ->assertJsonPath('data.requested_by', 'Gate Guard')
            ->assertJsonPath('meta.residents_linked', true)
            ->assertJsonPath('data.can_decide', false)        // the guard records, never decides
            ->assertJsonPath('data.can_check_in', false);

        $v = Visitor::findOrFail($r->json('data.id'));
        $this->assertSame($this->guard->id, (int) $v->requested_by);
        $this->assertSame($this->society->id, (int) $v->society_id);
        $this->assertSame($this->owned->id, (int) $v->flat_id);
        $this->assertNull($v->actual_entry_time);
        $this->assertNull($v->decided_by);
        $this->assertContains($this->ownerA->id, [(int) $v->host_user_id, (int) $this->ownerA->id]);
        $this->assertSame(['created', 'notified'], $this->logActions($v));
    }

    public function test_the_request_is_told_to_the_owner_the_household_and_the_admin_but_nobody_else(): void
    {
        $v = $this->pending($this->owned);

        foreach ([$this->ownerA, $this->ownerA2, $this->admin] as $who) {
            $this->assertContains('Visitor at the gate', $this->bell($who), $who->name);
        }
        foreach ([$this->ownerB, $this->tenantB, $this->villaOwner, $this->fallbackOwner, $this->guard, $this->otherAdmin, $this->otherOwner] as $nobody) {
            $this->assertSame([], $this->bell($nobody), $nobody->name . ' must not hear about this visitor');
        }
        $this->assertStringContainsString('in_app=sent(3)', $this->auditNotes($v, 'requested'));
    }

    public function test_a_unit_with_nobody_linked_says_so_to_the_guard_and_asks_the_admins_to_decide(): void
    {
        $r = $this->request($this->unlinked)->assertStatus(201)->assertJsonPath('meta.residents_linked', false);
        $this->assertStringContainsString('no owner or tenant is linked', $r->json('message'));

        $this->assertContains('Visitor at the gate - no owner linked', $this->bell($this->admin));
        $this->assertContains('No owner linked', $this->bell($this->guard));
        $this->assertSame([], $this->bell($this->ownerA), 'an unrelated owner is not asked in the unit owner\'s place');
        $this->assertSame('pending', Visitor::findOrFail($r->json('data.id'))->approval_status);

        // the admin can still decide
        $this->actingAs($this->admin)->postJson('/api/v1/visitors/' . $r->json('data.id') . '/approve')->assertOk();
    }

    public function test_the_guard_must_name_a_property_in_their_own_society(): void
    {
        $this->actingAs($this->guard)->postJson('/api/v1/visitors', ['visitor_phone' => '9876543210', 'visitor_type' => 'guest'])
            ->assertStatus(422);
        $this->request($this->foreign)->assertStatus(422);

        $this->assertSame(0, Visitor::count());
    }

    public function test_a_retried_submission_is_the_same_visitor_and_notifies_once(): void
    {
        $first = $this->request($this->owned, ['client_request_id' => 'gate-app-0001'])->assertStatus(201);
        $again = $this->request($this->owned, ['client_request_id' => 'gate-app-0001'])->assertOk()->assertJsonPath('meta.duplicate', true);

        $this->assertSame($first->json('data.id'), $again->json('data.id'));
        $this->assertSame(1, Visitor::count());
        $this->assertSame(['Visitor at the gate'], $this->bell($this->ownerA), 'one bell entry, not two');

        // another guard cannot reuse the id
        $otherGuard = $this->makeUserWithRole($this->society, 'Guard');
        $this->request($this->owned, ['client_request_id' => 'gate-app-0001'], $otherGuard)->assertStatus(409);
    }

    public function test_a_visitor_photo_is_stored_under_a_random_image_name_and_nothing_else_is_accepted(): void
    {
        // a real 1x1 PNG (this PHP has no GD, so the framework's image() faker is unavailable)
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $image = \Illuminate\Http\UploadedFile::fake()->createWithContent('front-gate.png', $png);
        $r = $this->actingAs($this->guard)->post('/api/v1/visitors', [
            'flat_id' => $this->owned->id, 'visitor_name' => 'With Photo', 'visitor_phone' => '9876543211', 'visitor_type' => 'guest', 'photo' => $image,
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $v = Visitor::findOrFail($r->json('data.id'));
        $this->assertMatchesRegularExpression('#^visitor-photos/\d+_[0-9a-f]{16}\.(jpg|png)$#', (string) $v->photo, 'a random name, an image extension, never the client\'s filename');
        $this->assertFileExists(public_path('storage/' . $v->photo));
        $this->assertStringStartsWith('/storage/visitor-photos/', $r->json('data.photo'));
        @unlink(public_path('storage/' . $v->photo));

        // a script, or a file that is not an image, is refused and nothing is registered
        foreach ([\Illuminate\Http\UploadedFile::fake()->createWithContent('shell.php', $png), \Illuminate\Http\UploadedFile::fake()->create('shell2.php', 5, 'application/x-php'), \Illuminate\Http\UploadedFile::fake()->create('notes.pdf', 5, 'application/pdf')] as $bad) {
            $this->actingAs($this->guard)->post('/api/v1/visitors', [
                'flat_id' => $this->owned->id, 'visitor_name' => 'Bad Photo', 'visitor_phone' => '9876543212', 'visitor_type' => 'guest', 'photo' => $bad,
            ], ['Accept' => 'application/json'])->assertStatus(422);
        }
        $this->assertSame(0, Visitor::where('visitor_name', 'Bad Photo')->count());
    }

    public function test_a_residents_own_invitation_is_pre_approved_and_sends_no_request(): void
    {
        $r = $this->request($this->owned, [], $this->ownerA)->assertStatus(201)->assertJsonPath('data.status', 'approved');

        $v = Visitor::findOrFail($r->json('data.id'));
        $this->assertSame('allowed', $v->approval_status);
        $this->assertSame('resident', $v->decision_role);
        $this->assertNotEmpty($v->qr_token);
        $this->assertSame([], $this->bell($this->admin), 'nothing happened at the gate, so no gate notification');

        // the guard checks them in with no second approval
        $this->actingAs($this->guard)->postJson("/api/v1/visitors/{$v->id}/check-in")->assertOk()->assertJsonPath('data.status', 'checked_in');
    }

    public function test_scanning_a_pre_approved_guests_qr_records_who_let_them_in_and_tells_the_unit_without_asking_anyone(): void
    {
        $v = Visitor::findOrFail($this->request($this->owned, [], $this->ownerA)->assertStatus(201)->json('data.id'));
        $this->assertNotEmpty($v->qr_token);

        $this->actingAs($this->guard)->postJson('/guard/scan', ['token' => $v->qr_token])->assertOk()->assertJson(['success' => true]);

        $v->refresh();
        $this->assertSame('entered', $v->entry_status);
        $this->assertSame($this->guard->id, (int) $v->checked_in_by);
        $this->assertContains('Visitor entered', $this->bell($this->ownerA2), 'the household hears their guest arrived');
        $this->assertNotContains('Visitor at the gate', $this->bell($this->ownerA2), 'no approval was asked for');
        $this->assertSame([], $this->bell($this->ownerB));
    }

    // ── the decision ────────────────────────────────────────────────────────

    public function test_the_owner_approves_and_the_guard_sees_the_new_status_without_anything_else(): void
    {
        $v = $this->pending($this->owned);

        $this->actingAs($this->ownerA)->getJson('/api/v1/visitors')->assertOk()
            ->assertJsonPath('data.0.id', $v->id)->assertJsonPath('data.0.can_decide', true);
        $this->actingAs($this->ownerA)->postJson("/api/v1/visitors/{$v->id}/approve")->assertOk()
            ->assertJsonPath('data.status', 'approved')->assertJsonPath('data.decided_by', 'Asha Owner');

        $v->refresh();
        $this->assertSame('allowed', $v->approval_status);
        $this->assertSame($this->ownerA->id, (int) $v->decided_by);
        $this->assertSame($this->ownerA->id, (int) $v->approved_by);
        $this->assertNotNull($v->decided_at);
        $this->assertSame('resident', $v->decision_role);
        $this->assertNotEmpty($v->qr_token);

        // what the guard's list (the app polls it) now says, and what they may do
        $this->actingAs($this->guard)->getJson('/api/v1/visitors')->assertOk()
            ->assertJsonPath('data.0.status', 'approved')->assertJsonPath('data.0.can_check_in', true);
        $this->assertContains('Visitor approved', $this->bell($this->guard));
        $this->assertSame('pending', $v->entry_status, 'approval is not entry');
    }

    public function test_the_owner_rejects_and_normal_check_in_is_refused_everywhere(): void
    {
        $v = $this->pending($this->owned);

        $this->actingAs($this->ownerA)->postJson("/api/v1/visitors/{$v->id}/reject", ['reason' => 'Not expecting anyone'])->assertOk()
            ->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.rejection_reason', 'Not expecting anyone');

        $v->refresh();
        $this->assertSame('denied', $v->approval_status);
        $this->assertSame($this->ownerA->id, (int) $v->decided_by);
        $this->assertNotNull($v->decided_at);

        foreach ([$this->guard, $this->admin] as $who) {
            $this->actingAs($who)->postJson("/api/v1/visitors/{$v->id}/check-in")->assertStatus(422);
        }
        $this->actingAs($this->guard)->post(route('guard.visitors.check-in', $v))->assertRedirect();
        $this->actingAs($this->admin)->post(route('visitors.check-in', $v))->assertRedirect();
        $this->actingAs($this->admin)->post(route('visitors.update-status', $v), ['status' => 'entered'])->assertRedirect();

        $this->assertSame('pending', $v->fresh()->entry_status);
        $this->assertNull($v->fresh()->actual_entry_time);
        $this->assertContains('Visitor rejected', $this->bell($this->guard));
        $this->assertContains('Visitor rejected', $this->bell($this->admin));
        $this->assertContains('Visitor rejected', $this->bell($this->ownerA2), 'the rest of the household learns it was decided');
        $this->assertNotContains('Visitor rejected', $this->bell($this->ownerA), 'the person who decided is not told what they just did');
    }

    public function test_only_the_residents_of_that_unit_or_an_administrator_may_decide(): void
    {
        $v = $this->pending($this->owned);

        // gate staff record, they do not decide - not even on their own request
        foreach ([$this->guard, $this->makeUserWithRole($this->society, 'Staff')] as $staff) {
            $this->actingAs($staff)->postJson("/api/v1/visitors/{$v->id}/approve")->assertStatus(403);
            $this->actingAs($staff)->postJson("/api/v1/visitors/{$v->id}/reject")->assertStatus(403);
        }
        // a neighbour, a tenant of another unit, and another society's admin do not even see it
        foreach ([$this->ownerB, $this->tenantB, $this->villaOwner, $this->otherAdmin, $this->otherOwner] as $stranger) {
            $this->actingAs($stranger)->postJson("/api/v1/visitors/{$v->id}/approve")->assertStatus(404);
            $this->actingAs($stranger)->getJson("/api/v1/visitors/{$v->id}")->assertStatus(404);
        }

        $this->assertSame('pending', $v->fresh()->approval_status);
        $this->assertNull($v->fresh()->decided_by);
    }

    public function test_an_administrator_can_decide_for_a_waiting_visitor_but_must_give_a_reason_to_deny(): void
    {
        $v = $this->pending($this->owned);

        $this->actingAs($this->admin)->postJson("/api/v1/visitors/{$v->id}/reject")->assertStatus(422);
        $this->assertSame('pending', $v->fresh()->approval_status);

        $this->actingAs($this->admin)->postJson("/api/v1/visitors/{$v->id}/approve")->assertOk();
        $this->assertSame('admin', $v->fresh()->decision_role);
        $this->assertContains('Visitor approved', $this->bell($this->guard));
    }

    public function test_two_people_deciding_at_once_leave_exactly_one_final_state(): void
    {
        $v = $this->pending($this->owned);
        $staleForAdmin = Visitor::findOrFail($v->id);   // both screens loaded it while it was pending
        $staleForOwner = Visitor::findOrFail($v->id);

        VisitorApprovalService::decide($staleForOwner, $this->ownerA, 'allowed');

        try {
            VisitorApprovalService::decide($staleForAdmin, $this->admin, 'denied', 'No one asked for this');
            $this->fail('the second, conflicting decision must be refused');
        } catch (VisitorWorkflowException $e) {
            $this->assertSame(409, $e->status);
            $this->assertStringContainsString('already allowed by Asha Owner', $e->getMessage());
        }
        $this->assertSame('allowed', $v->fresh()->approval_status);
        $this->assertSame($this->ownerA->id, (int) $v->fresh()->decided_by);

        // the same person tapping again is the same decision: accepted, not announced twice
        Event::fake([VisitorWorkflowEvent::class, VisitorApproved::class]);
        $again = VisitorApprovalService::decide(Visitor::findOrFail($v->id), $this->ownerA, 'allowed');
        $this->assertFalse($again['changed']);
        Event::assertNotDispatched(VisitorWorkflowEvent::class);
        Event::assertNotDispatched(VisitorApproved::class);

        // and through the API the loser gets a 409 with the winner named
        $this->actingAs($this->ownerA2)->postJson("/api/v1/visitors/{$v->id}/reject")->assertStatus(409)
            ->assertJsonPath('status', 'approved');
    }

    public function test_a_decision_cannot_be_made_once_the_visitor_has_entered(): void
    {
        $v = $this->pending($this->owned);
        VisitorApprovalService::decide($v, $this->ownerA, 'allowed');
        VisitorApprovalService::checkIn(Visitor::findOrFail($v->id), $this->guard);

        $this->actingAs($this->ownerA2)->postJson("/api/v1/visitors/{$v->id}/reject")->assertStatus(409);
        $this->actingAs($this->admin)->postJson("/api/v1/visitors/{$v->id}/override", ['decision' => 'deny', 'reason' => 'Changed our minds'])->assertStatus(409);
        $this->assertSame('allowed', $v->fresh()->approval_status);
    }

    // ── entry and exit ──────────────────────────────────────────────────────

    public function test_check_in_and_out_record_who_and_when_and_notify_only_what_actually_happened(): void
    {
        $v = $this->pending($this->owned);
        $this->actingAs($this->ownerA)->postJson("/api/v1/visitors/{$v->id}/approve")->assertOk();

        $this->assertNotContains('Visitor entered', $this->bell($this->ownerA2), 'approval alone sends no entry notification');
        $this->assertSame([], $this->bell($this->ownerB));

        $this->actingAs($this->guard)->postJson("/api/v1/visitors/{$v->id}/check-in", ['notes' => 'Gate 2'])->assertOk()
            ->assertJsonPath('data.status', 'checked_in');
        $v->refresh();
        $this->assertSame($this->guard->id, (int) $v->checked_in_by);
        $this->assertNotNull($v->actual_entry_time);
        $this->assertContains('Visitor entered', $this->bell($this->ownerA));
        $this->assertContains('Visitor entered', $this->bell($this->ownerA2));
        $this->assertNotContains('Visitor left', $this->bell($this->ownerA));

        // exit before / twice is refused; a clean exit records who and when
        $this->actingAs($this->guard)->postJson("/api/v1/visitors/{$v->id}/check-in")->assertStatus(422);
        $this->actingAs($this->guard)->postJson("/api/v1/visitors/{$v->id}/check-out")->assertOk()->assertJsonPath('data.status', 'checked_out');
        $v->refresh();
        $this->assertSame($this->guard->id, (int) $v->checked_out_by);
        $this->assertNotNull($v->actual_exit_time);
        $this->assertContains('Visitor left', $this->bell($this->ownerA));
        $this->actingAs($this->guard)->postJson("/api/v1/visitors/{$v->id}/check-out")->assertStatus(422);

        $this->assertSame(['created', 'notified', 'allowed', 'notified', 'entry', 'notified', 'exit', 'notified'], $this->logActions($v));
    }

    public function test_a_waiting_visitor_cannot_be_checked_in_and_a_resident_cannot_check_in_a_guard_logged_visitor(): void
    {
        $v = $this->pending($this->owned);

        $this->actingAs($this->guard)->postJson("/api/v1/visitors/{$v->id}/check-in")->assertStatus(422);

        VisitorApprovalService::decide($v, $this->ownerA, 'allowed');
        $this->actingAs($this->ownerA)->postJson("/api/v1/visitors/{$v->id}/check-in")->assertStatus(403);
        $this->actingAs($this->ownerA2)->postJson("/api/v1/visitors/{$v->id}/check-in")->assertStatus(403);
        $this->assertSame('pending', $v->fresh()->entry_status);
    }

    // ── manual override ─────────────────────────────────────────────────────

    public function test_an_administrator_overrides_a_rejection_only_with_a_reason_and_everyone_concerned_is_told(): void
    {
        $v = $this->pending($this->owned);
        $this->actingAs($this->ownerA)->postJson("/api/v1/visitors/{$v->id}/reject", ['reason' => 'Do not know him'])->assertOk();

        // no reason, a token reason, the guard and the owner: all refused, the rejection stands
        $this->actingAs($this->admin)->postJson("/api/v1/visitors/{$v->id}/override", ['decision' => 'allow'])->assertStatus(422);
        $this->actingAs($this->admin)->postJson("/api/v1/visitors/{$v->id}/override", ['decision' => 'allow', 'reason' => 'ok'])->assertStatus(422);
        $this->actingAs($this->guard)->postJson("/api/v1/visitors/{$v->id}/override", ['decision' => 'allow', 'reason' => 'Let him in please'])->assertStatus(403);
        $this->actingAs($this->ownerA)->postJson("/api/v1/visitors/{$v->id}/override", ['decision' => 'allow', 'reason' => 'Changed my mind actually'])->assertStatus(403);
        $this->assertSame('denied', $v->fresh()->approval_status);

        $this->actingAs($this->admin)->postJson("/api/v1/visitors/{$v->id}/override", ['decision' => 'allow', 'reason' => 'Owner confirmed by phone'])->assertOk()
            ->assertJsonPath('data.status', 'approved')->assertJsonPath('data.decision_role', 'override');

        $v->refresh();
        $this->assertSame('allowed', $v->approval_status);
        $this->assertSame($this->admin->id, (int) $v->override_by);
        $this->assertNotNull($v->override_at);
        $this->assertSame('Owner confirmed by phone', $v->override_reason);
        $this->assertNull($v->rejection_reason, 'the old rejection text no longer contradicts the new decision');
        $this->assertNotEmpty($v->qr_token);

        // the audit trail names who, what changed and why
        $log = $v->logs()->where('action', 'override')->firstOrFail();
        $this->assertSame($this->admin->id, (int) $log->user_id);
        $this->assertStringContainsString('from denied to allowed', $log->notes);
        $this->assertStringContainsString('Owner confirmed by phone', $log->notes);

        // never silent: the owner who rejected, the household and the guard all hear it
        foreach ([$this->ownerA, $this->ownerA2, $this->guard] as $who) {
            $this->assertContains('Visitor decision changed', $this->bell($who), $who->name);
        }
        $this->assertNotContains('Visitor decision changed', $this->bell($this->admin), 'the administrator who did it is not notified of it');

        // and the history is visible to the office, not to residents
        $this->actingAs($this->admin)->getJson("/api/v1/visitors/{$v->id}")->assertOk()->assertJsonCount(6, 'data.history')
            ->assertJsonPath('data.history.4.action', 'override');
        $this->actingAs($this->ownerA)->getJson("/api/v1/visitors/{$v->id}")->assertOk()->assertJsonMissingPath('data.history');
    }

    public function test_an_administrator_can_decide_for_residents_who_never_answered_and_can_revoke_before_entry(): void
    {
        $v = $this->pending($this->owned);

        $this->actingAs($this->admin)->postJson("/api/v1/visitors/{$v->id}/override", ['decision' => 'allow', 'reason' => 'Residents unreachable, known courier'])->assertOk();
        $this->assertSame('allowed', $v->fresh()->approval_status);

        // the same decision again is refused, the opposite is a second, recorded override
        $this->actingAs($this->admin)->postJson("/api/v1/visitors/{$v->id}/override", ['decision' => 'allow', 'reason' => 'Trying again'])->assertStatus(409);
        $this->actingAs($this->admin)->postJson("/api/v1/visitors/{$v->id}/override", ['decision' => 'deny', 'reason' => 'Owner called back, refuse'])->assertOk();
        $this->assertSame('denied', $v->fresh()->approval_status);
        $this->assertSame(2, $v->logs()->where('action', 'override')->count());
    }

    public function test_another_societys_administrator_cannot_override(): void
    {
        $v = $this->pending($this->owned);

        $this->actingAs($this->otherAdmin)->postJson("/api/v1/visitors/{$v->id}/override", ['decision' => 'allow', 'reason' => 'Trying from outside'])->assertStatus(404);
        $this->assertSame('pending', $v->fresh()->approval_status);
    }

    // ── nobody answers ──────────────────────────────────────────────────────

    public function test_a_visitor_nobody_answers_is_escalated_once_and_never_admitted(): void
    {
        $v = $this->pending($this->owned);
        $this->artisan('visitors:escalate-unanswered')->assertSuccessful();
        $this->assertNull($v->fresh()->escalated_at, 'not yet: the default wait is 10 minutes');

        Visitor::query()->whereKey($v->id)->update(['created_at' => now()->subMinutes(11)]);
        $this->artisan('visitors:escalate-unanswered')->expectsOutput('1 waiting visitor(s) escalated.')->assertSuccessful();

        $v->refresh();
        $this->assertNotNull($v->escalated_at);
        $this->assertSame('pending', $v->approval_status, 'time passing never admits anyone');
        $this->assertSame('pending', $v->entry_status);
        $this->assertContains('Visitor waiting - no answer', $this->bell($this->admin));
        $this->assertContains('Visitor waiting - no answer', $this->bell($this->guard));

        $this->artisan('visitors:escalate-unanswered')->expectsOutput('0 waiting visitor(s) escalated.');
        $this->assertSame(1, collect($this->bell($this->admin))->filter(fn ($t) => $t === 'Visitor waiting - no answer')->count());
        $this->assertSame(1, $v->logs()->where('action', 'escalated')->count());
    }

    public function test_the_wait_is_configurable_per_society_and_zero_switches_it_off_and_decided_visitors_are_left_alone(): void
    {
        $waiting = $this->pending($this->owned);
        $decided = $this->pending($this->rented);
        VisitorApprovalService::decide($decided, $this->ownerB, 'allowed');
        Visitor::query()->update(['created_at' => now()->subMinutes(30)]);

        SystemSetting::set('security', 'visitor_no_response_minutes', 0, 'integer', $this->society->id);
        $this->artisan('visitors:escalate-unanswered')->expectsOutput('0 waiting visitor(s) escalated.');
        $this->assertNull($waiting->fresh()->escalated_at);

        SystemSetting::set('security', 'visitor_no_response_minutes', 45, 'integer', $this->society->id);
        $this->artisan('visitors:escalate-unanswered')->expectsOutput('0 waiting visitor(s) escalated.');

        SystemSetting::set('security', 'visitor_no_response_minutes', 20, 'integer', $this->society->id);
        $this->artisan('visitors:escalate-unanswered')->expectsOutput('1 waiting visitor(s) escalated.');
        $this->assertNotNull($waiting->fresh()->escalated_at);
        $this->assertNull($decided->fresh()->escalated_at);
    }

    public function test_the_wait_is_set_by_the_society_admin_in_security_settings(): void
    {
        $form = ['login_attempt_limit' => 5, 'session_timeout' => 120, 'password_min_length' => 8];

        $this->actingAs($this->admin)->post(route('settings.security.update'), $form + ['visitor_no_response_minutes' => 3])->assertRedirect();
        $this->assertSame(3, (int) SystemSetting::get('security', 'visitor_no_response_minutes', 10, $this->society->id));
        $this->assertSame(10, (int) SystemSetting::get('security', 'visitor_no_response_minutes', 10, $this->other->id), 'another society keeps the default');
        $this->actingAs($this->admin)->get('/settings?tab=security')->assertOk()->assertSee('Visitor no-response time')->assertSee('value="3"', false);

        // out of range is refused and changes nothing; leaving the field out changes nothing
        $this->actingAs($this->admin)->post(route('settings.security.update'), $form + ['visitor_no_response_minutes' => 999])->assertSessionHasErrors('visitor_no_response_minutes');
        $this->actingAs($this->admin)->post(route('settings.security.update'), $form)->assertRedirect();
        $this->assertSame(3, (int) SystemSetting::get('security', 'visitor_no_response_minutes', 10, $this->society->id));

        // and it is what the escalation uses
        $v = $this->pending($this->owned);
        Visitor::query()->whereKey($v->id)->update(['created_at' => now()->subMinutes(4)]);
        $this->artisan('visitors:escalate-unanswered')->expectsOutput('1 waiting visitor(s) escalated.');
    }

    // ── isolation ───────────────────────────────────────────────────────────

    public function test_residents_see_and_count_only_their_own_units_visitors_and_the_guard_the_whole_society(): void
    {
        $mine = $this->pending($this->owned, ['visitor_name' => 'For Asha']);
        $neighbours = $this->pending($this->rented, ['visitor_name' => 'For Bala']);
        $foreignVisitor = Visitor::create([
            'society_id' => $this->other->id, 'flat_id' => $this->foreign->id, 'host_user_id' => $this->otherAdmin->id, 'visitor_name' => 'Elsewhere',
            'visitor_phone' => '9000000000', 'visitor_type' => 'guest', 'expected_entry_time' => now(), 'approval_status' => 'pending', 'entry_status' => 'pending',
        ]);

        $this->assertSame([$mine->id], collect($this->actingAs($this->ownerA)->getJson('/api/v1/visitors')->json('data'))->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$mine->id, $neighbours->id], collect($this->actingAs($this->guard)->getJson('/api/v1/visitors')->json('data'))->pluck('id')->all());
        $this->assertSame(1, $this->actingAs($this->ownerA)->getJson('/api/v1/visitors/dashboard')->json('data.pending'));
        $this->assertSame(2, $this->actingAs($this->guard)->getJson('/api/v1/visitors/dashboard')->json('data.pending'));
        $this->actingAs($this->guard)->postJson("/api/v1/visitors/{$foreignVisitor->id}/check-in")->assertStatus(404);
    }

    // ── channels ────────────────────────────────────────────────────────────

    public function test_the_request_email_is_queued_for_each_valid_address_with_every_variable_resolved(): void
    {
        $this->ownerA2->update(['email' => 'not-an-address']);
        Bus::fake([DispatchEventNotification::class]);

        $v = $this->pending($this->owned);

        Bus::assertDispatchedTimes(DispatchEventNotification::class, 2);
        Bus::assertDispatched(DispatchEventNotification::class, fn ($job) => $job->triggerEvent === 'visitor_request'
            && $job->recipient['email'] === $this->ownerA->email && $job->societyId === $this->society->id
            && str_starts_with($job->notificationKey, "visitor_request:{$v->id}:"));
        Bus::assertDispatched(DispatchEventNotification::class, fn ($job) => $job->recipient['email'] === $this->admin->email);
        Bus::assertNotDispatched(DispatchEventNotification::class, fn ($job) => $job->recipient['email'] === 'not-an-address');
        $this->assertStringContainsString('email=queued(2)', $this->auditNotes($v, 'requested'));
    }

    public function test_every_new_email_really_sends_and_no_placeholder_is_left_unresolved(): void
    {
        $v = $this->pending($this->owned);
        VisitorApprovalService::decide($v, $this->ownerA, 'denied', 'Not expecting anyone');
        VisitorApprovalService::override(Visitor::findOrFail($v->id), $this->admin, 'allowed', 'Owner confirmed by phone');
        VisitorApprovalService::checkIn(Visitor::findOrFail($v->id), $this->guard);

        foreach (['visitor_request', 'visitor_rejected', 'visitor_override', 'visitor_checked_in'] as $trigger) {
            $logs = EmailLog::where('trigger_action', $trigger)->where('status', 'sent')->get();
            $this->assertGreaterThan(0, $logs->count(), "no sent e-mail for {$trigger}");

            $template = EmailTemplate::getTemplateForEvent($trigger, $this->society->id);
            preg_match_all('/\{\{(\w+)\}\}/', $template->subject . ' ' . $template->body, $m);
            foreach ($logs as $log) {
                foreach (array_unique($m[1]) as $placeholder) {
                    $this->assertArrayHasKey($placeholder, $log->variables, "{$trigger}: {{{$placeholder}}} would reach {$log->recipient_email} unresolved");
                    $this->assertNotSame('', trim((string) $log->variables[$placeholder]), "{$trigger}: {$placeholder} is empty");
                }
            }
        }
        // the rejection e-mail goes to the household and the admin, not to the person who rejected or to the guard
        $rejected = EmailLog::where('trigger_action', 'visitor_rejected')->pluck('recipient_email')->all();
        $this->assertContains($this->admin->email, $rejected);
        $this->assertContains($this->ownerA2->email, $rejected);
        $this->assertNotContains($this->ownerA->email, $rejected);
        $this->assertNotContains($this->guard->email, $rejected);
    }

    public function test_text_a_guard_typed_cannot_inject_markup_into_an_email_and_the_visitors_number_is_masked(): void
    {
        $v = $this->pending($this->owned, ['visitor_name' => '<a href="http://evil.test">Click</a> & Co', 'purpose' => '<script>alert(1)</script>', 'visitor_phone' => '9876543210']);

        $log = EmailLog::where('trigger_action', 'visitor_request')->where('recipient_email', $this->ownerA->email)->firstOrFail();

        $this->assertStringNotContainsString('<a ', $log->variables['visitor_name']);
        $this->assertStringContainsString('&lt;a href=', $log->variables['visitor_name']);
        $this->assertStringNotContainsString('<script>', $log->variables['purpose']);
        $this->assertStringNotContainsString('9876543210', json_encode($log->variables), 'the full number is not put in the mail or kept in the delivery log');
        $this->assertStringContainsString('3210', $log->variables['visitor_phone']);
    }

    public function test_a_switched_off_email_rule_skips_email_only(): void
    {
        NotificationRules::setEnabled('visitor_request', 'email', false, $this->society->id);

        $v = $this->pending($this->owned);

        $this->assertSame(0, EmailLog::where('trigger_action', 'visitor_request')->count());
        $this->assertStringContainsString('email=skipped_disabled', $this->auditNotes($v, 'requested'));
        $this->assertContains('Visitor at the gate', $this->bell($this->ownerA), 'the bell still works');
    }

    public function test_whatsapp_sends_the_approved_arrival_template_with_the_values_in_its_own_order(): void
    {
        $this->configureWhatsApp();
        $this->captureWhatsApp();

        $v = $this->pending($this->owned);

        // owner + household account, not the admin: the message is addressed to the people of the unit
        $this->assertCount(2, $this->wa);
        $this->assertSame('37328', $this->wa[0]['message_id']);
        $this->assertSame('1209719742234591', $this->wa[0]['phone_number_id']);
        $when = $v->fresh()->expected_entry_time->format('d M, h:i A');
        $values = collect($this->wa)->pluck('variables_values')->all();
        $this->assertEqualsCanonicalizing(["Asha Owner|Ravi Courier|{$when}", "Asha Spouse|Ravi Courier|{$when}"], $values);
        foreach ($values as $value) {
            $this->assertStringNotContainsString('{{', $value);
        }
        $this->assertStringContainsString('whatsapp=queued(2)', $this->auditNotes($v, 'requested'));
        $this->assertSame(2, EmailLog::where('channel', 'whatsapp')->where('trigger_action', 'visitor_arrival')->where('status', 'sent')->count());
    }

    public function test_whatsapp_trouble_never_stops_the_request_or_the_other_channels(): void
    {
        // not switched on / not set up / no template / not approved: said plainly, nothing is sent
        $cases = [
            'skipped_no_template'            => fn () => null,
            'skipped_disabled'               => fn () => $this->configureWhatsApp('APPROVED', false),
            'skipped_template_not_approved'  => fn () => $this->configureWhatsApp('PENDING'),
        ];
        foreach ($cases as $expected => $setUp) {
            WhatsAppTemplate::query()->delete();
            SystemSetting::query()->where('group', 'whatsapp')->delete();
            $setUp();
            $this->captureWhatsApp();

            $v = $this->pending($this->owned, ['visitor_phone' => '98' . random_int(10000000, 99999999)]);

            $this->assertSame([], $this->wa, $expected . ': no provider request');
            $this->assertStringContainsString("whatsapp={$expected}", $this->auditNotes($v, 'requested'));
            $this->assertStringContainsString('in_app=sent(3)', $this->auditNotes($v, 'requested'));
            $this->assertStringContainsString('email=queued(', $this->auditNotes($v, 'requested'));
            $this->assertSame('pending', $v->approval_status);
        }

        // key missing: not configured
        WhatsAppTemplate::query()->delete();
        SystemSetting::query()->where('group', 'whatsapp')->delete();
        $this->configureWhatsApp();
        SystemSetting::set('whatsapp', 'whatsapp_api_key', '', 'encrypted', $this->society->id);
        $this->captureWhatsApp();
        $v = $this->pending($this->owned, ['visitor_phone' => '9811122233']);
        $this->assertSame([], $this->wa);
        $this->assertStringContainsString('whatsapp=skipped_not_configured', $this->auditNotes($v, 'requested'));
    }

    /**
     * Found by running the workflow against a copy of the development data: a society whose only "template" for a
     * notification is an older setting naming another product's (BookMizo) template was reported as `queued`, then the
     * sender refused it. The status now asks the sender's own rule, so it says what will actually happen.
     */
    public function test_an_older_setting_naming_another_products_template_is_reported_skipped_not_queued(): void
    {
        SystemSetting::set('whatsapp', 'whatsapp_enabled', true, 'boolean', $this->society->id);
        SystemSetting::set('whatsapp', 'whatsapp_api_key', 'wa-key-0123456789abcdefghijKLMN', 'encrypted', $this->society->id);
        SystemSetting::set('whatsapp', 'whatsapp_phone_number_id', '1209719742234591', 'string', $this->society->id);
        SystemSetting::set('whatsapp', 'whatsapp_visitor_arrival_template_name', 'bookmizo_booking_confirm_v2', 'string', $this->society->id);
        $this->captureWhatsApp();

        $v = $this->pending($this->owned);

        $this->assertSame([], $this->wa, 'nothing reached the provider');
        $this->assertStringContainsString('whatsapp=skipped_template_not_sendable', $this->auditNotes($v, 'requested'));
        $this->assertSame(0, EmailLog::where('channel', 'whatsapp')->count());
    }

    public function test_a_provider_404_or_a_200_that_carries_an_error_is_recorded_as_failed_and_the_visit_is_still_saved(): void
    {
        $this->configureWhatsApp();

        foreach ([[['status' => false, 'message' => 'WABA or Phone Number not found'], 404], [['error' => ['code' => 100, 'message' => '(#100) Param is invalid']], 200]] as $i => [$answer, $code]) {
            $this->captureWhatsApp($answer, $code);
            \Illuminate\Support\Facades\Cache::flush();

            $v = $this->pending($this->owned, ['visitor_phone' => '98765432' . (10 + $i)]);

            $this->assertNotEmpty($this->wa, 'the provider was asked');
            $this->assertSame('pending', $v->fresh()->approval_status, 'the request is saved');
            $this->assertContains('Visitor at the gate', $this->bell($this->ownerA), 'the bell was written');
            $this->assertSame(0, EmailLog::where('channel', 'whatsapp')->where('status', 'sent')->count(), 'no false "sent"');
            $this->assertGreaterThan(0, EmailLog::where('channel', 'whatsapp')->where('status', 'failed')->count());
            $this->assertGreaterThan(0, EmailLog::where('trigger_action', 'visitor_request')->where('channel', 'email')->where('status', 'sent')->count(), 'e-mail was independent');
        }
    }

    public function test_the_external_messaging_kill_switch_blocks_every_real_send_but_not_the_workflow(): void
    {
        $this->configureWhatsApp();
        WhatsAppService::$transportOverride = null;   // the real transport, which the switch and the test guard protect
        config(['messaging.enabled' => false]);

        $v = $this->pending($this->owned);

        Http::assertNothingSent();
        $this->assertSame([], $this->consumeBlockedOutbound(), 'the switch stopped it before any outbound call was attempted');
        $this->assertSame('pending', $v->approval_status);
        $this->assertContains('Visitor at the gate', $this->bell($this->ownerA));
        $this->assertSame(0, EmailLog::where('channel', 'whatsapp')->where('status', 'sent')->count());
    }

    public function test_sms_is_reported_as_not_sent_because_no_approved_visitor_sms_template_exists(): void
    {
        $v = $this->pending($this->owned);

        $this->assertStringContainsString('sms=skipped_no_template', $this->auditNotes($v, 'requested'));
    }

    public function test_the_audit_notes_hold_no_phone_number_address_or_message_text(): void
    {
        $this->configureWhatsApp();
        $this->captureWhatsApp();
        $v = $this->pending($this->owned);
        VisitorApprovalService::decide($v, $this->ownerA, 'allowed');

        foreach ($v->fresh()->logs()->where('action', 'notified')->pluck('notes') as $note) {
            $this->assertDoesNotMatchRegularExpression('/\d{8,}/', $note, 'a phone number');
            $this->assertStringNotContainsString('@', $note);
            $this->assertStringNotContainsString('Ravi', $note);
        }
    }

    // ── the existing "visitor approved" message now reaches the right people ──

    public function test_an_approval_is_announced_to_the_rest_of_the_unit_by_the_existing_approved_event_once(): void
    {
        Bus::fake([DispatchEventNotification::class]);
        $v = $this->pending($this->rented);
        Bus::assertNotDispatched(DispatchEventNotification::class, fn ($job) => $job->triggerEvent === 'visitor_approved');

        VisitorApprovalService::decide($v, $this->tenantB, 'allowed');

        // the owner is told; the tenant who decided is not
        Bus::assertDispatched(DispatchEventNotification::class, fn ($job) => $job->triggerEvent === 'visitor_approved' && $job->recipient['email'] === $this->ownerB->email);
        Bus::assertNotDispatched(DispatchEventNotification::class, fn ($job) => $job->triggerEvent === 'visitor_approved' && $job->recipient['email'] === $this->tenantB->email);
        $this->assertContains('Visitor approved', $this->bell($this->ownerB));
        $this->assertNotContains('Visitor approved', $this->bell($this->tenantB));
        $this->assertStringContainsString('email=left_to_visitor_approved_event', $this->auditNotes($v, 'approved'));
    }

    public function test_an_administrators_override_to_allowed_sends_the_approved_whatsapp_to_the_residents(): void
    {
        $approved = $this->configureWhatsApp();
        $approved->update(['name' => 'society_visitor_approved', 'purpose' => 'visitor', 'message_id' => '37327', 'var_count' => 4,
            'body_text' => "Hello {{1}},\n\nYour visitor {{2}} has been approved for entry at {{3}}.\nPlease follow society security guidelines. Contact security if needed: {{4}}. Thank you."]);
        $this->society->update(['phone' => '9000011111']);
        $v = $this->pending($this->owned);
        VisitorApprovalService::decide($v, $this->ownerA, 'denied', 'Not now');
        $this->captureWhatsApp();

        VisitorApprovalService::override(Visitor::findOrFail($v->id), $this->admin, 'allowed', 'Owner confirmed by phone');

        $this->assertCount(2, $this->wa, 'owner and household account');
        $this->assertSame('37327', $this->wa[0]['message_id']);
        $this->assertStringContainsString('whatsapp=queued(2)', $this->auditNotes($v, 'overridden'));
    }

    // ── web: decisions are never silent or direct ───────────────────────────

    public function test_pressing_allow_on_a_denied_visitor_needs_a_reason_and_becomes_a_recorded_override(): void
    {
        $v = $this->pending($this->owned);
        VisitorApprovalService::decide($v, $this->ownerA, 'denied', 'No');

        $this->actingAs($this->admin)->post(route('visitors.allow', $v))->assertSessionHasErrors('reason');
        $this->assertSame('denied', $v->fresh()->approval_status);

        $this->actingAs($this->admin)->post(route('visitors.allow', $v), ['reason' => 'Confirmed with the owner'])->assertRedirect(route('visitors.index'));
        $this->assertSame('allowed', $v->fresh()->approval_status);
        $this->assertSame('override', $v->fresh()->decision_role);
        $this->assertContains('override', $this->logActions($v));
    }

    public function test_the_web_status_screen_and_edit_form_cannot_silently_change_a_decision_or_an_entry(): void
    {
        $v = $this->pending($this->owned);
        VisitorApprovalService::decide($v, $this->ownerA, 'allowed');

        $this->actingAs($this->admin)->post(route('visitors.update-status', $v), ['status' => 'pending'])->assertSessionHas('error');
        $this->assertSame('allowed', $v->fresh()->approval_status);

        // the edit form still posts approval/entry status; they are ignored
        $this->actingAs($this->admin)->put(route('visitors.update', $v), [
            'visitor_name' => 'Ravi Courier', 'visitor_phone' => '9876543210', 'visitor_type' => 'delivery', 'flat_id' => $this->owned->id,
            'expected_entry_time' => now()->toDateString(), 'in_time' => '10:00', 'approval_status' => 'pending', 'entry_status' => 'exited',
        ])->assertRedirect();
        $v->refresh();
        $this->assertSame('allowed', $v->approval_status);
        $this->assertSame('pending', $v->entry_status);
    }

    public function test_staff_cannot_use_the_web_allow_and_deny_buttons_any_more(): void
    {
        $v = $this->pending($this->owned);
        $staff = $this->makeUserWithRole($this->society, 'Staff');

        $this->actingAs($staff)->post(route('visitors.allow', $v))->assertForbidden();
        $this->actingAs($staff)->post(route('visitors.deny', $v), ['reason' => 'x'])->assertForbidden();
        $this->assertSame('pending', $v->fresh()->approval_status);
    }

    public function test_the_guards_web_registration_uses_the_same_workflow(): void
    {
        $this->actingAs($this->guard)->post(route('guard.visitors.store'), [
            'flat_id' => $this->rented->id, 'visitor_name' => 'Walk In', 'visitor_phone' => '9888888888', 'visitor_type' => 'guest',
        ])->assertRedirect(route('guard.dashboard'));

        $v = Visitor::where('visitor_name', 'Walk In')->firstOrFail();
        $this->assertSame($this->guard->id, (int) $v->requested_by);
        $this->assertContains('Visitor at the gate', $this->bell($this->tenantB));
        $this->assertContains('Visitor at the gate', $this->bell($this->ownerB));
        $this->assertContains('Visitor at the gate', $this->bell($this->admin));
    }

    public function test_the_visitor_page_offers_an_administrator_the_override_form_and_residents_do_not_get_it(): void
    {
        $v = $this->pending($this->owned);
        VisitorApprovalService::decide($v, $this->ownerA, 'denied', 'No');

        $this->actingAs($this->admin)->get(route('visitors.show', $v))->assertOk()->assertSee('Allow anyway (override)');
        $this->actingAs($this->ownerA)->get(route('visitors.show', $v))->assertOk()->assertDontSee('Allow anyway (override)');
    }
}
