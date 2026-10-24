<?php

namespace Tests\Feature;

use App\Models\Society;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The gate in a browser on a laptop looks like the rest of the web app - sidebar, top bar, wide content - and on a
 * phone keeps its thumb-friendly layout. Either way it offers the gate's pages and nothing else.
 */
class GuardWebLayoutTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;
    private User $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->society = $this->makeSociety('Gate Society');
        $this->guard = $this->makeUserWithRole($this->society, 'Guard');

        // rows in every list, so the pages render the cards and not only their empty states
        $flat = $this->makeFlat($this->society, $this->makeBuilding($this->society), 'A-101');
        foreach ([['approval_status' => 'pending', 'entry_status' => 'pending'], ['approval_status' => 'allowed', 'entry_status' => 'pending'], ['approval_status' => 'allowed', 'entry_status' => 'entered', 'actual_entry_time' => now()]] as $i => $state) {
            Visitor::create(array_merge([
                'society_id' => $this->society->id, 'flat_id' => $flat->id, 'host_user_id' => $this->guard->id, 'visitor_name' => "Visitor {$i}",
                'visitor_phone' => '987654321' . $i, 'visitor_type' => 'guest', 'expected_entry_time' => now(),
            ], $state));
        }
    }

    public function test_the_gate_has_the_web_sidebar_and_top_bar_and_keeps_the_phone_tabs(): void
    {
        $html = $this->actingAs($this->guard)->get(route('guard.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="Gate navigation"', $html, 'the desktop sidebar');
        $this->assertStringContainsString('md:hidden fixed bottom-0', $html, 'the phone tab bar, hidden on wide screens');
        $this->assertStringContainsString('md:grid-cols-4', $html, 'the four counts sit in one row on a wide screen');
        $this->assertSame(1, substr_count($html, 'x-data="sfBell('), 'one notification bell, shared by both layouts');
        foreach (['guard.dashboard', 'guard.scan', 'guard.visitors.create', 'guard.history', 'guard.shift.current', 'guard.rules.index'] as $page) {
            $this->assertStringContainsString('href="' . route($page) . '"', $html, "the sidebar links {$page}");
        }
    }

    public function test_the_gate_offers_no_society_administration(): void
    {
        $html = $this->actingAs($this->guard)->get(route('guard.dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('aria-label="Main navigation"', $html, 'not the full app sidebar');
        foreach (['payments.index', 'owners.index', 'settings.index', 'notices.index'] as $module) {
            $this->assertStringNotContainsString('href="' . route($module) . '"', $html);
        }
    }

    public function test_every_gate_page_uses_the_gate_layout(): void
    {
        foreach (['guard.dashboard', 'guard.scan', 'guard.visitors.create', 'guard.history', 'guard.shift.current', 'guard.rules.index'] as $page) {
            $html = $this->actingAs($this->guard)->get(route($page))->assertOk()->getContent();

            $this->assertStringContainsString('aria-label="Gate navigation"', $html, "{$page} renders inside the gate layout");
            $this->assertStringNotContainsString('aria-label="Main navigation"', $html, "{$page} does not switch to the admin shell");
        }
    }
}
