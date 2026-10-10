<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Owner;
use App\Models\Society;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * User::ownedVilla() / ownedFlat() / ownedProperties() branched on
 * `if ($this->owner_id)`, returning the real hasOneThrough/hasManyThrough
 * only when true and an always-dead relation (`->whereRaw('1 = 0')`, joined
 * on `flats.owner_id = users.id` - never correct, since flats.owner_id
 * references owners.id, not users.id) otherwise.
 *
 * That branch reads instance state that plainly does not exist yet whenever
 * Eloquent builds the relation without a loaded row first:
 * whereHas('ownedVilla'), whereDoesntHave('ownedVilla'), and
 * User::whereHas('ownedProperties', ...) all call this method against a
 * throwaway, unhydrated User, where $this->owner_id is always null - so
 * every one of those calls silently took the dead branch, unconditionally.
 *
 * $user->ownedVilla accessed on an already-loaded instance was never
 * affected (found correctly below, pinned as a non-regression) - only
 * query-time use was broken. That is exactly how this went unnoticed: the
 * dozens of existing `$user->ownedVilla` call sites across the controllers
 * all kept working, while the few whereHas/whereDoesntHave call sites
 * (3 email listeners, EmailNotificationService, EmailTriggerService, and
 * Admin\UserVillaAssignmentController's "owners without a villa" /
 * "owners with a villa" lists) silently returned nothing, forever.
 *
 * Fixtures deliberately push owners.id and flats.id away from the ids of
 * the users under test, the same technique UserOwnerForeignKeyTest uses -
 * the id spaces overlap, so a test that happens to compare equal numbers
 * proves nothing about which table was actually joined.
 */
class UserOwnedPropertiesRelationTest extends TestCase
{
    use RefreshDatabase;

    private function ownerWithVilla(Society $society, string $propertyType = 'villa'): array
    {
        // Padding rows so the owner/flat ids used below cannot coincide
        // with the user id by accident.
        for ($i = 0; $i < 5; $i++) {
            Owner::create([
                'society_id' => $society->id,
                'name'       => 'Padding ' . $i,
                'email'      => 'padding' . $i . uniqid() . '@test.local',
                'phone'      => '9' . random_int(100000000, 999999999),
                'status'     => 'active',
            ]);
        }

        $owner = Owner::create([
            'society_id' => $society->id,
            'name'       => 'Real Owner',
            'email'      => 'owner' . uniqid() . '@test.local',
            'phone'      => '9' . random_int(100000000, 999999999),
            'status'     => 'active',
        ]);

        $flat = Flat::create([
            'society_id'    => $society->id,
            'owner_id'      => $owner->id,
            'property_type' => $propertyType,
            'flat_number'   => 'OP-1',
            'status'        => 'occupied',
        ]);

        $user = User::create([
            'society_id' => $society->id,
            'owner_id'   => $owner->id,
            'name'       => 'Owner Account',
            'email'      => 'owneruser' . uniqid() . '@test.local',
            'phone'      => '9' . random_int(100000000, 999999999),
            'password'   => bcrypt('correct-horse-battery-staple'),
            'status'     => 'active',
        ]);

        return compact('owner', 'flat', 'user');
    }

    // --------------------------------------------------- query-time lookups

    public function test_where_has_owned_properties_finds_the_owning_user(): void
    {
        $society = $this->makeSociety('Owned Properties Society');
        $f       = $this->ownerWithVilla($society);

        $owners = User::whereHas('ownedProperties', function ($q) use ($f) {
            $q->where('flats.id', $f['flat']->id);
        })->get();

        $this->assertTrue($owners->contains('id', $f['user']->id), 'whereHas(ownedProperties) must find the real owner');
    }

    public function test_where_has_owned_properties_does_not_match_a_user_with_no_owner_link(): void
    {
        $society = $this->makeSociety('Owned Properties Society');
        $f       = $this->ownerWithVilla($society);

        $unrelated = User::create([
            'society_id' => $society->id,
            'owner_id'   => null,
            'name'       => 'No Owner Link',
            'email'      => 'noowner' . uniqid() . '@test.local',
            'phone'      => '9' . random_int(100000000, 999999999),
            'password'   => bcrypt('correct-horse-battery-staple'),
            'status'     => 'active',
        ]);

        $owners = User::whereHas('ownedProperties', function ($q) use ($f) {
            $q->where('flats.id', $f['flat']->id);
        })->get();

        $this->assertFalse($owners->contains('id', $unrelated->id), 'a user with no owner link must never match');
    }

    public function test_where_has_owned_villa_finds_the_villa_owner(): void
    {
        $society = $this->makeSociety('Owned Properties Society');
        $f       = $this->ownerWithVilla($society, 'villa');

        $this->assertTrue(
            User::whereHas('ownedVilla')->whereKey($f['user']->id)->exists(),
            'whereHas(ownedVilla) must find the real villa owner'
        );
    }

    public function test_where_doesnt_have_owned_villa_excludes_the_villa_owner(): void
    {
        $society   = $this->makeSociety('Owned Properties Society');
        $f         = $this->ownerWithVilla($society, 'villa');
        $unrelated = User::create([
            'society_id' => $society->id,
            'owner_id'   => null,
            'name'       => 'No Owner Link',
            'email'      => 'noowner' . uniqid() . '@test.local',
            'phone'      => '9' . random_int(100000000, 999999999),
            'password'   => bcrypt('correct-horse-battery-staple'),
            'status'     => 'active',
        ]);

        $withoutVilla = User::whereDoesntHave('ownedVilla')->pluck('id');

        $this->assertFalse($withoutVilla->contains($f['user']->id), 'whereDoesntHave(ownedVilla) must exclude a real villa owner');
        $this->assertTrue($withoutVilla->contains($unrelated->id), 'whereDoesntHave(ownedVilla) must still include a user with no villa');
    }

    // ----------------------------------------- instance access (regression)

    /**
     * Not broken before this fix - pinned so the fix cannot regress it.
     * Every existing `$user->ownedVilla` call site across the controllers
     * depends on this continuing to work.
     */
    public function test_instance_access_to_owned_villa_still_works(): void
    {
        $society = $this->makeSociety('Owned Properties Society');
        $f       = $this->ownerWithVilla($society, 'villa');

        $villa = $f['user']->ownedVilla;

        $this->assertNotNull($villa);
        $this->assertSame($f['flat']->id, $villa->id);
    }

    public function test_instance_access_to_owned_properties_still_works(): void
    {
        $society = $this->makeSociety('Owned Properties Society');
        $f       = $this->ownerWithVilla($society);

        $properties = $f['user']->ownedProperties;

        $this->assertCount(1, $properties);
        $this->assertSame($f['flat']->id, $properties->first()->id);
    }

    public function test_a_user_with_no_owner_link_has_no_owned_villa(): void
    {
        $society = $this->makeSociety('Owned Properties Society');

        $user = User::create([
            'society_id' => $society->id,
            'owner_id'   => null,
            'name'       => 'No Owner Link',
            'email'      => 'noowner' . uniqid() . '@test.local',
            'phone'      => '9' . random_int(100000000, 999999999),
            'password'   => bcrypt('correct-horse-battery-staple'),
            'status'     => 'active',
        ]);

        $this->assertNull($user->ownedVilla);
        $this->assertCount(0, $user->ownedProperties);
    }
}
