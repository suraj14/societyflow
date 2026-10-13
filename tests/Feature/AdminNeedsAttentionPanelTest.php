<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\MaintenanceBill;
use App\Models\ServiceRequest;
use App\Models\Service;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Part 3 of the "final product excellence" brief: an action-first "Needs
 * Attention" panel above the admin dashboard's existing 8 KPI cards
 * (`DashboardController::needsAttention()`), not a decorative stat. Each
 * count must reflect real unactioned rows and link to the already-filtered
 * list - a count with no matching rows behind its link would be worse than
 * no panel at all.
 */
class AdminNeedsAttentionPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_brand_new_society_shows_the_empty_state_not_a_row_of_zeros(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $html = $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Nothing needs your attention', $html);
    }

    public function test_pending_items_across_every_module_are_counted_and_linked(): void
    {
        $society  = $this->makeSociety();
        $admin    = $this->makeUserWithRole($society, 'Admin');
        $building = $this->makeBuilding($society);
        $flat     = $this->makeFlat($society, $building);
        $resident = $this->makeResidentUser($society, $flat);

        Visitor::create([
            'society_id' => $society->id, 'flat_id' => $flat->id, 'host_user_id' => $resident->id,
            'visitor_name' => 'Pending Visitor', 'visitor_phone' => '9123456780', 'visitor_type' => 'guest',
            'expected_entry_time' => now(), 'approval_status' => 'pending', 'entry_status' => 'pending',
        ]);

        Complaint::create([
            'society_id' => $society->id, 'created_by' => $resident->id, 'title' => 'Leak',
            'description' => 'Leaking pipe', 'status' => 'open', 'complaint_number' => 'CMP-TEST-1',
        ]);

        $service = Service::create(['name' => 'Cleaning ' . uniqid(), 'category' => 'daily', 'is_enabled' => true]);
        ServiceRequest::create([
            'society_id' => $society->id, 'user_id' => $resident->id, 'service_id' => $service->id,
            'description' => 'Need cleaning', 'status' => 'pending',
        ]);

        // status/total/balance are all derived by MaintenanceBill's own
        // boot hook (calculateTotalAmount() -> updateStatus()) from
        // maintenance_amount + a past due_date - setting total_amount/
        // status directly is overwritten the moment the row saves.
        MaintenanceBill::create([
            'society_id' => $society->id, 'flat_id' => $flat->id, 'bill_number' => 'MB-TEST-1',
            'month' => 'January', 'year' => 2026, 'bill_date' => now()->subMonths(2)->toDateString(),
            'due_date' => now()->subMonth()->toDateString(), 'maintenance_amount' => 1000,
        ]);

        $facility = Facility::create(['society_id' => $society->id, 'name' => 'Hall', 'type' => 'hall']);
        FacilityBooking::create([
            'society_id' => $society->id, 'facility_id' => $facility->id, 'user_id' => $resident->id,
            'flat_id' => $flat->id, 'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00', 'end_time' => '12:00', 'status' => 'pending',
        ]);

        $html = $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Visitors waiting for approval', $html);
        $this->assertStringContainsString('New complaints', $html);
        // Product decision (2026-10-13): Service Requests is no longer a workflow. A pending historical row exists above and must
        // not surface as something for the admin to act on.
        $this->assertStringNotContainsString('Service requests to assign', $html);
        $this->assertStringContainsString('Overdue bills', $html);
        $this->assertStringContainsString('Facility bookings to review', $html);
        $this->assertStringNotContainsString('Nothing needs your attention', $html);
    }

    public function test_another_societys_pending_items_never_appear_in_the_count(): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        $adminA   = $this->makeUserWithRole($societyA, 'Admin');
        $buildingB = $this->makeBuilding($societyB);
        $flatB    = $this->makeFlat($societyB, $buildingB);
        $residentB = $this->makeResidentUser($societyB, $flatB);

        Complaint::create([
            'society_id' => $societyB->id, 'created_by' => $residentB->id, 'title' => 'Other society issue',
            'description' => 'desc', 'status' => 'open', 'complaint_number' => 'CMP-TEST-2',
        ]);

        $html = $this->actingAs($adminA)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Nothing needs your attention', $html);
    }
}
