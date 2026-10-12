<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\Notice;
use App\Models\Payment;
use App\Models\Society;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VillaArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The tenant dashboard's four Quick Actions - Pay Rent, Raise Request, View Notices, My Unit - were all `href="#"`:
 * they looked like buttons and did nothing. Each now goes to the real screen, and each destination is exercised as the
 * tenant would, finishing the workflow (opening Pay Rent never records a payment).
 *
 * Also pins the "rent shows on the admin screen but ₹0 for the tenant" explanation: the paid ₹10,000 September rent was
 * billed to Villa 160, where the tenant lived 22-24 Sep; he now rents Villa 159, which has no rent bill. Rent is billed to
 * the unit, so each screen is right for its own unit - nothing is copied or moved between them.
 */
class TenantQuickActionsTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;
    private Flat $current;   // villa 159
    private Flat $left;      // villa 160
    private User $tenantUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Royal Eden');
        $area = VillaArea::create(['society_id' => $this->society->id, 'name' => 'Garden Villas', 'status' => 'active']);
        $villa = fn (string $no) => Flat::create(['society_id' => $this->society->id, 'villa_area_id' => $area->id, 'property_type' => 'villa', 'flat_number' => $no, 'status' => 'on_rent']);
        $this->current = $villa('159');
        $this->left = $villa('160');

        $this->tenantUser = $this->makeUserWithRole($this->society, 'Tenant', ['name' => 'Piyus']);
        $tenant = Tenant::create(['society_id' => $this->society->id, 'flat_id' => $this->current->id, 'user_id' => $this->tenantUser->id, 'name' => 'Piyus', 'email' => 'piyus@t.local', 'phone' => '0000000001', 'move_in_date' => '2026-09-22', 'monthly_rent' => 10000, 'status' => 'active']);
        $this->tenantUser->update(['tenant_id' => $tenant->id]);
        $this->makeUserWithRole($this->society, 'Admin');   // the society's office (complaint notifications go to it)
    }

    private function rentBill(Flat $flat, int $amount = 10000, string $month = 'September'): MaintenanceBill
    {
        return MaintenanceBill::create(['society_id' => $this->society->id, 'flat_id' => $flat->id, 'month' => $month, 'year' => 2026, 'bill_type' => 'rent',
            'bill_date' => '2026-09-22', 'due_date' => '2026-10-22', 'maintenance_amount' => $amount, 'notes' => 'Monthly Rent']);
    }

    private function dashboard()
    {
        return $this->actingAs($this->tenantUser)->get(route('tenant.dashboard'))->assertOk();
    }

    // ── the four actions ────────────────────────────────────────────────────

    public function test_every_quick_action_links_to_a_real_destination_not_a_dead_hash(): void
    {
        $html = $this->dashboard()->getContent();
        $section = substr($html, strpos($html, 'Quick Actions'), 4000);

        preg_match_all('/<a href="([^"]*)"[^>]*>\s*<div[^>]*>\s*<i class="[^"]*"><\/i>\s*<\/div>\s*<span[^>]*>([^<]+)<\/span>/s', $section, $m, PREG_SET_ORDER);
        $links = array_column($m, 1, 2);

        $this->assertSame(['Pay Rent', 'Raise Request', 'View Notices', 'My Unit'], array_map('trim', array_keys($links)));
        $this->assertSame(route('tenant.rent'), $links['Pay Rent']);
        $this->assertSame(route('complaints.create'), $links['Raise Request']);
        $this->assertSame(route('notices.index'), $links['View Notices']);
        $this->assertSame('#my-unit', $links['My Unit']);
        $this->assertStringContainsString('id="my-unit"', $html, 'the My Unit link has somewhere to land');
        foreach ($links as $label => $href) {
            $this->assertNotSame('#', $href, "{$label} is not a dead link");
        }
    }

    public function test_pay_rent_opens_this_tenants_current_units_rent_and_records_nothing(): void
    {
        $mine = $this->rentBill($this->current, 10000, 'October');
        $before = Payment::count();

        $r = $this->actingAs($this->tenantUser)->get(route('tenant.rent'))->assertOk();

        $this->assertSame([$mine->id], $r->viewData('rentRecords')->pluck('id')->all());
        $this->assertEquals(10000.0, (float) $r->viewData('totalRent'));
        $this->assertEquals(0.0, (float) $r->viewData('totalPaid'));
        $this->assertEquals(10000.0, (float) $r->viewData('pendingAmount'));
        $this->assertSame($before, Payment::count(), 'opening the screen records no payment');
    }

    public function test_raise_request_opens_a_working_form_and_the_request_is_saved_against_the_tenants_unit(): void
    {
        $category = ComplaintCategory::create(['society_id' => $this->society->id, 'name' => 'Plumbing', 'status' => 'active']);

        $this->actingAs($this->tenantUser)->get(route('complaints.create'))->assertOk()->assertSee('Plumbing');

        // validation: nothing is saved without the required fields
        $this->actingAs($this->tenantUser)->post(route('complaints.store'), ['title' => ''])->assertSessionHasErrors(['category_id', 'title', 'description', 'priority']);
        $this->assertSame(0, Complaint::count());

        $this->actingAs($this->tenantUser)->post(route('complaints.store'), [
            'category_id' => $category->id, 'title' => 'Kitchen tap leaks', 'description' => 'Dripping all day', 'priority' => 'medium',
        ])->assertRedirect(route('complaints.index'));

        $c = Complaint::sole();
        $this->assertSame($this->tenantUser->id, (int) $c->created_by);
        $this->assertSame($this->current->id, (int) $c->flat_id, 'filed against the unit he rents, never one he left');
        $this->assertSame($this->society->id, (int) $c->society_id);
        $this->assertSame('open', $c->status);
        $this->actingAs($this->tenantUser)->get(route('complaints.index'))->assertOk()->assertSee('Kitchen tap leaks');
    }

    public function test_a_request_cannot_be_filed_against_someone_elses_unit(): void
    {
        $category = ComplaintCategory::create(['society_id' => $this->society->id, 'name' => 'Plumbing', 'status' => 'active']);

        $this->actingAs($this->tenantUser)->post(route('complaints.store'), [
            'category_id' => $category->id, 'title' => 'x', 'description' => 'y', 'priority' => 'low', 'flat_id' => $this->left->id,
        ])->assertForbidden();

        $this->assertSame(0, Complaint::count());
    }

    public function test_view_notices_shows_this_societys_published_notices_only(): void
    {
        $author = $this->makeUserWithRole($this->society, 'Admin');
        $notice = fn (array $o) => Notice::create($o + ['society_id' => $this->society->id, 'title' => 'T', 'content' => 'c', 'type' => 'general', 'priority' => 'medium', 'status' => 'published', 'created_by' => $author->id]);
        $notice(['title' => 'Water cut on Sunday']);
        $notice(['title' => 'Draft not for residents', 'status' => 'draft']);
        $other = $this->makeSociety('Other Heights');
        Notice::create(['society_id' => $other->id, 'title' => 'Other society secret', 'content' => 'c', 'type' => 'general', 'priority' => 'medium', 'status' => 'published', 'created_by' => $this->makeUserWithRole($other, 'Admin')->id]);

        $this->actingAs($this->tenantUser)->get(route('notices.index'))->assertOk()
            ->assertSee('Water cut on Sunday')->assertDontSee('Draft not for residents')->assertDontSee('Other society secret');
    }

    public function test_my_unit_shows_the_unit_he_rents_now_and_never_the_one_he_left(): void
    {
        $r = $this->dashboard();

        $this->assertSame($this->current->id, $r->viewData('unit')->id);
        $this->assertSame($this->tenantUser->tenant_id, $r->viewData('tenant')->id);
        $r->assertSee('159')->assertSee('Garden Villas');
        $this->assertStringNotContainsString('160', substr($r->getContent(), strpos($r->getContent(), 'id="my-unit"'), 3000));
    }

    public function test_a_tenant_without_a_tenancy_is_told_so_and_every_action_is_still_safe(): void
    {
        Tenant::where('user_id', $this->tenantUser->id)->update(['status' => 'inactive']);

        $this->dashboard()->assertSee('assign a unit to your account');
        $this->actingAs($this->tenantUser)->get(route('tenant.rent'))->assertRedirect(route('tenant.dashboard'))->assertSessionHas('error');
        $this->actingAs($this->tenantUser)->get(route('complaints.create'))->assertOk();
        $this->actingAs($this->tenantUser)->get(route('notices.index'))->assertOk();
    }

    public function test_the_actions_need_a_signed_in_user_and_the_dashboard_is_the_tenants_own(): void
    {
        auth()->logout();
        $this->get(route('tenant.dashboard'))->assertRedirect(route('login'));
        $this->get(route('tenant.rent'))->assertRedirect(route('login'));
        $this->get(route('complaints.create'))->assertRedirect(route('login'));
        $this->get(route('notices.index'))->assertRedirect(route('login'));
    }

    // ── "paid on the admin screen, ₹0 for the tenant" ────────────────────────

    public function test_rent_is_billed_to_the_unit_so_a_bill_on_the_unit_he_left_is_not_his_current_rent(): void
    {
        $left = $this->rentBill($this->left, 10000, 'September');
        Payment::create(['society_id' => $this->society->id, 'flat_id' => $this->left->id, 'maintenance_bill_id' => $left->id, 'user_id' => null, 'payment_id' => Payment::generatePaymentId(),
            'amount' => 10000, 'payment_method' => 'cash', 'status' => 'success', 'payment_date' => '2026-09-22', 'due_date' => '2026-10-22', 'bill_type' => 'rent']);

        // the office's Rent Management view of the SAME records: Villa 160's ₹10,000 is there
        $admin = $this->makeUserWithRole($this->society, 'Admin');
        $this->actingAs($admin)->get(route('admin.rents.index'))->assertOk()->assertSee('10,000');

        // the tenant's My Rent is for the unit he rents now (159), which has no rent bill: truthful empty state
        $r = $this->actingAs($this->tenantUser)->get(route('tenant.rent'))->assertOk();
        $this->assertSame($this->current->id, $r->viewData('flat')->id);
        $this->assertCount(0, $r->viewData('rentRecords'));
        $this->assertEquals(0.0, (float) $r->viewData('totalRent'));
        $this->assertEquals(0.0, (float) $r->viewData('totalPaid'));
        $this->assertEquals(0.0, (float) $r->viewData('pendingAmount'));
        $r->assertSee('No rent records found')->assertSee('Rent is billed to the home you rent now');

        // and nothing was moved, copied or rewritten to make the two agree
        $this->assertSame($this->left->id, (int) $left->fresh()->flat_id);
        $this->assertSame(1, Payment::where('maintenance_bill_id', $left->id)->count());
    }

    public function test_when_a_bill_exists_for_his_unit_he_sees_it_with_the_right_totals(): void
    {
        $mine = $this->rentBill($this->current, 10000, 'October');
        Payment::create(['society_id' => $this->society->id, 'flat_id' => $this->current->id, 'maintenance_bill_id' => $mine->id, 'user_id' => $this->tenantUser->id, 'payment_id' => Payment::generatePaymentId(),
            'amount' => 4000, 'payment_method' => 'cash', 'status' => 'success', 'payment_date' => '2026-10-05', 'due_date' => '2026-10-22', 'bill_type' => 'rent']);

        $r = $this->actingAs($this->tenantUser)->get(route('tenant.rent'))->assertOk();

        $this->assertSame([$mine->id], $r->viewData('rentRecords')->pluck('id')->all());
        $this->assertEquals(10000.0, (float) $r->viewData('totalRent'));
        $this->assertEquals(4000.0, (float) $r->viewData('totalPaid'));
        $this->assertEquals(6000.0, (float) $r->viewData('pendingAmount'));
    }
}
