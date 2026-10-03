<?php

namespace Tests\Feature;

use App\Events\UserCreated;
use App\Helpers\TemporaryPassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Locks in the fix for the worst defect found in this codebase.
 *
 * OwnerController::store hardcoded 'password' and UserController::store
 * hardcoded 'password123' for every account an admin created, then printed
 * the credential into a flash message. 22 of 32 accounts in the development
 * database shared the literal string "password", including both the super
 * admin and a society admin. Combined with the mobile API's email+password
 * login and no second factor, an email address was a login.
 */
class AdminCreatedCredentialsTest extends TestCase
{
    use RefreshDatabase;

    private const KNOWN_BAD = ['password', 'password123', 'secret', '12345678', 'changeme'];

    public function test_generated_password_is_random_and_long(): void
    {
        $first  = TemporaryPassword::generate();
        $second = TemporaryPassword::generate();

        $this->assertNotSame($first, $second, 'temporary passwords must not repeat');
        $this->assertGreaterThanOrEqual(16, strlen($first));
        $this->assertNotContains($first, self::KNOWN_BAD);
    }

    public function test_the_admin_notice_never_contains_the_password(): void
    {
        $password = TemporaryPassword::generate();
        $notice   = TemporaryPassword::noticeFor('someone@example.com');

        $this->assertStringNotContainsString($password, $notice);
        $this->assertStringContainsString('someone@example.com', $notice);
    }

    public function test_admin_created_owner_does_not_get_a_known_password(): void
    {
        Event::fake([UserCreated::class]);

        $society  = $this->makeSociety();
        $building = $this->makeBuilding($society);
        $admin    = $this->makeUserWithRole($society, 'Admin');
        $this->makeRole('Apartment Owner');
        $this->makeRole('Villa Owner');

        $response = $this->actingAs($admin)->post('/owners', [
            'name'          => 'New Owner',
            'email'         => 'newowner@test.local',
            'phone'         => '9876500001',
            'status'        => 'active',
            'property_type' => 'apartment',
            'building_id'   => $building->id,
            'flat_no'       => '101',
        ]);

        $response->assertRedirect();

        $created = User::where('email', 'newowner@test.local')->first();
        $this->assertNotNull($created, 'owner user account was not created');

        foreach (self::KNOWN_BAD as $bad) {
            $this->assertFalse(
                Hash::check($bad, $created->password),
                "owner account was created with the well-known password '{$bad}'"
            );
        }

        // The credential must reach the user by email, not the admin's screen.
        $issued = null;
        Event::assertDispatched(UserCreated::class, function (UserCreated $event) use ($created, &$issued) {
            if (!$event->user->is($created)) {
                return false;
            }
            $issued = $event->temporaryPassword;

            return is_string($issued) && strlen($issued) >= 16;
        });

        // The flash message mentions the word "password" by design ("a
        // temporary password has been emailed"); what it must never contain
        // is the credential itself.
        $flash = session('success');
        $this->assertIsString($flash);
        $this->assertNotNull($issued);
        $this->assertStringNotContainsString($issued, $flash, 'the temporary password leaked into the admin flash message');
    }

    /**
     * Owner + login account + role assignment are one unit of work inside
     * a DB::transaction() in OwnerController::store(). 'Owner' is now the
     * canonical role and is guarded with Role::firstOrCreate(), so a missing
     * role can no longer be the failure trigger for this test. Instead, a
     * one-off Owner::created listener forces a deterministic failure after
     * the Owner row is created but before the transaction commits, so the
     * original guarantee - no orphaned Owner, no orphaned User - is still
     * exercised end to end.
     */
    public function test_failed_owner_creation_leaves_no_partial_records(): void
    {
        $society  = $this->makeSociety();
        $building = $this->makeBuilding($society);
        $admin    = $this->makeUserWithRole($society, 'Admin');
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);

        $ownersBefore = \App\Models\Owner::count();
        $usersBefore  = User::count();

        $listener = \App\Models\Owner::created(function ($owner) {
            if ($owner->email === 'doomed@test.local') {
                throw new \RuntimeException('forced test failure to verify transaction rollback');
            }
        });

        try {
            $this->actingAs($admin)->post('/owners', [
                'name'          => 'Doomed Owner',
                'email'         => 'doomed@test.local',
                'phone'         => '9876500003',
                'status'        => 'active',
                'property_type' => 'apartment',
                'building_id'   => $building->id,
                'flat_no'       => '102',
            ]);
        } catch (\Throwable $e) {
            // The 500 is expected; what matters is what it left behind.
        } finally {
            Event::forget('eloquent.created: ' . \App\Models\Owner::class);
        }

        $this->assertSame($ownersBefore, \App\Models\Owner::count(), 'orphaned Owner row survived a failed create');
        $this->assertSame($usersBefore, User::count(), 'orphaned User row survived a failed create');
        $this->assertDatabaseMissing('users', ['email' => 'doomed@test.local']);
        $this->assertDatabaseMissing('owners', ['email' => 'doomed@test.local']);
    }

    public function test_admin_created_user_does_not_get_a_known_password(): void
    {
        Event::fake([UserCreated::class]);

        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $this->makeRole('Staff');

        $response = $this->actingAs($admin)->post('/users', [
            'name'  => 'New Staffer',
            'email' => 'newstaffer@test.local',
            'phone' => '9876500002',
            'role'  => 'Staff',
        ]);

        $response->assertRedirect();

        $created = User::where('email', 'newstaffer@test.local')->first();
        $this->assertNotNull($created, 'staff user account was not created');

        foreach (self::KNOWN_BAD as $bad) {
            $this->assertFalse(
                Hash::check($bad, $created->password),
                "user account was created with the well-known password '{$bad}'"
            );
        }

        Event::assertDispatched(UserCreated::class);

        $flash = session('success');
        $this->assertIsString($flash);
        $this->assertMatchesRegularExpression('/^User created successfully!/', $flash);
        $this->assertStringNotContainsString('password123', $flash);
    }
}
