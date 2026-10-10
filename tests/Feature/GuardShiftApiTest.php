<?php

namespace Tests\Feature;

use App\Models\Gate;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Society;
use App\Models\User;
use App\Services\GuardShiftService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The Guard app's Today's Shift. The roster existed only on the web: a guard
 * who opened the app saw nothing about their shift, gate or timing.
 *
 * Fixed "now" is Saturday 2026-10-10 10:00 IST (04:30 UTC) - the society
 * runs on Asia/Kolkata while the application stores UTC, which is the case
 * that used to be wrong.
 */
class GuardShiftApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-10 04:30:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function gate(Society $society, string $name = 'Main Gate'): Gate
    {
        return Gate::create(['society_id' => $society->id, 'name' => $name, 'type' => 'main', 'is_active' => true]);
    }

    private function shift(Society $society, string $name, string $start, string $end, int $grace = 15): Shift
    {
        return Shift::create(['society_id' => $society->id, 'name' => $name, 'start_time' => $start, 'end_time' => $end, 'grace_period_minutes' => $grace, 'is_active' => true]);
    }

    private function assign(Society $society, User $guard, Shift $shift, Gate $gate, string $date = '2026-10-10'): ShiftAssignment
    {
        return ShiftAssignment::create([
            'society_id' => $society->id, 'shift_id' => $shift->id, 'gate_id' => $gate->id,
            'guard_user_id' => $guard->id, 'date' => $date, 'status' => ShiftAssignment::STATUS_SCHEDULED,
        ]);
    }

    private function guard(Society $society, string $name = 'Guard One'): User
    {
        return $this->makeUserWithRole($society, 'Guard', ['name' => $name]);
    }

    // ── what the guard sees ─────────────────────────────────────────────────

    public function test_a_guard_sees_todays_shift_with_gate_timing_and_status(): void
    {
        $society = $this->makeSociety();
        $guard = $this->guard($society);
        $this->assign($society, $guard, $this->shift($society, 'Day Shift', '09:00', '18:00'), $this->gate($society));

        Sanctum::actingAs($guard);
        $r = $this->getJson('/api/v1/guard/shift')->assertOk();

        $r->assertJsonPath('data.current.shift_name', 'Day Shift')
            ->assertJsonPath('data.current.gate_name', 'Main Gate')
            ->assertJsonPath('data.current.start_time', '09:00')
            ->assertJsonPath('data.current.end_time', '18:00')
            ->assertJsonPath('data.current.phase', 'active')
            ->assertJsonPath('data.current.phase_label', 'Active')
            ->assertJsonPath('data.current.can_start', true)
            ->assertJsonPath('data.current.can_end', false)
            ->assertJsonPath('data.timezone', 'Asia/Kolkata');
        $this->assertCount(1, $r->json('data.today'));
        $this->assertStringContainsString('+05:30', $r->json('data.current.starts_at'), 'the shift window is on the society clock');
        $this->assertNull($r->json('data.next'));
    }

    public function test_before_the_shift_it_is_shown_as_upcoming_not_as_no_shift(): void
    {
        $society = $this->makeSociety();
        $guard = $this->guard($society);
        $this->assign($society, $guard, $this->shift($society, 'Evening Shift', '16:00', '23:00'), $this->gate($society));

        Sanctum::actingAs($guard);
        $r = $this->getJson('/api/v1/guard/shift')->assertOk();

        $this->assertNull($r->json('data.current'), 'not on yet');
        $this->assertSame('upcoming', $r->json('data.today.0.phase'));
        $this->assertSame('Upcoming', $r->json('data.today.0.phase_label'));
        $this->assertSame('Evening Shift', $r->json('data.next.shift_name'));
        $this->assertFalse($r->json('data.today.0.can_start'), 'cannot start before the shift');
    }

    public function test_after_the_shift_it_is_ended_or_missed(): void
    {
        $society = $this->makeSociety();
        $worked = $this->guard($society, 'Worked');
        $absent = $this->guard($society, 'Never came');
        $gate = $this->gate($society);
        $early = $this->shift($society, 'Early', '05:00', '09:00');
        $a = $this->assign($society, $worked, $early, $gate);
        $this->assign($society, $absent, $early, $gate);
        $service = new GuardShiftService();
        $service->startShift($a, Carbon::parse('2026-10-10 05:00:00', 'Asia/Kolkata'));

        Sanctum::actingAs($worked);
        $this->assertSame('ended', $this->getJson('/api/v1/guard/shift')->json('data.today.0.phase'), 'started and the window is over');

        Sanctum::actingAs($absent);
        $this->assertSame('missed', $this->getJson('/api/v1/guard/shift')->json('data.today.0.phase'), 'never started');
    }

    public function test_with_no_shift_today_it_says_so_and_lists_the_coming_ones(): void
    {
        $society = $this->makeSociety();
        $guard = $this->guard($society);
        $gate = $this->gate($society);
        $shift = $this->shift($society, 'Day Shift', '09:00', '18:00');
        $this->assign($society, $guard, $shift, $gate, '2026-10-12');
        $this->assign($society, $guard, $shift, $gate, '2026-10-30'); // beyond the 7-day window

        Sanctum::actingAs($guard);
        $r = $this->getJson('/api/v1/guard/shift')->assertOk();

        $this->assertNull($r->json('data.current'));
        $this->assertSame([], $r->json('data.today'));
        $this->assertCount(1, $r->json('data.upcoming'));
        $this->assertSame('2026-10-12', $r->json('data.upcoming.0.date'));
        $this->assertSame('2026-10-12', $r->json('data.next.date'));
    }

    public function test_an_overnight_shift_that_began_yesterday_is_still_todays_active_shift(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-09 20:30:00', 'UTC')); // 02:00 IST on the 10th
        $society = $this->makeSociety();
        $guard = $this->guard($society);
        $this->assign($society, $guard, $this->shift($society, 'Night', '22:00', '06:00'), $this->gate($society), '2026-10-09');

        Sanctum::actingAs($guard);
        $r = $this->getJson('/api/v1/guard/shift')->assertOk();

        $this->assertSame('Night', $r->json('data.current.shift_name'));
        $this->assertTrue($r->json('data.current.overnight'));
        $this->assertSame('active', $r->json('data.today.0.phase'));
    }

    // ── privacy and isolation ───────────────────────────────────────────────

    public function test_a_guard_never_sees_another_guards_assignment(): void
    {
        $society = $this->makeSociety();
        $mine = $this->guard($society, 'Mine');
        $theirs = $this->guard($society, 'Theirs');
        $gate = $this->gate($society);
        $this->assign($society, $theirs, $this->shift($society, 'Their Shift', '09:00', '18:00'), $gate);

        Sanctum::actingAs($mine);
        $r = $this->getJson('/api/v1/guard/shift')->assertOk();

        $this->assertNull($r->json('data.current'));
        $this->assertSame([], $r->json('data.today'));
        $this->assertStringNotContainsString('Their Shift', $r->getContent());
    }

    public function test_an_assignment_from_another_society_is_invisible(): void
    {
        $mine = $this->makeSociety('Mine');
        $other = $this->makeSociety('Other');
        $guard = $this->guard($mine);
        // The same person's account somehow carries a row in another society.
        $this->assign($other, $guard, $this->shift($other, 'Foreign', '09:00', '18:00'), $this->gate($other));

        Sanctum::actingAs($guard);
        $this->assertNull($this->getJson('/api/v1/guard/shift')->json('data.current'));
    }

    /** @dataProvider nonGuards */
    public function test_only_guards_have_a_shift(string $role): void
    {
        $user = $this->makeUserWithRole($this->makeSociety(), $role);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/guard/shift')->assertForbidden();
        $this->postJson('/api/v1/guard/shift/start')->assertForbidden();
        $this->postJson('/api/v1/guard/shift/end')->assertForbidden();
    }

    public static function nonGuards(): array
    {
        return ['tenant' => ['Tenant'], 'admin' => ['Admin'], 'staff' => ['Staff'], 'accountant' => ['Accountant']];
    }

    // ── the office changes the roster, the guard sees it ────────────────────

    public function test_what_the_admin_assigns_on_the_web_appears_in_the_app(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $guard = $this->guard($society);
        $gate = $this->gate($society, 'Back Gate');
        $shift = $this->shift($society, 'Day Shift', '09:00', '18:00');

        $this->actingAs($admin)->post(route('admin.shift-assignments.store'), [
            'guard_user_id' => $guard->id, 'shift_id' => $shift->id, 'gate_id' => $gate->id, 'date' => '2026-10-10',
        ])->assertRedirect();

        Sanctum::actingAs($guard);
        $this->getJson('/api/v1/guard/shift')->assertOk()->assertJsonPath('data.current.gate_name', 'Back Gate');
    }

    public function test_a_replaced_guard_stops_seeing_the_shift_and_the_replacement_sees_it(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $original = $this->guard($society, 'Original');
        $cover = $this->guard($society, 'Cover');
        $assignment = $this->assign($society, $original, $this->shift($society, 'Day Shift', '09:00', '18:00'), $this->gate($society));

        $this->actingAs($admin)->post(route('admin.shift-assignments.replace', $assignment), [
            'replacement_guard_id' => $cover->id, 'reason' => 'Fever',
        ])->assertRedirect();

        Sanctum::actingAs($original);
        $this->assertNull($this->getJson('/api/v1/guard/shift')->json('data.current'), 'no stale shift');
        $this->assertSame([], $this->getJson('/api/v1/guard/shift')->json('data.today'));

        Sanctum::actingAs($cover);
        $this->assertSame('Day Shift', $this->getJson('/api/v1/guard/shift')->json('data.current.shift_name'));
    }

    // ── start, end, handover ────────────────────────────────────────────────

    public function test_start_and_end_record_real_attendance(): void
    {
        $society = $this->makeSociety();
        $guard = $this->guard($society);
        $a = $this->assign($society, $guard, $this->shift($society, 'Day Shift', '09:00', '18:00'), $this->gate($society));

        Sanctum::actingAs($guard);
        $this->postJson('/api/v1/guard/shift/end')->assertStatus(404); // nothing started yet

        // 10:00 IST is 60 minutes after the 09:00 start, past the 15-minute grace.
        $this->postJson('/api/v1/guard/shift/start')->assertOk()
            ->assertJsonPath('data.status', 'late')
            ->assertJsonPath('data.can_end', true)
            ->assertJsonPath('data.can_start', false);
        $this->assertSame(ShiftAssignment::STATUS_LATE, $a->fresh()->status);
        $this->assertNotNull($a->fresh()->checked_in_at);

        // The instant survives storage: 10:00 IST is 04:30 UTC in the database, and
        // the app is told 10:00 +05:30 - not IST digits read back as UTC (15:30 IST).
        $this->assertSame('04:30:00', $a->fresh()->checked_in_at->format('H:i:s'), 'stored in the storage timezone');
        $r = $this->getJson('/api/v1/guard/shift')->assertOk();
        $this->assertSame('2026-10-10T10:00:00+05:30', $r->json('data.current.checked_in_at'));

        $this->postJson('/api/v1/guard/shift/start')->assertStatus(422);

        $this->postJson('/api/v1/guard/shift/end')->assertOk()->assertJsonPath('data.phase', 'ended');
        $this->assertSame(ShiftAssignment::STATUS_CHECKED_OUT, $a->fresh()->status);
        $this->postJson('/api/v1/guard/shift/end')->assertStatus(404);
    }

    public function test_starting_on_time_is_present(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 03:40:00', 'UTC')); // 09:10 IST
        $society = $this->makeSociety();
        $guard = $this->guard($society);
        $this->assign($society, $guard, $this->shift($society, 'Day Shift', '09:00', '18:00'), $this->gate($society));

        Sanctum::actingAs($guard);
        $this->postJson('/api/v1/guard/shift/start')->assertOk()->assertJsonPath('data.status', 'present');
    }

    public function test_nothing_to_start_outside_the_shift(): void
    {
        $society = $this->makeSociety();
        $guard = $this->guard($society);
        $this->assign($society, $guard, $this->shift($society, 'Evening', '16:00', '23:00'), $this->gate($society));

        Sanctum::actingAs($guard);
        $this->postJson('/api/v1/guard/shift/start')->assertStatus(404)->assertJsonPath('message', 'You have no shift to start right now.');
    }

    public function test_a_guard_can_still_end_a_shift_after_its_window_closed(): void
    {
        $society = $this->makeSociety();
        $guard = $this->guard($society);
        $a = $this->assign($society, $guard, $this->shift($society, 'Early', '05:00', '09:00'), $this->gate($society));
        (new GuardShiftService())->startShift($a, Carbon::parse('2026-10-10 05:00:00', 'Asia/Kolkata'));

        Sanctum::actingAs($guard);
        $this->postJson('/api/v1/guard/shift/end')->assertOk();
    }

    public function test_handover_notes_are_saved_once_for_the_active_shift(): void
    {
        $society = $this->makeSociety();
        $guard = $this->guard($society);
        $a = $this->assign($society, $guard, $this->shift($society, 'Day Shift', '09:00', '18:00'), $this->gate($society));

        Sanctum::actingAs($guard);
        $this->postJson('/api/v1/guard/shift/handover', ['notes' => 'Gate lock is stiff'])->assertStatus(404);

        $this->postJson('/api/v1/guard/shift/start')->assertOk();
        $this->postJson('/api/v1/guard/shift/handover', ['notes' => 'Gate lock is stiff', 'pending_visitors_note' => '2 visitors waiting'])
            ->assertCreated()->assertJsonPath('data.handover.notes', 'Gate lock is stiff');
        $this->assertDatabaseHas('shift_handovers', ['shift_assignment_id' => $a->id, 'notes' => 'Gate lock is stiff']);

        $this->postJson('/api/v1/guard/shift/handover', ['notes' => 'again'])->assertStatus(422);
    }
    // ── the office removes an assignment or marks a guard absent ────────────

    public function test_an_unstarted_assignment_can_be_removed_and_the_guard_stops_seeing_it(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $guard = $this->guard($society);
        $a = $this->assign($society, $guard, $this->shift($society, 'Day Shift', '09:00', '18:00'), $this->gate($society));

        $this->actingAs($admin)->delete(route('admin.shift-assignments.destroy', $a))->assertRedirect();

        $this->assertDatabaseMissing('shift_assignments', ['id' => $a->id]);
        Sanctum::actingAs($guard);
        $this->assertNull($this->getJson('/api/v1/guard/shift')->json('data.current'), 'no stale shift');
    }

    public function test_a_started_or_finished_assignment_cannot_be_removed(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $guard = $this->guard($society);
        $a = $this->assign($society, $guard, $this->shift($society, 'Day Shift', '09:00', '18:00'), $this->gate($society));
        (new GuardShiftService())->startShift($a, Carbon::parse('2026-10-10 09:00:00', 'Asia/Kolkata'));

        $this->actingAs($admin)->delete(route('admin.shift-assignments.destroy', $a))->assertStatus(422);
        $this->assertDatabaseHas('shift_assignments', ['id' => $a->id]);
    }

    public function test_a_guard_who_never_started_a_shift_that_has_begun_can_be_marked_absent(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $guard = $this->guard($society);
        $a = $this->assign($society, $guard, $this->shift($society, 'Day Shift', '09:00', '18:00'), $this->gate($society));

        $this->actingAs($admin)->post(route('admin.shift-assignments.absent', $a))->assertRedirect();

        $this->assertSame(ShiftAssignment::STATUS_ABSENT, $a->fresh()->status);
        Sanctum::actingAs($guard);
        $this->assertSame('missed', $this->getJson('/api/v1/guard/shift')->json('data.today.0.phase'));
        $this->assertNull($this->getJson('/api/v1/guard/shift')->json('data.current'));
    }

    public function test_a_shift_that_has_not_begun_cannot_be_marked_absent(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $a = $this->assign($society, $this->guard($society), $this->shift($society, 'Evening', '16:00', '23:00'), $this->gate($society));

        $this->actingAs($admin)->post(route('admin.shift-assignments.absent', $a))->assertStatus(422);
        $this->assertSame(ShiftAssignment::STATUS_SCHEDULED, $a->fresh()->status);
    }

    public function test_an_admin_cannot_remove_or_mark_another_societys_assignment(): void
    {
        $mine = $this->makeSociety('Mine');
        $other = $this->makeSociety('Other');
        $admin = $this->makeUserWithRole($mine, 'Admin');
        $theirs = $this->assign($other, $this->guard($other), $this->shift($other, 'Day', '09:00', '18:00'), $this->gate($other));

        $this->actingAs($admin)->delete(route('admin.shift-assignments.destroy', $theirs))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.shift-assignments.absent', $theirs))->assertForbidden();
        $this->assertDatabaseHas('shift_assignments', ['id' => $theirs->id, 'status' => 'scheduled']);
    }

    public function test_a_guard_cannot_remove_or_mark_assignments(): void
    {
        $society = $this->makeSociety();
        $guard = $this->guard($society);
        $a = $this->assign($society, $guard, $this->shift($society, 'Day', '09:00', '18:00'), $this->gate($society));

        $this->actingAs($guard)->delete(route('admin.shift-assignments.destroy', $a))->assertForbidden();
        $this->actingAs($guard)->post(route('admin.shift-assignments.absent', $a))->assertForbidden();
        $this->assertDatabaseHas('shift_assignments', ['id' => $a->id]);
    }
}
