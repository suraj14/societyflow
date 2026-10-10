<?php

namespace Tests\Feature;

use App\Models\Society;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Super Admin > Billing and Packages. The module was written against a schema
 * the model does not have (`$payment->society`, `->subscriptionPlan`, `paid_at`,
 * status `completed`, cycles `annual`/`lifetime`) and the `billing/{billing}`
 * route could not bind `show(SubscriptionPayment $payment)`. It only looked
 * healthy because `subscription_payments` was empty: nothing to eager-load.
 * These tests run it with real rows.
 */
class SuperAdminBillingTest extends TestCase
{
    use RefreshDatabase;

    private User $super;
    private Society $society;
    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Royal Eden');
        $this->super = $this->makeUserWithRole($this->society, 'Super Admin', ['society_id' => null]);
        $this->plan = SubscriptionPlan::create(['name' => 'Gold', 'slug' => 'gold', 'description' => 'Gold plan', 'monthly_price' => 999, 'yearly_price' => 9999, 'status' => 'active']);
    }

    private function subscription(string $cycle = 'monthly', ?\DateTimeInterface $ends = null, ?Society $society = null): Subscription
    {
        return Subscription::create([
            'society_id' => ($society ?? $this->society)->id, 'subscription_plan_id' => $this->plan->id, 'billing_cycle' => $cycle,
            'amount' => 999, 'start_date' => now()->subMonth()->toDateString(), 'end_date' => ($ends ?? now()->addDays(10))->format('Y-m-d'), 'status' => 'active',
        ]);
    }

    private function payment(Subscription $s, string $status = 'pending', array $over = []): SubscriptionPayment
    {
        return SubscriptionPayment::create(array_merge([
            'subscription_id' => $s->id, 'amount' => 999, 'payment_method' => 'manual', 'status' => $status,
            'payment_date' => now()->toDateString(), 'transaction_id' => 'TXN-' . uniqid(),
        ], $over));
    }

    // -------------------------------------------------------------- listing

    public function test_billing_index_renders_real_payments_with_society_plan_and_cycle(): void
    {
        $p = $this->payment($this->subscription('yearly'), 'success');

        $response = $this->actingAs($this->super)->get(route('super-admin.billing.index'))->assertOk();

        $response->assertSee('Royal Eden')->assertSee('Gold')->assertSee('Yearly')->assertSee($p->transaction_id);
        $response->assertDontSee('NEMJUPOAAXM2M1');   // the fabricated fallback id that used to be shown for any payment without one
        $response->assertSee('&#8377;', false);
    }

    public function test_a_payment_without_a_transaction_id_shows_a_dash_not_an_invented_one(): void
    {
        $this->payment($this->subscription(), 'pending', ['transaction_id' => null]);

        $html = $this->actingAs($this->super)->get(route('super-admin.billing.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('NEMJUPOAAXM2M1', $html);
    }

    public function test_revenue_statistics_count_paid_payments(): void
    {
        $s = $this->subscription();
        $this->payment($s, 'success', ['amount' => 1000]);
        $this->payment($s, 'success', ['amount' => 500]);
        $this->payment($s, 'pending', ['amount' => 700]);
        $this->payment($s, 'failed', ['amount' => 300]);

        $stats = $this->actingAs($this->super)->get(route('super-admin.billing.index'))->viewData('stats');

        $this->assertEquals(1500, $stats['total_revenue']);
        $this->assertEquals(1500, $stats['monthly_revenue']);
        $this->assertSame(1, $stats['pending_payments']);
        $this->assertSame(1, $stats['failed_payments']);
    }

    public function test_search_and_status_filter_work(): void
    {
        $other = $this->makeSociety('Mahalaxmi');
        $mine = $this->payment($this->subscription(), 'success', ['transaction_id' => 'ALPHA-1']);
        $theirs = $this->payment($this->subscription('monthly', null, $other), 'pending', ['transaction_id' => 'BETA-2']);

        $bySociety = $this->actingAs($this->super)->get(route('super-admin.billing.index', ['search' => 'Mahalaxmi']))->assertOk()->viewData('payments');
        $this->assertSame([$theirs->id], $bySociety->pluck('id')->all());

        $byTx = $this->actingAs($this->super)->get(route('super-admin.billing.index', ['search' => 'ALPHA']))->viewData('payments');
        $this->assertSame([$mine->id], $byTx->pluck('id')->all());

        $paid = $this->actingAs($this->super)->get(route('super-admin.billing.index', ['status' => 'success']))->viewData('payments');
        $this->assertSame([$mine->id], $paid->pluck('id')->all());
    }

    // -------------------------------------------------------------- detail + invoice

    public function test_the_payment_detail_page_renders_and_binds_the_right_record(): void
    {
        $this->payment($this->subscription(), 'success', ['transaction_id' => 'FIRST-1']);
        $second = $this->payment($this->subscription(), 'pending', ['transaction_id' => 'SECOND-2']);

        $this->actingAs($this->super)->get(route('super-admin.billing.show', $second))
            ->assertOk()->assertSee('SECOND-2')->assertDontSee('FIRST-1')->assertSee('Mark as paid');
    }

    public function test_the_invoice_downloads_as_a_pdf(): void
    {
        $p = $this->payment($this->subscription(), 'success');

        $response = $this->actingAs($this->super)->get(route('super-admin.billing.invoice', $p))->assertOk();

        $this->assertStringContainsString('pdf', (string) $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    // -------------------------------------------------------------- mark as paid

    public function test_marking_paid_succeeds_and_extends_a_monthly_subscription_from_its_end_date(): void
    {
        $ends = now()->addDays(10);
        $sub = $this->subscription('monthly', $ends);
        $p = $this->payment($sub, 'pending');

        $this->actingAs($this->super)->post(route('super-admin.billing.mark-paid', $p))->assertSessionHas('success');

        $this->assertSame('success', $p->fresh()->status);
        $this->assertSame($ends->copy()->addMonth()->toDateString(), $sub->fresh()->end_date->toDateString());
        $this->assertSame($ends->copy()->addMonth()->toDateString(), $this->society->fresh()->subscription_ends_at->toDateString());
    }

    public function test_marking_a_late_payment_paid_extends_from_today_not_from_the_lapsed_end_date(): void
    {
        $sub = $this->subscription('yearly', now()->subMonths(3));
        $sub->update(['status' => 'expired']);
        $p = $this->payment($sub, 'pending');

        $this->actingAs($this->super)->post(route('super-admin.billing.mark-paid', $p));

        $this->assertSame(now()->addYear()->toDateString(), $sub->fresh()->end_date->toDateString());
        $this->assertSame('active', $sub->fresh()->status);
        $this->assertTrue($sub->fresh()->end_date->isFuture());
    }

    public function test_marking_paid_twice_does_not_extend_twice(): void
    {
        $sub = $this->subscription('monthly', now()->addDays(10));
        $p = $this->payment($sub, 'pending');

        $this->actingAs($this->super)->post(route('super-admin.billing.mark-paid', $p));
        $afterFirst = $sub->fresh()->end_date->toDateString();
        $this->actingAs($this->super)->post(route('super-admin.billing.mark-paid', $p))->assertSessionHas('success');

        $this->assertSame($afterFirst, $sub->fresh()->end_date->toDateString());
    }

    public function test_a_refunded_payment_cannot_be_marked_paid(): void
    {
        $sub = $this->subscription('monthly', now()->addDays(10));
        $end = $sub->end_date->toDateString();
        $p = $this->payment($sub, 'refunded');

        $this->actingAs($this->super)->post(route('super-admin.billing.mark-paid', $p))->assertSessionHas('error');

        $this->assertSame('refunded', $p->fresh()->status);
        $this->assertSame($end, $sub->fresh()->end_date->toDateString());
    }

    // -------------------------------------------------------------- packages

    public function test_the_package_detail_page_renders_with_its_societies(): void
    {
        $this->subscription('yearly');

        $this->actingAs($this->super)->get(route('super-admin.packages.show', $this->plan))
            ->assertOk()->assertSee('Gold')->assertSee('Royal Eden')->assertSee('Yearly');
    }

    public function test_a_package_with_no_societies_says_so(): void
    {
        $this->actingAs($this->super)->get(route('super-admin.packages.show', $this->plan))
            ->assertOk()->assertSee('No society uses this package yet');
    }

    public function test_nobody_but_a_super_admin_can_mark_a_payment_paid(): void
    {
        $p = $this->payment($this->subscription(), 'pending');
        $admin = $this->makeUserWithRole($this->society, 'Admin');

        $this->actingAs($admin)->post(route('super-admin.billing.mark-paid', $p))->assertStatus(403);
        $this->assertSame('pending', $p->fresh()->status);
    }
}
