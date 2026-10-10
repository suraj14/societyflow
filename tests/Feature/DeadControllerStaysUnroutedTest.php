<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * App\Http\Controllers\SuperAdmin\SettingsController is dead code: every settings route points at the unified
 * App\Http\Controllers\SettingsController. The dead copy predates the messaging kill switch and the secret-masking
 * rules (it can send mail and talk to S3 directly), so it must not be wired up by accident. Delete it, or fix it
 * to match the live controller first - this test fails the moment a route points at it.
 */
class DeadControllerStaysUnroutedTest extends TestCase
{
    public function test_the_legacy_super_admin_settings_controller_has_no_route(): void
    {
        $routed = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with(ltrim((string) ($r->getActionName()), '\\'), 'App\\Http\\Controllers\\SuperAdmin\\SettingsController'))
            ->map(fn ($r) => $r->methods()[0] . ' /' . $r->uri())
            ->values()->all();

        $this->assertSame([], $routed);
        $this->assertTrue(class_exists(\App\Http\Controllers\SuperAdmin\SettingsController::class), 'if it was deleted, delete this test too');
    }
}
