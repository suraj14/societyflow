<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Society;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The guard's gate: what they can do, and what they must not see.
 *
 * Brief §8 and §18. Two things are being asserted here: that the gate works
 * at all, and that it stays a gate - a guard who can reach the society's
 * revenue or another society's visitors is the failure mode that matters.
 */
class GuardGateTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;
    private User $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->society = $this->makeSociety('Gate Society');
        $this->guard   = $this->makeUserWithRole($this->society, 'Guard');
    }

    /** @var array<int,\App\Models\Building> one building per society - the name is unique per society */
    private array $buildings = [];

    private function buildingFor(Society $society)
    {
        return $this->buildings[$society->id] ??= $this->makeBuilding($society);
    }

    private function visitorFor(Society $society, array $overrides = []): Visitor
    {
        static $n = 0;
        $flat = $this->makeFlat($society, $this->buildingFor($society), 'A-' . (++$n));

        // host_user_id is set by every real registration path. It is also
        // required here on MySQL, where the column is NOT NULL: the visitors
        // repair migration declares it nullable, but only its SQLite branch
        // rebuilds the table, so MySQL kept the original NOT NULL. The
        // divergence is filed as P1-15; this fixture simply behaves the way
        // the application does.
        return Visitor::create(array_merge([
            'society_id'          => $society->id,
            'flat_id'             => $flat->id,
            'host_user_id'        => $this->guard->id,
            'visitor_name'        => 'Test Visitor',
            'visitor_phone'       => '9876543210',
            'visitor_type'        => 'guest',
            'expected_entry_time' => now(),
            'approval_status'     => 'allowed',
            'entry_status'        => 'pending',
        ], $overrides));
    }

    // ------------------------------------------------------------- landing

    /**
     * The defect this fixes: Guard matched no branch in DashboardController
     * and fell through to "Default: Admin dashboard", which renders monthly
     * revenue, pending dues and occupancy.
     */
    public function test_guard_lands_on_the_gate_not_the_admin_dashboard(): void
    {
        $this->actingAs($this->guard)
            ->get('/dashboard')
            ->assertRedirect(route('guard.dashboard'));
    }

    public function test_gate_dashboard_renders_without_admin_figures(): void
    {
        $html = $this->actingAs($this->guard)->get('/guard')->assertOk()->getContent();

        $this->assertStringContainsString('Register Visitor', $html);
        $this->assertStringNotContainsString('Monthly Revenue', $html);
        $this->assertStringNotContainsString('Pending Dues', $html);
    }

    // ------------------------------------------------------- check in/out

    public function test_guard_checks_a_visitor_in_and_out(): void
    {
        $visitor = $this->visitorFor($this->society);

        $this->actingAs($this->guard)
            ->post(route('guard.visitors.check-in', $visitor))
            ->assertRedirect();

        $visitor->refresh();
        $this->assertSame('entered', $visitor->entry_status);
        $this->assertNotNull($visitor->actual_entry_time);

        $this->actingAs($this->guard)
            ->post(route('guard.visitors.check-out', $visitor))
            ->assertRedirect();

        $visitor->refresh();
        $this->assertSame('exited', $visitor->entry_status);
        $this->assertNotNull($visitor->actual_exit_time);
    }

    /** An unapproved visitor must not be admitted by the gate alone. */
    public function test_unapproved_visitor_cannot_be_checked_in(): void
    {
        $visitor = $this->visitorFor($this->society, ['approval_status' => 'pending']);

        $this->actingAs($this->guard)
            ->post(route('guard.visitors.check-in', $visitor));

        $this->assertSame('pending', $visitor->fresh()->entry_status);
    }

    // ------------------------------------------------------------ isolation

    /** Route-model binding hands over any id; the society must be checked. */
    public function test_guard_cannot_check_in_another_societys_visitor(): void
    {
        $other   = $this->makeSociety('Other Society');
        $visitor = $this->visitorFor($other);

        $this->actingAs($this->guard)
            ->post(route('guard.visitors.check-in', $visitor))
            ->assertForbidden();

        $this->assertSame('pending', $visitor->fresh()->entry_status);
    }

    public function test_property_search_never_returns_another_society(): void
    {
        $other = $this->makeSociety('Other Society');
        $this->makeFlat($other, $this->makeBuilding($other, 'Foreign Tower'), 'SECRET-1');

        $mine = $this->makeFlat($this->society, $this->makeBuilding($this->society, 'My Tower'), 'SECRET-2');

        $response = $this->actingAs($this->guard)
            ->getJson(route('guard.search.properties', ['q' => 'SECRET']))
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$mine->id], $ids);
    }

    public function test_registering_a_visitor_for_a_foreign_property_is_rejected(): void
    {
        $other = $this->makeSociety('Other Society');
        $flat  = $this->makeFlat($other, $this->makeBuilding($other), 'X-1');

        $this->actingAs($this->guard)
            ->post(route('guard.visitors.store'), [
                'flat_id'       => $flat->id,
                'visitor_name'  => 'Intruder',
                'visitor_phone' => '9876543210',
                'visitor_type'  => 'guest',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('visitors', ['visitor_name' => 'Intruder']);
    }

    /** A registered visitor is always stamped with the guard's own society. */
    public function test_registered_visitor_is_pinned_to_the_guards_society(): void
    {
        $flat = $this->makeFlat($this->society, $this->makeBuilding($this->society), 'B-1');

        $this->actingAs($this->guard)
            ->post(route('guard.visitors.store'), [
                'flat_id'       => $flat->id,
                'visitor_name'  => 'Courier',
                'visitor_phone' => '9876500000',
                'visitor_type'  => 'delivery',
            ])
            ->assertRedirect(route('guard.dashboard'));

        $this->assertDatabaseHas('visitors', [
            'visitor_name'    => 'Courier',
            'society_id'      => $this->society->id,
            'approval_status' => 'pending',
        ]);
    }

    // ------------------------------------------------------------ the gate

    /** A non-guard resident has no business at the gate console. */
    public function test_a_resident_cannot_open_the_gate_console(): void
    {
        $resident = $this->makeUserWithRole($this->society, 'Tenant');

        $this->actingAs($resident)->get('/guard')->assertForbidden();
    }

    /**
     * The gate lists three groups of visitors. Each must be one query, not
     * one per row - the gate reloads constantly on a poor connection.
     */
    public function test_gate_dashboard_does_not_issue_a_query_per_visitor(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->visitorFor($this->society, ['entry_status' => 'entered', 'actual_entry_time' => now()]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->guard)->get('/guard')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Counters + 3 lists + their eager loads + auth/roles. Well under
        // one-per-visitor, which is the regression being guarded against.
        $this->assertLessThan(25, $count, "gate dashboard ran {$count} queries");
    }
}
