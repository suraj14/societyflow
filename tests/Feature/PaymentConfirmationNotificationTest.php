<?php

namespace Tests\Feature;

use App\Events\PaymentReceived;
use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\VillaArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * A payment confirmation says "the office has the money". It is therefore sent when a payment BECOMES successful - an
 * office-recorded payment, or a resident's submission once an admin verifies it - and never for an unverified claim, a
 * rejected one, a refusal, or a second click on "verify". (Verifying a submitted payment sent nothing before: only the
 * office-recorded path dispatched the event, so a resident who paid and was verified was never told.)
 */
class PaymentConfirmationNotificationTest extends TestCase
{
    use RefreshDatabase;

    private $society;
    private $admin;
    private $tenantUser;
    private MaintenanceBill $bill;

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Alpha');
        $area = VillaArea::create(['society_id' => $this->society->id, 'name' => 'Garden', 'status' => 'active']);
        $villa = Flat::create(['society_id' => $this->society->id, 'villa_area_id' => $area->id, 'property_type' => 'villa', 'flat_number' => '159', 'status' => 'on_rent']);
        $this->admin = $this->makeUserWithRole($this->society, 'Admin');
        $this->tenantUser = $this->makeUserWithRole($this->society, 'Tenant');
        $tenant = Tenant::create(['society_id' => $this->society->id, 'flat_id' => $villa->id, 'user_id' => $this->tenantUser->id, 'name' => 'Piyus', 'email' => 'p@t.local', 'phone' => '9000000159', 'move_in_date' => '2026-09-22', 'monthly_rent' => 10000, 'status' => 'active']);
        $this->tenantUser->update(['tenant_id' => $tenant->id]);
        $this->bill = MaintenanceBill::create(['society_id' => $this->society->id, 'flat_id' => $villa->id, 'month' => 'October', 'year' => 2026, 'bill_type' => 'rent',
            'bill_date' => '2026-10-01', 'due_date' => '2026-10-15', 'maintenance_amount' => 10000, 'paid_amount' => 0]);
    }

    private function submit(): Payment
    {
        $this->actingAs($this->tenantUser)->postJson(route('tenant.rent.payment'), ['rent_id' => $this->bill->id, 'payment_date' => '2026-10-10', 'payment_amount' => 10000, 'payment_method' => 'cash'])
            ->assertOk()->assertJson(['success' => true]);

        return Payment::where('maintenance_bill_id', $this->bill->id)->sole();
    }

    public function test_a_resident_submission_announces_nothing_until_it_is_verified(): void
    {
        Event::fake([PaymentReceived::class]);

        $payment = $this->submit();

        $this->assertSame('pending', $payment->status);
        Event::assertNotDispatched(PaymentReceived::class);
    }

    public function test_verifying_the_payment_tells_the_payer_exactly_once(): void
    {
        $payment = $this->submit();
        Event::fake([PaymentReceived::class]);

        $this->actingAs($this->admin)->post(route('payments.approve', $payment))->assertRedirect()->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('payments.approve', $payment))->assertRedirect()->assertSessionHas('info');

        $this->assertSame('success', $payment->fresh()->status);
        Event::assertDispatchedTimes(PaymentReceived::class, 1);
        Event::assertDispatched(PaymentReceived::class, fn ($e) => $e->payment->is($payment) && $e->payment->status === 'success');
    }

    public function test_the_payer_gets_the_in_app_confirmation_and_only_after_the_verification(): void
    {
        $payments = fn () => $this->tenantUser->fresh()->notifications()->get()->filter(fn ($n) => ($n->data['category'] ?? null) === 'payment');

        $payment = $this->submit();
        $this->assertCount(0, $payments(), 'a claim is not a payment');

        $this->actingAs($this->admin)->post(route('payments.approve', $payment));

        $this->assertCount(1, $payments());
        $this->assertStringContainsString('10,000', $payments()->first()->data['body']);
    }
    public function test_a_rejected_submission_and_a_refused_approval_announce_nothing(): void
    {
        $payment = $this->submit();
        $other = $this->makeUserWithRole($this->makeSociety('Beta'), 'Admin');
        Event::fake([PaymentReceived::class]);

        $this->actingAs($this->tenantUser)->post(route('payments.approve', $payment))->assertForbidden();
        $this->actingAs($other)->post(route('payments.approve', $payment))->assertForbidden();
        $this->actingAs($this->admin)->post(route('payments.reject', $payment))->assertRedirect();
        $this->actingAs($this->admin)->post(route('payments.approve', $payment))->assertRedirect()->assertSessionHas('error');

        $this->assertSame('cancelled', $payment->fresh()->status);
        Event::assertNotDispatched(PaymentReceived::class);
    }

    public function test_an_office_recorded_payment_still_announces_once(): void
    {
        Event::fake([PaymentReceived::class]);

        $this->actingAs($this->admin)->post(route('payments.store'), [
            'flat_id' => $this->bill->flat_id, 'maintenance_bill_id' => $this->bill->id, 'amount' => 4000, 'payment_method' => 'cash', 'payment_date' => '2026-10-11',
        ]);

        Event::assertDispatchedTimes(PaymentReceived::class, 1);
    }
}
