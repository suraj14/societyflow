<?php

namespace Tests\Feature;

use App\Models\MaintenanceBill;
use App\Models\Payment;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The Accountant role previously had no dedicated dashboard branch and no
 * sidebar entries beyond "Dashboard" - every finance module existed but was
 * unreachable from the Accountant's own navigation, and several of the
 * underlying routes/controllers actively rejected the role even when linked
 * directly. This suite pins the fix: correct navigation, working routes,
 * society-scoped data, and no access to modules that aren't theirs.
 */
class AccountantAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_dashboard_shows_finance_view_not_admin_view(): void
    {
        $society = $this->makeSociety();
        $accountant = $this->makeUserWithRole($society, 'Accountant');

        $response = $this->actingAs($accountant)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('accountant.dashboard');
    }

    public function test_accountant_sees_finance_navigation_links(): void
    {
        $society = $this->makeSociety();
        $accountant = $this->makeUserWithRole($society, 'Accountant');

        $response = $this->actingAs($accountant)->get(route('dashboard'));

        $response->assertSee(route('payments.index'), false);
        $response->assertSee(route('admin.rents.index'), false);
        $response->assertSee(route('reports.financial'), false);
        $response->assertSee(route('utility-bills.index'), false);
    }

    public function test_accountant_can_view_payments_bills_and_financial_report(): void
    {
        $society = $this->makeSociety();
        $accountant = $this->makeUserWithRole($society, 'Accountant');

        $this->actingAs($accountant)->get(route('payments.index'))->assertOk();
        $this->actingAs($accountant)->get(route('admin.rents.index'))->assertOk();
        $this->actingAs($accountant)->get(route('reports.financial'))->assertOk();
        $this->actingAs($accountant)->get(route('utility-bills.index'))->assertOk();
    }

    public function test_accountant_cannot_manage_users_or_settings(): void
    {
        $society = $this->makeSociety();
        $accountant = $this->makeUserWithRole($society, 'Accountant');

        $this->actingAs($accountant)->get(route('users.index'))->assertForbidden();
        $this->actingAs($accountant)->get(route('settings.index'))->assertForbidden();
        $this->actingAs($accountant)->get('/super-admin/dashboard')->assertForbidden();
    }

    public function test_accountant_cannot_create_or_delete_rent_records(): void
    {
        $society = $this->makeSociety();
        $accountant = $this->makeUserWithRole($society, 'Accountant');

        $this->actingAs($accountant)->get(route('admin.rents.create'))->assertForbidden();
    }

    public function test_accountant_finance_data_is_scoped_to_their_own_society(): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        $accountant = $this->makeUserWithRole($societyA, 'Accountant');

        $buildingA = $this->makeBuilding($societyA);
        $flatA = $this->makeFlat($societyA, $buildingA, '101');
        Payment::create([
            'society_id' => $societyA->id,
            'flat_id' => $flatA->id,
            'amount' => 1500,
            'status' => 'success',
            'payment_date' => now(),
            'due_date' => now(),
            'bill_type' => 'maintenance',
            'payment_method' => 'cash',
        ]);

        $buildingB = $this->makeBuilding($societyB);
        $flatB = $this->makeFlat($societyB, $buildingB, '202');
        Payment::create([
            'society_id' => $societyB->id,
            'flat_id' => $flatB->id,
            'amount' => 9999,
            'status' => 'success',
            'payment_date' => now(),
            'due_date' => now(),
            'bill_type' => 'maintenance',
            'payment_method' => 'cash',
        ]);

        $response = $this->actingAs($accountant)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('1,500.00');
        $response->assertDontSee('9,999.00');
    }

    public function test_villa_and_apartment_owner_can_reach_financial_report(): void
    {
        // Regression for the exact anti-pattern CLAUDE.md §2 warns about:
        // the reports route and controller both checked hasRole('Owner'),
        // a role that does not exist, so every real owner was blocked.
        $society = $this->makeSociety();
        $apartmentOwner = $this->makeUserWithRole($society, 'Apartment Owner');
        $villaOwner = $this->makeUserWithRole($society, 'Villa Owner');

        $this->actingAs($apartmentOwner)->get(route('reports.financial'))->assertOk();
        $this->actingAs($villaOwner)->get(route('reports.financial'))->assertOk();
    }
}
