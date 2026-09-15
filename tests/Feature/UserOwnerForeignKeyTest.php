<?php

namespace Tests\Feature;

use App\Models\Owner;
use App\Models\Society;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * users.owner_id references owners.id, ON DELETE SET NULL.
 *
 * The constraint was declared in 2026_01_21_add_owner_tenant_to_users but
 * never applied on SQLite, where Laravel compiles ->foreign() on an existing
 * table to nothing. 2026_09_15_000001 rebuilds the table around it.
 *
 * Fixtures here deliberately push owners.id away from users.id: the two id
 * spaces overlap, so a test that happens to compare equal numbers proves
 * nothing about which table was consulted.
 */
class UserOwnerForeignKeyTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;

    protected function setUp(): void
    {
        parent::setUp();

        $this->society = $this->makeSociety('FK Society');
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

    /** An owner whose id cannot coincide with the user's. */
    private function distinctOwner(): Owner
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeOwner(name: 'Padding ' . $i);
        }

        return $this->makeOwner();
    }

    private function makeUser(?int $ownerId = null): User
    {
        return User::create([
            'society_id' => $this->society->id,
            'owner_id'   => $ownerId,
            'name'       => 'Account',
            'email'      => 'user' . uniqid() . '@test.local',
            'password'   => bcrypt('correct-horse-battery'),
            'status'     => 'active',
        ]);
    }

    // ------------------------------------------------------------ 1. exists

    public function test_the_foreign_key_exists_on_users_owner_id(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $keys = collect(DB::select('PRAGMA foreign_key_list(users)'));

            $ownerKey = $keys->firstWhere('from', 'owner_id');

            $this->assertNotNull($ownerKey, 'users.owner_id has no foreign key');
            $this->assertSame('owners', $ownerKey->table);
            $this->assertSame('id', $ownerKey->to);
            $this->assertSame('SET NULL', strtoupper($ownerKey->on_delete));

            return;
        }

        $row = DB::select(
            "SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
               AND CONSTRAINT_NAME = 'users_owner_id_foreign'"
        );

        $this->assertNotEmpty($row, 'users_owner_id_foreign is missing');
        $this->assertSame('SET NULL', strtoupper($row[0]->DELETE_RULE));
    }

    /** The rebuild must not have cost the table its other structure. */
    public function test_the_rebuild_preserved_the_other_users_columns_and_indexes(): void
    {
        foreach ([
            'id', 'society_id', 'name', 'email', 'email_verified_at', 'password',
            'phone', 'date_of_birth', 'gender', 'address', 'avatar', 'status',
            'last_login_at', 'remember_token', 'created_at', 'updated_at',
            'owner_id', 'tenant_id',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('users', $column),
                "users.{$column} was lost in the rebuild"
            );
        }

        if (DB::getDriverName() === 'sqlite') {
            $indexes = collect(DB::select("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='users'"))
                ->pluck('name');

            foreach ([
                'users_email_unique',
                'users_society_id_status_index',
                'users_email_status_index',
                'users_owner_id_index',
                'users_tenant_id_index',
            ] as $index) {
                $this->assertTrue($indexes->contains($index), "index {$index} was lost in the rebuild");
            }

            // society_id must keep its own constraint.
            $society = collect(DB::select('PRAGMA foreign_key_list(users)'))->firstWhere('from', 'society_id');
            $this->assertNotNull($society, 'the society_id foreign key was lost');
            $this->assertSame('CASCADE', strtoupper($society->on_delete));
        }
    }

    /** Email uniqueness still bites after the rebuild. */
    public function test_email_remains_unique_after_the_rebuild(): void
    {
        $user = $this->makeUser();

        $this->expectException(QueryException::class);

        User::create([
            'society_id' => $this->society->id,
            'name'       => 'Duplicate',
            'email'      => $user->email,
            'password'   => bcrypt('x'),
            'status'     => 'active',
        ]);
    }

    // ------------------------------------------------------ 2. owner deleted

    public function test_deleting_an_owner_nulls_the_users_link(): void
    {
        $owner = $this->distinctOwner();
        $user  = $this->makeUser($owner->id);

        $this->assertNotSame($owner->id, $user->id, 'fixture must not let the ids coincide');
        $this->assertSame($owner->id, $user->fresh()->owner_id);

        $owner->delete();

        $this->assertNull(
            $user->fresh()->owner_id,
            'deleting the owner must clear the link, not leave it dangling'
        );
    }

    /** The account itself survives; only the link goes. */
    public function test_deleting_an_owner_does_not_delete_the_user(): void
    {
        $owner = $this->distinctOwner();
        $user  = $this->makeUser($owner->id);

        $owner->delete();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'owner_id' => null]);
        $this->assertSame('active', $user->fresh()->status);
    }

    // ----------------------------------------------------- 3. unlinking

    public function test_unlinking_an_owner_nulls_the_column(): void
    {
        $owner = $this->distinctOwner();
        $user  = $this->makeUser($owner->id);

        $user->update(['owner_id' => null]);

        $this->assertNull($user->fresh()->owner_id);
        $this->assertDatabaseHas('owners', ['id' => $owner->id], );
    }

    // --------------------------------------------------- 4. reassignment

    public function test_reassigning_to_another_owner_stores_the_new_owner_id(): void
    {
        $first  = $this->distinctOwner();
        $second = $this->makeOwner(name: 'Second Owner');
        $user   = $this->makeUser($first->id);

        $user->update(['owner_id' => $second->id]);

        $this->assertSame($second->id, $user->fresh()->owner_id);
        $this->assertNotSame($first->id, $second->id);
    }

    // ------------------------------------------------- 5. invalid owner id

    public function test_an_owner_id_that_does_not_exist_is_rejected(): void
    {
        $missingId = (Owner::max('id') ?? 0) + 5000;

        $this->expectException(QueryException::class);

        $this->makeUser($missingId);
    }

    /** ...and the same on update, not only on insert. */
    public function test_updating_to_a_missing_owner_id_is_rejected(): void
    {
        $owner = $this->distinctOwner();
        $user  = $this->makeUser($owner->id);

        $missingId = (Owner::max('id') ?? 0) + 5000;

        $this->expectException(QueryException::class);

        $user->update(['owner_id' => $missingId]);
    }

    // --------------------------------------------- 6. cross-society owner

    /**
     * The database cannot express "same society" - that is the application's
     * job, and ResolvesPropertyOwner does it on the assignment screens. This
     * pins that guard so the new constraint is not mistaken for covering it.
     */
    public function test_cross_society_owner_assignment_is_rejected_by_the_assignment_screen(): void
    {
        $this->makeRole('Villa Owner');
        $admin = $this->makeUserWithRole($this->society, 'Admin');

        $other        = $this->makeSociety('Other Society');
        $foreignOwner = $this->makeOwner($other, 'Foreign Owner');

        $foreignUser = $this->makeUserWithRole($other, 'Villa Owner', ['owner_id' => $foreignOwner->id]);

        $villa = \App\Models\Flat::create([
            'society_id'    => $this->society->id,
            'property_type' => 'villa',
            'flat_number'   => 'V-FK',
            'status'        => 'vacant',
        ]);

        $this->actingAs($admin)->post(route('admin.villa-assignments.assign'), [
            'user_id'  => $foreignUser->id,
            'villa_id' => $villa->id,
        ]);

        $this->assertNull($villa->fresh()->owner_id, 'a foreign owner must not take a local villa');
    }

    // ------------------------------------------------- 7. users with no owner

    public function test_a_user_with_no_owner_is_valid_and_keeps_working(): void
    {
        $user = $this->makeUser(null);

        $this->assertNull($user->owner_id);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'owner_id' => null]);

        // The everyday path: such a user still authenticates and resolves to
        // no properties rather than erroring.
        $this->assertTrue(auth()->loginUsingId($user->id) instanceof User);
        $this->assertSame([], \App\Support\UserFlatResolver::flatIdsFor($user->fresh()));
    }

    /** Several accounts may sit unlinked at once - the column is not unique. */
    public function test_many_users_may_have_no_owner_at_the_same_time(): void
    {
        $this->makeUser(null);
        $this->makeUser(null);
        $this->makeUser(null);

        $this->assertSame(3, User::whereNull('owner_id')->where('society_id', $this->society->id)->count());
    }
}
