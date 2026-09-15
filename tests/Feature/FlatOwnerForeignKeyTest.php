<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\Society;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * flats.owner_id references owners.id, ON DELETE SET NULL.
 *
 * SQLite has carried this since the table rebuilds in 2026_01_23 / 2026_01_27,
 * but those rebuilds run only for SQLite, so MySQL - the production engine -
 * had no constraint at all until 2026_09_15_000003.
 *
 * The column stays nullable on purpose: 109 of 121 flats in development have
 * no owner, because most units genuinely have none. "Unassigned" is a real
 * state, not missing data, so nothing here may treat NULL as a defect.
 *
 * Fixtures push owners.id away from users.id deliberately - the two id spaces
 * overlap, so a test comparing equal integers proves nothing about which
 * table was consulted.
 */
class FlatOwnerForeignKeyTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;

    protected function setUp(): void
    {
        parent::setUp();

        $this->society = $this->makeSociety('Flat FK Society');
    }

    private function makeOwner(?Society $society = null, string $name = 'Real Owner'): Owner
    {
        return Owner::create([
            'society_id' => ($society ?? $this->society)->id,
            'name'       => $name,
            'email'      => 'owner' . uniqid() . '@test.local',
            'phone'      => '9876543210',
            'status'     => 'active',
        ]);
    }

    /** An owner whose id cannot coincide with any user id in this test. */
    private function distinctOwner(): Owner
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeOwner(name: 'Padding ' . $i);
        }

        return $this->makeOwner();
    }

    private function apartment(?int $ownerId = null, string $number = 'A-1'): Flat
    {
        return Flat::create([
            'society_id'    => $this->society->id,
            'building_id'   => $this->makeBuilding($this->society, 'Tower ' . uniqid())->id,
            'owner_id'      => $ownerId,
            'property_type' => 'apartment',
            'flat_number'   => $number,
            'floor'         => 1,
            'status'        => $ownerId ? 'occupied' : 'vacant',
        ]);
    }

    private function villa(?int $ownerId = null, string $number = 'V-1'): Flat
    {
        return Flat::create([
            'society_id'    => $this->society->id,
            'owner_id'      => $ownerId,
            'property_type' => 'villa',
            'flat_number'   => $number,
            'villa_name'    => 'Villa ' . $number,
            'status'        => $ownerId ? 'occupied' : 'vacant',
        ]);
    }

    // ------------------------------------------------------------ the schema

    public function test_the_foreign_key_exists_with_set_null(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $fk = collect(DB::select('PRAGMA foreign_key_list(flats)'))->firstWhere('from', 'owner_id');

            $this->assertNotNull($fk, 'flats.owner_id has no foreign key');
            $this->assertSame('owners', $fk->table);
            $this->assertSame('id', $fk->to);
            $this->assertSame('SET NULL', strtoupper($fk->on_delete));

            $column = collect(DB::select('PRAGMA table_info(flats)'))->firstWhere('name', 'owner_id');
            $this->assertSame(0, (int) $column->notnull, 'owner_id must stay nullable');

            return;
        }

        $row = DB::selectOne(
            "SELECT rc.DELETE_RULE, kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME
             FROM information_schema.REFERENTIAL_CONSTRAINTS rc
             JOIN information_schema.KEY_COLUMN_USAGE kcu
               ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
              AND kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
             WHERE rc.CONSTRAINT_SCHEMA = DATABASE() AND rc.TABLE_NAME = 'flats'
               AND kcu.COLUMN_NAME = 'owner_id'"
        );

        $this->assertNotNull($row, 'flats_owner_id_foreign is missing');
        $this->assertSame('owners', $row->REFERENCED_TABLE_NAME);
        $this->assertSame('id', $row->REFERENCED_COLUMN_NAME);
        $this->assertSame('SET NULL', strtoupper($row->DELETE_RULE));

        $column = DB::selectOne(
            "SELECT COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'flats' AND COLUMN_NAME = 'owner_id'"
        );
        $owners = DB::selectOne(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'owners' AND COLUMN_NAME = 'id'"
        );

        $this->assertSame('YES', $column->IS_NULLABLE, 'owner_id must stay nullable');
        $this->assertSame(
            strtolower($owners->COLUMN_TYPE),
            strtolower($column->COLUMN_TYPE),
            'flats.owner_id and owners.id must be the same type'
        );
    }

    /** Adding the constraint must not have cost flats its other keys. */
    public function test_the_other_flat_foreign_keys_survive(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $targets = collect(DB::select('PRAGMA foreign_key_list(flats)'))->pluck('table');
            $this->assertTrue($targets->contains('societies'));
            $this->assertTrue($targets->contains('buildings'));

            return;
        }

        $targets = collect(DB::select(
            "SELECT REFERENCED_TABLE_NAME t FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'flats'
               AND REFERENCED_TABLE_NAME IS NOT NULL"
        ))->pluck('t');

        $this->assertTrue($targets->contains('societies'));
        $this->assertTrue($targets->contains('buildings'));
        $this->assertTrue($targets->contains('owners'));
    }

    // ------------------------------------------------------ valid relation

    public function test_an_apartment_resolves_to_its_owner(): void
    {
        $owner = $this->distinctOwner();
        $flat  = $this->apartment($owner->id);

        $this->assertTrue($flat->fresh()->ownerModel->is($owner));
    }

    public function test_a_villa_resolves_to_its_owner(): void
    {
        $owner = $this->distinctOwner();
        $villa = $this->villa($owner->id);

        $this->assertTrue($villa->fresh()->ownerModel->is($owner));
        $this->assertSame('villa', $villa->property_type);
    }

    // --------------------------------------------------------- unassigned

    /** NULL is a legitimate state, not missing data. */
    public function test_an_unassigned_flat_is_valid_and_stays_null(): void
    {
        $flat = $this->apartment(null, 'A-VACANT');

        $this->assertNull($flat->owner_id);
        $this->assertNull($flat->fresh()->ownerModel);
        $this->assertDatabaseHas('flats', ['id' => $flat->id, 'owner_id' => null]);
    }

    public function test_many_flats_may_be_unassigned_at_once(): void
    {
        $this->apartment(null, 'A-1');
        $this->apartment(null, 'A-2');
        $this->villa(null, 'V-9');

        $this->assertSame(3, Flat::whereNull('owner_id')->count());
    }

    // ------------------------------------------------------- invalid ids

    public function test_an_owner_id_that_does_not_exist_is_rejected(): void
    {
        $missing = (Owner::max('id') ?? 0) + 5000;

        $this->expectException(QueryException::class);

        $this->apartment($missing, 'A-BAD');
    }

    public function test_updating_a_flat_to_a_missing_owner_is_rejected(): void
    {
        $owner = $this->distinctOwner();
        $flat  = $this->apartment($owner->id);

        $missing = (Owner::max('id') ?? 0) + 5000;

        $this->expectException(QueryException::class);

        $flat->update(['owner_id' => $missing]);
    }

    /**
     * A users.id is not an owners.id. The fixture guarantees the user's id
     * does not exist in owners, so the database itself refuses it.
     */
    public function test_a_user_id_cannot_be_stored_as_the_owner(): void
    {
        $this->makeRole('Apartment Owner');
        $owner = $this->distinctOwner();
        $user  = $this->makeUserWithRole($this->society, 'Apartment Owner', ['owner_id' => $owner->id]);

        // Make the user's id certainly absent from owners.
        while (Owner::whereKey($user->id)->exists()) {
            $user = $this->makeUserWithRole($this->society, 'Apartment Owner', ['owner_id' => $owner->id]);
        }

        $this->expectException(QueryException::class);

        $this->apartment($user->id, 'A-USERID');
    }

    // ------------------------------------------------------ owner deletion

    public function test_deleting_an_owner_nulls_its_flats_and_keeps_them(): void
    {
        $owner     = $this->distinctOwner();
        $apartment = $this->apartment($owner->id, 'A-10');
        $villa     = $this->villa($owner->id, 'V-10');
        $untouched = $this->apartment(null, 'A-11');

        $owner->delete();

        $this->assertNull($apartment->fresh()->owner_id, 'the apartment must be released');
        $this->assertNull($villa->fresh()->owner_id, 'the villa must be released');

        // Released, never deleted.
        $this->assertDatabaseHas('flats', ['id' => $apartment->id, 'flat_number' => 'A-10']);
        $this->assertDatabaseHas('flats', ['id' => $villa->id, 'flat_number' => 'V-10']);
        $this->assertDatabaseHas('flats', ['id' => $untouched->id, 'owner_id' => null]);
        $this->assertSame(3, Flat::count());
    }

    /** A released flat must not resolve to whoever reuses that id later. */
    public function test_a_released_flat_does_not_resolve_to_another_owner(): void
    {
        $owner = $this->distinctOwner();
        $flat  = $this->apartment($owner->id);

        $owner->delete();

        $successor = $this->makeOwner(name: 'Successor');

        $this->assertNull($flat->fresh()->owner_id);
        $this->assertNull($flat->fresh()->ownerModel);
        $this->assertNotNull($successor->id);
    }

    // -------------------------------------------- identity is the id alone

    /**
     * Ownership is the foreign key and nothing else. Renaming the unit, its
     * building or the society's address must not disturb it - there is no
     * address, name or number matching anywhere in this relation.
     */
    public function test_renaming_the_flat_or_building_preserves_ownership(): void
    {
        $owner    = $this->distinctOwner();
        $building = $this->makeBuilding($this->society, 'Original Tower');

        $flat = Flat::create([
            'society_id'    => $this->society->id,
            'building_id'   => $building->id,
            'owner_id'      => $owner->id,
            'property_type' => 'apartment',
            'flat_number'   => '101',
            'floor'         => 1,
            'status'        => 'occupied',
        ]);

        $flat->update(['flat_number' => '909']);
        $building->update(['name' => 'Renamed Tower']);
        $this->society->update(['name' => 'Renamed Society', 'address' => 'A totally new address']);
        $owner->update(['name' => 'Owner Renamed']);

        $flat = $flat->fresh();

        $this->assertSame($owner->id, $flat->owner_id, 'ownership must follow the id, not the labels');
        $this->assertTrue($flat->ownerModel->is($owner));
        $this->assertSame('909', $flat->flat_number);
    }

    /**
     * Two flats with identical numbers in different societies must not be
     * confused - only the foreign key decides.
     */
    public function test_identical_flat_numbers_in_two_societies_keep_separate_owners(): void
    {
        $mineOwner = $this->distinctOwner();
        $mine      = $this->apartment($mineOwner->id, '101');

        $other      = $this->makeSociety('Other Society');
        $otherOwner = $this->makeOwner($other, 'Other Owner');
        $otherFlat  = Flat::create([
            'society_id'    => $other->id,
            'building_id'   => $this->makeBuilding($other, 'Their Tower')->id,
            'owner_id'      => $otherOwner->id,
            'property_type' => 'apartment',
            'flat_number'   => '101',
            'floor'         => 1,
            'status'        => 'occupied',
        ]);

        $this->assertSame($mineOwner->id, $mine->fresh()->owner_id);
        $this->assertSame($otherOwner->id, $otherFlat->fresh()->owner_id);
        $this->assertNotSame($mine->fresh()->owner_id, $otherFlat->fresh()->owner_id);
    }
}
