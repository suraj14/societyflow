<?php

namespace Tests\Feature;

use App\Models\AccessPass;
use App\Models\Flat;
use App\Models\Society;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * "Villa 159 must never see Villa 160."
 *
 * Every case here changes an id in a URL or a request body and asserts the
 * server refuses - never that a button was hidden. Each one runs against
 * both the web routes and the mobile API, because the two must agree: a
 * rule enforced on only one of them is not enforced at all.
 *
 * The scoping itself is a database restriction (`whereIn flat_id`), not a
 * filter applied to a full result set, so an unauthorised row is never
 * loaded in the first place.
 */
class AccessPassPropertyScopeTest extends TestCase
{
    use RefreshDatabase;

    private function passFor(Flat $flat, array $overrides = []): AccessPass
    {
        // created_by is a real FK - a literal id only happens to exist in
        // some orderings, which made this fixture fail in isolation.
        $creator = $this->makeUserWithRole($flat->society, 'Admin');

        return AccessPass::create(array_merge([
            'society_id'   => $flat->society_id,
            'flat_id'      => $flat->id,
            'person_name'  => 'Helper ' . $flat->flat_number,
            'category'     => 'Cleaner',
            'pass_type'    => 'recurring_staff',
            'access_scope' => 'property',
            'token'        => AccessPass::generateToken(),
            'allowed_days' => [0, 1, 2, 3, 4, 5, 6],
            'valid_from'   => now()->subMonth()->toDateString(),
            'valid_until'  => now()->addMonth()->toDateString(),
            'status'       => 'active',
            'created_by'   => $creator->id,
        ], $overrides));
    }

    /**
     * One society, two villas, a tenant in each. The fixture every test
     * below shares: $mine is 159, $theirs is 160.
     *
     * @return array{0:Society,1:Flat,2:Flat,3:\App\Models\User}
     */
    private function twoVillas(): array
    {
        $society  = $this->makeSociety();
        $building = $this->makeBuilding($society);

        $mine   = $this->makeFlat($society, $building, '159');
        $theirs = $this->makeFlat($society, $building, '160');

        $tenant = $this->makeResidentUser($society, $mine);
        $tenant->assignRole($this->makeRole('Tenant'));

        return [$society, $mine, $theirs, $tenant];
    }

    // ── §5 list scoping ──────────────────────────────────────────────────

    public function test_a_tenant_list_contains_only_their_own_propertys_passes(): void
    {
        [$society, $mine, $theirs, $tenant] = $this->twoVillas();

        $this->passFor($mine,   ['person_name' => 'My Maid']);
        $this->passFor($theirs, ['person_name' => 'Their Maid']);

        // Web
        $response = $this->actingAs($tenant)->get(route('access-passes.index'))->assertOk();
        $response->assertSee('My Maid');
        $response->assertDontSee('Their Maid');

        // API
        Sanctum::actingAs($tenant);
        $names = collect($this->getJson('/api/v1/access-passes')->assertOk()->json('data'))
            ->pluck('person_name');

        $this->assertTrue($names->contains('My Maid'));
        $this->assertFalse($names->contains('Their Maid'));
    }

    public function test_a_tenant_does_not_see_society_wide_passes_they_cannot_manage(): void
    {
        [$society, $mine, , $tenant] = $this->twoVillas();

        $this->passFor($mine, ['person_name' => 'My Maid']);
        $this->passFor($mine, [
            'person_name'  => 'Society Electrician',
            'access_scope' => 'society',
            'flat_id'      => null,
        ]);

        Sanctum::actingAs($tenant);
        $names = collect($this->getJson('/api/v1/access-passes')->json('data'))->pluck('person_name');

        $this->assertTrue($names->contains('My Maid'));
        $this->assertFalse($names->contains('Society Electrician'));
    }

    public function test_an_admin_sees_every_pass_in_their_own_society_but_no_other(): void
    {
        [$society, $mine, $theirs] = $this->twoVillas();
        $admin = $this->makeUserWithRole($society, 'Admin');

        $this->passFor($mine,   ['person_name' => 'Villa 159 Maid']);
        $this->passFor($theirs, ['person_name' => 'Villa 160 Maid']);

        $other     = $this->makeSociety();
        $otherFlat = $this->makeFlat($other, $this->makeBuilding($other), '1');
        $this->passFor($otherFlat, ['person_name' => 'Other Society Maid']);

        Sanctum::actingAs($admin);
        $names = collect($this->getJson('/api/v1/access-passes')->json('data'))->pluck('person_name');

        $this->assertTrue($names->contains('Villa 159 Maid'));
        $this->assertTrue($names->contains('Villa 160 Maid'));
        $this->assertFalse($names->contains('Other Society Maid'));
    }

    /**
     * A Super Admin has no society_id, so scoping them the same way as
     * everyone else matched `society_id IS NULL` and returned an empty
     * list - every pass in the platform, invisible. Exempting them is the
     * documented pattern (CLAUDE.md §3).
     */
    public function test_a_super_admin_sees_passes_across_societies(): void
    {
        $a     = $this->makeSociety();
        $flatA = $this->makeFlat($a, $this->makeBuilding($a), '1');
        $this->passFor($flatA, ['person_name' => 'Society A Maid']);

        $b     = $this->makeSociety();
        $flatB = $this->makeFlat($b, $this->makeBuilding($b), '1');
        $this->passFor($flatB, ['person_name' => 'Society B Maid']);

        $super = $this->makeUserWithRole($a, 'Super Admin');
        $super->update(['society_id' => null]);
        $super = $super->fresh();

        // Web
        $passes = $this->actingAs($super)->get(route('access-passes.index'))
            ->assertOk()
            ->viewData('passes');
        $names = collect($passes->items())->pluck('person_name');
        $this->assertTrue($names->contains('Society A Maid'));
        $this->assertTrue($names->contains('Society B Maid'));

        // API
        Sanctum::actingAs($super);
        $apiNames = collect($this->getJson('/api/v1/access-passes')->assertOk()->json('data'))
            ->pluck('person_name');
        $this->assertTrue($apiNames->contains('Society A Maid'));
        $this->assertTrue($apiNames->contains('Society B Maid'));
    }

    // ── §8 IDOR: every verb, both clients ────────────────────────────────

    public function test_a_tenant_cannot_read_another_propertys_pass(): void
    {
        [, , $theirs, $tenant] = $this->twoVillas();
        $pass = $this->passFor($theirs);

        $this->actingAs($tenant)->get(route('access-passes.show', $pass))->assertForbidden();

        Sanctum::actingAs($tenant);
        $this->getJson("/api/v1/access-passes/{$pass->id}")->assertForbidden();
    }

    public function test_a_tenant_cannot_mutate_another_propertys_pass_by_any_verb(): void
    {
        [, , $theirs, $tenant] = $this->twoVillas();
        $pass = $this->passFor($theirs);

        $this->actingAs($tenant)->get(route('access-passes.edit', $pass))->assertForbidden();

        $this->actingAs($tenant)->put(route('access-passes.update', $pass), [
            'person_name' => 'Hijacked',
            'valid_from'  => now()->toDateString(),
        ])->assertForbidden();

        $this->actingAs($tenant)
            ->post(route('access-passes.extend', $pass), ['valid_until' => now()->addYear()->toDateString()])
            ->assertForbidden();

        $this->actingAs($tenant)->post(route('access-passes.block-today', $pass))->assertForbidden();
        $this->actingAs($tenant)->post(route('access-passes.unblock-today', $pass))->assertForbidden();
        $this->actingAs($tenant)->post(route('access-passes.revoke', $pass))->assertForbidden();

        $fresh = $pass->fresh();
        $this->assertSame('Helper 160', $fresh->person_name);
        $this->assertSame('active', $fresh->status);
        $this->assertEmpty($fresh->blocked_dates ?? []);
    }

    public function test_the_api_refuses_the_same_mutations(): void
    {
        [, , $theirs, $tenant] = $this->twoVillas();
        $pass = $this->passFor($theirs);

        Sanctum::actingAs($tenant);

        $this->putJson("/api/v1/access-passes/{$pass->id}", [
            'person_name' => 'Hijacked',
            'valid_from'  => now()->toDateString(),
        ])->assertForbidden();

        $this->postJson("/api/v1/access-passes/{$pass->id}/extend", [
            'valid_until' => now()->addYear()->toDateString(),
        ])->assertForbidden();

        $this->assertSame('Helper 160', $pass->fresh()->person_name);
    }

    /** A society-wide pass belongs to no flat, so no resident may manage it. */
    public function test_a_resident_cannot_manage_a_society_wide_pass(): void
    {
        [, $mine, , $tenant] = $this->twoVillas();

        $pass = $this->passFor($mine, [
            'access_scope' => 'society',
            'flat_id'      => null,
            'person_name'  => 'Society Electrician',
        ]);

        $this->actingAs($tenant)->get(route('access-passes.show', $pass))->assertForbidden();
        $this->actingAs($tenant)->put(route('access-passes.update', $pass), [
            'person_name' => 'Hijacked',
            'valid_from'  => now()->toDateString(),
        ])->assertForbidden();
    }

    // ── §6 create: the submitted property is never trusted ───────────────

    public function test_a_tenant_cannot_create_a_pass_against_another_property(): void
    {
        [, , $theirs, $tenant] = $this->twoVillas();

        $this->actingAs($tenant)->post(route('access-passes.store'), [
            'pass_type'    => 'recurring_staff',
            'person_name'  => 'Forged',
            'flat_id'      => $theirs->id,
            'allowed_days' => [1, 2, 3],
        ])->assertForbidden();

        $this->assertDatabaseMissing('access_passes', ['person_name' => 'Forged']);
    }

    public function test_a_tenant_cannot_create_a_pass_in_another_society(): void
    {
        [, , , $tenant] = $this->twoVillas();

        $other     = $this->makeSociety();
        $otherFlat = $this->makeFlat($other, $this->makeBuilding($other), '1');

        $this->actingAs($tenant)->post(route('access-passes.store'), [
            'pass_type'    => 'recurring_staff',
            'person_name'  => 'Cross Society',
            'flat_id'      => $otherFlat->id,
            'allowed_days' => [1],
        ])->assertForbidden();

        $this->assertDatabaseMissing('access_passes', ['person_name' => 'Cross Society']);
    }

    /** An admin's reach stops at their own society's properties. */
    public function test_an_admin_cannot_create_a_pass_in_another_society(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $other     = $this->makeSociety();
        $otherFlat = $this->makeFlat($other, $this->makeBuilding($other), '1');

        $this->actingAs($admin)->post(route('access-passes.store'), [
            'pass_type'    => 'recurring_staff',
            'person_name'  => 'Admin Cross Society',
            'flat_id'      => $otherFlat->id,
            'allowed_days' => [1],
        ])->assertForbidden();

        $this->assertDatabaseMissing('access_passes', ['person_name' => 'Admin Cross Society']);
    }

    /** The create screen offers only properties the user actually holds. */
    public function test_the_create_screen_offers_only_the_users_own_properties(): void
    {
        [, $mine, $theirs, $tenant] = $this->twoVillas();

        $response = $this->actingAs($tenant)->get(route('access-passes.create'))->assertOk();

        $flats = $response->viewData('flats')->pluck('id');
        $this->assertTrue($flats->contains($mine->id));
        $this->assertFalse($flats->contains($theirs->id));
    }

    // ── §9 the gate still refuses a foreign pass ─────────────────────────

    public function test_a_guard_cannot_admit_another_societys_pass(): void
    {
        $theirs     = $this->makeSociety();
        $theirFlat  = $this->makeFlat($theirs, $this->makeBuilding($theirs), '1');
        $pass       = $this->passFor($theirFlat);

        $ours  = $this->makeSociety();
        $guard = $this->makeUserWithRole($ours, 'Guard');

        Sanctum::actingAs($guard);
        $this->postJson('/api/v1/access-passes/scan', ['token' => $pass->token])
            ->assertStatus(422)
            ->assertJsonPath('message', 'QR code not recognised.')
            ->assertJsonPath('data.person', null);
    }
}
