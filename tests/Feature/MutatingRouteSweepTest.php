<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Owner;
use App\Models\Society;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VillaArea;
use Database\Seeders\RoyalEdenDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Every mutating web route (POST/PUT/PATCH/DELETE), for every role and for a
 * guest, against a world with real rows, submitted with an EMPTY payload and
 * real ids:
 *
 *   1. nothing may answer 5xx - an empty form is a validation error, a wrong
 *      role a refusal, a missing record a 404, never a crash;
 *   2. a role that has no business with the area must not get a success
 *      (2xx, or a redirect carrying a `success` flash) - so a hidden button is
 *      never the only thing standing between a Tenant and an admin action;
 *   3. a guest gets nothing but a refusal.
 *
 * Complements RoleRouteSweepTest (pages) and SuperAdminSurfaceTest.
 */
class MutatingRouteSweepTest extends TestCase
{
    use RefreshDatabase;

    private Society $eden;
    private array $ids = [];

    private const SKIP = '#^(logout|impersonation|login|register|forgot-password|reset-password|password|sanctum|livewire|_ignition|storage|\{locale\})#';

    /** Areas a non-office role must never be able to act in. */
    private const OFFICE_ONLY = '#^(super-admin|admin|users|settings|owners|villas|villa-areas|buildings|flats|societies|service-providers|expenses|notices-pending)(/|$)#';

    public static function roles(): array
    {
        return array_map(fn ($r) => [$r], ['Super Admin', 'Admin', 'Owner', 'Tenant', 'Guard', 'Staff', 'Accountant', 'guest']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        Storage::fake('public');
        foreach (['Super Admin', 'Admin', 'Owner', 'Tenant', 'Guard', 'Staff', 'Accountant', 'Villa Owner', 'Apartment Owner'] as $role) {
            $this->makeRole($role);
        }

        $this->makeSociety('Neighbour');
        $this->eden = $this->makeSociety('Royal Eden');
        $area = VillaArea::create(['society_id' => $this->eden->id, 'name' => 'A', 'status' => 'active']);
        foreach (['158' => 'Naresh', '159' => 'Lokesh', '160' => 'Suraj'] as $no => $name) {
            Flat::create(['society_id' => $this->eden->id, 'villa_area_id' => $area->id, 'property_type' => 'villa', 'flat_number' => $no, 'status' => 'vacant']);
            $owner = Owner::create(['society_id' => $this->eden->id, 'name' => $name, 'email' => strtolower($name) . '@o.test', 'phone' => '0000000' . $no, 'status' => 'active', 'property_type' => 'villa', 'villa_area_id' => $area->id, 'villa_no' => $no]);
            $this->makeUserWithRole($this->eden, 'Owner', ['name' => $name, 'email' => strtolower($name) . '@o.test', 'owner_id' => $owner->id]);
        }
        $tu = $this->makeUserWithRole($this->eden, 'Tenant', ['name' => 'Piyus', 'email' => 'piyus@t.test']);
        $tenant = Tenant::create(['society_id' => $this->eden->id, 'flat_id' => Flat::where('flat_number', '159')->value('id'), 'user_id' => $tu->id, 'name' => 'Piyus', 'email' => 'piyus@t.test', 'phone' => '0000000001', 'move_in_date' => '2026-09-22', 'status' => 'active']);
        $tu->update(['tenant_id' => $tenant->id]);
        $this->makeUserWithRole($this->eden, 'Admin', ['email' => 'admin@e.test']);
        config(['messaging.enabled' => false]);
        $this->seed(RoyalEdenDemoSeeder::class);

        $this->ids = $this->sampleIds();
    }

    /** param name => first id belonging to Royal Eden (or global). */
    private function sampleIds(): array
    {
        $first = fn (string $table, ?string $where = null, bool $scoped = true) => DB::table($table)
            ->when($scoped, fn ($q) => $q->where('society_id', $this->eden->id))
            ->when($where, fn ($q) => $q->whereRaw($where))->orderBy('id')->value('id');

        return array_filter([
            'payment' => $first('payments'), 'society' => $this->eden->id, 'document' => $first('society_documents'), 'society_document' => $first('society_documents'),
            'societyDocument' => $first('society_documents'), 'rent' => $first('maintenance_bills'), 'bill' => $first('maintenance_bills'),
            'facility' => $first('facilities'), 'facility_booking' => $first('facility_bookings'), 'visitor' => $first('visitors'), 'notice' => $first('notices'),
            'flat' => $first('flats'), 'villa' => $first('flats', "property_type='villa'"), 'villa_area' => $first('villa_areas'), 'event' => $first('events'),
            'owner' => $first('owners'), 'resident' => $first('residents'), 'utility_bill' => $first('utility_bills'), 'access_pass' => $first('access_passes'),
            'expense' => $first('expenses'), 'complaint' => $first('complaints'), 'tenant' => $first('tenants'), 'user' => $first('users', "email like '%@o.test'"),
            'shift' => $first('shifts'), 'gate' => $first('gates'), 'request' => $first('service_requests'),
            'role' => DB::table('roles')->where('name', 'Staff')->value('id'), 'package' => $first('subscription_plans', null, false),
            'email_template' => $first('email_templates', null, false), 'emailLog' => $first('email_logs'), 'service_provider' => $first('service_providers'),
            'building' => $first('buildings'), 'billing' => $first('subscriptions', null, false),
        ]);
    }

    /** @return list<array{0:string,1:string,2:string}> [method, concrete uri, route pattern] */
    private function endpoints(): array
    {
        $out = [];
        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            $uri = ltrim($route->uri(), '/');
            if (str_starts_with($uri, 'api/') || preg_match(self::SKIP, $uri)) {
                continue;
            }
            $concrete = '/' . preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => $this->ids[$m[1]] ?? '999999', $uri);
            foreach (array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS']) as $method) {
                $out[] = [$method === 'PATCH' ? 'PATCH' : $method, $concrete, $uri];
            }
        }

        return $out;
    }

    private function actor(string $role): ?User
    {
        return match ($role) {
            'guest' => null,
            'Super Admin' => $this->makeUserWithRole($this->eden, 'Super Admin', ['society_id' => null, 'email' => 'super@e.test']),
            'Owner' => User::where('society_id', $this->eden->id)->where('email', 'suraj@o.test')->firstOrFail(),
            'Tenant' => User::where('email', 'piyus@t.test')->firstOrFail(),
            'Admin' => User::where('email', 'admin@e.test')->firstOrFail(),
            default => $this->makeUserWithRole($this->eden, $role, ['email' => strtolower($role) . '@e.test']),
        };
    }

    public function test_the_surface_was_enumerated(): void
    {
        $this->assertGreaterThanOrEqual(200, count($this->endpoints()), 'about 250 mutating route/verb pairs when written');
        $this->assertNotEmpty($this->ids['facility_booking'] ?? null, 'the world has real rows to bind');
    }

    /** @dataProvider roles */
    public function test_no_mutating_route_crashes_or_succeeds_out_of_role(string $role): void
    {
        $actor = $this->actor($role);
        $crashed = [];
        $leaked = [];

        foreach ($this->endpoints() as [$method, $uri, $pattern]) {
            $actor ? $this->actingAs($actor) : $this->app['auth']->forgetGuards();
            try {
                $response = $this->call($method, $uri);
            } catch (\Throwable $e) {
                $crashed[] = "{$method} {$uri} -> " . get_class($e) . ': ' . substr($e->getMessage(), 0, 90);
                continue;
            }
            $status = $response->getStatusCode();

            if ($status >= 500) {
                $crashed[] = "{$method} {$uri} -> {$status}";
                continue;
            }

            $succeeded = ($status >= 200 && $status < 300) || ($status >= 300 && $status < 400 && $response->getSession()?->has('success'));
            $outOfRole = $role === 'guest'
                || (!in_array($role, ['Super Admin', 'Admin'], true) && preg_match(self::OFFICE_ONLY, $pattern))
                || ($role !== 'Super Admin' && str_starts_with($pattern, 'super-admin'));

            if ($succeeded && $outOfRole) {
                $leaked[] = "{$method} {$uri} -> {$status}" . ($status >= 300 ? ' (success flash)' : '');
            }
        }

        $this->assertSame([], $crashed, "{$role}: routes that crashed on an empty submission:\n  " . implode("\n  ", $crashed));
        $this->assertSame([], $leaked, "{$role}: routes that succeeded outside the role's remit:\n  " . implode("\n  ", $leaked));
    }
}
