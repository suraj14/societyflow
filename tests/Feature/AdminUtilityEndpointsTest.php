<?php

namespace Tests\Feature;

use App\Models\MaintenanceBill;
use App\Models\Society;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Small admin endpoints that no test referenced: rent-record validation, the maintenance report PDF, language
 * and cache settings.
 */
class AdminUtilityEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private Society $a;
    private Society $b;
    private User $adminA;
    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = $this->makeSociety('Alpha');
        $this->b = $this->makeSociety('Beta');
        $this->adminA = $this->makeUserWithRole($this->a, 'Admin');
        $this->super = $this->makeUserWithRole($this->a, 'Super Admin', ['society_id' => null]);
    }

    private function rent(Society $s): MaintenanceBill
    {
        $flat = $this->makeFlat($s, $this->makeBuilding($s, 'B' . uniqid()), (string) random_int(100, 999));

        return MaintenanceBill::create(['society_id' => $s->id, 'flat_id' => $flat->id, 'month' => 'October', 'year' => 2026, 'bill_type' => 'rent', 'bill_date' => '2026-10-05', 'due_date' => '2026-10-20', 'maintenance_amount' => 5000]);
    }

    // ----------------------------------------------------------- rent validation

    public function test_rent_validation_reports_an_existing_rent_for_the_month_in_your_own_society(): void
    {
        $bill = $this->rent($this->a);

        $this->actingAs($this->adminA)->postJson(route('admin.api.rent.validate'), ['unit_id' => $bill->flat_id, 'bill_date' => '2026-10-20'])
            ->assertOk()->assertJson(['valid' => false]);
        $this->actingAs($this->adminA)->postJson(route('admin.api.rent.validate'), ['unit_id' => $bill->flat_id, 'bill_date' => '2026-11-02'])
            ->assertOk()->assertJson(['valid' => true]);
    }

    public function test_rent_validation_is_no_oracle_for_another_societys_billing(): void
    {
        $theirs = $this->rent($this->b);

        $this->actingAs($this->adminA)->postJson(route('admin.api.rent.validate'), ['unit_id' => $theirs->flat_id, 'bill_date' => '2026-10-20'])
            ->assertOk()->assertJson(['valid' => true]);   // nothing is revealed about Beta's bill
    }

    public function test_rent_validation_handles_missing_and_malformed_input(): void
    {
        $this->actingAs($this->adminA)->postJson(route('admin.api.rent.validate'), [])->assertOk()->assertJson(['valid' => false]);
        $this->actingAs($this->adminA)->postJson(route('admin.api.rent.validate'), ['unit_id' => 1, 'bill_date' => 'not a date'])->assertOk()->assertJson(['valid' => false]);
    }

    // ----------------------------------------------------------- settings

    public function test_only_a_super_admin_can_change_the_language_settings(): void
    {
        $body = ['default_language' => 'hi', 'enabled_languages' => ['en', 'hi']];

        $this->actingAs($this->adminA)->post(route('settings.language.update'), $body)->assertStatus(403);
        $this->assertNull(SystemSetting::get('language', 'default_language', null, null));

        $this->actingAs($this->super)->post(route('settings.language.update'), $body)->assertSessionHas('success');
        $this->assertSame('hi', SystemSetting::get('language', 'default_language', null, null));
        $this->assertSame(['en', 'hi'], SystemSetting::get('language', 'enabled_languages', null, null));

        $this->actingAs($this->super)->post(route('settings.language.update'), [])->assertSessionHasErrors('default_language');
    }

    public function test_clearing_the_settings_cache_is_for_admins_only_and_loses_no_setting(): void
    {
        SystemSetting::set('app', 'app_name', 'Alpha Heights', 'string', $this->a->id);
        $tenant = $this->makeUserWithRole($this->a, 'Tenant');

        $this->actingAs($tenant)->post(route('settings.clear-cache'))->assertStatus(403);
        $this->actingAs($this->adminA)->post(route('settings.clear-cache'))->assertSessionHas('success');

        $this->assertSame('Alpha Heights', SystemSetting::get('app', 'app_name', null, $this->a->id), 'clearing the cache never deletes a stored setting');
    }

    // ----------------------------------------------------------- report download

    public function test_the_maintenance_report_downloads_as_a_pdf_for_an_admin(): void
    {
        $this->rent($this->a);

        $response = $this->actingAs($this->adminA)->post(route('reports.download-maintenance'), ['type' => 'monthly', 'month' => 10, 'year' => 2026])->assertOk();

        $this->assertStringContainsString('pdf', (string) $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_a_residents_maintenance_report_covers_only_their_own_unit(): void
    {
        $building = $this->makeBuilding($this->a, 'Tower');
        $mine = $this->makeFlat($this->a, $building, 'F-7731');
        $neighbours = $this->makeFlat($this->a, $building, 'F-7742');
        foreach ([$mine, $neighbours] as $flat) {
            MaintenanceBill::create(['society_id' => $this->a->id, 'flat_id' => $flat->id, 'month' => 'October', 'year' => 2026, 'bill_type' => 'maintenance', 'bill_date' => '2026-10-05', 'due_date' => '2026-10-20', 'maintenance_amount' => 2500]);
        }
        $tenant = $this->makeUserWithRole($this->a, 'Tenant');
        $record = \App\Models\Tenant::create(['society_id' => $this->a->id, 'flat_id' => $mine->id, 'user_id' => $tenant->id, 'name' => 'T', 'email' => 't@t.local', 'phone' => '0000000001', 'move_in_date' => '2026-09-01', 'status' => 'active']);
        $tenant->update(['tenant_id' => $record->id]);

        $period = ['type' => 'monthly', 'month' => 10, 'year' => 2026];

        $residents = $this->actingAs($tenant)->get(route('reports.maintenance', $period))->assertOk()->viewData('data');
        $office = $this->actingAs($this->adminA)->get(route('reports.maintenance', $period))->assertOk()->viewData('data');

        $this->assertEquals(2500, $residents['billed'], "only the resident's own unit is billed in their report");
        $this->assertEquals(5000, $office['billed'], 'the office sees the whole society');
    }

    public function test_an_invalid_report_period_goes_back_with_errors_not_a_500(): void
    {
        $response = $this->actingAs($this->adminA)->post(route('reports.download-maintenance'), ['type' => 'monthly', 'month' => 13, 'year' => 1800]);

        $this->assertContains($response->getStatusCode(), [302, 422]);
    }
}
