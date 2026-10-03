<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Owner;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VillaArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Visitor registration used to fetch every flat over AJAX and cascade
 * Property Type -> Villa Area -> Villa; for a villa-only society the
 * cascade fired on DOMContentLoaded before the fetch resolved, so the Villa
 * Area list was built from an empty array and the form dead-ended on
 * "No villa areas available". The destination property is now rendered
 * server-side and Villa Area is not asked for at all. This also pins the
 * resident side: a Tenant or Owner is scoped to the units UserFlatResolver
 * says they live in / own / rent - for creating, viewing and deciding on
 * visitors - and the Guard -> resident approval flow.
 */
class VisitorManagementTest extends TestCase
{
    use RefreshDatabase;

    /** Royal Eden-shaped fixture: Villa 159, Owner Lokesh, Tenant Piyus; Villa 165 unassigned. */
    private function royalEden(): array
    {
        $society = $this->makeSociety('Royal Eden');
        $area = VillaArea::create(['society_id' => $society->id, 'name' => 'Suman Sadan', 'status' => 'active']);

        $villa159 = Flat::create([
            'society_id' => $society->id, 'property_type' => 'villa', 'villa_area_id' => $area->id,
            'flat_number' => '159', 'floor' => 0, 'status' => 'on_rent',
        ]);
        $villa165 = Flat::create([
            'society_id' => $society->id, 'property_type' => 'villa', 'villa_area_id' => $area->id,
            'flat_number' => '165', 'floor' => 0, 'status' => 'vacant',
        ]);

        $owner = Owner::create([
            'society_id' => $society->id, 'name' => 'Lokesh Suman', 'email' => 'lokesh' . uniqid() . '@test.local',
            'phone' => '9000000060', 'status' => 'active', 'property_type' => 'villa',
            'villa_area_id' => $area->id, 'villa_no' => '159',
        ]);
        $ownerUser = $this->makeUserWithRole($society, 'Owner', ['name' => 'Lokesh Suman', 'email' => $owner->email, 'owner_id' => $owner->id]);

        $tenantUser = $this->makeUserWithRole($society, 'Tenant', ['name' => 'Piyus Chudarri']);
        $tenant = Tenant::create([
            'society_id' => $society->id, 'flat_id' => $villa159->id, 'user_id' => $tenantUser->id,
            'name' => 'Piyus Chudarri', 'email' => $tenantUser->email, 'phone' => '9000000061',
            'move_in_date' => now(), 'status' => 'active',
        ]);
        $tenantUser->update(['tenant_id' => $tenant->id]);

        $admin = $this->makeUserWithRole($society, 'Admin');
        $guard = $this->makeUserWithRole($society, 'Guard');

        return compact('society', 'area', 'villa159', 'villa165', 'owner', 'ownerUser', 'tenantUser', 'tenant', 'admin', 'guard');
    }

    private function visitorPayload(array $overrides = []): array
    {
        return array_merge([
            'visitor_name' => 'Ravi Courier',
            'visitor_phone' => '9111111111',
            'visitor_type' => 'guest',
            'expected_entry_time' => now()->toDateString(),
            'in_time' => '10:00',
            'expected_count' => 1,
        ], $overrides);
    }

    public function test_admin_sees_every_villa_with_its_owner_and_no_villa_area_field(): void
    {
        $f = $this->royalEden();

        $response = $this->actingAs($f['admin'])->get('/visitors/create');

        $response->assertOk();
        $response->assertSee('Villa 159');
        $response->assertSee('Lokesh Suman');
        $response->assertSee('Villa 165');
        $response->assertSee('Not Assigned');
        $response->assertDontSee('villa_area_select');
        $response->assertDontSee('Select Villa Area');
    }

    public function test_admin_can_create_a_visitor_for_a_villa_without_villa_areas_being_configured(): void
    {
        $society = $this->makeSociety('No Areas');
        $admin = $this->makeUserWithRole($society, 'Admin');
        $villa = Flat::create([
            'society_id' => $society->id, 'property_type' => 'villa', 'villa_area_id' => null,
            'flat_number' => '7', 'floor' => 0, 'status' => 'vacant',
        ]);

        $this->actingAs($admin)->get('/visitors/create')->assertOk()->assertSee('Villa 7');

        $this->actingAs($admin)->post('/visitors', $this->visitorPayload(['flat_id' => $villa->id]))
            ->assertRedirect(route('visitors.index'));

        $this->assertDatabaseHas('visitors', ['flat_id' => $villa->id, 'visitor_name' => 'Ravi Courier', 'approval_status' => 'pending']);
    }

    public function test_admin_can_create_a_visitor_for_any_villa_in_their_society_but_not_another_societys(): void
    {
        $f = $this->royalEden();
        $other = $this->makeSociety('Elsewhere');
        $otherVilla = Flat::create(['society_id' => $other->id, 'property_type' => 'villa', 'flat_number' => '1', 'floor' => 0, 'status' => 'vacant']);

        $this->actingAs($f['admin'])->post('/visitors', $this->visitorPayload(['flat_id' => $f['villa165']->id]))
            ->assertRedirect(route('visitors.index'));
        $this->assertDatabaseHas('visitors', ['flat_id' => $f['villa165']->id, 'society_id' => $f['society']->id]);

        $this->actingAs($f['admin'])->post('/visitors', $this->visitorPayload(['flat_id' => $otherVilla->id]))
            ->assertForbidden();
        $this->assertDatabaseMissing('visitors', ['flat_id' => $otherVilla->id]);
    }

    public function test_tenant_sees_only_their_property_as_fixed_context_and_can_invite_a_visitor(): void
    {
        $f = $this->royalEden();

        $page = $this->actingAs($f['tenantUser'])->get('/visitors/create');
        $page->assertOk();
        $page->assertSee('Villa 159');
        $page->assertSee('Suman Sadan');
        $page->assertSee('Invite Visitor');
        $page->assertDontSee('name="flat_id" id="flat_id"', false); // no picker, fixed property
        $page->assertDontSee('Villa 165');

        // No flat_id posted at all: the only property is implied.
        $this->actingAs($f['tenantUser'])->post('/visitors', $this->visitorPayload())
            ->assertRedirect(route('visitors.index'));

        $visitor = Visitor::where('visitor_name', 'Ravi Courier')->firstOrFail();
        $this->assertSame($f['villa159']->id, $visitor->flat_id);
        $this->assertSame($f['society']->id, $visitor->society_id);
        $this->assertSame($f['tenantUser']->id, $visitor->host_user_id);
        // Own guest is pre-approved and gets a QR token via the existing hook.
        $this->assertSame('allowed', $visitor->approval_status);
        $this->assertNotEmpty($visitor->qr_token);
    }

    public function test_tenant_cannot_create_a_visitor_for_another_villa_even_by_tampering_with_flat_id(): void
    {
        $f = $this->royalEden();

        $this->actingAs($f['tenantUser'])->post('/visitors', $this->visitorPayload(['flat_id' => $f['villa165']->id]))
            ->assertForbidden();

        $this->assertDatabaseMissing('visitors', ['flat_id' => $f['villa165']->id]);
    }

    public function test_tenant_can_approve_and_deny_a_guard_registered_visitor_for_their_property(): void
    {
        $f = $this->royalEden();

        // Guard registers an unexpected visitor at the gate for Villa 159.
        $this->actingAs($f['guard'])->post('/guard/visitors', [
            'flat_id' => $f['villa159']->id, 'visitor_name' => 'Walk In', 'visitor_phone' => '9222222222', 'visitor_type' => 'guest',
        ])->assertRedirect();
        $visitor = Visitor::where('visitor_name', 'Walk In')->firstOrFail();
        $this->assertSame('pending', $visitor->approval_status);

        // The resident sees it, and can decide.
        $this->actingAs($f['tenantUser'])->get('/visitors')->assertOk()->assertSee('Walk In');

        $this->actingAs($f['tenantUser'])->post("/visitors/{$visitor->id}/allow")->assertRedirect(route('visitors.index'));
        $this->assertSame('allowed', $visitor->fresh()->approval_status);
        $this->assertSame($f['tenantUser']->id, $visitor->fresh()->approved_by);

        // And the guard can now check them in through the existing gate flow.
        $this->actingAs($f['guard'])->post("/guard/visitors/{$visitor->id}/check-in")->assertRedirect();
        $this->assertSame('entered', $visitor->fresh()->entry_status);

        $second = Visitor::create([
            'society_id' => $f['society']->id, 'flat_id' => $f['villa159']->id, 'host_user_id' => $f['guard']->id,
            'visitor_name' => 'Unwanted', 'visitor_phone' => '9333333333', 'visitor_type' => 'other',
            'expected_entry_time' => now(), 'approval_status' => 'pending', 'entry_status' => 'pending',
        ]);
        $this->actingAs($f['tenantUser'])->post("/visitors/{$second->id}/deny", ['reason' => 'Not expecting anyone'])
            ->assertRedirect(route('visitors.index'));
        $this->assertSame('denied', $second->fresh()->approval_status);
    }

    public function test_tenant_cannot_approve_or_view_a_visitor_for_another_property(): void
    {
        $f = $this->royalEden();

        $visitor = Visitor::create([
            'society_id' => $f['society']->id, 'flat_id' => $f['villa165']->id, 'host_user_id' => $f['admin']->id,
            'visitor_name' => 'Someone Else', 'visitor_phone' => '9444444444', 'visitor_type' => 'guest',
            'expected_entry_time' => now(), 'approval_status' => 'pending', 'entry_status' => 'pending',
        ]);

        $this->actingAs($f['tenantUser'])->post("/visitors/{$visitor->id}/allow")->assertForbidden();
        $this->actingAs($f['tenantUser'])->post("/visitors/{$visitor->id}/deny", ['reason' => 'x'])->assertForbidden();
        $this->actingAs($f['tenantUser'])->get("/visitors/{$visitor->id}")->assertForbidden();
        $this->actingAs($f['tenantUser'])->get('/visitors')->assertOk()->assertDontSee('Someone Else');

        $this->assertSame('pending', $visitor->fresh()->approval_status);
    }

    public function test_owner_of_a_rented_villa_still_manages_its_visitors_and_stays_owner(): void
    {
        $f = $this->royalEden();

        // The owner's unit is linked only by address (flats.owner_id null) - the real data shape.
        $page = $this->actingAs($f['ownerUser'])->get('/visitors/create');
        $page->assertOk()->assertSee('Villa 159');

        $this->actingAs($f['ownerUser'])->post('/visitors', $this->visitorPayload(['visitor_name' => 'Owner Guest']))
            ->assertRedirect(route('visitors.index'));
        $this->assertDatabaseHas('visitors', ['flat_id' => $f['villa159']->id, 'visitor_name' => 'Owner Guest', 'host_user_id' => $f['ownerUser']->id]);

        $f['ownerUser']->refresh();
        $this->assertTrue($f['ownerUser']->hasRole('Owner'));
        $this->assertSame('active', $f['ownerUser']->status);
        $this->assertSame($f['owner']->id, $f['ownerUser']->owner_id);
        $this->assertSame($f['villa159']->id, $f['tenant']->fresh()->flat_id);
    }

    public function test_resident_with_no_property_is_sent_back_with_a_message_instead_of_a_dead_end(): void
    {
        $society = $this->makeSociety();
        $tenantUser = $this->makeUserWithRole($society, 'Tenant');

        $this->actingAs($tenantUser)->get('/visitors/create')
            ->assertRedirect(route('visitors.index'))
            ->assertSessionHas('error');

        $this->actingAs($tenantUser)->post('/visitors', $this->visitorPayload())->assertForbidden();
    }
}
