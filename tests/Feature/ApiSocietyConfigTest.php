<?php

namespace Tests\Feature;

use App\Enums\SocietyPropertyType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The mobile app must be told what kind of society it is talking to.
 *
 * Without this the app can only guess - typically by checking whether
 * villa_number happens to be populated - which shows apartment UI inside a
 * villa township whenever a unit has not been assigned yet. Login and
 * profile now both carry the society's own capability.
 */
class ApiSocietyConfigTest extends TestCase
{
    use RefreshDatabase;

    private function residentIn(SocietyPropertyType $type): array
    {
        $society = $this->makeSociety();
        $society->update(['property_type' => $type->value]);

        $flat = $this->makeFlat($society, $this->makeBuilding($society));
        $user = $this->makeResidentUser($society, $flat);

        return [$society->fresh(), $user];
    }

    public function test_login_returns_the_society_capability(): void
    {
        [$society, $user] = $this->residentIn(SocietyPropertyType::Villa);

        $body = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()->json('user');

        $this->assertSame('villa', $body['property_type']);
        $this->assertSame($society->id, $body['society']['id']);
        $this->assertSame('villa', $body['society']['property_type']);
        $this->assertTrue($body['society']['supports_villas']);
        $this->assertFalse($body['society']['supports_apartments']);
        $this->assertFalse($body['society']['is_mixed']);
    }

    public function test_me_returns_the_society_capability(): void
    {
        [, $user] = $this->residentIn(SocietyPropertyType::Apartment);
        Sanctum::actingAs($user);

        $body = $this->getJson('/api/v1/auth/me')->assertOk()->json('data');

        $this->assertSame('apartment', $body['property_type']);
        $this->assertTrue($body['society']['supports_apartments']);
        $this->assertFalse($body['society']['supports_villas']);
    }

    public function test_profile_returns_the_society_capability(): void
    {
        [, $user] = $this->residentIn(SocietyPropertyType::Both);
        Sanctum::actingAs($user);

        $body = $this->getJson('/api/v1/profile')->assertOk()->json('data');

        $this->assertSame('both', $body['property_type']);
        $this->assertTrue($body['society']['supports_apartments']);
        $this->assertTrue($body['society']['supports_villas']);
        $this->assertTrue($body['society']['is_mixed']);
    }

    /** Older builds that ignore `society` must keep parsing the response. */
    public function test_existing_keys_are_unchanged(): void
    {
        [, $user] = $this->residentIn(SocietyPropertyType::Villa);
        Sanctum::actingAs($user);

        $body = $this->getJson('/api/v1/auth/me')->assertOk()->json('data');

        foreach (['id', 'name', 'email', 'phone', 'role', 'status', 'society_name', 'society_id', 'is_owner', 'is_tenant'] as $key) {
            $this->assertArrayHasKey($key, $body, "the API dropped the existing key '{$key}'");
        }
    }
}
