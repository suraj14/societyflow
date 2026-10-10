<?php

namespace Tests\Feature;

use App\Jobs\DispatchEventNotification;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P1-61: `BillGenerated`/`SendBillGeneratedEmail`/its `bill_generated`
 * template all existed (seeded by 2026_10_07_000002) but nothing in the
 * codebase ever dispatched the event - confirmed by grep across every
 * controller for `MaintenanceBill::create` finding exactly one real bill-
 * creation call site, `Admin\RentController::store()`, which did not fire
 * it. It does now.
 */
class BillGeneratedNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createRentBill($society, $admin, $flat, $tenant)
    {
        return $this->actingAs($admin)->post(route('admin.rents.store'), [
            'property_type' => $flat->property_type ?? 'apartment',
            'unit_id'       => $flat->id,
            'tenant_id'     => $tenant->id,
            'rent_amount'   => 15000,
            'bill_date'     => now()->toDateString(),
            'due_date'      => now()->addDays(10)->toDateString(),
            'status'        => 'pending',
        ]);
    }

    public function test_creating_a_rent_bill_through_the_real_controller_action_queues_an_email_to_the_tenant(): void
    {
        Queue::fake();

        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $building = $this->makeBuilding($society);
        $flat = $this->makeFlat($society, $building);
        $flat->update(['property_type' => 'apartment']);
        $tenantUser = $this->makeResidentUser($society, $flat);

        $tenant = Tenant::create([
            'society_id'      => $society->id,
            'flat_id'         => $flat->id,
            'user_id'         => $tenantUser->id,
            'name'            => $tenantUser->name,
            'email'           => $tenantUser->email,
            'phone'           => $tenantUser->phone,
            'status'          => 'active',
            'move_in_date'    => now()->toDateString(),
            'monthly_rent'    => 15000,
        ]);
        // SendBillGeneratedEmail resolves tenants via User::tenant()
        // (belongsTo -> users.tenant_id), not tenants.user_id.
        $tenantUser->update(['tenant_id' => $tenant->id]);
        $this->makeRole('Tenant');
        $tenantUser->assignRole('Tenant');

        $response = $this->createRentBill($society, $admin, $flat, $tenant);

        $response->assertRedirect(route('admin.rents.index'));

        $this->assertDatabaseHas('maintenance_bills', ['flat_id' => $flat->id, 'bill_type' => 'rent']);

        Queue::assertPushed(DispatchEventNotification::class, function (DispatchEventNotification $job) use ($tenantUser) {
            return $job->triggerEvent === 'bill_generated'
                && $job->recipient['email'] === $tenantUser->email;
        });
    }

    public function test_the_admin_who_created_the_bill_is_also_notified(): void
    {
        Queue::fake();

        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $building = $this->makeBuilding($society);
        $flat = $this->makeFlat($society, $building);
        $flat->update(['property_type' => 'apartment']);
        $tenantUser = $this->makeResidentUser($society, $flat);

        $tenant = Tenant::create([
            'society_id'   => $society->id,
            'flat_id'      => $flat->id,
            'user_id'      => $tenantUser->id,
            'name'         => $tenantUser->name,
            'email'        => $tenantUser->email,
            'phone'        => $tenantUser->phone,
            'status'       => 'active',
            'move_in_date' => now()->toDateString(),
            'monthly_rent' => 15000,
        ]);

        $this->createRentBill($society, $admin, $flat, $tenant)->assertRedirect();

        Queue::assertPushed(DispatchEventNotification::class, function (DispatchEventNotification $job) use ($admin) {
            return $job->triggerEvent === 'bill_generated' && $job->recipient['email'] === $admin->email;
        });
    }

    public function test_creating_a_bill_in_one_society_never_notifies_another_societys_admin(): void
    {
        Queue::fake();

        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        $adminA = $this->makeUserWithRole($societyA, 'Admin');
        $adminB = $this->makeUserWithRole($societyB, 'Admin');
        $buildingA = $this->makeBuilding($societyA);
        $flatA = $this->makeFlat($societyA, $buildingA);
        $flatA->update(['property_type' => 'apartment']);
        $tenantUserA = $this->makeResidentUser($societyA, $flatA);

        $tenantA = Tenant::create([
            'society_id'   => $societyA->id,
            'flat_id'      => $flatA->id,
            'user_id'      => $tenantUserA->id,
            'name'         => $tenantUserA->name,
            'email'        => $tenantUserA->email,
            'phone'        => $tenantUserA->phone,
            'status'       => 'active',
            'move_in_date' => now()->toDateString(),
            'monthly_rent' => 10000,
        ]);

        $this->createRentBill($societyA, $adminA, $flatA, $tenantA)->assertRedirect();

        Queue::assertNotPushed(DispatchEventNotification::class, function (DispatchEventNotification $job) use ($adminB) {
            return $job->recipient['email'] === $adminB->email;
        });
    }
}
