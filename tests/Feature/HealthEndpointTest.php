<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The mobile app discovers its server by scanning the local subnet and used
 * to accept the first host that completed a TCP connection on port 8000,
 * then send that host the user's bearer token. Any machine on the network
 * listening on that port would have received it.
 *
 * The app now requires this endpoint to answer with the application marker
 * before trusting a host, so the contract has to stay stable.
 */
class HealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_identifies_the_application(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertExactJson([
                'application' => 'societyflow',
                'api'         => 'v1',
                'status'      => 'ok',
            ]);
    }

    public function test_health_endpoint_needs_no_authentication(): void
    {
        // Discovery runs before login, so this must work with no token.
        $this->getJson('/api/v1/health')->assertOk();
    }

    public function test_health_endpoint_exposes_no_tenant_data(): void
    {
        $society = $this->makeSociety('Very Secret Society');

        $body = $this->getJson('/api/v1/health')->assertOk()->getContent();

        $this->assertStringNotContainsString($society->name, $body);
        $this->assertStringNotContainsString('society_id', $body);
    }
}
