<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Support\InAppNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Backend pieces of the mobile QA pass: a service request is a resident
 * action (Guard/Staff have no property to request a service for), the
 * office's bell gets a new-request entry, a provider's JSON carries the
 * `service_id` the app needs to group providers under their service, and
 * the society-contacts endpoint (Emergency Contacts / Help & Support) is
 * society-scoped and never leaks a manager with no phone number.
 */
class MobileQaBackendTest extends TestCase
{
    use RefreshDatabase;

    private function service(): Service
    {
        return Service::create(['name' => 'Plumbing', 'category' => 'maintenance', 'is_enabled' => true, 'sort_order' => 1]);
    }

    public function test_a_guard_cannot_file_a_service_request(): void
    {
        $society = $this->makeSociety();
        $guard = $this->makeUserWithRole($society, 'Guard');
        $service = $this->service();

        Sanctum::actingAs($guard);
        $this->postJson('/api/v1/service-requests', ['service_id' => $service->id, 'description' => 'Need help'])
            ->assertStatus(403);

        $this->assertDatabaseCount('service_requests', 0);
    }

    public function test_a_staff_member_cannot_file_a_service_request(): void
    {
        $society = $this->makeSociety();
        $staff = $this->makeUserWithRole($society, 'Staff');
        $service = $this->service();

        Sanctum::actingAs($staff);
        $this->postJson('/api/v1/service-requests', ['service_id' => $service->id, 'description' => 'Need help'])
            ->assertStatus(403);
    }

    public function test_a_resident_can_still_file_a_request_and_the_office_is_notified(): void
    {
        $society = $this->makeSociety();
        $resident = $this->makeUserWithRole($society, 'Tenant');
        $admin = $this->makeUserWithRole($society, 'Admin');
        $service = $this->service();

        Sanctum::actingAs($resident);
        $this->postJson('/api/v1/service-requests', ['service_id' => $service->id, 'description' => 'Leaking tap'])
            ->assertStatus(201)->assertJsonPath('success', true);

        $this->assertDatabaseCount('service_requests', 1);
        $this->assertSame(
            ['New service request'],
            $admin->fresh()->notifications()->pluck('data')->map(fn ($d) => $d['title'])->all()
        );
    }

    public function test_a_resident_whose_role_was_never_assigned_can_still_file_a_request(): void
    {
        // Found by SchemaContractTest/ServiceRequestLifecycleTest: the first
        // version of the rule was a role-name list, which refused a resident
        // who lives in a flat but has no Spatie role.
        $society = $this->makeSociety();
        $resident = $this->makeResidentUser($society, $this->makeFlat($society, $this->makeBuilding($society)));
        $this->assertSame([], $resident->getRoleNames()->all(), 'precondition: no role assigned');
        $service = $this->service();

        Sanctum::actingAs($resident);
        $this->postJson('/api/v1/service-requests', ['service_id' => $service->id, 'description' => 'Fan not working'])
            ->assertStatus(201);
    }

    public function test_provider_payload_carries_the_service_id_the_app_groups_by(): void
    {
        $society = $this->makeSociety();
        $resident = $this->makeResidentUser($society, $this->makeFlat($society, $this->makeBuilding($society)));
        $service = $this->service();
        ServiceProvider::create([
            'society_id' => $society->id, 'service_id' => $service->id,
            'name' => 'Ramesh Plumbing', 'contact_number' => '9000000000', 'status' => 'active',
        ]);

        Sanctum::actingAs($resident);
        $this->getJson('/api/v1/service-providers')->assertOk()
            ->assertJsonPath('data.0.service_id', $service->id)
            ->assertJsonPath('data.0.phone', '9000000000');
    }

    // ── Society contacts (Emergency Contacts / Help & Support) ──────────────

    public function test_society_contacts_returns_office_managers_and_emergency_numbers(): void
    {
        $society = $this->makeSociety();
        $society->update(['phone' => '04012345678', 'email' => 'office@test.local']);
        $resident = $this->makeResidentUser($society, $this->makeFlat($society, $this->makeBuilding($society)));
        $manager = $this->makeUserWithRole($society, 'Admin', ['phone' => '9848000001']);

        Sanctum::actingAs($resident);
        $r = $this->getJson('/api/v1/society/contacts')->assertOk();

        $this->assertSame($society->name, $r->json('data.society.name'));
        $this->assertSame('04012345678', $r->json('data.society.phone'));
        $this->assertSame($manager->name, $r->json('data.managers.0.name'));
        $this->assertNotEmpty($r->json('data.emergency'));
        $this->assertContains('100', array_column($r->json('data.emergency'), 'phone'));
    }

    public function test_an_admin_without_a_phone_number_is_not_offered_as_a_contact(): void
    {
        $society = $this->makeSociety();
        $resident = $this->makeResidentUser($society, $this->makeFlat($society, $this->makeBuilding($society)));
        $this->makeUserWithRole($society, 'Admin', ['phone' => null]);

        Sanctum::actingAs($resident);
        $r = $this->getJson('/api/v1/society/contacts')->assertOk();

        $this->assertSame([], $r->json('data.managers'), 'an admin with no phone cannot be called, so is not listed');
    }

    public function test_society_contacts_are_scoped_to_the_callers_own_society(): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        $residentA = $this->makeResidentUser($societyA, $this->makeFlat($societyA, $this->makeBuilding($societyA)));
        $this->makeUserWithRole($societyB, 'Admin', ['phone' => '9000000002', 'name' => 'Other Society Manager']);

        Sanctum::actingAs($residentA);
        $r = $this->getJson('/api/v1/society/contacts')->assertOk();

        $this->assertSame($societyA->name, $r->json('data.society.name'));
        $this->assertNotContains('Other Society Manager', array_column($r->json('data.managers'), 'name'));
    }

    public function test_placeholder_society_details_are_returned_as_missing(): void
    {
        $society = $this->makeSociety();
        $society->update(['address' => 'To be updated', 'city' => 'to be updated', 'pincode' => '000000', 'phone' => '9000000123']);
        $resident = $this->makeUserWithRole($society, 'Tenant');

        Sanctum::actingAs($resident);
        $r = $this->getJson('/api/v1/society/contacts')->assertOk();

        $this->assertNull($r->json('data.society.address'), 'placeholder address/city/pincode are not real contact details');
        $this->assertSame('9000000123', $r->json('data.society.phone'));
    }

    public function test_society_contacts_requires_authentication(): void
    {
        $this->getJson('/api/v1/society/contacts')->assertUnauthorized();
    }
}
