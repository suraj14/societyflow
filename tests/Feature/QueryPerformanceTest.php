<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Event;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\MaintenanceBill;
use App\Models\Notice;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Guards the list endpoints against N+1 queries.
 *
 * Each test seeds a small number of rows and then a larger number, and
 * asserts the query count did not grow with the row count. A relationship
 * that is lazily loaded inside a map() shows up immediately: the count
 * scales with the data instead of staying flat.
 *
 * The thresholds are deliberately generous - the point is to catch growth,
 * not to pin an exact number that churns whenever a scope is added.
 */
class QueryPerformanceTest extends TestCase
{
    use RefreshDatabase;

    /** Runs $callback and returns how many queries it executed. */
    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $callback();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_visitor_list_does_not_scale_queries_with_rows(): void
    {
        $society  = $this->makeSociety();
        $building = $this->makeBuilding($society);
        $flat     = $this->makeFlat($society, $building);
        $user     = $this->makeResidentUser($society, $flat);

        $makeVisitors = function (int $count) use ($society, $flat, $user) {
            for ($i = 0; $i < $count; $i++) {
                Visitor::create([
                    'society_id'          => $society->id,
                    'flat_id'             => $flat->id,
                    'host_user_id'        => $user->id,
                    'visitor_name'        => 'Visitor ' . uniqid(),
                    'visitor_phone'       => '98765' . random_int(10000, 99999),
                    'visitor_type'        => 'guest',
                    'expected_entry_time' => now(),
                    'approval_status'     => 'pending',
                    'entry_status'        => 'pending',
                ]);
            }
        };

        Sanctum::actingAs($user);

        $makeVisitors(2);
        $few = $this->countQueries(fn () => $this->getJson('/api/v1/visitors')->assertOk());

        $makeVisitors(20);
        $many = $this->countQueries(fn () => $this->getJson('/api/v1/visitors')->assertOk());

        $this->assertLessThanOrEqual(
            $few + 2,
            $many,
            "visitor list issued {$many} queries for 22 rows vs {$few} for 2 - relationships are lazily loaded"
        );
    }

    public function test_complaint_list_does_not_scale_queries_with_rows(): void
    {
        $society  = $this->makeSociety();
        $flat     = $this->makeFlat($society, $this->makeBuilding($society));
        $user     = $this->makeResidentUser($society, $flat);
        $category = ComplaintCategory::create([
            'society_id' => $society->id, 'name' => 'Plumbing', 'status' => 'active',
        ]);

        $makeComplaints = function (int $count) use ($society, $flat, $user, $category) {
            for ($i = 0; $i < $count; $i++) {
                Complaint::create([
                    'society_id'            => $society->id,
                    'complaint_category_id' => $category->id,
                    'flat_id'               => $flat->id,
                    'created_by'            => $user->id,
                    'title'                 => 'Issue ' . $i,
                    'description'           => 'Something',
                    'status'                => 'open',
                    'priority'              => 'medium',
                ]);
            }
        };

        Sanctum::actingAs($user);

        $makeComplaints(2);
        $few = $this->countQueries(fn () => $this->getJson('/api/v1/complaints')->assertOk());

        $makeComplaints(18);
        $many = $this->countQueries(fn () => $this->getJson('/api/v1/complaints')->assertOk());

        $this->assertLessThanOrEqual($few + 2, $many, "complaint list: {$few} -> {$many} queries");
    }

    public function test_notice_list_does_not_scale_queries_with_rows(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $user    = $this->makeResidentUser($society, $flat);

        $makeNotices = function (int $count) use ($society, $user) {
            for ($i = 0; $i < $count; $i++) {
                Notice::create([
                    'society_id'   => $society->id,
                    'created_by'   => $user->id,
                    'title'        => 'Notice ' . $i,
                    'content'      => 'Body',
                    'type'         => 'general',
                    'priority'     => 'medium',
                    'status'       => 'published',
                    'publish_date' => now()->subDay(),
                ]);
            }
        };

        Sanctum::actingAs($user);

        $makeNotices(2);
        $few = $this->countQueries(fn () => $this->getJson('/api/v1/notices')->assertOk());

        $makeNotices(18);
        $many = $this->countQueries(fn () => $this->getJson('/api/v1/notices')->assertOk());

        $this->assertLessThanOrEqual($few + 2, $many, "notice list: {$few} -> {$many} queries");
    }

    public function test_facility_booking_list_does_not_scale_queries_with_rows(): void
    {
        $society  = $this->makeSociety();
        $flat     = $this->makeFlat($society, $this->makeBuilding($society));
        $user     = $this->makeResidentUser($society, $flat);
        $facility = Facility::create([
            'society_id' => $society->id, 'name' => 'Gym', 'type' => 'gym', 'status' => 'active',
        ]);

        $makeBookings = function (int $count) use ($society, $flat, $user, $facility) {
            for ($i = 0; $i < $count; $i++) {
                FacilityBooking::create([
                    'society_id'   => $society->id,
                    'facility_id'  => $facility->id,
                    'user_id'      => $user->id,
                    'flat_id'      => $flat->id,
                    'booking_date' => now()->addDays($i + 1)->toDateString(),
                    'start_time'   => '10:00',
                    'end_time'     => '11:00',
                    'status'       => 'pending',
                ]);
            }
        };

        Sanctum::actingAs($user);

        $makeBookings(2);
        $few = $this->countQueries(fn () => $this->getJson('/api/v1/facility-bookings')->assertOk());

        $makeBookings(18);
        $many = $this->countQueries(fn () => $this->getJson('/api/v1/facility-bookings')->assertOk());

        $this->assertLessThanOrEqual($few + 2, $many, "booking list: {$few} -> {$many} queries");
    }

    public function test_event_list_does_not_scale_queries_with_rows(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $user    = $this->makeResidentUser($society, $flat);
        $user->assignRole($this->makeRole('Tenant')->name);

        $makeEvents = function (int $count) use ($society) {
            for ($i = 0; $i < $count; $i++) {
                Event::create([
                    'society_id'    => $society->id,
                    'event_name'    => 'Event ' . $i,
                    'location'      => 'Hall',
                    'description'   => 'Something',
                    'start_date'    => now()->addDays($i + 1),
                    'end_date'      => now()->addDays($i + 1)->addHour(),
                    'status'        => 'pending',
                    'is_role_based' => true,
                    'visible_roles' => ['Tenant'],
                ]);
            }
        };

        Sanctum::actingAs($user->fresh());

        $makeEvents(2);
        $few = $this->countQueries(fn () => $this->getJson('/api/v1/events')->assertOk());

        $makeEvents(18);
        $many = $this->countQueries(fn () => $this->getJson('/api/v1/events')->assertOk());

        $this->assertLessThanOrEqual($few + 2, $many, "event list: {$few} -> {$many} queries");
    }

    public function test_bill_list_does_not_scale_queries_with_rows(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $user    = $this->makeResidentUser($society, $flat);

        // maintenance_bills is unique on (society, flat, month, year), so the
        // month label has to keep advancing across both batches.
        $monthCounter = 0;

        $makeBills = function (int $count) use ($society, $flat, &$monthCounter) {
            for ($i = 0; $i < $count; $i++) {
                MaintenanceBill::create([
                    'society_id'     => $society->id,
                    'flat_id'        => $flat->id,
                    'month'          => 'M' . $monthCounter++,
                    'year'           => 2026,
                    'bill_date'      => now()->toDateString(),
                    'due_date'       => now()->addDays(10)->toDateString(),
                    'total_amount'   => 1000,
                    'balance_amount' => 1000,
                    'status'         => 'pending',
                ]);
            }
        };

        Sanctum::actingAs($user);

        $makeBills(2);
        $few = $this->countQueries(fn () => $this->getJson('/api/v1/bills')->assertOk());

        $makeBills(10);
        $many = $this->countQueries(fn () => $this->getJson('/api/v1/bills')->assertOk());

        $this->assertLessThanOrEqual($few + 2, $many, "bill list: {$few} -> {$many} queries");
    }
}
