<?php

namespace Tests\Feature;

use App\Models\AccessPass;
use App\Models\Flat;
use App\Models\Society;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The mobile guard's QR scan - the app equivalent of the web gate's
 * GuardController::scan() test coverage in AccessPassTest, driven through
 * Sanctum instead of the session guard. Both hit the same AccessPassService,
 * so this file only re-proves the handful of cases that are genuinely
 * different at the API boundary (auth, role, response shape) rather than
 * repeating the full security matrix already covered there.
 */
class AccessPassApiTest extends TestCase
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

    public function test_unauthenticated_scan_is_rejected(): void
    {
        $this->postJson('/api/v1/access-passes/scan', ['token' => 'whatever'])
            ->assertUnauthorized();
    }

    public function test_non_guard_role_cannot_scan(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $tenant  = $this->makeResidentUser($society, $flat);
        $tenant->assignRole($this->makeRole('Tenant')->name);
        $pass = $this->passFor($flat);

        Sanctum::actingAs($tenant->fresh());

        $this->postJson('/api/v1/access-passes/scan', ['token' => $pass->token])
            ->assertForbidden()
            ->assertJson(['success' => false]);
    }

    public function test_guard_scans_a_valid_pass_and_gets_entry_allowed(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $guard   = $this->makeUserWithRole($society, 'Guard');
        $pass    = $this->passFor($flat);

        Sanctum::actingAs($guard->fresh());

        $response = $this->postJson('/api/v1/access-passes/scan', ['token' => $pass->token]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => ['person' => 'Ramraj', 'category' => 'Cleaner', 'action' => 'entered'],
        ]);
        $this->assertDatabaseHas('visitors', ['access_pass_id' => $pass->id, 'entry_status' => 'entered']);
    }

    public function test_admin_can_also_scan(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $pass    = $this->passFor($flat);

        Sanctum::actingAs($admin->fresh());

        $this->postJson('/api/v1/access-passes/scan', ['token' => $pass->token])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_scan_of_a_token_from_another_society_is_rejected(): void
    {
        $societyA = $this->makeSociety('A');
        $societyB = $this->makeSociety('B');
        $flatB    = $this->makeFlat($societyB, $this->makeBuilding($societyB));
        $guardA   = $this->makeUserWithRole($societyA, 'Guard');
        $pass     = $this->passFor($flatB);

        Sanctum::actingAs($guardA->fresh());

        $response = $this->postJson('/api/v1/access-passes/scan', ['token' => $pass->token]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('visitors', ['access_pass_id' => $pass->id]);
    }

    public function test_scan_of_an_unknown_token_returns_a_clear_message(): void
    {
        $society = $this->makeSociety();
        $guard   = $this->makeUserWithRole($society, 'Guard');

        Sanctum::actingAs($guard->fresh());

        $this->postJson('/api/v1/access-passes/scan', ['token' => 'not-a-real-token'])
            ->assertStatus(422)
            ->assertJson(['success' => false, 'message' => 'QR code not recognised.']);
    }

    public function test_scan_of_a_revoked_pass_is_rejected(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $guard   = $this->makeUserWithRole($society, 'Guard');
        $pass    = $this->passFor($flat, ['status' => 'revoked']);

        Sanctum::actingAs($guard->fresh());

        $this->postJson('/api/v1/access-passes/scan', ['token' => $pass->token])
            ->assertStatus(422)
            ->assertJson(['message' => 'Access revoked.']);
    }

    public function test_duplicate_scan_returns_already_inside(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $guard   = $this->makeUserWithRole($society, 'Guard');
        $pass    = $this->passFor($flat);

        Sanctum::actingAs($guard->fresh());

        $this->postJson('/api/v1/access-passes/scan', ['token' => $pass->token])->assertOk();
        $response = $this->postJson('/api/v1/access-passes/scan', ['token' => $pass->token]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Already checked in - check out first.']);
    }

    public function test_pre_approved_guest_qr_scans_in_via_the_api_too(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $host    = $this->makeResidentUser($society, $flat);
        $guard   = $this->makeUserWithRole($society, 'Guard');

        $visitor = Visitor::create([
            'society_id'          => $society->id,
            'flat_id'             => $flat->id,
            'host_user_id'        => $host->id,
            'visitor_name'        => 'Mobile Guest',
            'visitor_phone'       => '9876500099',
            'visitor_type'        => 'guest',
            'expected_count'      => 1,
            'expected_entry_time' => now(),
            'approval_status'     => 'allowed',
            'entry_status'        => 'pending',
        ]);

        Sanctum::actingAs($guard->fresh());

        $this->postJson('/api/v1/access-passes/scan', ['token' => $visitor->fresh()->qr_token])
            ->assertOk()
            ->assertJson(['success' => true, 'data' => ['person' => 'Mobile Guest']]);

        $this->assertSame('entered', $visitor->fresh()->entry_status);
    }
}
