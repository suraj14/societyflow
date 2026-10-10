<?php

namespace Tests\Feature;

use App\Models\AccessPass;
use App\Models\Flat;
use App\Models\Society;
use App\Models\Visitor;
use App\Services\AccessPassService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recurring-staff / temporary-service-provider QR gate access.
 *
 * Every scenario Part 22 of the brief names explicitly: wrong society, wrong
 * property (via the flat's own society scoping), expired, revoked, blocked
 * today, wrong day, wrong time, duplicate check-in, and that a valid scan
 * both creates the visit (reusing the existing visitors table - see
 * AccessPassService's own docblock) and that checkout then works through
 * the gate's existing, unmodified check-out endpoint.
 */
class AccessPassTest extends TestCase
{
    use RefreshDatabase;

    private function guardIn(Society $society)
    {
        return $this->makeUserWithRole($society, 'Guard');
    }

    private function passFor(Flat $flat, array $overrides = []): AccessPass
    {
        $admin = $this->makeUserWithRole($flat->society, 'Admin');

        return AccessPass::create(array_merge([
            'society_id'   => $flat->society_id,
            'flat_id'      => $flat->id,
            'person_name'  => 'Ramesh Kumar',
            'person_phone' => '9876500001',
            'category'     => 'Cleaner',
            'pass_type'    => 'recurring_staff',
            'token'        => AccessPass::generateToken(),
            'allowed_days' => [0, 1, 2, 3, 4, 5, 6], // every day, so day-of-week never blocks by default
            'valid_from'   => now()->subDay()->toDateString(),
            'valid_until'  => null,
            'status'       => 'active',
            'created_by'   => $admin->id,
        ], $overrides));
    }

    // -------------------------------------------------------------- happy path

    public function test_valid_pass_scan_checks_in_and_appears_in_visitors_inside(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $guard   = $this->guardIn($society);
        $pass    = $this->passFor($flat);

        $response = $this->actingAs($guard)->postJson('/guard/scan', ['token' => $pass->token]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('visitors', [
            'access_pass_id' => $pass->id,
            'flat_id'        => $flat->id,
            'entry_status'   => 'entered',
            'approval_status' => 'allowed',
        ]);
    }

    /** The valid-scan visit is an ordinary visitor row - checkout must just work. */
    public function test_checked_in_pass_visitor_can_be_checked_out_through_the_normal_gate_endpoint(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $guard   = $this->guardIn($society);
        $pass    = $this->passFor($flat);

        $this->actingAs($guard)->postJson('/guard/scan', ['token' => $pass->token]);
        $visitor = Visitor::where('access_pass_id', $pass->id)->firstOrFail();

        $this->actingAs($guard)->post("/guard/visitors/{$visitor->id}/check-out")
            ->assertRedirect();

        $this->assertSame('exited', $visitor->fresh()->entry_status);
    }

    /**
     * Found by a live scan against the running dev server, not by this
     * suite: every fixture above supplies person_phone, so a NULL one -
     * visitors.visitor_phone is NOT NULL - never surfaced until a real pass
     * was created through the actual form (where phone is optional).
     */
    public function test_pass_with_no_phone_number_can_still_be_scanned_in(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $guard   = $this->guardIn($society);
        $pass    = $this->passFor($flat, ['person_phone' => null]);

        $response = $this->actingAs($guard)->postJson('/guard/scan', ['token' => $pass->token]);

        $response->assertOk();
        $this->assertDatabaseHas('visitors', ['access_pass_id' => $pass->id, 'visitor_phone' => '']);
    }

    // -------------------------------------------------------------- security matrix

    public function test_scan_rejects_a_token_from_another_society(): void
    {
        $societyA = $this->makeSociety('A');
        $societyB = $this->makeSociety('B');
        $flatB    = $this->makeFlat($societyB, $this->makeBuilding($societyB));
        $guardA   = $this->guardIn($societyA);
        $pass     = $this->passFor($flatB);

        $response = $this->actingAs($guardA)->postJson('/guard/scan', ['token' => $pass->token]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertDatabaseMissing('visitors', ['access_pass_id' => $pass->id]);
    }

    public function test_scan_rejects_an_unknown_token(): void
    {
        $society = $this->makeSociety();
        $guard   = $this->guardIn($society);

        $this->actingAs($guard)->postJson('/guard/scan', ['token' => 'not-a-real-token'])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_scan_rejects_a_revoked_pass(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $guard   = $this->guardIn($society);
        $pass    = $this->passFor($flat, ['status' => 'revoked']);

        $this->actingAs($guard)->postJson('/guard/scan', ['token' => $pass->token])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Access revoked.']);
    }

    public function test_scan_rejects_an_expired_pass(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $guard   = $this->guardIn($society);
        $pass    = $this->passFor($flat, [
            'pass_type'   => 'temporary_service',
            'valid_from'  => now()->subDays(3)->toDateString(),
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($guard)->postJson('/guard/scan', ['token' => $pass->token])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'This pass has expired.']);
    }

    public function test_scan_rejects_a_pass_not_yet_valid(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $guard   = $this->guardIn($society);
        $pass    = $this->passFor($flat, ['valid_from' => now()->addDays(2)->toDateString()]);

        $this->actingAs($guard)->postJson('/guard/scan', ['token' => $pass->token])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'This pass is not valid yet.']);
    }

    public function test_scan_rejects_a_pass_blocked_for_today(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $guard   = $this->guardIn($society);
        $pass    = $this->passFor($flat);

        $pass->blockToday();

        $this->actingAs($guard)->postJson('/guard/scan', ['token' => $pass->token])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Access blocked for today.']);
    }

    /** The day after "Block Today", authorisation returns automatically. */
    public function test_pass_is_not_blocked_the_day_after_block_today(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $pass    = $this->passFor($flat);

        // Simulate a block from yesterday still sitting in the column.
        $pass->update(['blocked_dates' => [now()->subDay()->toDateString()]]);

        $this->assertFalse($pass->fresh()->isBlockedOn(now()));
    }

    public function test_scan_rejects_a_pass_on_a_day_it_is_not_allowed(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $guard   = $this->guardIn($society);

        // AccessPassService::verify() checks the schedule on the society's
        // own clock (SocietyClock::local(), Asia/Kolkata by default), not
        // the server's raw now() - this test's own "today" must be computed
        // the same way, or it silently excludes the wrong day of week
        // whenever UTC and IST disagree (UTC evening = IST past midnight,
        // ~5.5h/day). Found failing at exactly such a moment.
        $todayLocal = now()->timezone($society->clockTimezone())->dayOfWeek;
        $notToday = collect(range(0, 6))->reject(fn ($d) => $d === $todayLocal)->values()->all();
        $pass = $this->passFor($flat, ['allowed_days' => $notToday]);

        $this->actingAs($guard)->postJson('/guard/scan', ['token' => $pass->token])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Not an authorised day for this pass.']);
    }

    /**
     * Uses AccessPassService::verify()'s own $at parameter rather than wall-
     * clock arithmetic (now()->subHours(2) wraps unpredictably across
     * midnight depending on when the test happens to run) - a fixed pass
     * window checked at a fixed moment outside it, deterministic regardless
     * of when the suite executes.
     */
    public function test_scan_rejects_a_pass_outside_its_time_window(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $pass    = $this->passFor($flat, ['start_time' => '08:00:00', 'end_time' => '11:00:00']);

        // The window is on the society's clock (Asia/Kolkata by default -
        // see SocietyClock), so the scan moment is stated there too. The
        // suite's storage timezone is UTC, 5½ h behind.
        $result = (new AccessPassService())->verify($pass->token, $society->id, \Illuminate\Support\Carbon::parse('14:00:00', $society->clockTimezone()));

        $this->assertFalse($result['ok']);
        $this->assertSame(AccessPassService::REASON_WRONG_TIME, $result['reason']);
    }

    public function test_scan_allows_a_pass_inside_its_time_window(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $pass    = $this->passFor($flat, ['start_time' => '08:00:00', 'end_time' => '11:00:00']);

        $result = (new AccessPassService())->verify($pass->token, $society->id, \Illuminate\Support\Carbon::parse('09:30:00', $society->clockTimezone()));

        $this->assertTrue($result['ok']);
    }

    public function test_scan_rejects_a_duplicate_check_in_while_already_inside(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $guard   = $this->guardIn($society);
        $pass    = $this->passFor($flat);

        $this->actingAs($guard)->postJson('/guard/scan', ['token' => $pass->token])->assertOk();

        $response = $this->actingAs($guard)->postJson('/guard/scan', ['token' => $pass->token]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Already checked in - check out first.']);
        $this->assertSame(1, Visitor::where('access_pass_id', $pass->id)->count());
    }

    /** After checkout, the same pass can scan in again. */
    public function test_pass_can_check_in_again_after_checking_out(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $guard   = $this->guardIn($society);
        $pass    = $this->passFor($flat);

        $this->actingAs($guard)->postJson('/guard/scan', ['token' => $pass->token])->assertOk();
        $firstVisit = Visitor::where('access_pass_id', $pass->id)->firstOrFail();
        $this->actingAs($guard)->post("/guard/visitors/{$firstVisit->id}/check-out");

        $this->actingAs($guard)->postJson('/guard/scan', ['token' => $pass->token])->assertOk();

        $this->assertSame(2, Visitor::where('access_pass_id', $pass->id)->count());
    }

    // ------------------------------------------------------- temporary service

    public function test_temporary_service_pass_works_within_its_single_day_window(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $pass    = $this->passFor($flat, [
            'pass_type'    => 'temporary_service',
            'category'     => 'Electrician',
            'allowed_days' => null,
            'valid_from'   => now()->toDateString(),
            'valid_until'  => now()->toDateString(),
            'start_time'   => '08:00:00',
            'end_time'     => '18:00:00',
        ]);

        $result = (new AccessPassService())->verify($pass->token, $society->id, \Illuminate\Support\Carbon::parse('10:00:00'));

        $this->assertTrue($result['ok']);
    }

    public function test_temporary_service_pass_is_expired_the_day_after(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $guard   = $this->guardIn($society);
        $pass    = $this->passFor($flat, [
            'pass_type'    => 'temporary_service',
            'allowed_days' => null,
            'valid_from'   => now()->subDay()->toDateString(),
            'valid_until'  => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($guard)->postJson('/guard/scan', ['token' => $pass->token])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'This pass has expired.']);
    }

    // ---------------------------------------------------------- guest QR reuse

    public function test_pre_approved_guest_gets_a_qr_token_and_can_be_scanned_in(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $host    = $this->makeResidentUser($society, $flat);
        $guard   = $this->guardIn($society);

        $visitor = Visitor::create([
            'society_id'          => $society->id,
            'flat_id'             => $flat->id,
            'host_user_id'        => $host->id,
            'visitor_name'        => 'Priya Guest',
            'visitor_phone'       => '9876500002',
            'visitor_type'        => 'guest',
            'expected_count'      => 1,
            'expected_entry_time' => now(),
            'approval_status'     => 'allowed', // host-approved at invitation time
            'entry_status'        => 'pending',
        ]);

        $this->assertNotNull($visitor->fresh()->qr_token, 'a pre-approved visitor must get a QR token automatically');

        $response = $this->actingAs($guard)->postJson('/guard/scan', ['token' => $visitor->fresh()->qr_token]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertSame('entered', $visitor->fresh()->entry_status);
    }

    public function test_pending_visitor_gets_no_qr_token(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $host    = $this->makeResidentUser($society, $flat);

        $visitor = Visitor::create([
            'society_id'          => $society->id,
            'flat_id'             => $flat->id,
            'host_user_id'        => $host->id,
            'visitor_name'        => 'Unapproved Guest',
            'visitor_phone'       => '9876500003',
            'visitor_type'        => 'guest',
            'expected_count'      => 1,
            'expected_entry_time' => now(),
            'approval_status'     => 'pending',
            'entry_status'        => 'pending',
        ]);

        $this->assertNull($visitor->fresh()->qr_token);
    }

    // ------------------------------------------------------- resident authoring

    public function test_resident_cannot_create_a_pass_for_another_flat(): void
    {
        $society     = $this->makeSociety();
        $building    = $this->makeBuilding($society);
        $ownFlat     = $this->makeFlat($society, $building, 'A-1');
        $othersFlat  = $this->makeFlat($society, $building, 'A-2');
        $owner       = $this->makeResidentUser($society, $ownFlat);
        $this->makeRole('Apartment Owner');
        $owner->assignRole('Apartment Owner');

        $response = $this->actingAs($owner)->post('/access-passes', [
            'flat_id'      => $othersFlat->id,
            'pass_type'    => 'recurring_staff',
            'person_name'  => 'Sneaky Pass',
            'allowed_days' => [1, 2, 3],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('access_passes', ['flat_id' => $othersFlat->id]);
    }

    public function test_resident_can_create_a_recurring_pass_for_their_own_flat(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $owner   = $this->makeResidentUser($society, $flat);
        $this->makeRole('Apartment Owner');
        $owner->assignRole('Apartment Owner');

        $response = $this->actingAs($owner)->post('/access-passes', [
            'flat_id'      => $flat->id,
            'pass_type'    => 'recurring_staff',
            'person_name'  => 'Maid Sunita',
            'category'     => 'Maid',
            'allowed_days' => [1, 2, 3, 4, 5],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('access_passes', ['flat_id' => $flat->id, 'person_name' => 'Maid Sunita']);
    }

    // -------------------------------------------------------- society-wide staff

    private function societyWidePassFor(Society $society, array $overrides = []): AccessPass
    {
        $admin = $this->makeUserWithRole($society, 'Admin');

        return AccessPass::create(array_merge([
            'society_id'   => $society->id,
            'flat_id'      => null,
            'person_name'  => 'Suresh Electrician',
            'category'     => 'Society Electrician',
            'pass_type'    => 'recurring_staff',
            'access_scope' => 'society',
            'token'        => AccessPass::generateToken(),
            'allowed_days' => [0, 1, 2, 3, 4, 5, 6],
            'valid_from'   => now()->subDay()->toDateString(),
            'valid_until'  => null,
            'status'       => 'active',
            'created_by'   => $admin->id,
        ], $overrides));
    }

    public function test_society_wide_pass_scan_does_not_require_a_property(): void
    {
        $society = $this->makeSociety();
        $guard   = $this->guardIn($society);
        $pass    = $this->societyWidePassFor($society);

        $response = $this->actingAs($guard)->postJson('/guard/scan', ['token' => $pass->token]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertTrue($pass->fresh()->currently_inside);
    }

    /** Scanning again while inside toggles checkout - no "Visitors Inside" browse needed. */
    public function test_society_wide_pass_second_scan_checks_out(): void
    {
        $society = $this->makeSociety();
        $guard   = $this->guardIn($society);
        $pass    = $this->societyWidePassFor($society);

        $this->actingAs($guard)->postJson('/guard/scan', ['token' => $pass->token])->assertOk();
        $this->assertTrue($pass->fresh()->currently_inside);

        $response = $this->actingAs($guard)->postJson('/guard/scan', ['token' => $pass->token]);

        $response->assertOk();
        $response->assertJsonFragment(['message' => 'Suresh Electrician - checked out.']);
        $this->assertFalse($pass->fresh()->currently_inside);
    }

    public function test_society_wide_pass_scan_rejects_another_society(): void
    {
        $societyA = $this->makeSociety('A');
        $societyB = $this->makeSociety('B');
        $guardA   = $this->guardIn($societyA);
        $pass     = $this->societyWidePassFor($societyB);

        $response = $this->actingAs($guardA)->postJson('/guard/scan', ['token' => $pass->token]);

        $response->assertStatus(422);
        $this->assertFalse($pass->fresh()->currently_inside);
    }

    /** Only Admin/Super Admin may create a society-wide pass. */
    public function test_resident_cannot_create_a_society_wide_pass(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $owner   = $this->makeResidentUser($society, $flat);
        $this->makeRole('Apartment Owner');
        $owner->assignRole('Apartment Owner');

        $this->actingAs($owner)->post('/access-passes', [
            'access_scope' => 'society',
            'pass_type'    => 'recurring_staff',
            'person_name'  => 'Sneaky Society Pass',
            'allowed_days' => [1, 2, 3],
        ])->assertRedirect(); // falls back to property-scoped and then fails validation (no flat_id)

        $this->assertDatabaseMissing('access_passes', ['person_name' => 'Sneaky Society Pass', 'access_scope' => 'society']);
    }

    public function test_admin_can_create_a_society_wide_pass(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $response = $this->actingAs($admin)->post('/access-passes', [
            'access_scope' => 'society',
            'pass_type'    => 'recurring_staff',
            'person_name'  => 'Society Cleaner',
            'category'     => 'Housekeeping',
            'allowed_days' => [1, 2, 3, 4, 5],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('access_passes', [
            'person_name'  => 'Society Cleaner',
            'access_scope' => 'society',
            'flat_id'      => null,
        ]);
    }

    public function test_only_the_owning_flats_resident_or_admin_can_block_or_revoke(): void
    {
        $society    = $this->makeSociety();
        $building   = $this->makeBuilding($society);
        $ownFlat    = $this->makeFlat($society, $building, 'A-1');
        $othersFlat = $this->makeFlat($society, $building, 'A-2');
        $owner      = $this->makeResidentUser($society, $ownFlat);
        $this->makeRole('Apartment Owner');
        $owner->assignRole('Apartment Owner');

        $pass = $this->passFor($othersFlat);

        $this->actingAs($owner)->post("/access-passes/{$pass->id}/revoke")->assertForbidden();
        $this->assertSame('active', $pass->fresh()->status);
    }
}
