<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Flat;
use App\Models\Society;
use App\Models\User;
use App\Services\FinancialReportService;
use App\Support\ReportPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Mirrors the real society-6 shape: 9 bookings (5 approved incl. one free gym slot, 1 rejected, 1 completed free, 2 cancelled).
 * "Pending" on the cards is APPROVAL status; "Payment Pending" is money owed. Both screens read one scope (awaitingPayment).
 */
class FacilityPaymentStatusTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;
    private User $admin;
    private Flat $flat;
    private Facility $clubhouse;
    private Facility $gym;

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Royal');
        $this->flat = $this->makeFlat($this->society, $this->makeBuilding($this->society), '101');
        $this->admin = $this->makeUserWithRole($this->society, 'Admin');
        Permission::firstOrCreate(['name' => 'manage_facilities', 'guard_name' => 'web']);
        $this->admin->givePermissionTo('manage_facilities');
        $this->clubhouse = $this->facility($this->society, 'Clubhouse', 1500);
        $this->gym = $this->facility($this->society, 'Gym', 500);
    }

    private function facility(Society $s, string $name, float $charge): Facility
    {
        return Facility::create(['society_id' => $s->id, 'name' => $name, 'type' => 'clubhouse', 'booking_charge' => $charge, 'status' => 'active', 'capacity' => 50,
            'opening_time' => '06:00', 'closing_time' => '22:00', 'advance_booking_days' => 60, 'max_booking_hours' => 8, 'requires_approval' => true]);
    }

    private function booking(string $status, float $amount, ?Facility $f = null, ?Society $s = null, ?Flat $flat = null, int $day = 1): FacilityBooking
    {
        $s ??= $this->society;

        return FacilityBooking::create(['society_id' => $s->id, 'facility_id' => ($f ?? $this->clubhouse)->id, 'user_id' => $this->admin->id, 'flat_id' => ($flat ?? $this->flat)->id,
            'booking_date' => now()->addDays($day)->toDateString(), 'start_time' => '10:00', 'end_time' => '12:00', 'purpose' => 'Party', 'expected_guests' => 5,
            'booking_amount' => $amount, 'security_deposit' => 0, 'payment_status' => 'pending', 'status' => $status]);
    }

    /** @return array<string,FacilityBooking> */
    private function realShape(): array
    {
        return [
            'a1' => $this->booking('approved', 1500, null, null, null, 1), 'a2' => $this->booking('approved', 1500, null, null, null, 2),
            'rej' => $this->booking('rejected', 1500, null, null, null, 3), 'canFree' => $this->booking('cancelled', 0, $this->gym, null, null, 4),
            'doneFree' => $this->booking('completed', 0, $this->gym, null, null, 5), 'a3' => $this->booking('approved', 1500, null, null, null, 6),
            'canPaid' => $this->booking('cancelled', 1500, null, null, null, 7), 'a4' => $this->booking('approved', 1500, null, null, null, 8),
            'gymFree' => $this->booking('approved', 0, $this->gym, null, null, 9),
        ];
    }

    private function stats(): array
    {
        return $this->actingAs($this->admin)->get(route('facility-bookings.index'))->assertOk()->viewData('stats');
    }

    private function report(): array
    {
        return app(FinancialReportService::class)->build($this->society->id, ReportPeriod::monthly((int) now()->year, (int) now()->month));
    }

    public function test_the_cards_count_all_nine_and_payment_pending_is_the_four_charged_approved_bookings(): void
    {
        $this->realShape();
        $s = $this->stats();

        $this->assertSame(9, $s['total']);
        $this->assertSame(5, $s['approved']);
        $this->assertSame(0, $s['pending'], 'Pending = awaiting approval');
        $this->assertSame(1, $s['rejected']);
        $this->assertSame(1, $s['completed']);
        $this->assertSame(2, $s['cancelled']);
        $this->assertEquals(0, $s['received_amount']);
        $this->assertSame(4, $s['awaiting_count']);
        $this->assertEquals(6000, $s['awaiting_amount']);
    }

    public function test_the_page_labels_approval_pending_and_payment_pending_separately(): void
    {
        $this->realShape();
        $page = $this->actingAs($this->admin)->get(route('facility-bookings.index'))->assertOk();

        $page->assertSee('Pending Approval');
        $page->assertSee('Payment Pending:', false);
        $page->assertSee('4 bookings', false);
        $page->assertSee('6,000.00', false);
    }

    public function test_the_summary_and_the_financial_report_agree_on_the_same_four_bookings(): void
    {
        $b = $this->realShape();
        $report = $this->report()['facility']['receivable'];

        $this->assertSame(4, $report['count']);
        $this->assertEquals(6000, $report['amount']);
        $this->assertEqualsCanonicalizing(
            [$b['a1']->booking_number, $b['a2']->booking_number, $b['a3']->booking_number, $b['a4']->booking_number],
            array_column($report['rows'], 'booking')
        );
        $s = $this->stats();
        $this->assertSame($report['count'], $s['awaiting_count']);
        $this->assertEquals($report['amount'], $s['awaiting_amount']);
        $this->assertEquals(0, $this->report()['overview']['facility_collected'], 'owed is not income');
    }

    public function test_cancelled_rejected_pending_and_free_bookings_are_never_receivables_but_a_completed_charged_one_is(): void
    {
        $this->booking('cancelled', 1500, null, null, null, 1);
        $this->booking('rejected', 1500, null, null, null, 2);
        $this->booking('pending', 1500, null, null, null, 3);
        $this->booking('approved', 0, $this->gym, null, null, 4);
        $done = $this->booking('completed', 1500, null, null, null, 5);

        $r = $this->report()['facility']['receivable'];
        $this->assertSame(1, $r['count']);
        $this->assertEquals(1500, $r['amount']);
        $this->assertSame($done->booking_number, $r['rows'][0]['booking']);
        $this->assertSame(1, $this->stats()['awaiting_count']);
    }

    public function test_another_societys_bookings_are_in_neither_figure(): void
    {
        $this->realShape();
        $other = $this->makeSociety('Other');
        $oflat = $this->makeFlat($other, $this->makeBuilding($other), '201');
        $of = $this->facility($other, 'Hall', 9000);
        $this->booking('approved', 9000, $of, $other, $oflat);

        $this->assertEquals(6000, $this->stats()['awaiting_amount']);
        $this->assertEquals(6000, $this->report()['facility']['receivable']['amount']);
    }

    public function test_recording_a_payment_moves_the_booking_out_of_payment_pending_and_into_income_exactly_once(): void
    {
        $b = $this->realShape();
        $url = route('facility-bookings.record-payment', $b['a1']);
        $post = ['payment_method' => 'cash', 'paid_on' => now()->toDateString()];

        $this->actingAs($this->admin)->post($url, $post)->assertSessionHas('success');
        $this->actingAs($this->admin)->post($url, $post);        // double submit / refresh
        $this->actingAs($this->admin)->post($url, $post);

        $s = $this->stats();
        $this->assertEquals(1500, $s['received_amount']);
        $this->assertSame(3, $s['awaiting_count']);
        $this->assertEquals(4500, $s['awaiting_amount']);
        $r = $this->report();
        $this->assertEquals(1500, $r['overview']['facility_collected']);
        $this->assertEquals(1500, $r['facility']['collected']['amount']);
        $this->assertSame(1, $r['facility']['collected']['count']);
        $this->assertEquals(4500, $r['facility']['receivable']['amount']);
    }

    public function test_a_refund_is_counted_once_and_a_repeat_changes_nothing(): void
    {
        $b = $this->realShape();
        $this->actingAs($this->admin)->post(route('facility-bookings.record-payment', $b['a1']), ['payment_method' => 'cash', 'paid_on' => now()->toDateString()]);
        $url = route('facility-bookings.record-refund', $b['a1']);

        $this->actingAs($this->admin)->post($url, ['refund_amount' => 1500, 'refund_reason' => 'host withdrew']);
        $this->actingAs($this->admin)->post($url, ['refund_amount' => 1500, 'refund_reason' => 'host withdrew']);

        $s = $this->stats();
        $this->assertEquals(1500, $s['refunded_amount']);
        $this->assertEquals(1500, $this->report()['facility']['refunded']['amount']);
        $this->assertSame(3, $s['awaiting_count'], 'a refunded booking is not owed again');
    }
}
