<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\MaintenanceBill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsFinanceLedger;
use Tests\TestCase;

/**
 * Web and the mobile API read the same ledger and must agree: the same bills for the same person, with the real bill
 * type and the same amounts, the same payments, the same count of unpaid bills. The ledger and its figures are the
 * ones described on FinanceReconciliationTest. (Flutter only renders what the API sends - lib/features/payments
 * shows bill_type and the amounts verbatim - so agreement here is agreement on the phone.)
 */
class WebMobileFinanceParityTest extends TestCase
{
    use BuildsFinanceLedger;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildLedger();
    }

    private function api(string $path, $as)
    {
        Sanctum::actingAs($as);

        return $this->getJson('/api/v1' . $path)->assertOk();
    }

    private function billRow(array $rows, int $id): array
    {
        return collect($rows)->firstWhere('id', $id) ?? $this->fail("bill {$id} is not in the API answer");
    }

    public function test_the_tenants_bills_on_the_phone_are_his_units_bills_with_the_ledger_amounts(): void
    {
        $rows = $this->api('/bills', $this->tenant)->json('data');

        $this->assertEqualsCanonicalizing([$this->maint159sep->id, $this->maint159oct->id], array_column($rows, 'id'), 'Villa 159\'s bills only - not Villa 160\'s, not Beta\'s');
        foreach ([$this->maint159sep, $this->maint159oct] as $bill) {
            $fresh = $bill->fresh();
            $row = $this->billRow($rows, $bill->id);
            $this->assertSame('maintenance', $row['bill_type']);
            $this->assertEquals((float) $fresh->total_amount, $row['total_amount']);
            $this->assertEquals((float) $fresh->paid_amount, $row['paid_amount']);
            $this->assertEquals((float) $fresh->balance_amount, $row['balance_amount']);
            $this->assertSame($fresh->status, $row['status']);
        }
        $this->assertSame('paid', $this->billRow($rows, $this->maint159sep->id)['status']);
        $this->assertEquals(2700.0, $this->billRow($rows, $this->maint159oct->id)['balance_amount']);
    }

    public function test_a_rent_bill_is_labelled_rent_on_the_phone_not_maintenance(): void
    {
        $rows = $this->api('/bills', $this->owner)->json('data');

        $this->assertSame('rent', $this->billRow($rows, $this->rent160->id)['bill_type'], 'every bill used to be sent as "maintenance"');
        $this->assertSame('maintenance', $this->billRow($rows, $this->maint160->id)['bill_type']);
        $this->assertEquals(10000.0, $this->billRow($rows, $this->rent160->id)['paid_amount']);
        $this->assertSame('paid', $this->billRow($rows, $this->rent160->id)['status']);
    }

    public function test_the_dashboard_unpaid_bill_count_matches_the_web_dashboard(): void
    {
        $web = $this->actingAs($this->tenant)->get(route('tenant.dashboard'))->assertOk()->viewData('stats');
        $mobile = $this->api('/dashboard', $this->tenant)->json('data');

        $this->assertSame(1, $web['pending_bills'], 'the unpaid October bill');
        $this->assertSame($web['pending_bills'], $mobile['pending_bills']);
        $this->assertSame(2700.0, (float) $web['total_due']);
    }

    public function test_the_payment_history_on_the_phone_is_the_same_set_the_web_bills_screen_lists(): void
    {
        $webIds = $this->actingAs($this->tenant)->get(route('payments.index'))->assertOk()->viewData('payments')->pluck('id')->all();
        $mobile = $this->api('/payments/history', $this->tenant)->json('data');

        $this->assertEqualsCanonicalizing($webIds, array_column($mobile, 'id'));
        $expected = Payment::where('flat_id', $this->v159->id)->pluck('id')->all();
        $this->assertEqualsCanonicalizing($expected, array_column($mobile, 'id'), 'every payment recorded on his unit, whoever recorded it, and nothing else');
        $byStatus = collect($mobile)->countBy('status')->all();
        ksort($byStatus);
        $this->assertSame(['cancelled' => 1, 'failed' => 1, 'pending' => 1, 'success' => 2], $byStatus, 'the phone shows the true status of each attempt');
    }

    public function test_the_phone_counts_only_success_payments_as_paid(): void
    {
        $data = $this->api('/payments', $this->tenant)->json('data');

        $this->assertSame(2, count($data['payments']), 'only the two success payments are "paid"');
        $this->assertEquals(2700.0, array_sum(array_column($data['payments'], 'amount')));
        $this->assertSame(['success'], array_values(array_unique(array_column($data['payments'], 'status'))));
    }

    public function test_the_recent_payment_names_the_right_kind_and_includes_office_recorded_payments(): void
    {
        $tenant = collect($this->api('/dashboard', $this->tenant)->json('data.recent_activities'))->firstWhere('type', 'payment');
        $owner = collect($this->api('/dashboard', $this->owner)->json('data.recent_activities'))->firstWhere('type', 'payment');

        $this->assertSame('Maintenance bill paid', $tenant['title'], 'recorded at the office (no user_id), on his unit');
        $this->assertSame('Rent bill paid', $owner['title']);
    }

    public function test_nobody_sees_another_units_or_societys_figures_on_the_phone(): void
    {
        $tenantBills = array_column($this->api('/bills', $this->tenant)->json('data'), 'id');
        $this->assertEmpty(array_intersect($tenantBills, [$this->rent160->id, $this->maint160->id]));
        $this->assertEmpty(array_intersect($tenantBills, MaintenanceBill::where('society_id', $this->beta->id)->pluck('id')->all()));

        $betaAdmin = $this->makeUserWithRole($this->beta, 'Admin');
        $this->assertSame([], $this->api('/bills', $betaAdmin)->json('data'), 'a user with no unit has no bills');
        $this->assertSame(0, $this->api('/dashboard', $betaAdmin)->json('data.pending_bills'));
    }

    public function test_the_api_needs_a_signed_in_user(): void
    {
        $this->getJson('/api/v1/bills')->assertStatus(401);
        $this->getJson('/api/v1/dashboard')->assertStatus(401);
        $this->getJson('/api/v1/payments/history')->assertStatus(401);
    }
}
