<?php

namespace Tests\Feature;

use App\Models\MaintenanceBill;
use App\Models\Society;
use App\Models\SystemSetting;
use App\Services\UpiPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * UPI deep link generation (brief §12).
 *
 * `upi://pay?pa=<vpa>&pn=<payee>&am=<amount>&cu=INR&tn=<note>` with the
 * amount, payee and VPA all resolved server-side - the caller only ever
 * names which bill to pay, never how much for it.
 */
class UpiPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function billIn(Society $society, float $balance = 1500.00, string $number = 'MB-001'): MaintenanceBill
    {
        $building = $this->makeBuilding($society);
        $flat     = $this->makeFlat($society, $building, 'U-' . uniqid());

        return MaintenanceBill::create([
            'society_id'     => $society->id,
            'flat_id'        => $flat->id,
            'bill_number'    => $number,
            'month'          => now()->month,
            'year'           => now()->year,
            'bill_date'      => now()->toDateString(),
            'due_date'       => now()->addDays(10)->toDateString(),
            // total_amount/balance_amount are recomputed on save from
            // maintenance_amount (MaintenanceBill::calculateTotalAmount) -
            // set the input the model actually derives them from.
            'maintenance_amount' => $balance,
            'paid_amount'        => 0,
            'status'             => 'pending',
        ]);
    }

    // ------------------------------------------------------------- the link

    public function test_the_link_carries_the_bills_balance_not_an_arbitrary_amount(): void
    {
        $society = $this->makeSociety();
        SystemSetting::set('payment_upi', 'upi_id', 'society@upi', 'string', $society->id);
        SystemSetting::set('payment_upi', 'payee_name', 'Urban Heights Society', 'string', $society->id);

        $bill = $this->billIn($society, 2345.50);

        $link = app(UpiPaymentService::class)->buildLink($bill);

        $this->assertStringStartsWith('upi://pay?', $link);
        parse_str(substr($link, strlen('upi://pay?')), $params);

        $this->assertSame('society@upi', $params['pa']);
        $this->assertSame('Urban Heights Society', $params['pn']);
        $this->assertSame('2345.50', $params['am']);
        $this->assertSame('INR', $params['cu']);
        $this->assertStringContainsString($bill->bill_number, $params['tn']);
    }

    /** A name/note with special characters must survive the round trip. */
    public function test_payee_name_and_note_are_url_encoded(): void
    {
        $society = $this->makeSociety();
        SystemSetting::set('payment_upi', 'upi_id', 'society@upi', 'string', $society->id);
        SystemSetting::set('payment_upi', 'payee_name', 'A & B Society, Phase-1', 'string', $society->id);

        $bill = $this->billIn($society, 100, 'MB & Special #1');

        $link = app(UpiPaymentService::class)->buildLink($bill);

        // The raw characters must not appear unescaped in the query string.
        $this->assertStringNotContainsString('A & B', $link);

        parse_str(substr($link, strlen('upi://pay?')), $params);
        $this->assertSame('A & B Society, Phase-1', $params['pn']);
        $this->assertStringContainsString('MB & Special #1', $params['tn']);
    }

    public function test_a_society_with_no_upi_id_configured_is_rejected(): void
    {
        $society = $this->makeSociety();
        $bill    = $this->billIn($society);

        $this->expectException(InvalidArgumentException::class);

        app(UpiPaymentService::class)->buildLink($bill);
    }

    public function test_a_fully_paid_bill_is_rejected(): void
    {
        $society = $this->makeSociety();
        SystemSetting::set('payment_upi', 'upi_id', 'society@upi', 'string', $society->id);
        $bill = $this->billIn($society, 0);

        $this->expectException(InvalidArgumentException::class);

        app(UpiPaymentService::class)->buildLink($bill);
    }

    // ---------------------------------------------------------- the endpoint

    public function test_the_owner_of_the_bill_can_generate_its_link(): void
    {
        $society = $this->makeSociety();
        SystemSetting::set('payment_upi', 'upi_id', 'society@upi', 'string', $society->id);

        $building = $this->makeBuilding($society);
        $flat     = $this->makeFlat($society, $building);
        $resident = $this->makeResidentUser($society, $flat);

        $bill = MaintenanceBill::create([
            'society_id'     => $society->id,
            'flat_id'        => $flat->id,
            'bill_number'    => 'MB-OWN',
            'month'          => now()->month,
            'year'           => now()->year,
            'bill_date'      => now()->toDateString(),
            'due_date'           => now()->addDays(10)->toDateString(),
            'maintenance_amount' => 500,
            'paid_amount'        => 0,
            'status'             => 'pending',
        ]);

        $response = $this->actingAs($resident)
            ->getJson(route('bills.upi-link', $bill))
            ->assertOk();

        $this->assertTrue($response->json('success'));
        $this->assertStringStartsWith('upi://pay?', $response->json('link'));
    }

    public function test_a_resident_cannot_generate_a_link_for_someone_elses_bill(): void
    {
        $society = $this->makeSociety();
        SystemSetting::set('payment_upi', 'upi_id', 'society@upi', 'string', $society->id);

        $building     = $this->makeBuilding($society);
        $theirFlat    = $this->makeFlat($society, $building, 'OTHER-1');
        $myFlat       = $this->makeFlat($society, $building, 'MINE-1');
        $me           = $this->makeResidentUser($society, $myFlat);

        $bill = MaintenanceBill::create([
            'society_id' => $society->id, 'flat_id' => $theirFlat->id, 'bill_number' => 'MB-THEIRS',
            'month' => now()->month, 'year' => now()->year, 'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(), 'maintenance_amount' => 500,
            'paid_amount' => 0, 'status' => 'pending',
        ]);

        $this->actingAs($me)
            ->getJson(route('bills.upi-link', $bill))
            ->assertForbidden();
    }

    public function test_a_user_cannot_generate_a_link_for_a_bill_in_another_society(): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        SystemSetting::set('payment_upi', 'upi_id', 'b@upi', 'string', $societyB->id);

        $adminA = $this->makeUserWithRole($societyA, 'Admin');
        $billB  = $this->billIn($societyB);

        $this->actingAs($adminA)
            ->getJson(route('bills.upi-link', $billB))
            ->assertForbidden();
    }

    public function test_admin_can_generate_a_link_for_any_bill_in_their_own_society(): void
    {
        $society = $this->makeSociety();
        SystemSetting::set('payment_upi', 'upi_id', 'society@upi', 'string', $society->id);
        $admin = $this->makeUserWithRole($society, 'Admin');
        $bill  = $this->billIn($society);

        $this->actingAs($admin)
            ->getJson(route('bills.upi-link', $bill))
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    /**
     * Requesting the link must never itself create or touch a Payment
     * record - opening the deep link only pre-fills the resident's UPI
     * app, it does not verify anything.
     */
    public function test_requesting_the_link_creates_no_payment_record(): void
    {
        $society = $this->makeSociety();
        SystemSetting::set('payment_upi', 'upi_id', 'society@upi', 'string', $society->id);
        $admin = $this->makeUserWithRole($society, 'Admin');
        $bill  = $this->billIn($society);

        $this->actingAs($admin)->getJson(route('bills.upi-link', $bill));

        $this->assertSame(0, \App\Models\Payment::count());
        $this->assertSame('pending', $bill->fresh()->status);
    }
}
