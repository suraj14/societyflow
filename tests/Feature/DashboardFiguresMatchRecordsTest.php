<?php

namespace Tests\Feature;

use App\Models\MaintenanceBill;
use App\Models\Payment;
use App\Models\Society;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard figures are checked against independently computed records (never constants from the controller).
 * Found by the web audit: the Owner and Tenant dashboards counted the user's own pending *Payment* rows as
 * "Pending Bills / Total Due", so an unpaid maintenance bill showed as 0 due on web while the mobile dashboard
 * (which counts outstanding bills on the user's units) showed it.
 */
class DashboardFiguresMatchRecordsTest extends TestCase
{
    use RefreshDatabase;

    private Society $a;
    private Society $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = $this->makeSociety('Alpha');
        $this->b = $this->makeSociety('Beta');
    }

    private int $seq = 0;

    private function bill(Society $s, $flat, float $amount, string $type = 'maintenance', string $due = '2026-10-20'): MaintenanceBill
    {
        // One bill per flat per month is a schema rule, so every extra bill on a flat takes another month.
        $month = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'][$this->seq++ % 12];

        return MaintenanceBill::create(['society_id' => $s->id, 'flat_id' => $flat->id, 'month' => $month, 'year' => 2026, 'bill_type' => $type, 'bill_date' => '2026-10-01', 'due_date' => $due, 'maintenance_amount' => $amount]);
    }

    private function pay(Society $s, MaintenanceBill $bill, float $amount, string $status = 'success', ?string $date = null): Payment
    {
        return Payment::create([
            'society_id' => $s->id, 'flat_id' => $bill->flat_id, 'maintenance_bill_id' => $bill->id, 'payment_id' => Payment::generatePaymentId(),
            'amount' => $amount, 'payment_method' => 'cash', 'status' => $status, 'payment_date' => $date ?? now()->toDateString(),
            'bill_type' => $bill->bill_type, 'due_date' => $bill->due_date,
        ]);
    }

    public function test_an_owner_with_two_units_sees_dues_summed_once_per_bill_and_only_for_their_own_units(): void
    {
        $building = $this->makeBuilding($this->a);
        $f1 = $this->makeFlat($this->a, $building, '101');
        $f2 = $this->makeFlat($this->a, $building, '102');
        $neighbour = $this->makeFlat($this->a, $building, '103');
        $owner = $this->makeResidentUser($this->a, $f1);
        \App\Models\Resident::create(['society_id' => $this->a->id, 'flat_id' => $f2->id, 'user_id' => $owner->id, 'name' => 'x', 'email' => 'x@t.local', 'phone' => '0000000003', 'type' => 'owner', 'status' => 'active', 'move_in_date' => '2026-01-01']);
        $owner->assignRole($this->makeRole('Apartment Owner'));

        $this->bill($this->a, $f1, 1000);
        $b2 = $this->bill($this->a, $f2, 2500);
        $this->pay($this->a, $b2, 500);                      // partial: 2000 still owed
        $settled = $this->bill($this->a, $f1, 700);
        $this->pay($this->a, $settled, 700);                 // fully paid: not outstanding
        $this->bill($this->a, $neighbour, 9999);             // someone else's unit
        $other = $this->makeFlat($this->b, $this->makeBuilding($this->b), '101');
        $this->bill($this->b, $other, 8888);                 // another society

        $expected = MaintenanceBill::whereIn('flat_id', [$f1->id, $f2->id])->whereIn('status', ['pending', 'partial', 'overdue'])->get();
        $this->assertCount(2, $expected, 'fixture sanity: two outstanding bills');
        $this->assertEquals(3000, $expected->sum('balance_amount'));

        $stats = $this->actingAs($owner)->get(route('dashboard'))->assertOk()->viewData('stats');

        $this->assertSame(2, $stats['pending_bills']);
        $this->assertEquals(3000, $stats['total_due']);
    }

    public function test_a_tenant_sees_the_outstanding_rent_of_their_tenancy_and_a_settled_tenant_sees_zero(): void
    {
        $flat = $this->makeFlat($this->a, $this->makeBuilding($this->a), '201');
        $user = $this->makeUserWithRole($this->a, 'Tenant');
        $record = Tenant::create(['society_id' => $this->a->id, 'flat_id' => $flat->id, 'user_id' => $user->id, 'name' => 'T', 'email' => 'tt@t.local', 'phone' => '0000000001', 'move_in_date' => '2026-09-01', 'status' => 'active']);
        $user->update(['tenant_id' => $record->id]);

        $stats = $this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('stats');
        $this->assertSame(0, $stats['pending_bills'], 'empty state: nothing owed, no error');
        $this->assertEquals(0, $stats['total_due']);

        $rent = $this->bill($this->a, $flat, 12000, 'rent');
        $stats = $this->actingAs($user)->get(route('dashboard'))->viewData('stats');
        $this->assertSame(1, $stats['pending_bills']);
        $this->assertEquals(12000, $stats['total_due']);

        $this->pay($this->a, $rent, 12000);
        $stats = $this->actingAs($user)->get(route('dashboard'))->viewData('stats');
        $this->assertSame(0, $stats['pending_bills']);
        $this->assertEquals(0, $stats['total_due']);
    }

    public function test_accountant_figures_equal_the_records_and_ignore_pending_payments_and_other_societies(): void
    {
        $acct = $this->makeUserWithRole($this->a, 'Accountant');
        $flat = $this->makeFlat($this->a, $this->makeBuilding($this->a), '301');
        $bill = $this->bill($this->a, $flat, 5000);
        $this->pay($this->a, $bill, 1500, 'success');
        $this->pay($this->a, $bill, 700, 'pending');                                              // not collected
        $bill2 = $this->bill($this->a, $flat, 3000);
        $this->pay($this->a, $bill2, 3000, 'success', now()->subMonths(2)->toDateString());       // collected earlier
        $otherFlat = $this->makeFlat($this->b, $this->makeBuilding($this->b), '1');
        $ob = $this->bill($this->b, $otherFlat, 4000);
        $this->pay($this->b, $ob, 4000);

        $mine = fn () => Payment::where('society_id', $this->a->id)->where('status', 'success');
        $stats = $this->actingAs($acct)->get(route('dashboard'))->assertOk()->viewData('stats');

        $this->assertEquals($mine()->sum('amount'), $stats['total_collected']);
        $this->assertEquals(4500, $stats['total_collected']);
        $this->assertEquals($mine()->whereMonth('payment_date', now()->month)->whereYear('payment_date', now()->year)->sum('amount'), $stats['monthly_collection']);
        $this->assertEquals(1500, $stats['monthly_collection'], 'only successful payments dated this month');
        $this->assertSame(1, $stats['pending_bills'], 'bill 2 is settled, bill 1 is partial');
        $this->assertEquals(3500, $stats['pending_dues']);
        $this->assertSame(2, $stats['total_bills'], 'the other society bill is not counted');
    }

    public function test_staff_figures_follow_todays_visitors_and_open_work_in_their_society_only(): void
    {
        $staff = $this->makeUserWithRole($this->a, 'Staff');
        $flat = $this->makeFlat($this->a, $this->makeBuilding($this->a), '401');
        $other = $this->makeFlat($this->b, $this->makeBuilding($this->b), '1');
        $mk = fn ($soc, $f, $when, $name) => \App\Models\Visitor::create(['society_id' => $soc->id, 'flat_id' => $f->id, 'visitor_name' => $name, 'visitor_phone' => '1', 'visitor_type' => 'guest', 'expected_entry_time' => $when, 'approval_status' => 'pending', 'entry_status' => 'pending']);
        $mk($this->a, $flat, \App\Support\SocietyClock::now()->setTime(12, 0), 'today-1');
        $mk($this->a, $flat, \App\Support\SocietyClock::now()->setTime(18, 0), 'today-2');
        $mk($this->a, $flat, \App\Support\SocietyClock::now()->subDays(3), 'old');
        $mk($this->b, $other, \App\Support\SocietyClock::now()->setTime(12, 0), 'other-society');
        $complaint = fn ($soc, $status, $n) => \App\Models\Complaint::create(['society_id' => $soc->id, 'created_by' => $staff->id, 'title' => $n, 'description' => 'd', 'priority' => 'low', 'status' => $status]);
        $complaint($this->a, 'open', 'o1');
        $complaint($this->a, 'in_progress', 'o2');
        $complaint($this->a, 'resolved', 'closed');
        $complaint($this->b, 'open', 'foreign');

        $stats = $this->actingAs($staff)->get(route('dashboard'))->assertOk()->viewData('stats');

        $this->assertSame(2, $stats['visitors_today']);
        $this->assertSame(2, $stats['pending_tickets']);
        $this->assertSame(0, $stats['todays_events']);
    }
}
