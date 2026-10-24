<?php

namespace Tests;

use App\Models\Building;
use App\Models\Flat;
use App\Models\Resident;
use App\Models\Society;
use App\Models\User;
use App\Support\ExternalMessaging;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /** @var list<string> real provider requests a test tried to make (they are blocked) */
    private array $blockedOutbound = [];

    protected function setUp(): void
    {
        parent::setUp();

        // A developer's local .env (an allow-list for a real-delivery test, say) must never change what the tests see: every test starts
        // with no allow-list and with the kill switch as phpunit.xml sets it (OFF), and states any other value itself.
        config(['messaging.allowlist' => null, 'messaging.enabled' => false]);

        // No automated test may reach a real SMS / WhatsApp provider. Tests mock the transport; anything that gets as far
        // as a real request is blocked here, recorded, and fails the test in tearDown() even if the app swallowed the error.
        $this->blockedOutbound = [];
        ExternalMessaging::$outboundGuard = function (string $channel, string $host) {
            $this->blockedOutbound[] = "{$channel} -> {$host}";

            throw new \RuntimeException("Blocked: a test tried to make a real {$channel} request to {$host}. Mock the transport.");
        };
    }

    /**
     * A browser session belongs to ONE person: AuthenticateSession keeps that person's password hash in it and ends the session once the hash no
     * longer matches. Switching to a different user inside one test is therefore a different browser, not the same one, so it gets a fresh session -
     * exactly what a second person on a second browser would have.
     */
    public function actingAs(\Illuminate\Contracts\Auth\Authenticatable $user, $guard = null)
    {
        // Compare with what the SESSION remembers (AuthenticateSession's own record), not with a guard's cached user: a token guard and the web
        // guard can hold different users, but the session is what gets ended.
        $remembered = $this->app['session']->get('password_hash_web');
        if ($remembered !== null && $remembered !== $user->getAuthPassword()) {
            $this->flushSession();
        }

        return parent::actingAs($user, $guard);
    }

    /**
     * A web page is always served on the web (session) guard. Sanctum::actingAs() leaves the default guard on 'sanctum' for the rest of the test, and
     * AuthenticateSession (web middleware group) asks the default guard about remember-me state, which a token guard does not have - something that
     * cannot happen in production, where the default guard is `web`. API requests keep whatever guard the test chose.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $path = (string) parse_url((string) $uri, PHP_URL_PATH);
        $auth = $this->app['auth'];
        if (!str_starts_with(ltrim($path, '/'), 'api/') && $auth->getDefaultDriver() !== 'web') {
            $user = $auth->guard()->user();
            $auth->shouldUse('web');
            if ($user) {
                $auth->guard('web')->setUser($user);
            }
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    /** For the guard's own test: return the blocked requests so far and forget them. */
    protected function consumeBlockedOutbound(): array
    {
        $blocked = $this->blockedOutbound;
        $this->blockedOutbound = [];

        return $blocked;
    }

    protected function tearDown(): void
    {
        ExternalMessaging::$outboundGuard = null;
        $blocked = $this->blockedOutbound;

        parent::tearDown();

        $this->assertSame([], $blocked, 'No automated test may reach a real SMS / WhatsApp provider.');
    }

    /**
     * Ensure a role exists on the web guard and return it.
     *
     * Spatie caches its permission map; RefreshDatabase truncates the tables
     * underneath that cache, so it has to be reset per test or roles created
     * here appear missing.
     */
    protected function makeRole(string $name): Role
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    /** Create an active user in $society holding $role. */
    protected function makeUserWithRole(Society $society, string $role, array $overrides = []): User
    {
        $this->makeRole($role);

        $user = User::create(array_merge([
            'society_id' => $society->id,
            'name'       => $role . ' User',
            'email'      => strtolower(str_replace(' ', '', $role)) . uniqid() . '@test.local',
            'phone'      => '9' . random_int(100000000, 999999999),
            'password'   => bcrypt('correct-horse-battery-staple'),
            'status'     => 'active',
        ], $overrides));

        $user->assignRole($role);

        return $user;
    }

    /** Create a society with one building. */
    protected function makeSociety(string $name = 'Test Society'): Society
    {
        $slug = strtolower(str_replace(' ', '-', $name)) . '-' . uniqid();

        return Society::create([
            'name'    => $name,
            'slug'    => $slug,
            'email'   => $slug . '@test.local',
            'phone'   => '9' . random_int(100000000, 999999999),
            'address' => 'Test Address',
            'city'    => 'Test City',
            'state'   => 'Test State',
            'pincode' => '000000',
            'country' => 'India',
            'status'  => 'active',
        ]);
    }

    protected function makeBuilding(Society $society, string $name = 'Tower A'): Building
    {
        return Building::create([
            'society_id'   => $society->id,
            'name'         => $name,
            'total_floors' => 5,
        ]);
    }

    protected function makeFlat(Society $society, Building $building, string $number = '101'): Flat
    {
        return Flat::create([
            'society_id'  => $society->id,
            'building_id' => $building->id,
            'flat_number' => $number,
            'floor'       => 1,
            'status'      => 'occupied',
        ]);
    }

    /** Create an active user with a resident record on the given flat. */
    protected function makeResidentUser(Society $society, Flat $flat, array $overrides = []): User
    {
        $user = User::create(array_merge([
            'society_id' => $society->id,
            'name'       => 'Resident ' . $flat->flat_number,
            'email'      => 'resident' . uniqid() . '@test.local',
            'phone'      => '9' . random_int(100000000, 999999999),
            'password'   => bcrypt('password123'),
            'status'     => 'active',
        ], $overrides));

        Resident::create([
            'society_id'   => $society->id,
            'flat_id'      => $flat->id,
            'user_id'      => $user->id,
            'name'         => $user->name,
            'email'        => $user->email,
            'phone'        => $user->phone,
            'type'         => 'tenant',
            'move_in_date' => now()->toDateString(),
            'status'       => 'active',
        ]);

        return $user;
    }
}
