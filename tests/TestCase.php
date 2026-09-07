<?php

namespace Tests;

use App\Models\Building;
use App\Models\Flat;
use App\Models\Resident;
use App\Models\Society;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

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
