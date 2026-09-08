<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\UtilityBill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Search filters must not widen what a user can see.
 *
 * Several listings applied their access scoping with ->where(...) and then
 * appended ->orWhere(...) / ->orWhereHas(...) without grouping. OR binds
 * across the whole WHERE clause, so the trailing condition escaped the
 * scoping entirely:
 *
 *   WHERE user_id = 7 AND EXISTS(user matches) OR EXISTS(facility matches)
 *
 * Anything matching the second EXISTS came back regardless of user_id. The
 * search box was an access-control bypass.
 */
class SearchScopeLeakTest extends TestCase
{
    use RefreshDatabase;

    public function test_facility_booking_search_cannot_escape_the_user_scope(): void
    {
        $society  = $this->makeSociety();
        $building = $this->makeBuilding($society);
        $flat     = $this->makeFlat($society, $building, '101');

        $mine     = $this->makeResidentUser($society, $flat);
        $mine->assignRole($this->makeRole('Tenant')->name);
        $stranger = $this->makeResidentUser($society, $flat);

        $facility = Facility::create([
            'society_id' => $society->id,
            'name'       => 'Gym',
            'type'       => 'gym',
            'status'     => 'active',
        ]);

        $make = fn ($user, $purpose) => FacilityBooking::create([
            'society_id'   => $society->id,
            'facility_id'  => $facility->id,
            'user_id'      => $user->id,
            'flat_id'      => $flat->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time'   => '10:00',
            'end_time'     => '11:00',
            'purpose'      => $purpose,
            'status'       => 'pending',
        ]);

        $make($mine, 'My booking');
        $make($stranger, 'Someone elses booking');

        // Searching the facility name previously matched every booking of
        // that facility, bypassing user_id.
        $response = $this->actingAs($mine)->get('/facility-bookings?search=Gym');
        $response->assertOk();

        $visible = $response->viewData('bookings');
        $userIds = collect($visible->items())->pluck('user_id')->unique()->all();

        $this->assertSame([$mine->id], array_values($userIds), 'the search returned another resident\'s bookings');
    }

    public function test_utility_bill_search_cannot_escape_the_resident_scope(): void
    {
        $society  = $this->makeSociety();
        $building = $this->makeBuilding($society);
        $myFlat   = $this->makeFlat($society, $building, '201');
        $other    = $this->makeFlat($society, $building, '202');

        $resident = $this->makeResidentUser($society, $myFlat);
        $resident->assignRole($this->makeRole('Tenant')->name);

        $make = fn ($flat) => UtilityBill::create([
            'society_id'  => $society->id,
            'flat_id'     => $flat->id,
            'bill_type'   => 'Electricity',
            'bill_amount' => 1000,
            'bill_date'   => now()->toDateString(),
            'due_date'    => now()->addDays(10)->toDateString(),
            'status'      => 'unpaid',
        ]);

        $make($myFlat);
        $make($other);

        // Searching the bill type previously matched every Electricity bill
        // in the society, bypassing the flat scoping.
        $response = $this->actingAs($resident)->get('/utility-bills?search=Electricity');
        $response->assertOk();

        $flatIds = collect($response->viewData('bills')->items())->pluck('flat_id')->unique()->all();

        $this->assertSame([$myFlat->id], array_values($flatIds), 'the search returned another flat\'s bills');
    }
}
