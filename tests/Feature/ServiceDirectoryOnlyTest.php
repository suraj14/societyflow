<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Product decision (2026-10-13): Service Requests is not a workflow. Residents contact providers directly (Call /
 * WhatsApp from the Services directory). This pins what was removed from the user-facing web and API surface and what
 * is deliberately kept: provider records, historical requests, complaints, facility bookings, and the old endpoints
 * (older installed apps still call them; they are not advertised anywhere).
 */
class ServiceDirectoryOnlyTest extends TestCase
{
    use RefreshDatabase;

    private $society;
    private $admin;
    private $resident;
    private ServiceProvider $electrician;
    private ServiceProvider $nophone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Alpha');
        $this->admin = $this->makeUserWithRole($this->society, 'Admin');
        $flat = $this->makeFlat($this->society, $this->makeBuilding($this->society));
        $this->resident = $this->makeResidentUser($this->society, $flat);

        $service = Service::firstOrCreate(['name' => 'Electrician', 'category' => 'maintenance'], ['is_enabled' => true, 'sort_order' => 1]);
        $this->electrician = ServiceProvider::create(['society_id' => $this->society->id, 'service_id' => $service->id, 'name' => 'Ramesh Electric', 'contact_number' => '+91 98480-12345',
            'availability' => 'available', 'price' => 350, 'price_type' => 'per_visit', 'status' => 'active']);
        $this->nophone = ServiceProvider::create(['society_id' => $this->society->id, 'service_id' => $service->id, 'name' => 'No Phone Plumber', 'contact_number' => '',
            'availability' => 'available', 'status' => 'active']);
        ServiceProvider::create(['society_id' => $this->makeSociety('Beta')->id, 'service_id' => $service->id, 'name' => 'Other Society Plumber', 'contact_number' => '9111111111', 'availability' => 'available', 'status' => 'active']);
    }

    public function test_the_web_services_page_has_no_requests_entry_and_offers_call_and_whatsapp(): void
    {
        foreach ([$this->admin, $this->resident] as $who) {
            $html = $this->actingAs($who)->get(route('services.index'))->assertOk()->getContent();

            $this->assertStringNotContainsString('My Requests', $html);
            $this->assertStringNotContainsString('/service-requests', $html, 'no link to the removed workflow');
            $this->assertStringContainsString('Ramesh Electric', $html);
            $this->assertStringContainsString('tel:+91 98480-12345', $html);
            $this->assertStringContainsString('https://wa.me/919848012345', $html);
            $this->assertStringNotContainsString('Other Society Plumber', $html, 'society scope');
        }
    }

    public function test_no_dashboard_or_menu_points_at_service_requests(): void
    {
        ServiceRequest::create(['society_id' => $this->society->id, 'user_id' => $this->resident->id, 'service_id' => $this->electrician->service_id, 'description' => 'old request', 'status' => 'pending']);

        foreach ([$this->admin, $this->resident] as $who) {
            $html = $this->actingAs($who)->get(route('dashboard'))->assertOk()->getContent();
            $this->assertStringNotContainsString('service-requests', $html);
            $this->assertStringNotContainsString('Service requests', $html);
        }
    }

    public function test_the_mobile_dashboard_never_lists_service_requests_for_attention(): void
    {
        ServiceRequest::create(['society_id' => $this->society->id, 'user_id' => $this->resident->id, 'service_id' => $this->electrician->service_id, 'description' => 'x', 'status' => 'pending']);
        Sanctum::actingAs($this->admin);

        $keys = array_column($this->getJson('/api/v1/admin/dashboard')->assertOk()->json('data.attention'), 'key');

        $this->assertNotContains('service_requests', $keys);
    }

    public function test_the_directory_api_returns_what_is_stored_and_nothing_invented(): void
    {
        Sanctum::actingAs($this->resident);
        $rows = collect($this->getJson('/api/v1/service-providers')->assertOk()->json('data'))->keyBy('name');

        $this->assertCount(2, $rows, 'this society\'s active providers only');
        $this->assertSame('+91 98480-12345', $rows['Ramesh Electric']['phone']);
        $this->assertEquals(350.0, $rows['Ramesh Electric']['charges']);
        $this->assertSame('per_visit', $rows['Ramesh Electric']['price_type']);
        $this->assertSame('Electrician', $rows['Ramesh Electric']['service_name']);
        $this->assertSame('available', $rows['Ramesh Electric']['availability']);
        $this->assertEquals(0.0, $rows['No Phone Plumber']['charges'], 'an unset price is 0, which the app shows as "Price not specified"');
        $this->assertTrue(in_array($rows['No Phone Plumber']['phone'], ['', null], true));
    }

    public function test_an_inactive_provider_is_not_listed(): void
    {
        $this->nophone->update(['status' => 'inactive']);
        Sanctum::actingAs($this->resident);

        $this->assertSame(['Ramesh Electric'], array_column($this->getJson('/api/v1/service-providers')->json('data'), 'name'));
    }

    public function test_historical_requests_and_other_workflows_are_untouched(): void
    {
        $request = ServiceRequest::create(['society_id' => $this->society->id, 'user_id' => $this->resident->id, 'service_id' => $this->electrician->service_id, 'description' => 'history', 'status' => 'completed']);
        $complaint = Complaint::create(['society_id' => $this->society->id, 'created_by' => $this->resident->id, 'title' => 'Leak', 'description' => 'x', 'status' => 'open', 'complaint_number' => 'CMP-KEEP-1']);

        $this->assertNotNull($request->fresh(), 'the historical row is kept');
        Sanctum::actingAs($this->admin);
        $this->assertSame(1, $this->getJson('/api/v1/admin/complaints')->assertOk()->json('meta.total'), 'complaints are a separate workflow and still work');
        $this->actingAs($this->admin)->get(route('complaints.index'))->assertOk();
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'manage_facilities', 'guard_name' => 'web']);
        $this->admin->givePermissionTo('manage_facilities');
        $this->actingAs($this->admin)->get(route('facility-bookings.index'))->assertOk();
        $this->assertNotNull($complaint->fresh());
    }

    public function test_the_old_endpoints_still_answer_so_installed_apps_do_not_break(): void
    {
        Sanctum::actingAs($this->resident);
        $this->getJson('/api/v1/service-requests')->assertOk();
        $this->actingAs($this->admin)->get(route('service-requests.index'))->assertOk();
    }
}
