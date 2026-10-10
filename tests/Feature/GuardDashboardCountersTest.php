<?php

namespace Tests\Feature;

use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The gate dashboard's "Inside" number must agree with the "Visitors
 * Inside" list printed directly beneath it.
 *
 * The counters came from one grouped query bounded to today by
 * `expected_entry_time`, but the list below them was bounded only by
 * `entry_status = 'entered'`. Anyone who entered before midnight and had
 * not checked out was therefore listed as inside while being left out of
 * the count - the two disagreed every night, and during any overnight
 * stay. Whether someone is on the premises does not depend on the day
 * they were expected, so the count follows the list.
 */
class GuardDashboardCountersTest extends TestCase
{
    use RefreshDatabase;

    public function test_inside_counter_includes_someone_who_entered_before_today(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $guard   = $this->makeUserWithRole($society, 'Guard');
        $host    = $this->makeResidentUser($society, $flat);

        // Arrived yesterday evening, never checked out - still on site.
        Visitor::create([
            'society_id'          => $society->id,
            'flat_id'             => $flat->id,
            'host_user_id'        => $host->id,
            'visitor_name'        => 'Overnight Guest',
            'visitor_phone'       => '9876500003',
            'visitor_type'        => 'guest',
            'purpose'             => 'Stay',
            'expected_count'      => 1,
            'expected_entry_time' => now()->subDay(),
            'approval_status'     => 'allowed',
            'entry_status'        => 'entered',
            'actual_entry_time'   => now()->subDay(),
        ]);

        $response = $this->actingAs($guard)->get('/guard');
        $response->assertOk();

        $stats = $response->viewData('stats');
        $inside = $response->viewData('inside');

        $this->assertSame(
            $inside->count(),
            $stats['inside'],
            'the Inside counter must match the Visitors Inside list'
        );
        $this->assertSame(1, $stats['inside']);
    }

    public function test_inside_counter_still_counts_todays_entries(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $guard   = $this->makeUserWithRole($society, 'Guard');
        $host    = $this->makeResidentUser($society, $flat);

        Visitor::create([
            'society_id'          => $society->id,
            'flat_id'             => $flat->id,
            'host_user_id'        => $host->id,
            'visitor_name'        => 'Today Guest',
            'visitor_phone'       => '9876500004',
            'visitor_type'        => 'guest',
            'purpose'             => 'Visit',
            'expected_count'      => 1,
            'expected_entry_time' => now(),
            'approval_status'     => 'allowed',
            'entry_status'        => 'entered',
            'actual_entry_time'   => now(),
        ]);

        $response = $this->actingAs($guard)->get('/guard');
        $stats = $response->viewData('stats');

        $this->assertSame(1, $stats['inside']);
    }

    /** A checked-out visitor leaves the Inside count and lands in Departed. */
    public function test_checked_out_visitor_is_not_counted_inside(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $guard   = $this->makeUserWithRole($society, 'Guard');
        $host    = $this->makeResidentUser($society, $flat);

        $visitor = Visitor::create([
            'society_id'          => $society->id,
            'flat_id'             => $flat->id,
            'host_user_id'        => $host->id,
            'visitor_name'        => 'Departed Guest',
            'visitor_phone'       => '9876500005',
            'visitor_type'        => 'guest',
            'purpose'             => 'Visit',
            'expected_count'      => 1,
            'expected_entry_time' => now(),
            'approval_status'     => 'allowed',
            'entry_status'        => 'entered',
            'actual_entry_time'   => now(),
        ]);

        $visitor->markExit($guard);

        $response = $this->actingAs($guard)->get('/guard');
        $stats = $response->viewData('stats');

        $this->assertSame(0, $stats['inside']);
        $this->assertSame(1, $stats['departed']);
    }

    /** Counters stay inside the guard's own society. */
    public function test_inside_counter_is_scoped_to_the_guards_society(): void
    {
        $ours   = $this->makeSociety();
        $theirs = $this->makeSociety();

        $guard     = $this->makeUserWithRole($ours, 'Guard');
        $theirFlat = $this->makeFlat($theirs, $this->makeBuilding($theirs));
        $theirHost = $this->makeResidentUser($theirs, $theirFlat);

        Visitor::create([
            'society_id'          => $theirs->id,
            'flat_id'             => $theirFlat->id,
            'host_user_id'        => $theirHost->id,
            'visitor_name'        => 'Other Society Guest',
            'visitor_phone'       => '9876500006',
            'visitor_type'        => 'guest',
            'purpose'             => 'Visit',
            'expected_count'      => 1,
            'expected_entry_time' => now(),
            'approval_status'     => 'allowed',
            'entry_status'        => 'entered',
            'actual_entry_time'   => now(),
        ]);

        $response = $this->actingAs($guard)->get('/guard');
        $stats = $response->viewData('stats');

        $this->assertSame(0, $stats['inside']);
    }
}
