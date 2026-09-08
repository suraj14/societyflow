<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Event;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\MaintenanceBill;
use App\Models\Notice;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Society;
use App\Models\UtilityBill;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Renders every major screen with realistic data in the database.
 *
 * This exists because the utility-bills listing fataled on any page that
 * actually had a bill on it - the view called ->simplePaginate() on an
 * already-paginated result - and nothing caught it. Empty-database checks
 * would not have: the bug only appears once a row exists.
 *
 * The assertion is deliberately weak (not a 5xx) because the point is
 * coverage of the render path, not of the content.
 */
class WebSmokeTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;

    protected function setUp(): void
    {
        parent::setUp();

        $this->society = $this->makeSociety('Smoke Society');
        $this->seedContent();
    }

    private function seedContent(): void
    {
        $building = $this->makeBuilding($this->society);
        $flat     = $this->makeFlat($this->society, $building, '101');
        $resident = $this->makeResidentUser($this->society, $flat);

        $owner = Owner::create([
            'society_id'    => $this->society->id,
            'name'          => 'Smoke Owner',
            'email'         => 'smokeowner@test.local',
            'status'        => 'active',
            'property_type' => 'apartment',
            'building_id'   => $building->id,
            'flat_no'       => '101',
        ]);
        $flat->update(['owner_id' => $owner->id]);

        $category = ComplaintCategory::create([
            'society_id' => $this->society->id, 'name' => 'Plumbing', 'status' => 'active',
        ]);

        Complaint::create([
            'society_id' => $this->society->id, 'complaint_category_id' => $category->id,
            'flat_id' => $flat->id, 'created_by' => $resident->id,
            'title' => 'Leaky tap', 'description' => 'Dripping', 'status' => 'open', 'priority' => 'medium',
        ]);

        $facility = Facility::create([
            'society_id' => $this->society->id, 'name' => 'Gym', 'type' => 'gym',
            'booking_charge' => 100, 'capacity' => 20, 'status' => 'active',
        ]);

        FacilityBooking::create([
            'society_id' => $this->society->id, 'facility_id' => $facility->id,
            'user_id' => $resident->id, 'flat_id' => $flat->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00', 'end_time' => '11:00', 'status' => 'pending',
        ]);

        Visitor::create([
            'society_id' => $this->society->id, 'flat_id' => $flat->id,
            'host_user_id' => $resident->id, 'visitor_name' => 'Guest',
            'visitor_phone' => '9876500001', 'visitor_type' => 'guest',
            'expected_entry_time' => now(), 'approval_status' => 'pending', 'entry_status' => 'pending',
        ]);

        Notice::create([
            'society_id' => $this->society->id, 'created_by' => $resident->id,
            'title' => 'Water supply', 'content' => 'Off at noon', 'type' => 'general',
            'priority' => 'medium', 'status' => 'published', 'publish_date' => now()->subDay(),
        ]);

        Event::create([
            'society_id' => $this->society->id, 'event_name' => 'Meetup', 'location' => 'Lawn',
            'description' => 'Come along', 'start_date' => now()->addWeek(),
            'end_date' => now()->addWeek()->addHour(), 'status' => 'pending',
            'is_role_based' => true, 'visible_roles' => ['Admin', 'Tenant', 'Apartment Owner'],
        ]);

        MaintenanceBill::create([
            'society_id' => $this->society->id, 'flat_id' => $flat->id,
            'month' => 'January', 'year' => 2026,
            'bill_date' => now()->toDateString(), 'due_date' => now()->addDays(10)->toDateString(),
            'total_amount' => 2000, 'balance_amount' => 2000, 'status' => 'pending',
        ]);

        Payment::create([
            'society_id' => $this->society->id, 'user_id' => $resident->id, 'flat_id' => $flat->id,
            'payment_id' => 'PAY-SMOKE', 'amount' => 2000, 'status' => 'success',
            'payment_date' => now()->toDateString(), 'bill_type' => 'Maintenance',
            'due_date' => now()->toDateString(),
        ]);

        UtilityBill::create([
            'society_id' => $this->society->id, 'flat_id' => $flat->id,
            'bill_type' => 'Electricity', 'bill_amount' => 900,
            'bill_date' => now()->toDateString(), 'due_date' => now()->addDays(10)->toDateString(),
            'status' => 'unpaid',
        ]);
    }

    public static function adminRoutes(): array
    {
        return [
            'dashboard'         => ['/dashboard'],
            'buildings index'   => ['/buildings'],
            'flats index'       => ['/flats'],
            'owners index'      => ['/owners'],
            'users index'       => ['/users'],
            'complaints index'  => ['/complaints'],
            'facilities index'  => ['/facilities'],
            'bookings index'    => ['/facility-bookings'],
            'visitors index'    => ['/visitors'],
            'notices index'     => ['/notices'],
            'events index'      => ['/events'],
            'payments index'    => ['/payments'],
            'utility bills'     => ['/utility-bills'],
            'services'          => ['/services'],
            'settings'          => ['/settings'],
            'villa areas'       => ['/villa-areas'],
            'villas'            => ['/villas'],
            'reports financial' => ['/reports/financial'],
            'reports maint.'    => ['/reports/maintenance'],
        ];
    }

    /**
     * @dataProvider adminRoutes
     */
    public function test_admin_screens_render(string $uri): void
    {
        $admin = $this->makeUserWithRole($this->society, 'Admin');

        $response = $this->actingAs($admin)->get($uri);

        $this->assertLessThan(
            500,
            $response->getStatusCode(),
            "{$uri} returned {$response->getStatusCode()} for an Admin"
        );
    }

    public static function residentRoutes(): array
    {
        return [
            'dashboard'        => ['/dashboard'],
            'complaints index' => ['/complaints'],
            'notices index'    => ['/notices'],
            'events index'     => ['/events'],
            'facilities index' => ['/facilities'],
            'bookings index'   => ['/facility-bookings'],
            'visitors index'   => ['/visitors'],
            'utility bills'    => ['/utility-bills'],
            'payments index'   => ['/payments'],
            'profile'          => ['/profile'],
        ];
    }

    /**
     * @dataProvider residentRoutes
     */
    public function test_tenant_screens_render(string $uri): void
    {
        $flat   = $this->makeFlat($this->society, $this->makeBuilding($this->society, 'Tower B'), '301');
        $tenant = $this->makeResidentUser($this->society, $flat);
        $tenant->assignRole($this->makeRole('Tenant')->name);

        $response = $this->actingAs($tenant)->get($uri);

        $this->assertLessThan(
            500,
            $response->getStatusCode(),
            "{$uri} returned {$response->getStatusCode()} for a Tenant"
        );
    }

    /**
     * @dataProvider residentRoutes
     */
    public function test_apartment_owner_screens_render(string $uri): void
    {
        $flat  = $this->makeFlat($this->society, $this->makeBuilding($this->society, 'Tower C'), '401');
        $owner = $this->makeResidentUser($this->society, $flat);
        $owner->assignRole($this->makeRole('Apartment Owner')->name);

        $response = $this->actingAs($owner)->get($uri);

        $this->assertLessThan(
            500,
            $response->getStatusCode(),
            "{$uri} returned {$response->getStatusCode()} for an Apartment Owner"
        );
    }

    public function test_create_forms_render_for_admin(): void
    {
        $admin = $this->makeUserWithRole($this->society, 'Admin');

        foreach (['/buildings/create', '/flats/create', '/owners/create', '/complaints/create',
                  '/facilities/create', '/notices/create', '/events/create', '/visitors/create',
                  '/utility-bills/create', '/payments/create'] as $uri) {
            $response = $this->actingAs($admin)->get($uri);

            $this->assertLessThan(
                500,
                $response->getStatusCode(),
                "{$uri} returned {$response->getStatusCode()}"
            );
        }
    }
}
