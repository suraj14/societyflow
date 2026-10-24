<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Deployment gates: `php artisan route:cache` must work (no closure routes) and the delivery log must accept every outcome the code writes (MySQL kept an ENUM). */
class DeploymentCacheAndLogSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_route_uses_a_closure_so_route_cache_can_serialise_the_table(): void
    {
        $closures = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => ($r->getAction('uses') instanceof \Closure))
            ->map(fn ($r) => $r->methods()[0] . ' ' . $r->uri())
            ->values()->all();

        $this->assertSame([], $closures);
    }

    public function test_the_former_closure_routes_behave_as_before(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertExactJson(['application' => 'societyflow', 'api' => 'v1', 'status' => 'ok']);
        $this->get('/logout')->assertRedirect('/login');
        $this->getJson('/api/user')->assertUnauthorized();
        $this->get('/residents')->assertRedirect();   // guests are sent to sign in, never served
        $this->get('/staff/dashboard')->assertRedirect();
    }

    public function test_the_delivery_log_stores_every_outcome_status(): void
    {
        $society = $this->makeSociety();
        foreach (['pending', 'sent', 'failed', 'blocked', 'simulated'] as $status) {
            $row = EmailLog::create(['society_id' => $society->id, 'channel' => 'email', 'recipient_email' => "{$status}@example.test", 'trigger_action' => 'notice_published', 'status' => $status]);
            $this->assertSame($status, $row->fresh()->status);
        }
    }
}
