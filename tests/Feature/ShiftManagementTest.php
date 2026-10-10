<?php

namespace Tests\Feature;

use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShiftManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_shift(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');

        $this->actingAs($admin)->post(route('admin.shifts.store'), [
            'name' => 'Night Shift',
            'start_time' => '22:00',
            'end_time' => '06:00',
            'grace_period_minutes' => 15,
        ])->assertRedirect(route('admin.shifts.index'));

        $this->assertDatabaseHas('shifts', ['name' => 'Night Shift', 'society_id' => $society->id]);
    }

    public function test_an_overnight_shift_is_correctly_detected(): void
    {
        $shift = new Shift(['start_time' => '22:00:00', 'end_time' => '06:00:00']);
        $this->assertTrue($shift->isOvernight());

        $dayShift = new Shift(['start_time' => '06:00:00', 'end_time' => '14:00:00']);
        $this->assertFalse($dayShift->isOvernight());
    }

    /**
     * The exact bug class this session's own Access Pass fix was found
     * for (naive same-day comparison silently excluding the hours after
     * midnight). An overnight shift starting on day D must cover the hours
     * after midnight on day D+1, not just up to 23:59 on D.
     */
    public function test_an_overnight_shift_window_correctly_spans_midnight(): void
    {
        $shift = new Shift(['start_time' => '22:00:00', 'end_time' => '06:00:00']);

        [$start, $end] = $shift->windowStartingOn('2026-01-10');

        $this->assertSame('2026-01-10 22:00:00', $start->toDateTimeString());
        $this->assertSame('2026-01-11 06:00:00', $end->toDateTimeString());

        $this->assertTrue($shift->coversMoment('2026-01-10', Carbon::parse('2026-01-10 23:30:00', 'Asia/Kolkata')));
        $this->assertTrue($shift->coversMoment('2026-01-10', Carbon::parse('2026-01-11 02:00:00', 'Asia/Kolkata')));
        $this->assertFalse($shift->coversMoment('2026-01-10', Carbon::parse('2026-01-11 07:00:00', 'Asia/Kolkata')));
        $this->assertFalse($shift->coversMoment('2026-01-10', Carbon::parse('2026-01-10 21:00:00', 'Asia/Kolkata')));
    }

    public function test_a_day_shift_window_does_not_spill_into_the_next_day(): void
    {
        $shift = new Shift(['start_time' => '06:00:00', 'end_time' => '14:00:00']);

        [$start, $end] = $shift->windowStartingOn('2026-01-10');

        $this->assertSame('2026-01-10 06:00:00', $start->toDateTimeString());
        $this->assertSame('2026-01-10 14:00:00', $end->toDateTimeString());
    }

    public function test_admin_cannot_edit_another_societys_shift(): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        $adminB = $this->makeUserWithRole($societyB, 'Admin');

        $shiftA = Shift::create([
            'society_id' => $societyA->id, 'name' => 'Morning', 'start_time' => '06:00', 'end_time' => '14:00',
            'grace_period_minutes' => 15, 'is_active' => true,
        ]);

        $this->actingAs($adminB)->get(route('admin.shifts.edit', $shiftA))->assertForbidden();
    }

    public function test_guard_cannot_manage_shifts(): void
    {
        $society = $this->makeSociety();
        $guard = $this->makeUserWithRole($society, 'Guard');

        $this->actingAs($guard)->get(route('admin.shifts.index'))->assertForbidden();
    }
}
