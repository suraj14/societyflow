<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Owner;
use App\Models\Resident;
use App\Models\Society;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VillaArea;
use App\Models\Visitor;
use App\Support\UserFlatResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-aware, role-aware visitor management.
 *
 * The Add Visitor form used to demand a Villa Area and showed "No villa areas
 * available" in villa-only societies: the property-type partial fired the
 * villa loader on DOMContentLoaded, before the async /api/flats fetch had
 * returned, so the list was built from an empty array and never refreshed.
 * The form now takes the destination property directly (server-rendered),
 * and a tenant's destination is resolved server-side from their tenancy.
 *
 * Fixture mirrors the reported Royal Eden case: Villa 159 owned by Lokesh and
 * rented to Piyus, Villa 158 owned by Naresh, Villa 160 unowned - and no
 * Villa Areas at all.
 */
class VisitorPropertyFlowTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;
    private Flat $villa159;
    private Flat $villa158;
    private Flat $villa160;
    private User $admin;
    private User $guard;
    private User $piyus;   // tenant of 159
    private User $lokesh;  // owner of 159
    private Owner $lokeshOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->society = $this->makeSociety('Royal Eden');
        $this->society->update(['property_type' => 'villa']);

        $this->lokeshOwner = $this->owner('Lokesh Suman');
        $nareshOwner = $this->owner('Naresh Suman');

        $this->villa159 = $this->villa($this->society, '159', $this->lokeshOwner, 'on_rent');
        $this->villa158 = $this->villa($this->society, '158', $nareshOwner);
        $this->villa160 = $this->villa($this->society, '160');

        $this->admin = $this->makeUserWithRole($this->society, 'Admin', ['name' => 'Royal Eden Admin']);
        $this->guard = $this->makeUserWithRole($this->society, 'Guard');

        $this->lokesh = $this->makeUserWithRole($this->society, 'Owner', [
            'name' => 'Lokesh Suman', 'owner_id' => $this->lokeshOwner->id,
        ]);

        $this->piyus = $this->makeUserWithRole($this->society, 'Tenant', ['name' => 'Piyus Chudarri']);
        $tenancy = Tenant::create([
            'society_id' => $this->society->id, 'flat_id' => $this->villa159->id, 'user_id' => $this->piyus->id,
            'name' => 'Piyus Chudarri', 'email' => $this->piyus->email, 'phone' => '9000000159',
            'move_in_date' => now(), 'status' => 'active',
        ]);
        $this->piyus->update(['tenant_id' => $tenancy->id]);

        // A stale legacy residents row pointing Piyus at Villa 160 exists in
        // the real data; the active tenancy must win over it.
        Resident::create([
            'society_id' => $this->society->id, 'flat_id' => $this->villa160->id, 'user_id' => $this->piyus->id,
            'name' => 'Piyus Chudarri', 'email' => $this->piyus->email, 'phone' => '9000000159',
            'type' => 'tenant', 'move_in_date' => now()->toDateString(), 'status' => 'active',
        ]);
    }

    // ------------------------------------------------------------------ admin

    public function test_admin_can_create_visitor_for_a_villa_without_villa_areas(): void
    {
        $this->assertSame(0, VillaArea::count(), 'fixture must have no villa areas');

        $this->actingAs($this->admin)->get(route('visitors.create'))
            ->assertOk()
            ->assertSee('Visiting Property')
            ->assertSee('Villa 159 — Lokesh Suman')
            // (The admin sidebar legitimately links to "Villa Areas", so the
            // form's own field is what's asserted absent.)
            ->assertDontSee('villa_area_select', false)
            ->assertDontSee('Select Villa Area')
            ->assertDontSee('No villa areas available');

        $this->actingAs($this->admin)
            ->post(route('visitors.store'), $this->visitorPayload(['flat_id' => $this->villa159->id]))
            ->assertRedirect(route('visitors.index'));

        $this->assertDatabaseHas('visitors', [
            'visitor_name'    => 'Ramesh Guest',
            'flat_id'         => $this->villa159->id,
            'society_id'      => $this->society->id,
            'host_user_id'    => $this->admin->id,
            'approval_status' => 'pending',
        ]);
    }

    public function test_admin_can_create_visitor_for_any_villa_in_the_society(): void
    {
        $this->actingAs($this->admin)->get(route('visitors.create'))
            ->assertOk()
            ->assertSee('Villa 158 — Naresh Suman')
            ->assertSee('Villa 160 — Not Assigned');

        foreach ([$this->villa158, $this->villa160] as $villa) {
            $this->actingAs($this->admin)
                ->post(route('visitors.store'), $this->visitorPayload(['flat_id' => $villa->id]))
                ->assertRedirect(route('visitors.index'));

            $this->assertDatabaseHas('visitors', ['flat_id' => $villa->id, 'society_id' => $this->society->id]);
        }
    }

    public function test_admin_cannot_create_visitor_for_another_societys_villa(): void
    {
        $other = $this->makeSociety('Other Estate');
        $foreign = $this->villa($other, '900');

        $this->actingAs($this->admin)
            ->post(route('visitors.store'), $this->visitorPayload(['flat_id' => $foreign->id]))
            ->assertForbidden();

        $this->assertDatabaseMissing('visitors', ['flat_id' => $foreign->id]);
    }

    public function test_admin_still_sees_society_wide_visitor_list_with_property_and_host(): void
    {
        $this->pendingVisitor($this->villa158, 'Naresh Guest');
        $this->pendingVisitor($this->villa159, 'Piyus Guest');

        $this->actingAs($this->admin)->get(route('visitors.index'))
            ->assertOk()
            ->assertSee('Naresh Guest')
            ->assertSee('Piyus Guest')
            ->assertSee('Villa 158')
            ->assertSee('Check-in')
            ->assertSee('Check-out');

        // Property filter
        $this->actingAs($this->admin)->get(route('visitors.index', ['flat_id' => $this->villa158->id]))
            ->assertOk()
            ->assertSee('Naresh Guest')
            ->assertDontSee('Piyus Guest');
    }

    // ----------------------------------------------------------------- tenant

    public function test_tenant_add_visitor_form_shows_only_their_villa_read_only(): void
    {
        $this->actingAs($this->piyus)->get(route('visitors.create'))
            ->assertOk()
            ->assertSee('Invite Visitor')
            ->assertSee('Visiting:')
            ->assertSee('Villa 159')
            ->assertDontSee('name="flat_id"', false)
            ->assertDontSee('name="property_type"', false)
            ->assertDontSee('villa_area_select', false)
            ->assertDontSee('Villa 158')
            ->assertDontSee('Villa 160');
    }

    public function test_tenant_can_create_visitor_for_assigned_villa(): void
    {
        $this->actingAs($this->piyus)
            ->post(route('visitors.store'), $this->visitorPayload())
            ->assertRedirect(route('visitors.index'));

        $visitor = Visitor::where('visitor_name', 'Ramesh Guest')->firstOrFail();

        $this->assertSame($this->villa159->id, (int) $visitor->flat_id);
        $this->assertSame($this->society->id, (int) $visitor->society_id);
        $this->assertSame($this->piyus->id, (int) $visitor->host_user_id);
        // Resident's own invite is pre-approved and gets the existing gate QR token.
        $this->assertSame('allowed', $visitor->approval_status);
        $this->assertSame($this->piyus->id, (int) $visitor->approved_by);
        $this->assertNotEmpty($visitor->qr_token);
    }

    public function test_tenant_cannot_create_visitor_for_another_villa(): void
    {
        // Naresh's villa, and Villa 160 that a stale residents row points at.
        foreach ([$this->villa158, $this->villa160] as $villa) {
            $this->actingAs($this->piyus)
                ->post(route('visitors.store'), $this->visitorPayload(['flat_id' => $villa->id]))
                ->assertForbidden();

            $this->assertDatabaseMissing('visitors', ['flat_id' => $villa->id]);
        }
    }

    public function test_tenant_cannot_change_destination_through_request_manipulation(): void
    {
        $other = $this->makeSociety('Other Estate');
        $foreign = $this->villa($other, '159');

        // Another society's unit with the same number.
        $this->actingAs($this->piyus)
            ->post(route('visitors.store'), $this->visitorPayload(['flat_id' => $foreign->id]))
            ->assertForbidden();
        $this->assertSame(0, Visitor::count());

        // A smuggled society_id is ignored - society comes from the property.
        $this->actingAs($this->piyus)
            ->post(route('visitors.store'), $this->visitorPayload([
                'flat_id' => $this->villa159->id, 'society_id' => $other->id,
            ]))
            ->assertRedirect(route('visitors.index'));

        $this->assertDatabaseHas('visitors', ['flat_id' => $this->villa159->id, 'society_id' => $this->society->id]);
        $this->assertDatabaseMissing('visitors', ['society_id' => $other->id]);
    }

    public function test_tenant_can_approve_and_deny_visitors_for_assigned_villa(): void
    {
        $first = $this->pendingVisitor($this->villa159, 'Courier One');
        $second = $this->pendingVisitor($this->villa159, 'Courier Two');

        $this->actingAs($this->piyus)->get(route('visitors.index'))
            ->assertOk()
            ->assertSee('Courier One')
            ->assertSee('visitorDropdownButton' . $first->id, false);

        $this->actingAs($this->piyus)->post(route('visitors.allow', $first))
            ->assertRedirect(route('visitors.index'));
        $first->refresh();
        $this->assertSame('allowed', $first->approval_status);
        $this->assertSame($this->piyus->id, (int) $first->approved_by);
        $this->assertNotEmpty($first->qr_token);

        // A resident need not give a reason.
        $this->actingAs($this->piyus)->post(route('visitors.deny', $second))
            ->assertRedirect(route('visitors.index'));
        $this->assertSame('denied', $second->fresh()->approval_status);
    }

    public function test_tenant_cannot_approve_or_see_visitors_for_another_property(): void
    {
        $neighbours = $this->pendingVisitor($this->villa158, 'Naresh Guest');

        $this->actingAs($this->piyus)->post(route('visitors.allow', $neighbours))->assertForbidden();
        $this->actingAs($this->piyus)->post(route('visitors.deny', $neighbours))->assertForbidden();
        $this->assertSame('pending', $neighbours->fresh()->approval_status);

        $this->actingAs($this->piyus)->get(route('visitors.show', $neighbours))->assertForbidden();
        $this->actingAs($this->piyus)->get(route('visitors.index'))
            ->assertOk()
            ->assertDontSee('Naresh Guest');
    }

    public function test_tenant_cannot_approve_another_societys_visitor(): void
    {
        $other = $this->makeSociety('Other Estate');
        $foreign = $this->pendingVisitor($this->villa($other, '159'), 'Foreign Guest');

        $this->actingAs($this->piyus)->post(route('visitors.allow', $foreign))->assertForbidden();
        $this->assertSame('pending', $foreign->fresh()->approval_status);
    }

    public function test_guard_cannot_approve_on_residents_behalf(): void
    {
        $visitor = $this->pendingVisitor($this->villa159, 'Gate Guest');

        $this->actingAs($this->guard)->post(route('visitors.allow', $visitor))->assertForbidden();
        $this->assertSame('pending', $visitor->fresh()->approval_status);
    }

    // ----------------------------------------------------------- owner/tenant

    public function test_owner_remains_owner_when_the_property_has_a_tenant(): void
    {
        $this->actingAs($this->piyus)->post(route('visitors.store'), $this->visitorPayload());

        $this->assertSame($this->lokeshOwner->id, (int) $this->villa159->fresh()->owner_id);
        $this->assertTrue($this->lokesh->fresh()->hasRole('Owner'));
        $this->assertSame($this->lokeshOwner->id, (int) $this->lokesh->fresh()->owner_id);
        $this->assertContains($this->villa159->id, UserFlatResolver::ownedFlatIds($this->lokesh)->all());

        $this->assertFalse($this->piyus->fresh()->hasRole('Owner'));
        $this->assertNull($this->piyus->fresh()->owner_id);

        // The owner keeps visibility of their own villa's visitors.
        $this->actingAs($this->lokesh)->get(route('visitors.index'))
            ->assertOk()
            ->assertSee('Ramesh Guest');
    }

    // ------------------------------------------------------------ guard / QR

    public function test_guard_registered_visitor_is_approved_by_tenant_then_checked_in_and_out(): void
    {
        $this->actingAs($this->guard)->post(route('guard.visitors.store'), [
            'flat_id' => $this->villa159->id, 'visitor_name' => 'Walk In',
            'visitor_phone' => '9888888888', 'visitor_type' => 'guest',
        ])->assertRedirect(route('guard.dashboard'));

        $visitor = Visitor::where('visitor_name', 'Walk In')->firstOrFail();
        $this->assertSame('pending', $visitor->approval_status);

        $this->actingAs($this->piyus)->post(route('visitors.allow', $visitor));
        $this->assertSame('allowed', $visitor->fresh()->approval_status);

        $this->actingAs($this->guard)->post(route('guard.visitors.check-in', $visitor))->assertRedirect();
        $this->assertSame('entered', $visitor->fresh()->entry_status);

        $this->actingAs($this->guard)->post(route('guard.visitors.check-out', $visitor))->assertRedirect();
        $this->assertSame('exited', $visitor->fresh()->entry_status);
    }

    public function test_tenant_invited_visitor_can_be_scanned_in_with_existing_guest_qr(): void
    {
        $this->actingAs($this->piyus)->post(route('visitors.store'), $this->visitorPayload());
        $visitor = Visitor::where('visitor_name', 'Ramesh Guest')->firstOrFail();

        $this->actingAs($this->piyus)->get(route('visitors.show', $visitor))
            ->assertOk()
            ->assertSee($visitor->qr_token);

        $this->actingAs($this->guard)->postJson('/guard/scan', ['token' => $visitor->qr_token])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame('entered', $visitor->fresh()->entry_status);
    }

    // --------------------------------------------------------------- helpers

    private function owner(string $name): Owner
    {
        return Owner::create([
            'society_id' => $this->society->id,
            'name'       => $name,
            'email'      => 'owner' . uniqid() . '@test.local',
            'phone'      => '9' . random_int(100000000, 999999999),
            'status'     => 'active',
        ]);
    }

    private function villa(Society $society, string $number, ?Owner $owner = null, string $status = 'occupied'): Flat
    {
        return Flat::create([
            'society_id'    => $society->id,
            'property_type' => 'villa',
            'villa_area_id' => null,
            'owner_id'      => $owner?->id,
            'flat_number'   => $number,
            'floor'         => 0,
            'status'        => $status,
        ]);
    }

    private function pendingVisitor(Flat $flat, string $name): Visitor
    {
        return Visitor::create([
            'society_id'          => $flat->society_id,
            'flat_id'             => $flat->id,
            'host_user_id'        => $this->guard->id,
            'visitor_name'        => $name,
            'visitor_phone'       => '9777777777',
            'visitor_type'        => 'delivery',
            'expected_entry_time' => now(),
            'approval_status'     => 'pending',
            'entry_status'        => 'pending',
        ]);
    }

    private function visitorPayload(array $overrides = []): array
    {
        return array_merge([
            'visitor_name'        => 'Ramesh Guest',
            'visitor_phone'       => '9876543210',
            'visitor_type'        => 'guest',
            'expected_entry_time' => now()->toDateString(),
            'in_time'             => '18:30',
        ], $overrides);
    }
}
