<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\Payment;
use App\Models\Society;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Defects found by the web audit (MutatingRouteSweepTest and the role crawl),
 * each pinned here: a partial form must not crash, validation must not be
 * reported as a server failure, and an admin action must say truthfully what
 * it did.
 */
class WebAuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->society = $this->makeSociety('Eden');
        $this->admin = $this->makeUserWithRole($this->society, 'Admin');
    }

    private function rentBill(?Society $society = null): MaintenanceBill
    {
        $society ??= $this->society;
        $flat = $this->makeFlat($society, $this->makeBuilding($society, 'B' . uniqid()), (string) random_int(100, 999));

        return MaintenanceBill::create([
            'society_id' => $society->id, 'flat_id' => $flat->id, 'month' => 'October', 'year' => 2026, 'bill_type' => 'rent',
            'bill_date' => '2026-10-01', 'due_date' => '2026-10-20', 'maintenance_amount' => 10000,
        ]);
    }

    // ------------------------------------------------- payment gateway settings

    public function test_saving_payment_settings_with_a_partial_form_does_not_crash(): void
    {
        // Only one section posted (or nothing at all): every other field is simply absent.
        foreach ([[], ['razorpay_enabled' => 1], ['stripe_key' => 'sk_test_x'], ['payment_test_mode' => 1, 'cashfree_enabled' => 1]] as $payload) {
            $response = $this->actingAs($this->admin)->post(route('settings.payment.update'), $payload);

            $this->assertLessThan(500, $response->getStatusCode(), 'payload ' . json_encode($payload));
        }
    }

    public function test_a_partial_payment_settings_form_still_saves_what_it_did_send_and_keeps_the_rest(): void
    {
        SystemSetting::set('payment', 'razorpay_key', 'rzp_existing_key', 'encrypted', $this->society->id);

        $this->actingAs($this->admin)->post(route('settings.payment.update'), ['stripe_key' => 'sk_test_new']);

        $this->assertSame('sk_test_new', SystemSetting::get('payment', 'stripe_key', null, $this->society->id));
        $this->assertSame('rzp_existing_key', SystemSetting::get('payment', 'razorpay_key', null, $this->society->id), 'an absent field never erases a stored value');
    }

    // ------------------------------------------------------ admin rent payment

    public function test_an_incomplete_rent_payment_is_a_422_with_field_errors_not_a_500(): void
    {
        $bill = $this->rentBill();

        $response = $this->actingAs($this->admin)->postJson(route('admin.rents.add-payment'), ['rent_id' => $bill->id]);

        $response->assertStatus(422)->assertJson(['success' => false])->assertJsonValidationErrors(['payment_date', 'payment_amount', 'payment_method']);
        $this->assertSame(0, Payment::count());
    }

    public function test_a_rent_payment_for_a_missing_or_foreign_bill_is_refused_cleanly(): void
    {
        $foreign = $this->rentBill($this->makeSociety('Other'));
        $body = ['payment_date' => '2026-10-10', 'payment_amount' => 100, 'payment_method' => 'cash'];

        $this->actingAs($this->admin)->postJson(route('admin.rents.add-payment'), $body + ['rent_id' => 999999])->assertStatus(422);
        $this->actingAs($this->admin)->postJson(route('admin.rents.add-payment'), $body + ['rent_id' => $foreign->id])->assertStatus(404);
        $this->assertSame(0, Payment::count());
    }

    public function test_a_valid_rent_payment_still_records_and_updates_the_bill(): void
    {
        $bill = $this->rentBill();

        $this->actingAs($this->admin)->postJson(route('admin.rents.add-payment'), [
            'rent_id' => $bill->id, 'payment_date' => '2026-10-10', 'payment_amount' => 4000, 'payment_method' => 'cash',
        ])->assertOk()->assertJson(['success' => true]);

        $bill->refresh();
        $this->assertSame('4000.00', (string) $bill->paid_amount);
        $this->assertSame('partial', $bill->status);
    }

    // ------------------------------------------------- email-template defaults

    public function test_initialize_defaults_for_a_society_admin_is_a_truthful_no_op_not_a_crash(): void
    {
        $before = EmailTemplate::count();
        $this->assertGreaterThan(0, EmailTemplate::whereNull('society_id')->count(), 'the migrations seed the global defaults');

        $response = $this->actingAs($this->admin)->post(route('admin.email-templates.initialize'));

        $response->assertRedirect(route('admin.email-templates.index'));
        $response->assertSessionHas('success');
        $response->assertSessionHasNoErrors();
        $this->assertStringContainsString('Nothing to add', (string) session('success'));
        $this->assertSame($before, EmailTemplate::count(), 'no per-society copies that the unique slug index would reject');
    }

    public function test_initialize_defaults_creates_only_what_is_missing_and_a_rerun_adds_nothing(): void
    {
        $super = $this->makeUserWithRole($this->society, 'Super Admin', ['society_id' => null]);
        $default = EmailTemplate::getDefaultTemplates()[0];
        $slug = $default['slug'];
        // Make this default genuinely missing: no template with its slug AND none serving its trigger event.
        EmailTemplate::where('slug', $slug)->orWhere('trigger_event', $default['trigger_event'])->delete();
        $before = EmailTemplate::count();

        $this->actingAs($super)->post(route('admin.email-templates.initialize'))->assertSessionHas('success');
        $this->assertSame($before + 1, EmailTemplate::count(), 'only the missing default was created');
        $this->assertSame(1, EmailTemplate::where('slug', $slug)->count());
        $this->assertSame(1, EmailTemplate::where('trigger_event', $default['trigger_event'])->count(), 'one template per event, never two');

        $this->actingAs($super)->post(route('admin.email-templates.initialize'));
        $this->assertSame($before + 1, EmailTemplate::count(), 'second run is a no-op');
    }
}
