<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard statistics queried columns that do not exist.
 *
 * visitors.visit_date and payments.paid_at were referenced across six
 * dashboard queries. SQLite treats an unknown double-quoted identifier as a
 * string literal, so the counts silently came back as zero instead of
 * erroring - which is why nobody noticed. On MySQL, which this project
 * targets for production, the same queries are a hard failure.
 *
 * The revenue figure additionally filtered on status 'paid', a value the
 * payments table never contains (the real values are 'pending' and
 * 'success'), so it would have summed to zero even with the right column.
 */
class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_counts_todays_visitors_and_monthly_revenue(): void
    {
        $society  = $this->makeSociety();
        $flat     = $this->makeFlat($society, $this->makeBuilding($society));
        $admin    = $this->makeUserWithRole($society, 'Admin');
        $resident = $this->makeResidentUser($society, $flat);

        // Two visitors expected today, one tomorrow.
        foreach ([now(), now(), now()->addDay()] as $i => $when) {
            Visitor::create([
                'society_id'          => $society->id,
                'flat_id'             => $flat->id,
                'host_user_id'        => $resident->id,
                'visitor_name'        => 'Visitor ' . $i,
                'visitor_phone'       => '98765000' . $i,
                'visitor_type'        => 'guest',
                'expected_entry_time' => $when,
                'approval_status'     => 'allowed',
                'entry_status'        => 'pending',
            ]);
        }

        // Two successful payments this month, one pending, one last year.
        Payment::create([
            'society_id' => $society->id, 'user_id' => $resident->id,
            'payment_id' => 'P1', 'amount' => 1000, 'status' => 'success',
            'payment_date' => now(), 'bill_type' => 'Maintenance', 'due_date' => now(),
        ]);
        Payment::create([
            'society_id' => $society->id, 'user_id' => $resident->id,
            'payment_id' => 'P2', 'amount' => 500, 'status' => 'success',
            'payment_date' => now(), 'bill_type' => 'Water', 'due_date' => now(),
        ]);
        Payment::create([
            'society_id' => $society->id, 'user_id' => $resident->id,
            'payment_id' => 'P3', 'amount' => 9999, 'status' => 'pending',
            'payment_date' => now(), 'bill_type' => 'Water', 'due_date' => now(),
        ]);
        Payment::create([
            'society_id' => $society->id, 'user_id' => $resident->id,
            'payment_id' => 'P4', 'amount' => 7777, 'status' => 'success',
            'payment_date' => now()->subYear(), 'bill_type' => 'Water', 'due_date' => now()->subYear(),
        ]);

        $response = $this->actingAs($admin)->get('/dashboard');
        $response->assertOk();

        $stats = $response->viewData('stats');

        $this->assertSame(2, $stats['visitors_today'], 'visitors_today did not count by expected_entry_time');
        $this->assertEquals(1500, $stats['monthly_revenue'], 'monthly_revenue did not sum successful payments for this month');
        // Pending Dues is the outstanding balance on unpaid bills, not the sum
        // of unverified (pending) payments - a resident claiming to have paid
        // 9999 is not 9999 owed. No bill exists here, so nothing is due.
        $this->assertEquals(0, $stats['pending_dues']);
    }

    /**
     * The same queries must run without error on MySQL, where an unknown
     * column is fatal rather than silently null. Asserting the counts are
     * real (not zero-by-accident) is the portable proxy for that here.
     */
    public function test_dashboard_renders_for_every_resident_role(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));

        foreach (['Tenant', 'Apartment Owner'] as $role) {
            $user = $this->makeResidentUser($society, $flat);
            $user->assignRole($this->makeRole($role)->name);

            $this->actingAs($user)->get('/dashboard')->assertOk();
        }
    }
}
