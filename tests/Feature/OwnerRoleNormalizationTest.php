<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Owner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Villa Owner and Apartment Owner have always carried identical permission
 * sets (see RolesAndPermissionsSeeder) - they only ever encoded a property
 * type, not a different capability. This suite pins the normalization to
 * the canonical 'Owner' role: the migration command is safe and idempotent,
 * new owners are created with 'Owner' directly, and 'Owner' users get the
 * same access as their legacy-role counterparts did, with no manual
 * property-type question needed - the flat/owner record already knows it.
 */
class OwnerRoleNormalizationTest extends TestCase
{
    use RefreshDatabase;

    private function villaFlat(\App\Models\Society $society, Owner $owner): Flat
    {
        return Flat::create([
            'society_id' => $society->id,
            'owner_id' => $owner->id,
            'flat_number' => 'V-1',
            'property_type' => 'villa',
            'status' => 'occupied',
        ]);
    }

    public function test_migrate_command_report_mode_changes_nothing(): void
    {
        $society = $this->makeSociety();
        $user = $this->makeUserWithRole($society, 'Villa Owner');

        Artisan::call('users:migrate-to-owner-role');

        $user->refresh();
        $this->assertTrue($user->hasRole('Villa Owner'));
        $this->assertFalse($user->hasRole('Owner'));
    }

    public function test_migrate_command_force_moves_villa_and_apartment_owners_to_owner(): void
    {
        $society = $this->makeSociety();
        $villaUser = $this->makeUserWithRole($society, 'Villa Owner');
        $apartmentUser = $this->makeUserWithRole($society, 'Apartment Owner');
        $bothUser = $this->makeUserWithRole($society, 'Villa Owner');
        $bothUser->assignRole('Apartment Owner');
        $alreadyOwner = $this->makeUserWithRole($society, 'Owner');

        Artisan::call('users:migrate-to-owner-role', ['--force' => true]);

        foreach ([$villaUser, $apartmentUser, $bothUser] as $user) {
            $user->refresh();
            $this->assertTrue($user->hasRole('Owner'), "user {$user->id} should hold Owner");
            $this->assertFalse($user->hasRole('Villa Owner'), "user {$user->id} should not hold Villa Owner");
            $this->assertFalse($user->hasRole('Apartment Owner'), "user {$user->id} should not hold Apartment Owner");
        }

        // A user who already held Owner is untouched and not duplicated.
        $alreadyOwner->refresh();
        $this->assertSame(['Owner'], $alreadyOwner->getRoleNames()->toArray());
    }

    public function test_migrate_command_is_idempotent(): void
    {
        $this->makeRole('Owner');
        $society = $this->makeSociety();
        $this->makeUserWithRole($society, 'Villa Owner');

        Artisan::call('users:migrate-to-owner-role', ['--force' => true]);
        $firstCount = \App\Models\User::role('Owner')->count();

        Artisan::call('users:migrate-to-owner-role', ['--force' => true]);
        $secondCount = \App\Models\User::role('Owner')->count();

        $this->assertSame($firstCount, $secondCount);
    }

    public function test_owner_with_only_a_villa_routes_to_villa_owner_dashboard(): void
    {
        $society = $this->makeSociety();
        $owner = Owner::create([
            'society_id' => $society->id, 'name' => 'V Owner', 'email' => 'vowner@test.local',
            'phone' => '9000000001', 'status' => 'active',
        ]);
        $user = $this->makeUserWithRole($society, 'Owner', ['owner_id' => $owner->id]);
        $this->villaFlat($society, $owner);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('villa-owner.dashboard'));
    }

    public function test_owner_with_only_an_apartment_routes_to_owner_dashboard(): void
    {
        $society = $this->makeSociety();
        $building = $this->makeBuilding($society);
        $owner = Owner::create([
            'society_id' => $society->id, 'name' => 'A Owner', 'email' => 'aowner@test.local',
            'phone' => '9000000002', 'status' => 'active',
        ]);
        $user = $this->makeUserWithRole($society, 'Owner', ['owner_id' => $owner->id]);
        $flat = $this->makeFlat($society, $building, '101');
        $flat->update(['owner_id' => $owner->id, 'property_type' => 'apartment']);

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertOk();
        $response->assertViewIs('owner.dashboard');
    }

    public function test_owner_can_reach_own_apartment_villa_reports_and_payments(): void
    {
        $society = $this->makeSociety();
        $building = $this->makeBuilding($society);
        $owner = Owner::create([
            'society_id' => $society->id, 'name' => 'Combo Owner', 'email' => 'combo@test.local',
            'phone' => '9000000003', 'status' => 'active',
        ]);
        $user = $this->makeUserWithRole($society, 'Owner', ['owner_id' => $owner->id]);
        $flat = $this->makeFlat($society, $building, '202');
        $flat->update(['owner_id' => $owner->id, 'property_type' => 'apartment']);
        $this->villaFlat($society, $owner);

        $this->actingAs($user)->get('/my-apartment')->assertOk();
        $this->actingAs($user)->get('/my-villa')->assertOk();
        $this->actingAs($user)->get('/payments')->assertOk();
        $this->actingAs($user)->get('/reports/financial')->assertOk();
    }

    public function test_owner_cannot_manage_users(): void
    {
        $society = $this->makeSociety();
        $user = $this->makeUserWithRole($society, 'Owner');

        $this->actingAs($user)->get('/users')->assertForbidden();
    }

    public function test_villa_owner_role_still_works_for_a_not_yet_migrated_user(): void
    {
        // Legacy roles remain registered and functional until migrated -
        // this is the "do not break existing authorization" guarantee.
        $society = $this->makeSociety();
        $owner = Owner::create([
            'society_id' => $society->id, 'name' => 'Legacy Owner', 'email' => 'legacy@test.local',
            'phone' => '9000000004', 'status' => 'active',
        ]);
        $user = $this->makeUserWithRole($society, 'Villa Owner', ['owner_id' => $owner->id]);
        $this->villaFlat($society, $owner);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('villa-owner.dashboard'));
        $this->actingAs($user)->get('/my-villa')->assertOk();
    }

    public function test_new_owner_created_via_owner_controller_gets_canonical_role(): void
    {
        $this->makeRole('Owner');
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');

        $response = $this->actingAs($admin)->post('/owners', [
            'name' => 'Fresh Owner',
            'email' => 'fresh.owner@test.local',
            'phone' => '9876500000',
            'property_type' => 'villa',
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $newUser = \App\Models\User::where('email', 'fresh.owner@test.local')->first();
        $this->assertNotNull($newUser, 'owner user was not created');
        $this->assertTrue($newUser->hasRole('Owner'));
        $this->assertFalse($newUser->hasRole('Villa Owner'));
        $this->assertFalse($newUser->hasRole('Apartment Owner'));
    }

    public function test_assignable_roles_no_longer_offer_legacy_owner_role_names(): void
    {
        $this->makeRole('Owner');
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');

        $html = $this->actingAs($admin)->get('/users/create')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/value="Owner"/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="Villa Owner"/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="Apartment Owner"/', $html);
    }
}
