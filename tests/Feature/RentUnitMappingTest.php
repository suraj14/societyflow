<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Society;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VillaArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A rent record keeps the exact unit it was saved against. Real case: a September rent on Villa 160 (no active tenant) while the
 * tenant lives in Villa 159. The edit page used to list only units with an active tenant, so the rent's own unit was not an option,
 * nothing was pre-selected, and saving silently re-pointed the rent at whichever unit the browser submitted. Rules now: the
 * unit stays unless deliberately moved; a move is validated, refused once payments exist, and audited.
 */
class RentUnitMappingTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;
    private User $admin;
    private Flat $villa159;   // active tenant
    private Flat $villa160;   // owner Suraj, no active tenant
    private Tenant $tenant;
    private Owner $owner;
    private MaintenanceBill $rent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Royal');
        $this->admin = $this->makeUserWithRole($this->society, 'Admin');
        $area = VillaArea::create(['society_id' => $this->society->id, 'name' => 'Garden', 'status' => 'active']);
        $this->villa160 = Flat::create(['society_id' => $this->society->id, 'property_type' => 'villa', 'villa_area_id' => $area->id, 'flat_number' => '160', 'villa_name' => 'Suman Sadan', 'floor' => 0, 'status' => 'occupied']);
        $this->villa159 = Flat::create(['society_id' => $this->society->id, 'property_type' => 'villa', 'villa_area_id' => $area->id, 'flat_number' => '159', 'floor' => 0, 'status' => 'vacant']);
        $this->owner = Owner::create(['society_id' => $this->society->id, 'name' => 'Suraj Suman', 'email' => 's@t.local', 'phone' => '9000000160', 'status' => 'active']);
        $this->villa160->update(['owner_id' => $this->owner->id]);

        $tu = $this->makeUserWithRole($this->society, 'Tenant');
        $this->tenant = Tenant::create(['society_id' => $this->society->id, 'flat_id' => $this->villa159->id, 'user_id' => $tu->id, 'name' => 'Piyus', 'email' => $tu->email,
            'phone' => '9000000061', 'move_in_date' => now(), 'status' => 'active', 'monthly_rent' => 10000]);
        $tu->update(['tenant_id' => $this->tenant->id]);

        $this->rent = $this->rentOn($this->villa159);
    }

    private function rentOn(Flat $flat, string $date = '2026-09-22'): MaintenanceBill
    {
        return MaintenanceBill::create(['society_id' => $this->society->id, 'flat_id' => $flat->id, 'bill_number' => 'RENT-' . $this->society->id . '-' . $flat->id . '-' . uniqid(),
            'month' => 'September', 'year' => 2026, 'maintenance_amount' => 10000, 'total_amount' => 10000, 'paid_amount' => 0, 'bill_date' => $date, 'due_date' => '2026-10-22', 'bill_type' => 'rent', 'notes' => 'Monthly Rent']);
    }

    private function save(MaintenanceBill $rent, array $over = [], bool $withUnit = true)
    {
        $data = $over + ['rent_amount' => 10000, 'bill_date' => '2026-09-22', 'due_date' => '2026-10-22', 'description' => 'Monthly Rent'];
        if ($withUnit && !array_key_exists('flat_id', $data)) {
            $data['flat_id'] = $rent->flat_id;
        }

        return $this->actingAs($this->admin)->put(route('admin.rents.update', $rent), $data);
    }

    public function test_a_rent_on_villa_159_stays_there_through_repeated_edits_and_reloads(): void
    {
        foreach ([12000, 10000, 11000] as $amount) {
            $this->save($this->rent, ['rent_amount' => $amount, 'description' => "edit $amount"])->assertRedirect(route('admin.rents.index'));
            $this->get(route('admin.rents.edit', $this->rent))->assertOk();
            $this->assertSame($this->villa159->id, $this->rent->fresh()->flat_id);
        }
        $this->assertSame($this->villa160->id, $this->villa160->fresh()->id);
        $this->assertSame(0, MaintenanceBill::where('flat_id', $this->villa160->id)->count(), 'Villa 160 stays separate');
    }

    public function test_editing_unrelated_fields_without_the_unit_field_does_not_move_the_rent(): void
    {
        $this->save($this->rent, ['due_date' => '2026-10-28', 'description' => 'moved due date'], false)->assertRedirect(route('admin.rents.index'));

        $this->assertSame($this->villa159->id, $this->rent->fresh()->flat_id);
        $this->assertSame(0, ActivityLog::where('action', 'rent.unit_changed')->count());
    }

    public function test_a_rent_whose_unit_has_no_active_tenant_still_shows_and_keeps_its_own_unit_on_the_edit_page(): void
    {
        $orphan = $this->rentOn($this->villa160, '2026-08-22');   // Villa 160 has no active tenant: the real-data shape

        $html = $this->actingAs($this->admin)->get(route('admin.rents.edit', $orphan))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option value="' . $this->villa160->id . '"\s+selected/', $html, 'its own unit is the pre-selected option');

        $this->save($orphan, ['bill_date' => '2026-08-22', 'rent_amount' => 10500])->assertRedirect(route('admin.rents.index'));
        $this->assertSame($this->villa160->id, $orphan->fresh()->flat_id, 'saving the form it was given does not re-point the rent');
        $this->assertEquals(10500, $orphan->fresh()->maintenance_amount);
    }

    public function test_a_deliberate_move_to_a_tenanted_unit_is_allowed_once_and_audited(): void
    {
        $orphan = $this->rentOn($this->villa160, '2026-08-22');

        $this->save($orphan, ['flat_id' => $this->villa159->id, 'bill_date' => '2026-08-22'])->assertRedirect(route('admin.rents.index'));

        $this->assertSame($this->villa159->id, $orphan->fresh()->flat_id);
        $log = ActivityLog::where('action', 'rent.unit_changed')->where('model_id', $orphan->id)->first();
        $this->assertNotNull($log);
        $this->assertSame($this->villa160->id, $log->old_values['flat_id']);
        $this->assertSame($this->villa159->id, $log->new_values['flat_id']);
    }

    public function test_a_rent_with_payments_cannot_be_moved_and_keeps_its_history(): void
    {
        $orphan = $this->rentOn($this->villa160, '2026-08-22');
        Payment::create(['society_id' => $this->society->id, 'flat_id' => $this->villa160->id, 'maintenance_bill_id' => $orphan->id, 'payment_id' => 'PAY-R-1', 'amount' => 4000,
            'payment_method' => 'cash', 'status' => 'success', 'payment_date' => '2026-08-25', 'due_date' => '2026-09-22', 'bill_type' => 'rent', 'receipt_number' => 'RCP-R-1']);

        $this->save($orphan, ['flat_id' => $this->villa159->id, 'bill_date' => '2026-08-22'])->assertSessionHasErrors('flat_id');

        $this->assertSame($this->villa160->id, $orphan->fresh()->flat_id);
        $this->assertSame(1, $orphan->payments()->count());
        $this->assertEquals(4000, $orphan->fresh()->paid_amount);
    }

    public function test_a_unit_from_another_society_can_never_be_assigned(): void
    {
        $other = $this->makeSociety('Other');
        $theirs = $this->makeFlat($other, $this->makeBuilding($other), '101');

        $this->save($this->rent, ['flat_id' => $theirs->id])->assertSessionHasErrors('flat_id');

        $this->assertSame($this->villa159->id, $this->rent->fresh()->flat_id);
    }

    public function test_a_move_to_a_unit_without_a_tenant_or_with_a_rent_that_month_is_refused(): void
    {
        $this->save($this->rent, ['flat_id' => $this->villa160->id])->assertSessionHasErrors('flat_id');   // no active tenant
        $this->assertSame($this->villa159->id, $this->rent->fresh()->flat_id);

        $tu = $this->makeUserWithRole($this->society, 'Tenant');
        Tenant::create(['society_id' => $this->society->id, 'flat_id' => $this->villa160->id, 'user_id' => $tu->id, 'name' => 'Z', 'email' => $tu->email,
            'phone' => '9000000062', 'move_in_date' => now(), 'status' => 'active', 'monthly_rent' => 1]);
        $this->rentOn($this->villa160, '2026-09-05');   // Villa 160 already has a September rent

        $this->save($this->rent, ['flat_id' => $this->villa160->id])->assertSessionHasErrors('flat_id');
        $this->assertSame($this->villa159->id, $this->rent->fresh()->flat_id);
    }
    public function test_owner_and_tenant_stay_separate_and_the_list_shows_na_rather_than_the_owner(): void
    {
        $orphan = $this->rentOn($this->villa160, '2026-08-22');

        $page = $this->actingAs($this->admin)->get(route('admin.rents.index'))->assertOk();
        $page->assertSee('N/A');
        $page->assertSee(User::find($this->tenant->user_id)->name);
        $this->assertSame($this->owner->id, $this->villa160->fresh()->owner_id);
        $this->assertNull(Tenant::where('flat_id', $this->villa160->id)->first(), 'the owner is not made a tenant');
        $this->assertSame($this->villa159->id, $this->tenant->fresh()->flat_id);
        $this->assertSame($this->villa160->id, $orphan->fresh()->flat_id);
    }

    public function test_the_rent_stays_on_its_unit_in_reports_and_the_api(): void
    {
        $this->save($this->rent, ['rent_amount' => 10400])->assertRedirect();
        $this->assertSame($this->villa159->id, $this->rent->fresh()->flat_id);

        $report = app(\App\Services\FinancialReportService::class)->maintenanceReport($this->society->id, \App\Support\ReportPeriod::monthly(2026, 9), null);
        $this->assertSame(0, (int) ($report['count'] ?? 0), 'rent is never maintenance');

        Sanctum::actingAs($this->admin);
        $json = $this->getJson('/api/v1/admin/bills')->assertOk()->getContent();
        $this->assertStringNotContainsString('"flat_id":' . $this->villa160->id, $json);
    }
}
