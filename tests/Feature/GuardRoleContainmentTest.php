<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A gate account stays on the gate.
 *
 * Before RestrictGuardAccess, a Guard logged into the web app and opened
 * /payments, /payments/create, /utility-bills, /facilities/create and the
 * resident portal at /my/dashboard - all 200. Two things combined to allow
 * it: 128 web routes carry no role middleware, and RestrictStaffAccess
 * only ever examined the Staff role, so nothing looked at Guards at all.
 *
 * These tests assert the server's answer, never a hidden menu. They also
 * pin the two ways the fix could go wrong: shutting a Guard out of their
 * own job, or restricting somebody who merely also holds the Guard role.
 */
class GuardRoleContainmentTest extends TestCase
{
    use RefreshDatabase;

    /** Modules a gate account has no business in. */
    public static function forbiddenPaths(): array
    {
        return [
            'payments list'       => ['/payments'],
            'record a payment'    => ['/payments/create'],
            'utility bills'       => ['/utility-bills'],
            'create utility bill' => ['/utility-bills/create'],
            'facilities'          => ['/facilities'],
            'create a facility'   => ['/facilities/create'],
            'events'              => ['/events'],
            'notices'             => ['/notices'],
            'create a notice'     => ['/notices/create'],
            'complaints'          => ['/complaints'],
            'services'            => ['/services'],
            'service providers'   => ['/services/providers'],
            'resident portal'     => ['/my/dashboard'],
            'resident bills'      => ['/my/bills'],
            'admin dashboard'     => ['/admin/dashboard'],
        ];
    }

    /**
     * @dataProvider forbiddenPaths
     */
    public function test_a_guard_is_refused_unrelated_modules(string $path): void
    {
        $society = $this->makeSociety();
        $guard   = $this->makeUserWithRole($society, 'Guard');

        $this->actingAs($guard)->get($path)->assertForbidden();
    }

    public function test_a_guard_keeps_their_own_gate_workflow(): void
    {
        $society = $this->makeSociety();
        $guard   = $this->makeUserWithRole($society, 'Guard');

        $this->actingAs($guard)->get('/guard')->assertOk();
        $this->actingAs($guard)->get('/guard/scan')->assertOk();
        $this->actingAs($guard)->get('/guard/visitors/create')->assertOk();
        $this->actingAs($guard)->get('/guard/history')->assertOk();
        $this->actingAs($guard)->get('/profile')->assertOk();
    }

    /**
     * The containment must not leak outward: an Admin who happens to also
     * be a Guard keeps the access their Admin role grants.
     */
    public function test_an_admin_who_is_also_a_guard_is_not_restricted(): void
    {
        $society = $this->makeSociety();
        $user    = $this->makeUserWithRole($society, 'Admin');
        $user->assignRole($this->makeRole('Guard'));

        $this->actingAs($user->fresh())->get('/payments')->assertOk();
    }

    /** Roles that are not Guards must be entirely unaffected by this middleware. */
    public function test_an_admin_still_reaches_the_modules_they_manage(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $this->actingAs($admin)->get('/payments')->assertOk();
        $this->actingAs($admin)->get('/notices')->assertOk();
        $this->actingAs($admin)->get('/facilities')->assertOk();
    }

    public function test_a_resident_still_reaches_their_own_modules(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $tenant  = $this->makeResidentUser($society, $flat);
        $tenant->assignRole($this->makeRole('Tenant'));

        $this->actingAs($tenant->fresh())->get('/notices')->assertOk();
        $this->actingAs($tenant->fresh())->get('/complaints')->assertOk();
    }

    /** Staff lost utility-bills, which had been left open "for testing". */
    public function test_staff_cannot_reach_utility_bills(): void
    {
        $society = $this->makeSociety();
        $staff   = $this->makeUserWithRole($society, 'Staff');

        $this->actingAs($staff)->get('/utility-bills')->assertForbidden();
    }
}
