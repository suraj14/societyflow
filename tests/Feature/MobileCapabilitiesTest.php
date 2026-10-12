<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The mobile app learns what an account may do from `user.capabilities` / `user.roles` (App\Support\MobileCapabilities),
 * not from a single role string. Each capability is cross-checked against what the REAL endpoint answers, so the
 * matrix cannot drift from the backend, and multi-role accounts get the union of their roles.
 *
 * Also: the booking 403 in the Flutter report. The server was right (Admin is not a resident and holds no
 * `book_facility`); the app showed a Book button anyway and printed a generic "role/permission check failed".
 */
class MobileCapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    private $society;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);   // the real role -> permission map
        $this->society = $this->makeSociety('Alpha');
        SystemSetting::set('features', 'enable_events', true, 'boolean', $this->society->id);
    }

    private function account(string ...$roles): User
    {
        $user = $this->makeUserWithRole($this->society, $roles[0]);
        foreach (array_slice($roles, 1) as $r) {
            $this->makeRole($r);
            $user->assignRole($r);
        }

        return $user;
    }

    /** A tenant/owner with an actual unit, as a resident has. */
    private function withUnit(User $user): User
    {
        $flat = $this->makeFlat($this->society, $this->makeBuilding($this->society), 'F' . random_int(100, 999));
        $record = Tenant::create(['society_id' => $this->society->id, 'flat_id' => $flat->id, 'user_id' => $user->id, 'name' => 'T', 'email' => uniqid() . '@t.local', 'phone' => '0000000001', 'move_in_date' => '2026-09-01', 'status' => 'active']);
        $user->update(['tenant_id' => $record->id]);

        return $user->fresh();
    }

    private function me(User $user): array
    {
        Sanctum::actingAs($user);
        $json = $this->getJson('/api/v1/auth/me')->assertOk()->json();

        return $json['user'] ?? $json['data'] ?? $json;
    }

    private function httpStatus(User $user, string $method, string $url, array $data = []): int
    {
        Sanctum::actingAs($user);

        return $this->json($method, $url, $data)->getStatusCode();
    }

    public function test_the_payload_lists_every_role_not_just_the_first(): void
    {
        $me = $this->me($this->account('Admin', 'Accountant'));

        $this->assertEqualsCanonicalizing(['Admin', 'Accountant'], $me['roles']);
        $this->assertArrayHasKey('role', $me, 'the legacy single-role key is kept for shipped builds');
    }

    public function test_admin_is_the_office_console_but_not_a_facility_booker_and_the_endpoints_agree(): void
    {
        $admin = $this->account('Admin');
        $c = $this->me($admin)['capabilities'];

        $this->assertTrue($c['admin_console']);
        $this->assertTrue($c['review_bookings']);
        $this->assertFalse($c['book_facility']);

        $this->assertSame(200, $this->httpStatus($admin, 'GET', '/api/v1/admin/dashboard'));
        // the booking endpoint answers 403 for Admin - exactly what the capability says
        $this->assertSame(403, $this->httpStatus($admin, 'POST', '/api/v1/facility-bookings', ['facility_id' => 1, 'booking_date' => '2030-01-01', 'start_time' => '10:00', 'end_time' => '11:00']));
    }

    public function test_a_tenant_may_book_and_is_not_an_office_user_and_the_endpoints_agree(): void
    {
        $tenant = $this->withUnit($this->account('Tenant'));
        $c = $this->me($tenant)['capabilities'];

        $this->assertTrue($c['book_facility']);
        $this->assertTrue($c['bills']);
        $this->assertFalse($c['admin_console']);
        $this->assertFalse($c['gate_scan']);

        $this->assertSame(403, $this->httpStatus($tenant, 'GET', '/api/v1/admin/dashboard'));
        $this->assertNotSame(403, $this->httpStatus($tenant, 'POST', '/api/v1/facility-bookings', ['facility_id' => 999999, 'booking_date' => '2030-01-01', 'start_time' => '10:00', 'end_time' => '11:00']), 'a resident passes the role gate (a missing facility is a 404/422, not a 403)');
    }

    public function test_staff_manage_visitors_but_cannot_book_request_services_or_use_the_office_console(): void
    {
        $staff = $this->account('Staff');
        $c = $this->me($staff)['capabilities'];

        $this->assertTrue($c['visitor_manage']);
        $this->assertFalse($c['book_facility']);
        $this->assertFalse($c['request_service']);
        $this->assertFalse($c['admin_console']);
        $this->assertSame(403, $this->httpStatus($staff, 'GET', '/api/v1/admin/dashboard'));
        $this->assertSame(403, $this->httpStatus($staff, 'POST', '/api/v1/facility-bookings', ['facility_id' => 1, 'booking_date' => '2030-01-01', 'start_time' => '10:00', 'end_time' => '11:00']));
    }

    public function test_a_guard_gets_only_the_gate_surface_and_the_endpoints_agree(): void
    {
        $guard = $this->account('Guard');
        $c = $this->me($guard)['capabilities'];

        $this->assertTrue($c['gate_scan']);
        $this->assertTrue($c['guard_shift']);
        $this->assertTrue($c['visitor_manage']);
        foreach (['book_facility', 'view_facilities', 'bills', 'file_complaint', 'request_service', 'access_passes', 'admin_console'] as $key) {
            $this->assertFalse($c[$key], $key);
        }
        $this->assertTrue($c['notices']);
        $this->assertTrue($c['services_directory']);

        $this->assertSame(403, $this->httpStatus($guard, 'GET', '/api/v1/facilities'), 'RestrictGuardApiAccess');
        $this->assertSame(403, $this->httpStatus($guard, 'GET', '/api/v1/bills'));
        $this->assertSame(200, $this->httpStatus($guard, 'GET', '/api/v1/notices'));
    }

    public function test_an_accountant_has_no_office_console_gate_or_booking_rights(): void
    {
        $c = $this->me($this->account('Accountant'))['capabilities'];

        foreach (['admin_console', 'gate_scan', 'book_facility', 'visitor_manage'] as $key) {
            $this->assertFalse($c[$key], $key);
        }
    }

    public function test_multi_role_accounts_get_the_union_of_their_roles(): void
    {
        $ownerTenant = $this->withUnit($this->account('Apartment Owner', 'Tenant'));
        $this->assertTrue($this->me($ownerTenant)['capabilities']['book_facility']);

        $adminAccountant = $this->me($this->account('Admin', 'Accountant'))['capabilities'];
        $this->assertTrue($adminAccountant['admin_console']);

        // a Guard who is also Admin is NOT confined to the gate surface (RestrictGuardApiAccess)
        $guardAdmin = $this->account('Guard', 'Admin');
        $c = $this->me($guardAdmin)['capabilities'];
        $this->assertTrue($c['admin_console']);
        $this->assertTrue($c['guard_shift']);
        $this->assertSame(200, $this->httpStatus($guardAdmin, 'GET', '/api/v1/admin/dashboard'));
    }

    public function test_module_switches_turn_capabilities_off_and_events_is_enforced_by_the_api(): void
    {
        $tenant = $this->withUnit($this->account('Tenant'));

        SystemSetting::set('features', 'enable_facilities', false, 'boolean', $this->society->id);
        $this->assertFalse($this->me($tenant)['capabilities']['book_facility']);
        $this->assertFalse($this->me($tenant)['modules']['facilities']);

        // Events: default off -> capability false AND the endpoint refuses (it used to serve events regardless)
        SystemSetting::set('features', 'enable_events', false, 'boolean', $this->society->id);
        $this->assertFalse($this->me($tenant)['capabilities']['events']);
        $this->assertSame(403, $this->httpStatus($tenant, 'GET', '/api/v1/events'));

        SystemSetting::set('features', 'enable_events', true, 'boolean', $this->society->id);
        $this->assertTrue($this->me($tenant)['capabilities']['events']);
        $this->assertSame(200, $this->httpStatus($tenant, 'GET', '/api/v1/events'));
    }

    public function test_request_service_matches_the_endpoint_for_a_resident_and_for_staff(): void
    {
        $service = Service::create(['name' => 'Plumbing', 'category' => 'maintenance', 'is_enabled' => true, 'sort_order' => 1]);
        $payload = ['service_id' => $service->id, 'description' => 'Tap leaking'];

        $resident = $this->withUnit($this->account('Tenant'));
        $this->assertTrue($this->me($resident)['capabilities']['request_service']);
        $this->assertSame(201, $this->httpStatus($resident, 'POST', '/api/v1/service-requests', $payload));

        $staff = $this->account('Staff');
        $this->assertFalse($this->me($staff)['capabilities']['request_service']);
        $this->assertSame(403, $this->httpStatus($staff, 'POST', '/api/v1/service-requests', $payload));
    }
}
