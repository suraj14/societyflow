<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceAttendance;
use App\Models\ServiceProvider;
use App\Models\Society;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POST services/clock-in and clock-out sat under "all authenticated users" with no role or society check:
 * a Tenant, or an Admin of another society who guessed an id, could write attendance for any provider.
 * The page offers the buttons to Admin, Staff and owners only; the server now enforces the same, scoped
 * to the provider's society.
 */
class ServiceAttendanceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;
    private Society $other;
    private ServiceProvider $provider;
    private ServiceProvider $foreignProvider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Eden');
        $this->other = $this->makeSociety('Other');
        $service = Service::create(['name' => 'Maid', 'category' => 'home', 'is_enabled' => true]);
        $make = fn (Society $s, string $name) => ServiceProvider::create(['society_id' => $s->id, 'service_id' => $service->id, 'name' => $name, 'contact_number' => '0000000001', 'status' => 'active']);
        $this->provider = $make($this->society, 'Sunita');
        $this->foreignProvider = $make($this->other, 'Geeta');
    }

    private function clockIn(User $as, ServiceProvider $p)
    {
        return $this->actingAs($as)->post(route('services.clock-in'), ['service_provider_id' => $p->id]);
    }

    private function clockOut(User $as, ServiceProvider $p)
    {
        return $this->actingAs($as)->post(route('services.clock-out'), ['service_provider_id' => $p->id]);
    }

    /** @dataProvider allowedRoles */
    public function test_admin_staff_and_owners_can_mark_a_provider_in_and_out(string $role): void
    {
        $user = $this->makeUserWithRole($this->society, $role);

        $this->clockIn($user, $this->provider)->assertSessionHas('success');
        $row = ServiceAttendance::where('service_provider_id', $this->provider->id)->sole();
        $this->assertNotNull($row->clock_in_time);
        $this->assertSame($this->society->id, (int) $row->society_id);

        $this->clockOut($user, $this->provider)->assertSessionHas('success');
        $this->assertNotNull($row->fresh()->clock_out_time);
    }

    public static function allowedRoles(): array
    {
        return array_map(fn ($r) => [$r], ['Admin', 'Staff', 'Owner']);
    }

    /** @dataProvider refusedRoles */
    public function test_other_roles_cannot_record_attendance(string $role): void
    {
        $user = $this->makeUserWithRole($this->society, $role);

        $this->clockIn($user, $this->provider)->assertStatus(403);
        $this->clockOut($user, $this->provider)->assertStatus(403);
        $this->assertSame(0, ServiceAttendance::count());
    }

    public static function refusedRoles(): array
    {
        return array_map(fn ($r) => [$r], ['Tenant', 'Guard', 'Accountant']);
    }

    public function test_nobody_can_record_attendance_for_another_societys_provider(): void
    {
        foreach (['Admin', 'Staff', 'Owner'] as $role) {
            $user = $this->makeUserWithRole($this->society, $role);
            $this->clockIn($user, $this->foreignProvider)->assertStatus(403);
        }
        $this->assertSame(0, ServiceAttendance::count(), 'nothing was written for the foreign provider');
    }

    public function test_a_super_admin_records_it_against_the_providers_own_society(): void
    {
        $super = $this->makeUserWithRole($this->society, 'Super Admin', ['society_id' => null]);

        $this->clockIn($super, $this->foreignProvider)->assertSessionHas('success');

        $this->assertSame($this->other->id, (int) ServiceAttendance::sole()->society_id, 'not a null society');
    }

    public function test_the_existing_rules_still_hold(): void
    {
        $admin = $this->makeUserWithRole($this->society, 'Admin');

        $this->clockOut($admin, $this->provider)->assertSessionHas('error');              // never clocked in
        $this->clockIn($admin, $this->provider)->assertSessionHas('success');
        $this->clockIn($admin, $this->provider)->assertSessionHas('error');               // already in today
        $this->clockOut($admin, $this->provider)->assertSessionHas('success');
        $this->clockOut($admin, $this->provider)->assertSessionHas('error');              // already out
        $this->assertSame(1, ServiceAttendance::count());
    }
}
