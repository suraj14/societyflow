<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Gate;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\ServiceRequest;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Society;
use App\Models\User;
use App\Support\SocietyOverview;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The Admin on the phone. A Society Admin used to get the resident's screen
 * (a home with their own bills and visitors, all zero) and nothing to act on.
 * These endpoints are society-wide and use the web's own numbers and
 * workflows; each one starts from the admin's own society_id.
 */
class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Royal Eden');
        $this->admin = $this->makeUserWithRole($this->society, 'Admin');
    }

    private function resident(Society $society = null, string $name = 'Asha'): User
    {
        $society ??= $this->society;
        $flat = $this->makeFlat($society, $this->makeBuilding($society, 'B' . uniqid()), (string) random_int(100, 999));
        $user = $this->makeResidentUser($society, $flat, ['name' => $name]);
        $this->makeRole('Tenant');
        $user->assignRole('Tenant');

        return $user;
    }

    private function booking(Society $society, User $user, string $status = 'pending'): FacilityBooking
    {
        $facility = Facility::firstOrCreate(['society_id' => $society->id, 'name' => 'Clubhouse'], ['type' => 'clubhouse', 'booking_charge' => 500, 'status' => 'active']);

        return FacilityBooking::create([
            'society_id' => $society->id, 'facility_id' => $facility->id, 'user_id' => $user->id, 'flat_id' => null,
            'booking_date' => '2026-10-12', 'start_time' => '10:00', 'end_time' => '12:00', 'booking_amount' => 500, 'status' => $status,
        ]);
    }

    private function complaint(Society $society, User $user, string $status = 'open'): Complaint
    {
        return Complaint::create(['society_id' => $society->id, 'created_by' => $user->id, 'title' => 'Lift stuck', 'description' => 'Tower B', 'priority' => 'high', 'status' => $status]);
    }

    // ── who may use it ──────────────────────────────────────────────────────

    public function test_every_admin_route_refuses_everyone_but_a_society_admin(): void
    {
        $routes = array_values(array_filter(
            RouteFacade::getRoutes()->getRoutes(),
            fn (Route $r) => str_starts_with($r->uri(), 'api/v1/admin/')
        ));
        $this->assertGreaterThanOrEqual(8, count($routes), 'the walk must cover the admin API');

        $others = [
            $this->resident(),
            $this->makeUserWithRole($this->society, 'Guard'),
            $this->makeUserWithRole($this->society, 'Staff'),
            $this->makeUserWithRole($this->society, 'Accountant'),
            $this->makeUserWithRole($this->society, 'Owner'),
        ];

        $leaks = [];
        foreach ($others as $user) {
            Sanctum::actingAs($user);
            foreach ($routes as $route) {
                foreach ($route->methods() as $method) {
                    if ($method === 'HEAD') {
                        continue;
                    }
                    $url = '/' . preg_replace('/\{[^}]+\}/', '1', $route->uri());
                    $status = $this->json($method, $url, ['status' => 'approved', 'provider_id' => 1])->getStatusCode();
                    if ($status !== 403) {
                        $leaks[] = $user->getRoleNames()->first() . " {$method} {$url} -> {$status}";
                    }
                }
            }
        }
        $this->assertSame([], $leaks);
    }

    public function test_a_super_admin_without_a_society_has_no_society_to_manage(): void
    {
        $super = $this->makeUserWithRole($this->society, 'Super Admin', ['society_id' => null]);
        Sanctum::actingAs($super);
        $this->getJson('/api/v1/admin/dashboard')->assertForbidden();
    }

    // ── dashboard ───────────────────────────────────────────────────────────

    public function test_the_dashboard_shows_the_same_numbers_as_the_web_dashboard(): void
    {
        $this->resident();
        $this->resident();
        Sanctum::actingAs($this->admin);

        $r = $this->getJson('/api/v1/admin/dashboard')->assertOk();
        $web = SocietyOverview::kpis($this->society->id);

        $this->assertSame('Royal Eden', $r->json('data.society.name'));
        foreach (['visitors_today', 'open_tickets', 'pending_bills_count' => 'pending_bills', 'total_units' => 'units'] as $k => $v) {
            $webKey = is_int($k) ? $v : $k;
            $apiKey = $v;
            $this->assertSame($web[$webKey], $r->json("data.kpis.{$apiKey}"), "{$apiKey} must equal the web's {$webKey}");
        }
        $this->assertSame($web['total_residents'], $r->json('data.kpis.residents'));
        $this->assertSame((float) $web['pending_dues'], (float) $r->json('data.kpis.pending_dues'));
    }

    public function test_attention_lists_only_what_is_waiting_in_this_society(): void
    {
        $other = $this->makeSociety('Other');
        $mine = $this->resident();
        $theirs = $this->resident($other, 'Stranger');
        $this->booking($this->society, $mine);
        $this->booking($this->society, $mine, 'approved'); // not waiting
        $this->complaint($this->society, $mine);
        $this->complaint($this->society, $mine, 'resolved'); // not waiting
        // Another society's waiting items must not count.
        $this->booking($other, $theirs);
        $this->complaint($other, $theirs);
        $this->complaint($other, $theirs);

        Sanctum::actingAs($this->admin);
        $attention = collect($this->getJson('/api/v1/admin/dashboard')->assertOk()->json('data.attention'))->keyBy('key');

        $this->assertSame(1, $attention['bookings']['count']);
        $this->assertSame(1, $attention['complaints']['count']);
        $this->assertArrayNotHasKey('service_requests', $attention->all(), 'zero rows are left out');
    }

    public function test_a_quiet_society_has_an_empty_attention_list_not_a_row_of_zeros(): void
    {
        Sanctum::actingAs($this->admin);
        $this->assertSame([], $this->getJson('/api/v1/admin/dashboard')->assertOk()->json('data.attention'));
    }

    public function test_guards_on_duty_are_the_ones_who_started_and_have_not_ended(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 04:30:00', 'UTC')); // 10:00 IST
        try {
            $gate = Gate::create(['society_id' => $this->society->id, 'name' => 'Main Gate', 'type' => 'main', 'is_active' => true]);
            $shift = Shift::create(['society_id' => $this->society->id, 'name' => 'Day', 'start_time' => '09:00', 'end_time' => '18:00', 'grace_period_minutes' => 15, 'is_active' => true]);
            $on = $this->makeUserWithRole($this->society, 'Guard', ['name' => 'On Duty Guard']);
            $off = $this->makeUserWithRole($this->society, 'Guard', ['name' => 'Not Started Guard']);
            foreach ([$on, $off] as $g) {
                ShiftAssignment::create(['society_id' => $this->society->id, 'shift_id' => $shift->id, 'gate_id' => $gate->id, 'guard_user_id' => $g->id, 'date' => '2026-10-10', 'status' => 'scheduled']);
            }
            // Started through the real service, so the instant is stored the way production stores it.
            (new \App\Services\GuardShiftService())->startShift(
                ShiftAssignment::where('guard_user_id', $on->id)->firstOrFail(),
                Carbon::parse('2026-10-10 09:05:00', 'Asia/Kolkata')
            );

            Sanctum::actingAs($this->admin);
            $r = $this->getJson('/api/v1/admin/dashboard')->assertOk();

            $this->assertCount(1, $r->json('data.guards_on_duty'));
            $this->assertSame('On Duty Guard', $r->json('data.guards_on_duty.0.guard'));
            $this->assertSame('Main Gate', $r->json('data.guards_on_duty.0.gate'));
            $this->assertSame('09:05', $r->json('data.guards_on_duty.0.since'));
        } finally {
            Carbon::setTestNow();
        }
    }

    // ── facility bookings ───────────────────────────────────────────────────

    public function test_the_admin_sees_pending_bookings_of_their_own_society_first(): void
    {
        $other = $this->makeSociety('Other');
        $mine = $this->resident();
        $this->booking($this->society, $mine);
        $this->booking($other, $this->resident($other, 'Stranger'));

        Sanctum::actingAs($this->admin);
        $r = $this->getJson('/api/v1/admin/facility-bookings')->assertOk();

        $this->assertCount(1, $r->json('data'));
        $this->assertSame('Asha', $r->json('data.0.resident.name'));
        $this->assertSame('10:00', $r->json('data.0.start_time'));
        $this->assertSame(['approved', 'rejected', 'cancelled'], $r->json('data.0.actions'));
    }

    public function test_approving_a_booking_tells_the_resident(): void
    {
        $resident = $this->resident();
        $booking = $this->booking($this->society, $resident);

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/facility-bookings/{$booking->id}/status", ['status' => 'approved', 'admin_notes' => 'See you there'])
            ->assertOk()->assertJsonPath('data.status', 'approved');

        $booking->refresh();
        $this->assertSame('approved', $booking->status);
        $this->assertNotNull($booking->approved_at);
        $this->assertSame('See you there', $booking->admin_notes);
        $this->assertContains('Booking Approved', $resident->fresh()->notifications()->pluck('data')->map(fn ($d) => $d['title'])->all());
    }

    public function test_a_booking_cannot_jump_to_an_impossible_status(): void
    {
        $booking = $this->booking($this->society, $this->resident(), 'pending');

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/facility-bookings/{$booking->id}/status", ['status' => 'completed'])->assertStatus(422);
        $this->assertSame('pending', $booking->fresh()->status);

        $this->postJson("/api/v1/admin/facility-bookings/{$booking->id}/status", ['status' => 'rejected'])->assertOk();
        $this->postJson("/api/v1/admin/facility-bookings/{$booking->id}/status", ['status' => 'approved'])->assertStatus(422);
    }

    public function test_another_societys_booking_is_a_404(): void
    {
        $other = $this->makeSociety('Other');
        $theirs = $this->booking($other, $this->resident($other, 'Stranger'));

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/facility-bookings/{$theirs->id}/status", ['status' => 'approved'])->assertNotFound();
        $this->assertSame('pending', $theirs->fresh()->status);
    }

    // ── complaints ──────────────────────────────────────────────────────────

    public function test_the_admin_lists_unresolved_complaints_of_their_society(): void
    {
        $other = $this->makeSociety('Other');
        $mine = $this->resident();
        $this->complaint($this->society, $mine);
        $this->complaint($this->society, $mine, 'closed');
        $this->complaint($other, $this->resident($other, 'Stranger'));

        Sanctum::actingAs($this->admin);
        $r = $this->getJson('/api/v1/admin/complaints')->assertOk();

        $this->assertCount(1, $r->json('data'));
        $this->assertSame('Lift stuck', $r->json('data.0.title'));
        $this->assertCount(2, $this->getJson('/api/v1/admin/complaints?status=all')->json('data'));
    }

    public function test_resolving_a_complaint_records_it_and_tells_the_resident(): void
    {
        $resident = $this->resident();
        $complaint = $this->complaint($this->society, $resident);

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/complaints/{$complaint->id}/status", ['status' => 'resolved', 'message' => 'Technician fixed it'])
            ->assertOk()->assertJsonPath('data.status', 'resolved');

        $complaint->refresh();
        $this->assertSame('resolved', $complaint->status);
        $this->assertNotNull($complaint->resolved_at);
        $this->assertDatabaseHas('complaint_updates', ['complaint_id' => $complaint->id, 'message' => 'Technician fixed it', 'user_id' => $this->admin->id]);
        $this->assertContains('Complaint updated', $resident->fresh()->notifications()->pluck('data')->map(fn ($d) => $d['title'])->all());
    }

    public function test_setting_the_status_it_already_has_is_refused(): void
    {
        $complaint = $this->complaint($this->society, $this->resident(), 'in_progress');

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/complaints/{$complaint->id}/status", ['status' => 'in_progress'])->assertStatus(422);
    }

    public function test_another_societys_complaint_is_a_404(): void
    {
        $other = $this->makeSociety('Other');
        $theirs = $this->complaint($other, $this->resident($other, 'Stranger'));

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/complaints/{$theirs->id}/status", ['status' => 'closed'])->assertNotFound();
        $this->assertSame('open', $theirs->fresh()->status);
    }

    // ── service requests ────────────────────────────────────────────────────

    private function serviceRequest(Society $society, User $user, Service $service, string $status = 'pending'): ServiceRequest
    {
        return ServiceRequest::create(['society_id' => $society->id, 'user_id' => $user->id, 'service_id' => $service->id, 'description' => 'Leaking tap', 'status' => $status]);
    }

    private function provider(Society $society, Service $service, string $name = 'Ramesh', string $status = 'active'): ServiceProvider
    {
        return ServiceProvider::create(['society_id' => $society->id, 'service_id' => $service->id, 'name' => $name, 'contact_number' => '9000000001', 'status' => $status]);
    }

    public function test_a_request_is_assigned_started_and_completed_from_the_phone(): void
    {
        $service = Service::create(['name' => 'Plumbing', 'category' => 'maintenance', 'is_enabled' => true, 'sort_order' => 1]);
        $resident = $this->resident();
        $provider = $this->provider($this->society, $service);
        $request = $this->serviceRequest($this->society, $resident, $service);

        Sanctum::actingAs($this->admin);
        $list = $this->getJson('/api/v1/admin/service-requests')->assertOk();
        $this->assertSame([$provider->id], array_column($list->json('data.0.providers'), 'id'), 'only providers of this service in this society are offered');

        $this->postJson("/api/v1/admin/service-requests/{$request->id}/assign", ['provider_id' => $provider->id])
            ->assertOk()->assertJsonPath('data.status', 'assigned')->assertJsonPath('data.provider.name', 'Ramesh');
        $this->postJson("/api/v1/admin/service-requests/{$request->id}/status", ['status' => 'in_progress'])->assertOk()->assertJsonPath('data.status', 'in_progress');
        $this->postJson("/api/v1/admin/service-requests/{$request->id}/status", ['status' => 'completed'])->assertOk()->assertJsonPath('data.status', 'completed');

        $request->refresh();
        $this->assertNotNull($request->started_at);
        $this->assertNotNull($request->completed_at);
        $this->assertContains('Service request completed', $resident->fresh()->notifications()->pluck('data')->map(fn ($d) => $d['title'])->all());
    }

    public function test_a_provider_of_another_service_or_society_or_inactive_cannot_be_assigned(): void
    {
        $plumbing = Service::create(['name' => 'Plumbing', 'category' => 'maintenance', 'is_enabled' => true, 'sort_order' => 1]);
        $cook = Service::firstOrCreate(['name' => 'Cook', 'category' => 'daily'], ['is_enabled' => true, 'sort_order' => 2]);
        $other = $this->makeSociety('Other');
        $request = $this->serviceRequest($this->society, $this->resident(), $plumbing);

        Sanctum::actingAs($this->admin);
        foreach ([$this->provider($this->society, $cook), $this->provider($other, $plumbing), $this->provider($this->society, $plumbing, 'Gone', 'inactive')] as $bad) {
            $this->postJson("/api/v1/admin/service-requests/{$request->id}/assign", ['provider_id' => $bad->id])->assertStatus(422);
        }
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_a_request_cannot_start_before_it_is_assigned_or_be_reassigned_once_done(): void
    {
        $service = Service::create(['name' => 'Plumbing', 'category' => 'maintenance', 'is_enabled' => true, 'sort_order' => 1]);
        $provider = $this->provider($this->society, $service);
        $pending = $this->serviceRequest($this->society, $this->resident(), $service);
        $done = $this->serviceRequest($this->society, $this->resident(), $service, 'completed');

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/service-requests/{$pending->id}/status", ['status' => 'in_progress'])->assertStatus(422);
        $this->postJson("/api/v1/admin/service-requests/{$done->id}/assign", ['provider_id' => $provider->id])->assertStatus(422);
        $this->assertSame('completed', $done->fresh()->status, 'a finished request must not slip back to assigned');
    }

    public function test_another_societys_request_is_a_404(): void
    {
        $service = Service::create(['name' => 'Plumbing', 'category' => 'maintenance', 'is_enabled' => true, 'sort_order' => 1]);
        $other = $this->makeSociety('Other');
        $theirs = $this->serviceRequest($other, $this->resident($other, 'Stranger'), $service);
        $provider = $this->provider($other, $service);

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/service-requests/{$theirs->id}/assign", ['provider_id' => $provider->id])->assertNotFound();
        $this->assertCount(0, $this->getJson('/api/v1/admin/service-requests')->json('data'));
    }
}
