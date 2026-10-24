<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Settings -> Features: a switched-off module is refused (web and API), per society, for every role except the global Super Admin. */
class ModuleSwitchEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private $a;
    private $b;
    private $ownerA;
    private $ownerB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = $this->makeSociety('Alpha');
        $this->b = $this->makeSociety('Beta');
        $this->ownerA = $this->makeUserWithRole($this->a, 'Owner');
        $this->ownerB = $this->makeUserWithRole($this->b, 'Owner');
    }

    private function off(string $key, $society): void
    {
        SystemSetting::set('features', $key, false, 'boolean', $society->id);
    }

    public function test_path_to_switch_mapping_covers_web_and_api_spellings(): void
    {
        foreach (['complaints', 'complaints/5', 'api/v1/complaints', 'api/v1/admin/complaints/3/status'] as $p) {
            $this->assertSame('enable_complaints', EnsureModuleEnabled::switchFor($p), $p);
        }
        $this->assertSame('enable_facilities', EnsureModuleEnabled::switchFor('api/v1/admin/facility-bookings/2/status'));
        $this->assertSame('enable_visitors', EnsureModuleEnabled::switchFor('visitors/create'));
        $this->assertSame('enable_notices', EnsureModuleEnabled::switchFor('notices-history'));
        $this->assertSame('enable_services', EnsureModuleEnabled::switchFor('api/v1/service-requests'));
        $this->assertNull(EnsureModuleEnabled::switchFor('dashboard'));
        $this->assertNull(EnsureModuleEnabled::switchFor('api/v1/payments'));
    }

    public function test_an_unset_switch_means_on(): void
    {
        $this->actingAs($this->ownerA)->get(route('complaints.index'))->assertOk();
        Sanctum::actingAs($this->ownerA);
        $this->getJson('/api/v1/complaints')->assertOk();
    }

    public function test_switching_complaints_off_refuses_web_and_api_for_that_society_only(): void
    {
        $this->off('enable_complaints', $this->a);

        $this->actingAs($this->ownerA)->get(route('complaints.index'))->assertForbidden();
        Sanctum::actingAs($this->ownerA);
        $this->getJson('/api/v1/complaints')->assertForbidden()->assertJson(['success' => false, 'module_disabled' => true]);
        $this->postJson('/api/v1/complaints', ['title' => 'x', 'description' => 'y', 'priority' => 'low'])->assertForbidden();

        $this->actingAs($this->ownerB)->get(route('complaints.index'))->assertOk();
        Sanctum::actingAs($this->ownerB);
        $this->getJson('/api/v1/complaints')->assertOk();
    }

    public function test_every_role_of_the_society_is_refused_but_the_super_admin_is_not(): void
    {
        $this->off('enable_visitors', $this->a);
        $admin = $this->makeUserWithRole($this->a, 'Admin');
        $super = $this->makeUserWithRole($this->a, 'Super Admin', ['society_id' => null]);

        $this->actingAs($admin)->get(route('visitors.index'))->assertForbidden();
        $this->actingAs($this->ownerA)->get(route('visitors.index'))->assertForbidden();
        $this->actingAs($super)->get(route('visitors.index'))->assertOk();
    }

    public function test_switching_back_on_restores_access_and_other_modules_were_never_affected(): void
    {
        $this->off('enable_notices', $this->a);
        $this->actingAs($this->ownerA)->get(route('notices.index'))->assertForbidden();
        $this->actingAs($this->ownerA)->get(route('complaints.index'))->assertOk();

        SystemSetting::set('features', 'enable_notices', true, 'boolean', $this->a->id);

        $this->actingAs($this->ownerA)->get(route('notices.index'))->assertOk();
    }

    public function test_facilities_and_services_switches_cover_their_api_spellings(): void
    {
        $this->off('enable_facilities', $this->a);
        $this->off('enable_services', $this->a);
        Sanctum::actingAs($this->ownerA);

        $this->getJson('/api/v1/facilities')->assertForbidden();
        $this->getJson('/api/v1/facility-bookings')->assertForbidden();
        $this->getJson('/api/v1/services')->assertForbidden();
        $this->getJson('/api/v1/service-requests')->assertForbidden();
        $this->getJson('/api/v1/society/info')->assertOk();   // an unrelated endpoint is untouched
    }
}
