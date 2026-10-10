<?php

namespace Tests\Feature;

use App\Models\AccessPass;
use App\Models\ActivityLog;
use App\Models\Flat;
use App\Models\Society;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Editing and extending an existing pass.
 *
 * The rules under test are deliberately the *server's*, not a screen's:
 * both the web form and the mobile API go through AccessPassEditService,
 * so each case here is proven once and holds for both clients. The three
 * that matter most:
 *
 *   - the token is never reissued, so a printed QR survives an edit;
 *   - a revoked pass is terminal and no write path can revive it;
 *   - an expired pass *is* editable, because repairing it is the point.
 */
class AccessPassEditExtendTest extends TestCase
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
            'access_scope' => 'property',
            'token'        => AccessPass::generateToken(),
            'allowed_days' => [1, 2, 3, 4, 5],
            'valid_from'   => now()->subMonth()->toDateString(),
            'valid_until'  => now()->addMonth()->toDateString(),
            'status'       => 'active',
            'created_by'   => $admin->id,
        ], $overrides));
    }

    private function society(): Society
    {
        return $this->makeSociety();
    }

    // ── Core: the token survives ─────────────────────────────────────────

    public function test_editing_a_pass_never_reissues_its_token(): void
    {
        $society = $this->society();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $pass    = $this->passFor($flat);
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $original = $pass->token;

        $this->actingAs($admin)->put(route('access-passes.update', $pass), [
            'person_name' => 'Ramraj Kumar',
            'valid_from'  => $pass->valid_from->toDateString(),
            'valid_until' => now()->addYear()->toDateString(),
            'flat_id'     => $flat->id,
        ])->assertRedirect();

        $this->assertSame($original, $pass->fresh()->token, 'a printed QR must keep working after an edit');
        $this->assertSame('Ramraj Kumar', $pass->fresh()->person_name);
    }

    public function test_extending_an_expired_pass_makes_it_scannable_again_with_the_same_token(): void
    {
        $society = $this->society();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $pass = $this->passFor($flat, [
            // Valid on every day of the week: this test is about EXPIRY. With
            // the helper's default Mon-Fri window the final gate scan was
            // (correctly) refused whenever the suite ran on a Saturday or
            // Sunday, which looked like a broken extension. Day-of-week
            // restriction has its own tests in AccessPassTest.
            'allowed_days' => [0, 1, 2, 3, 4, 5, 6],
            'valid_from'  => now()->subMonths(2)->toDateString(),
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        $this->assertTrue($pass->isExpired());
        $original = $pass->token;

        $this->actingAs($admin)->post(route('access-passes.extend', $pass), [
            'valid_until' => now()->addMonth()->toDateString(),
        ])->assertRedirect();

        $fresh = $pass->fresh();
        $this->assertFalse($fresh->isExpired());
        $this->assertSame($original, $fresh->token);

        // The gate must now admit it - the real proof the extension took.
        $guard  = $this->makeUserWithRole($society, 'Guard');
        Sanctum::actingAs($guard);
        $this->postJson('/api/v1/access-passes/scan', ['token' => $original])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    /** Pushing a date out must not wipe a time window the form never mentioned. */
    public function test_extending_does_not_erase_an_existing_time_window(): void
    {
        $society = $this->society();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $pass = $this->passFor($flat, [
            'start_time'  => '08:00',
            'end_time'    => '20:00',
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($admin)->post(route('access-passes.extend', $pass), [
            'valid_until' => now()->addMonth()->toDateString(),
        ])->assertRedirect();

        $fresh = $pass->fresh();
        $this->assertNotNull($fresh->start_time);
        $this->assertNotNull($fresh->end_time);
        $this->assertSame([1, 2, 3, 4, 5], $fresh->allowed_days);
    }

    // ── Revoked is terminal ──────────────────────────────────────────────

    public function test_a_revoked_pass_cannot_be_edited(): void
    {
        $society = $this->society();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $pass    = $this->passFor($flat);

        $pass->revoke();

        $this->actingAs($admin)->put(route('access-passes.update', $pass), [
            'person_name' => 'Someone Else',
            'valid_from'  => now()->toDateString(),
            'flat_id'     => $flat->id,
        ])->assertForbidden();

        $this->assertSame('Ramraj', $pass->fresh()->person_name);
    }

    public function test_a_revoked_pass_cannot_be_extended_back_into_service(): void
    {
        $society = $this->society();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $pass    = $this->passFor($flat);

        $pass->revoke();

        $this->actingAs($admin)->post(route('access-passes.extend', $pass), [
            'valid_until' => now()->addYear()->toDateString(),
        ])->assertForbidden();

        $this->assertSame('revoked', $pass->fresh()->status);

        // And the gate still refuses it.
        $guard = $this->makeUserWithRole($society, 'Guard');
        Sanctum::actingAs($guard);
        $this->postJson('/api/v1/access-passes/scan', ['token' => $pass->token])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Access revoked.');
    }

    public function test_the_edit_screen_is_refused_for_a_revoked_pass(): void
    {
        $society = $this->society();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $pass    = $this->passFor($flat);

        $pass->revoke();

        $this->actingAs($admin)->get(route('access-passes.edit', $pass))->assertForbidden();
    }

    // ── Authorization / isolation ────────────────────────────────────────

    public function test_an_admin_cannot_edit_another_societys_pass(): void
    {
        $theirs    = $this->society();
        $theirFlat = $this->makeFlat($theirs, $this->makeBuilding($theirs));
        $pass      = $this->passFor($theirFlat);

        $ours      = $this->society();
        $ourAdmin  = $this->makeUserWithRole($ours, 'Admin');

        $this->actingAs($ourAdmin)->put(route('access-passes.update', $pass), [
            'person_name' => 'Hijacked',
            'valid_from'  => now()->toDateString(),
        ])->assertForbidden();

        $this->assertSame('Ramraj', $pass->fresh()->person_name);
    }

    /** Moving a pass onto a property you do not hold would grant yourself access to it. */
    public function test_a_resident_cannot_move_a_pass_to_someone_elses_property(): void
    {
        $society  = $this->society();
        $building = $this->makeBuilding($society);
        $mine     = $this->makeFlat($society, $building, '101');
        $theirs   = $this->makeFlat($society, $building, '202');

        $resident = $this->makeResidentUser($society, $mine);
        $resident->assignRole($this->makeRole('Tenant'));

        $pass = $this->passFor($mine);

        $this->actingAs($resident)->put(route('access-passes.update', $pass), [
            'person_name' => 'Ramraj',
            'valid_from'  => $pass->valid_from->toDateString(),
            'flat_id'     => $theirs->id,
        ])->assertForbidden();

        $this->assertSame($mine->id, $pass->fresh()->flat_id);
    }

    /** A resident's edit is always property-scoped, whatever the form claims. */
    public function test_a_resident_cannot_promote_a_pass_to_society_wide(): void
    {
        $society  = $this->society();
        $flat     = $this->makeFlat($society, $this->makeBuilding($society));
        $resident = $this->makeResidentUser($society, $flat);
        $resident->assignRole($this->makeRole('Tenant'));

        $pass = $this->passFor($flat);

        $this->actingAs($resident)->put(route('access-passes.update', $pass), [
            'person_name'  => 'Ramraj',
            'valid_from'   => $pass->valid_from->toDateString(),
            'flat_id'      => $flat->id,
            'access_scope' => 'society',
        ]);

        $fresh = $pass->fresh();
        $this->assertSame('property', $fresh->access_scope);
        $this->assertSame($flat->id, $fresh->flat_id);
    }

    // ── Audit ────────────────────────────────────────────────────────────

    public function test_an_edit_is_audited_with_old_and_new_values(): void
    {
        $society = $this->society();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $pass    = $this->passFor($flat);

        $this->actingAs($admin)->put(route('access-passes.update', $pass), [
            'person_name' => 'Ramraj Kumar',
            'valid_from'  => $pass->valid_from->toDateString(),
            'valid_until' => now()->addYear()->toDateString(),
            'flat_id'     => $flat->id,
        ]);

        $log = ActivityLog::where('model_type', AccessPass::class)
            ->where('model_id', $pass->id)
            ->where('action', 'access_pass_updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($log, 'an edit must leave an audit row');
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('Ramraj', $log->old_values['person_name']);
        $this->assertSame('Ramraj Kumar', $log->new_values['person_name']);
    }

    /** The audit trail must never carry the pass token. */
    public function test_the_audit_row_never_contains_the_token(): void
    {
        $society = $this->society();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $pass    = $this->passFor($flat);

        $this->actingAs($admin)->post(route('access-passes.extend', $pass), [
            'valid_until' => now()->addYear()->toDateString(),
        ]);

        $log = ActivityLog::where('model_id', $pass->id)->latest('id')->firstOrFail();

        $encoded = json_encode([$log->old_values, $log->new_values]);
        $this->assertStringNotContainsString($pass->token, $encoded);
    }

    public function test_an_unchanged_submission_writes_no_audit_row(): void
    {
        $society = $this->society();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $pass    = $this->passFor($flat);

        ActivityLog::query()->delete();

        $this->actingAs($admin)->put(route('access-passes.update', $pass), [
            'person_name'  => $pass->person_name,
            'person_phone' => $pass->person_phone,
            'category'     => $pass->category,
            'flat_id'      => $flat->id,
            'allowed_days' => $pass->allowed_days,
            'valid_from'   => $pass->valid_from->toDateString(),
            'valid_until'  => $pass->valid_until->toDateString(),
        ]);

        $this->assertSame(0, ActivityLog::where('model_id', $pass->id)->count());
    }

    // ── Block / unblock ──────────────────────────────────────────────────

    public function test_block_today_then_unblock_restores_gate_access(): void
    {
        $society = $this->society();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $guard   = $this->makeUserWithRole($society, 'Guard');
        $pass    = $this->passFor($flat, ['allowed_days' => [0, 1, 2, 3, 4, 5, 6]]);

        $this->actingAs($admin)->post(route('access-passes.block-today', $pass));
        $this->assertTrue($pass->fresh()->isBlockedToday());

        Sanctum::actingAs($guard);
        $this->postJson('/api/v1/access-passes/scan', ['token' => $pass->token])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Access blocked for today.');

        $this->actingAs($admin)->post(route('access-passes.unblock-today', $pass));
        $this->assertFalse($pass->fresh()->isBlockedToday());

        Sanctum::actingAs($guard);
        $this->postJson('/api/v1/access-passes/scan', ['token' => $pass->token])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    // ── The API half: same rules, same service ───────────────────────────

    public function test_the_api_extends_a_pass_and_the_gate_honours_it_immediately(): void
    {
        $society = $this->society();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $guard   = $this->makeUserWithRole($society, 'Guard');

        $pass = $this->passFor($flat, [
            'valid_from'   => now()->subMonths(2)->toDateString(),
            'valid_until'  => now()->subDay()->toDateString(),
            'allowed_days' => [0, 1, 2, 3, 4, 5, 6],
        ]);

        // Expired: refused.
        Sanctum::actingAs($guard);
        $this->postJson('/api/v1/access-passes/scan', ['token' => $pass->token])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This pass has expired.');

        // Extended through the app.
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/access-passes/{$pass->id}/extend", [
            'valid_until' => now()->addMonth()->toDateString(),
        ])->assertOk()->assertJson(['success' => true]);

        // The very next scan is admitted - no new QR anywhere.
        Sanctum::actingAs($guard);
        $this->postJson('/api/v1/access-passes/scan', ['token' => $pass->token])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_the_api_never_returns_the_pass_token(): void
    {
        $society = $this->society();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $pass    = $this->passFor($flat);

        Sanctum::actingAs($admin);

        $show  = $this->getJson("/api/v1/access-passes/{$pass->id}")->assertOk();
        $index = $this->getJson('/api/v1/access-passes')->assertOk();

        $this->assertStringNotContainsString($pass->token, $show->getContent());
        $this->assertStringNotContainsString($pass->token, $index->getContent());
    }

    public function test_the_api_refuses_a_cross_society_pass(): void
    {
        $theirs    = $this->society();
        $theirFlat = $this->makeFlat($theirs, $this->makeBuilding($theirs));
        $pass      = $this->passFor($theirFlat);

        $ours     = $this->society();
        $ourAdmin = $this->makeUserWithRole($ours, 'Admin');

        Sanctum::actingAs($ourAdmin);

        $this->getJson("/api/v1/access-passes/{$pass->id}")->assertForbidden();
        $this->putJson("/api/v1/access-passes/{$pass->id}", [
            'person_name' => 'Hijacked',
            'valid_from'  => now()->toDateString(),
        ])->assertForbidden();
    }

    public function test_the_api_list_is_scoped_to_the_callers_society(): void
    {
        $theirs    = $this->society();
        $theirFlat = $this->makeFlat($theirs, $this->makeBuilding($theirs));
        $this->passFor($theirFlat, ['person_name' => 'Their Staff']);

        $ours     = $this->society();
        $ourFlat  = $this->makeFlat($ours, $this->makeBuilding($ours));
        $this->passFor($ourFlat, ['person_name' => 'Our Staff']);

        $ourAdmin = $this->makeUserWithRole($ours, 'Admin');
        Sanctum::actingAs($ourAdmin);

        $response = $this->getJson('/api/v1/access-passes')->assertOk();

        $names = collect($response->json('data'))->pluck('person_name');
        $this->assertTrue($names->contains('Our Staff'));
        $this->assertFalse($names->contains('Their Staff'));
    }

    public function test_extend_rejects_a_date_in_the_past(): void
    {
        $society = $this->society();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $pass    = $this->passFor($flat);

        $this->actingAs($admin)
            ->post(route('access-passes.extend', $pass), [
                'valid_until' => now()->subWeek()->toDateString(),
            ])
            ->assertSessionHasErrors('valid_until');
    }
}
