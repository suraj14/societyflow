<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\Society;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VillaArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A unit can have a rent bill AND a maintenance bill for the same month.
 *
 * Found while tracing "Villa 159's My Rent shows nothing": maintenance_bills was made unique on
 * (society_id, flat_id, month, year) BEFORE the bill_type column existed and the index was never widened. A rent bill
 * and a maintenance bill for the same unit and month therefore collided: with a maintenance bill for September on
 * Villa 159, the admin could not raise September rent for its tenant - the insert hit the unique index and the form
 * answered with a generic error. One bill per unit, month AND type is the intended rule, and is what the index now
 * enforces.
 */
class RentAndMaintenanceBillCoexistenceTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;
    private Flat $villa;
    private Tenant $tenant;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Alpha');
        $area = VillaArea::create(['society_id' => $this->society->id, 'name' => 'Garden', 'status' => 'active']);
        $this->villa = Flat::create(['society_id' => $this->society->id, 'villa_area_id' => $area->id, 'property_type' => 'villa', 'flat_number' => '159', 'status' => 'on_rent']);
        $user = $this->makeUserWithRole($this->society, 'Tenant');
        $this->tenant = Tenant::create(['society_id' => $this->society->id, 'flat_id' => $this->villa->id, 'user_id' => $user->id, 'name' => 'Piyus', 'email' => 'p@t.local', 'phone' => '9000000159', 'move_in_date' => '2026-09-22', 'monthly_rent' => 10000, 'status' => 'active']);
        $user->update(['tenant_id' => $this->tenant->id]);
        $this->admin = $this->makeUserWithRole($this->society, 'Admin');
    }

    private function bill(string $type, string $month = 'September', int $amount = 2700): MaintenanceBill
    {
        $d = \Carbon\Carbon::parse("1 {$month} 2026");

        return MaintenanceBill::create(['society_id' => $this->society->id, 'flat_id' => $this->villa->id, 'month' => $month, 'year' => 2026, 'bill_type' => $type,
            'bill_date' => $d->toDateString(), 'due_date' => $d->copy()->addDays(14)->toDateString(), 'maintenance_amount' => $amount, 'paid_amount' => 0]);
    }

    private function raiseRent(string $date = '2026-09-22')
    {
        return $this->actingAs($this->admin)->postJson(route('admin.rents.store'), [
            'property_type' => 'villa', 'unit_id' => $this->villa->id, 'tenant_id' => $this->tenant->id, 'rent_amount' => 10000,
            'bill_date' => $date, 'due_date' => \Carbon\Carbon::parse($date)->addDays(30)->toDateString(), 'status' => 'pending',
        ]);
    }

    public function test_the_admin_can_raise_rent_for_a_month_that_already_has_a_maintenance_bill(): void
    {
        $this->bill('maintenance', 'September');

        $this->raiseRent('2026-09-22')->assertOk()->assertJson(['success' => true]);

        $this->assertSame(1, MaintenanceBill::where('flat_id', $this->villa->id)->where('bill_type', 'rent')->count());
        $this->assertSame(1, MaintenanceBill::where('flat_id', $this->villa->id)->where('bill_type', 'maintenance')->count());
    }

    public function test_the_tenant_then_sees_the_rent_while_the_maintenance_bill_stays_separate(): void
    {
        $maintenance = $this->bill('maintenance', 'September');
        $this->raiseRent('2026-09-22')->assertOk();

        $r = $this->actingAs(User::find($this->tenant->user_id))->get(route('tenant.rent'))->assertOk();

        $this->assertCount(1, $r->viewData('rentRecords'));
        $this->assertEquals(10000.0, (float) $r->viewData('totalRent'));
        $this->assertNotContains($maintenance->id, $r->viewData('rentRecords')->pluck('id')->all(), 'maintenance is not rent');
    }

    public function test_the_same_type_for_the_same_unit_and_month_is_still_refused(): void
    {
        $this->raiseRent('2026-09-22')->assertOk();

        // the application's own check says so plainly ...
        $this->raiseRent('2026-09-25')->assertStatus(422)->assertJsonValidationErrors('bill_date');
        $this->assertStringContainsString('A rent bill already exists for this unit in September 2026', $this->raiseRent('2026-09-25')->json('errors.bill_date.0'));
        $this->assertSame(1, MaintenanceBill::where('flat_id', $this->villa->id)->where('bill_type', 'rent')->count());

        // ... and so does the database, whatever the application does
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $this->bill('rent', 'September');
    }

    public function test_two_maintenance_bills_for_one_unit_and_month_are_still_refused_by_the_database(): void
    {
        $this->bill('maintenance', 'September');

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $this->bill('maintenance', 'September');
    }

    public function test_the_index_is_per_unit_month_and_type(): void
    {
        $indexes = collect(Schema::getIndexes('maintenance_bills'))->where('unique', true);

        $perType = $indexes->first(fn ($i) => $i['columns'] === ['society_id', 'flat_id', 'month', 'year', 'bill_type']);
        $this->assertNotNull($perType, 'unique on (society, flat, month, year, bill_type)');
        $this->assertNull($indexes->first(fn ($i) => $i['columns'] === ['society_id', 'flat_id', 'month', 'year']), 'the old index that ignored the bill type is gone');
    }
}
