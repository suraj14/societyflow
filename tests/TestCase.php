<?php

namespace Tests;

use App\Models\Building;
use App\Models\Flat;
use App\Models\Resident;
use App\Models\Society;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

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
