<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Service requests could be created (ServiceApiController::requestService)
 * but never listed, assigned, progressed or completed - no screen, web or
 * mobile, ever read the row back. This is the lifecycle that fills that gap:
 * pending -> assigned -> in_progress -> completed, or cancelled from any
 * open state, plus the cross-society/cross-resident boundary around it.
 */
class ServiceRequestLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function service(): Service
    {
        return Service::create(['name' => 'Plumbing', 'category' => 'maintenance', 'is_enabled' => true, 'sort_order' => 1]);
    }

    private function provider($society, Service $service): ServiceProvider
    {
        return ServiceProvider::create([
            'society_id' => $society->id, 'service_id' => $service->id,
            'name' => 'Ramesh Plumbing', 'contact_number' => '9000000000',
            'availability' => 'available', 'price_type' => 'per_visit', 'status' => 'active',
        ]);
    }

    public function test_full_lifecycle_create_assign_start_complete(): void
    {
        $society  = $this->makeSociety();
        $flat     = $this->makeFlat($society, $this->makeBuilding($society));
        $resident = $this->makeResidentUser($society, $flat);
        $admin    = $this->makeUserWithRole($society, 'Admin');
        $service  = $this->service();
        $provider = $this->provider($society, $service);

        // Create, via the mobile API, as the resident.
        Sanctum::actingAs($resident);
        $create = $this->postJson('/api/v1/service-requests', [
            'service_id'  => $service->id,
            'description' => 'Leaking kitchen tap.',
        ])->assertCreated();

        $id = $create->json('data.id');
        $sr = ServiceRequest::findOrFail($id);
        $this->assertSame('pending', $sr->status);

        // The resident can see it in their own list.
        $this->getJson('/api/v1/service-requests')
            ->assertOk()
            ->assertJsonFragment(['id' => $id]);

        // Assign, as admin, on the web.
        $this->actingAs($admin)->post("/service-requests/{$sr->id}/assign", [
            'assigned_provider_id' => $provider->id,
        ])->assertRedirect();

        $sr->refresh();
        $this->assertSame('assigned', $sr->status);
        $this->assertSame($provider->id, $sr->assigned_provider_id);
        $this->assertSame($admin->id, $sr->assigned_by);
        $this->assertNotNull($sr->assigned_at);

        // Start.
        $this->actingAs($admin)->post("/service-requests/{$sr->id}/status", [
            'status' => 'in_progress',
        ])->assertRedirect();
        $this->assertSame('in_progress', $sr->fresh()->status);
        $this->assertNotNull($sr->fresh()->started_at);

        // Complete.
        $this->actingAs($admin)->post("/service-requests/{$sr->id}/status", [
            'status' => 'completed',
        ])->assertRedirect();
        $this->assertSame('completed', $sr->fresh()->status);
        $this->assertNotNull($sr->fresh()->completed_at);
    }

    public function test_a_resident_can_cancel_their_own_pending_request(): void
    {
        $society  = $this->makeSociety();
        $flat     = $this->makeFlat($society, $this->makeBuilding($society));
        $resident = $this->makeResidentUser($society, $flat);
        $service  = $this->service();

        $sr = ServiceRequest::create([
            'society_id' => $society->id, 'user_id' => $resident->id,
            'service_id' => $service->id, 'description' => 'x', 'status' => 'pending',
        ]);

        Sanctum::actingAs($resident);
        $this->postJson("/api/v1/service-requests/{$sr->id}/cancel")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('cancelled', $sr->fresh()->status);
        $this->assertNotNull($sr->fresh()->cancelled_at);
    }

    public function test_a_resident_cannot_cancel_someone_elses_request(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $a       = $this->makeResidentUser($society, $flat);
        $b       = $this->makeResidentUser($society, $flat);
        $service = $this->service();

        $sr = ServiceRequest::create([
            'society_id' => $society->id, 'user_id' => $a->id,
            'service_id' => $service->id, 'description' => 'x', 'status' => 'pending',
        ]);

        Sanctum::actingAs($b);
        $this->postJson("/api/v1/service-requests/{$sr->id}/cancel")->assertStatus(404);
        $this->assertSame('pending', $sr->fresh()->status);
    }

    public function test_a_resident_cannot_start_or_complete_their_own_request(): void
    {
        $society  = $this->makeSociety();
        $flat     = $this->makeFlat($society, $this->makeBuilding($society));
        $resident = $this->makeResidentUser($society, $flat);
        $service  = $this->service();
        $provider = $this->provider($society, $service);

        $sr = ServiceRequest::create([
            'society_id' => $society->id, 'user_id' => $resident->id,
            'service_id' => $service->id, 'description' => 'x', 'status' => 'assigned',
            'assigned_provider_id' => $provider->id,
        ]);

        $this->actingAs($resident)->post("/service-requests/{$sr->id}/status", [
            'status' => 'completed',
        ])->assertForbidden();

        $this->assertSame('assigned', $sr->fresh()->status);
    }

    public function test_an_admin_in_another_society_cannot_assign_or_progress_it(): void
    {
        $societyA = $this->makeSociety();
        $flatA    = $this->makeFlat($societyA, $this->makeBuilding($societyA));
        $residentA = $this->makeResidentUser($societyA, $flatA);
        $service   = $this->service();
        $sr = ServiceRequest::create([
            'society_id' => $societyA->id, 'user_id' => $residentA->id,
            'service_id' => $service->id, 'description' => 'SECRET request A', 'status' => 'pending',
        ]);

        $societyB  = $this->makeSociety();
        $providerB = $this->provider($societyB, $service);
        $adminB    = $this->makeUserWithRole($societyB, 'Admin');

        $this->actingAs($adminB)->post("/service-requests/{$sr->id}/assign", [
            'assigned_provider_id' => $providerB->id,
        ])->assertForbidden();

        $this->assertSame('pending', $sr->fresh()->status);
        $this->assertNull($sr->fresh()->assigned_provider_id);
    }

    public function test_assigning_a_provider_of_another_society_or_the_wrong_service_is_rejected(): void
    {
        $society  = $this->makeSociety();
        $flat     = $this->makeFlat($society, $this->makeBuilding($society));
        $resident = $this->makeResidentUser($society, $flat);
        $admin    = $this->makeUserWithRole($society, 'Admin');
        $service  = $this->service();
        $otherService = Service::create(['name' => 'Test Secondary Service', 'category' => 'test', 'is_enabled' => true, 'sort_order' => 2]);

        $sr = ServiceRequest::create([
            'society_id' => $society->id, 'user_id' => $resident->id,
            'service_id' => $service->id, 'description' => 'x', 'status' => 'pending',
        ]);

        // Wrong society.
        $otherSociety = $this->makeSociety();
        $foreignProvider = $this->provider($otherSociety, $service);
        $this->actingAs($admin)->post("/service-requests/{$sr->id}/assign", [
            'assigned_provider_id' => $foreignProvider->id,
        ])->assertSessionHasErrors('assigned_provider_id');

        // Right society, wrong service.
        $wrongServiceProvider = $this->provider($society, $otherService);
        $this->actingAs($admin)->post("/service-requests/{$sr->id}/assign", [
            'assigned_provider_id' => $wrongServiceProvider->id,
        ])->assertSessionHasErrors('assigned_provider_id');

        $this->assertNull($sr->fresh()->assigned_provider_id);
    }

    public function test_the_requests_list_never_shows_another_societys_requests(): void
    {
        $societyA = $this->makeSociety();
        $flatA    = $this->makeFlat($societyA, $this->makeBuilding($societyA));
        $residentA = $this->makeResidentUser($societyA, $flatA);
        $service   = $this->service();
        ServiceRequest::create([
            'society_id' => $societyA->id, 'user_id' => $residentA->id,
            'service_id' => $service->id, 'description' => 'SECRET Request Leak Test', 'status' => 'pending',
        ]);

        $societyB = $this->makeSociety();
        $adminB   = $this->makeUserWithRole($societyB, 'Admin');

        $this->actingAs($adminB)->get('/service-requests')
            ->assertOk()
            ->assertDontSee('SECRET Request Leak Test');
    }

    public function test_cannot_start_a_request_that_has_not_been_assigned(): void
    {
        $society  = $this->makeSociety();
        $flat     = $this->makeFlat($society, $this->makeBuilding($society));
        $resident = $this->makeResidentUser($society, $flat);
        $admin    = $this->makeUserWithRole($society, 'Admin');
        $service  = $this->service();

        $sr = ServiceRequest::create([
            'society_id' => $society->id, 'user_id' => $resident->id,
            'service_id' => $service->id, 'description' => 'x', 'status' => 'pending',
        ]);

        $this->actingAs($admin)->post("/service-requests/{$sr->id}/status", [
            'status' => 'in_progress',
        ])->assertStatus(422);

        $this->assertSame('pending', $sr->fresh()->status);
    }

    public function test_cannot_act_on_an_already_closed_request(): void
    {
        $society  = $this->makeSociety();
        $flat     = $this->makeFlat($society, $this->makeBuilding($society));
        $resident = $this->makeResidentUser($society, $flat);
        $admin    = $this->makeUserWithRole($society, 'Admin');
        $service  = $this->service();

        $sr = ServiceRequest::create([
            'society_id' => $society->id, 'user_id' => $resident->id,
            'service_id' => $service->id, 'description' => 'x', 'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->actingAs($admin)->post("/service-requests/{$sr->id}/status", [
            'status' => 'cancelled',
        ])->assertStatus(422);
    }
}
