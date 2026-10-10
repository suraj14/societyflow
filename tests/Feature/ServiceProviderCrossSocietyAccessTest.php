<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `ServiceProviderController` authorized edit/update/destroy/quickUpdate/
 * toggleAvailability with `$this->authorize('manage_services')` - a plain
 * role permission with no model attached. Any Admin, in any society, could
 * therefore update, delete or toggle any other society's service provider
 * by id. `ServiceProviderPolicy::update()`/`delete()` already had the
 * correct same-society check; the controller just never called it.
 */
class ServiceProviderCrossSocietyAccessTest extends TestCase
{
    use RefreshDatabase;

    private function provider($society): ServiceProvider
    {
        $service = Service::create([
            'name' => 'Plumbing', 'category' => 'maintenance', 'is_enabled' => true, 'sort_order' => 1,
        ]);

        return ServiceProvider::create([
            'society_id'      => $society->id,
            'service_id'      => $service->id,
            'name'            => 'SECRET Provider A',
            'contact_number'  => '9000000000',
            'availability'    => 'available',
            'price_type'      => 'per_visit',
            'status'          => 'active',
        ]);
    }

    public function test_an_admin_in_another_society_cannot_view_edit_form(): void
    {
        $societyA = $this->makeSociety();
        $provider = $this->provider($societyA);
        $adminB   = $this->makeUserWithRole($this->makeSociety(), 'Admin');

        $this->actingAs($adminB)->get("/service-providers/{$provider->id}/edit")
            ->assertForbidden();
    }

    public function test_an_admin_in_another_society_cannot_update(): void
    {
        $societyA = $this->makeSociety();
        $provider = $this->provider($societyA);
        $adminB   = $this->makeUserWithRole($this->makeSociety(), 'Admin');

        $this->actingAs($adminB)->put("/service-providers/{$provider->id}", [
            'service_id'      => $provider->service_id,
            'name'            => 'Taken over',
            'contact_number'  => '9999999999',
            'availability'    => 'available',
            'price_type'      => 'per_visit',
            'status'          => 'active',
        ])->assertForbidden();

        $this->assertSame('SECRET Provider A', $provider->fresh()->name);
    }

    public function test_an_admin_in_another_society_cannot_delete(): void
    {
        $societyA = $this->makeSociety();
        $provider = $this->provider($societyA);
        $adminB   = $this->makeUserWithRole($this->makeSociety(), 'Admin');

        $this->actingAs($adminB)->delete("/service-providers/{$provider->id}")
            ->assertForbidden();

        $this->assertNotNull($provider->fresh());
    }

    public function test_an_admin_in_another_society_cannot_quick_update(): void
    {
        $societyA = $this->makeSociety();
        $provider = $this->provider($societyA);
        $adminB   = $this->makeUserWithRole($this->makeSociety(), 'Admin');

        $this->actingAs($adminB)->patch("/service-providers/{$provider->id}/quick-update", [
            'availability' => 'not_available',
            'price_type'   => 'per_visit',
        ])->assertForbidden();

        $this->assertSame('available', $provider->fresh()->availability);
    }

    public function test_an_admin_in_another_society_cannot_toggle_availability(): void
    {
        $societyA = $this->makeSociety();
        $provider = $this->provider($societyA);
        $adminB   = $this->makeUserWithRole($this->makeSociety(), 'Admin');

        $this->actingAs($adminB)->post("/service-providers/{$provider->id}/toggle-availability", [
            'availability' => 'not_available',
        ])->assertForbidden();

        $this->assertSame('available', $provider->fresh()->availability);
    }

    public function test_an_admin_in_their_own_society_can_still_update(): void
    {
        $society  = $this->makeSociety();
        $provider = $this->provider($society);
        $admin    = $this->makeUserWithRole($society, 'Admin');

        $this->actingAs($admin)->put("/service-providers/{$provider->id}", [
            'service_id'      => $provider->service_id,
            'name'            => 'Renamed Legitimately',
            'contact_number'  => '9999999999',
            'availability'    => 'available',
            'price_type'      => 'per_visit',
            'status'          => 'active',
        ])->assertRedirect(route('service-providers.index'));

        $this->assertSame('Renamed Legitimately', $provider->fresh()->name);
    }
}
