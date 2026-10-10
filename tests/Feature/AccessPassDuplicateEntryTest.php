<?php

namespace Tests\Feature;

use App\Models\AccessPass;
use App\Models\Flat;
use App\Models\User;
use App\Models\Visitor;
use App\Services\AccessPassService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * One pass, one open visit - however many times the QR is scanned.
 *
 * The gates used to call AccessPassService::verify() and then checkIn() as
 * two separate statements. Nothing held the pass between them, so a second
 * scan arriving before the first had committed ran the same "is anyone
 * inside?" read, also saw nothing, and inserted a second 'entered' row for
 * the same person. These tests pin the behaviour that replaced it:
 * verification and check-in are one locked transaction, so the repeat scan
 * is refused with ALREADY_INSIDE instead of creating a duplicate.
 */
class AccessPassDuplicateEntryTest extends TestCase
{
    use RefreshDatabase;

    private function passFor(Flat $flat, array $overrides = []): AccessPass
    {
        $admin = $this->makeUserWithRole($flat->society, 'Admin');

        return AccessPass::create(array_merge([
            'society_id'   => $flat->society_id,
            'flat_id'      => $flat->id,
            'person_name'  => 'Ramraj',
            'person_phone' => '9876500001',
            'category'     => 'Cleaner',
            'pass_type'    => 'recurring_staff',
            'token'        => AccessPass::generateToken(),
            'allowed_days' => [0, 1, 2, 3, 4, 5, 6],
            'valid_from'   => now()->subDay()->toDateString(),
            'valid_until'  => null,
            'status'       => 'active',
            'created_by'   => $admin->id,
        ], $overrides));
    }

    private function openVisits(AccessPass $pass): int
    {
        return Visitor::where('access_pass_id', $pass->id)
            ->where('entry_status', 'entered')
            ->count();
    }

    public function test_first_scan_creates_exactly_one_entry(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $pass    = $this->passFor($flat);
        $guard   = $this->makeUserWithRole($society, 'Guard');

        Sanctum::actingAs($guard);

        $this->postJson('/api/v1/access-passes/scan', ['token' => $pass->token])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(1, $this->openVisits($pass));
    }

    public function test_duplicate_scan_is_refused_and_creates_no_second_row(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $pass    = $this->passFor($flat);
        $guard   = $this->makeUserWithRole($society, 'Guard');

        Sanctum::actingAs($guard);

        $this->postJson('/api/v1/access-passes/scan', ['token' => $pass->token])->assertOk();

        $this->postJson('/api/v1/access-passes/scan', ['token' => $pass->token])
            ->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonPath('message', 'Already checked in - check out first.');

        $this->assertSame(1, $this->openVisits($pass));
        $this->assertSame(1, Visitor::where('access_pass_id', $pass->id)->count());
    }

    /**
     * A guard double-tapping, or the app retrying, sends the same scan
     * several times in a row. Only the first may become a visit.
     */
    public function test_rapid_repeated_scans_create_only_one_entry(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $pass    = $this->passFor($flat);
        $guard   = $this->makeUserWithRole($society, 'Guard');

        Sanctum::actingAs($guard);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/access-passes/scan', ['token' => $pass->token]);
        }

        $this->assertSame(1, Visitor::where('access_pass_id', $pass->id)->count());
        $this->assertSame(1, $this->openVisits($pass));
    }

    /**
     * The service-level entry point is the one that must hold the lock -
     * calling it repeatedly is what a concurrent gate does, and it must be
     * the thing that refuses, not the controller above it.
     */
    public function test_service_level_repeat_check_in_is_refused(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $pass    = $this->passFor($flat);
        $guard   = $this->makeUserWithRole($society, 'Guard');

        $service = app(AccessPassService::class);

        $first = $service->verifyAndCheckIn($pass->token, $society->id, $guard);
        $this->assertTrue($first['ok']);
        $this->assertSame('entered', $first['action']);

        $second = $service->verifyAndCheckIn($pass->token, $society->id, $guard);
        $this->assertFalse($second['ok']);
        $this->assertSame(AccessPassService::REASON_ALREADY_INSIDE, $second['reason']);

        $this->assertSame(1, Visitor::where('access_pass_id', $pass->id)->count());
    }

    /** History is kept: checking out then returning is a second visit, not an edit of the first. */
    public function test_checkout_then_re_entry_creates_a_second_visit_and_keeps_the_first(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $pass    = $this->passFor($flat);
        $guard   = $this->makeUserWithRole($society, 'Guard');

        Sanctum::actingAs($guard);

        $this->postJson('/api/v1/access-passes/scan', ['token' => $pass->token])->assertOk();

        $first = Visitor::where('access_pass_id', $pass->id)->firstOrFail();
        $first->markExit($guard);

        $this->postJson('/api/v1/access-passes/scan', ['token' => $pass->token])->assertOk();

        $this->assertSame(2, Visitor::where('access_pass_id', $pass->id)->count());
        $this->assertSame(1, $this->openVisits($pass));
        $this->assertTrue($first->fresh()->hasExited(), 'the earlier visit must be preserved as history');
    }

    public function test_revoked_pass_creates_no_entry(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $pass    = $this->passFor($flat);
        $guard   = $this->makeUserWithRole($society, 'Guard');

        $pass->revoke();

        Sanctum::actingAs($guard);

        $this->postJson('/api/v1/access-passes/scan', ['token' => $pass->token])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Access revoked.');

        $this->assertSame(0, Visitor::where('access_pass_id', $pass->id)->count());
    }

    public function test_expired_pass_creates_no_entry(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $guard   = $this->makeUserWithRole($society, 'Guard');

        $pass = $this->passFor($flat, [
            'valid_from'  => now()->subDays(10)->toDateString(),
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        Sanctum::actingAs($guard);

        $this->postJson('/api/v1/access-passes/scan', ['token' => $pass->token])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This pass has expired.');

        $this->assertSame(0, Visitor::where('access_pass_id', $pass->id)->count());
    }

    /**
     * A guard may only admit their own society's passes, and a foreign
     * token must not confirm its own existence.
     */
    public function test_cross_society_scan_creates_no_entry_and_does_not_confirm_the_pass(): void
    {
        $theirs     = $this->makeSociety();
        $theirFlat  = $this->makeFlat($theirs, $this->makeBuilding($theirs));
        $pass       = $this->passFor($theirFlat);

        $ours  = $this->makeSociety();
        $guard = $this->makeUserWithRole($ours, 'Guard');

        Sanctum::actingAs($guard);

        $this->postJson('/api/v1/access-passes/scan', ['token' => $pass->token])
            ->assertStatus(422)
            ->assertJsonPath('message', 'QR code not recognised.')
            ->assertJsonPath('data.person', null);

        $this->assertSame(0, Visitor::where('access_pass_id', $pass->id)->count());
    }

    /** The pre-approved-guest half of the gate must be just as idempotent. */
    public function test_pre_approved_guest_cannot_be_checked_in_twice(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $guard   = $this->makeUserWithRole($society, 'Guard');
        $host    = $this->makeResidentUser($society, $flat);

        $guest = Visitor::create([
            'society_id'          => $society->id,
            'flat_id'             => $flat->id,
            'host_user_id'        => $host->id,
            'visitor_name'        => 'Pre-approved Guest',
            'visitor_phone'       => '9876500002',
            'visitor_type'        => 'guest',
            'purpose'             => 'Visit',
            'expected_count'      => 1,
            'expected_entry_time' => now(),
            'approval_status'     => 'allowed',
            'entry_status'        => 'pending',
            'qr_token'            => 'guest-token-' . uniqid(),
        ]);

        Sanctum::actingAs($guard);

        $this->postJson('/api/v1/access-passes/scan', ['token' => $guest->qr_token])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->postJson('/api/v1/access-passes/scan', ['token' => $guest->qr_token])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This guest is already checked in.');

        // Still exactly one guest row, entered once.
        $this->assertSame(1, Visitor::where('qr_token', $guest->qr_token)->count());
        $this->assertSame('entered', $guest->fresh()->entry_status);
    }
}
