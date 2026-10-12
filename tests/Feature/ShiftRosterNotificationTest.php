<?php

namespace Tests\Feature;

use App\Models\Gate;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Services\GuardShiftService;
use App\Support\SocietyClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A roster change tells the guard it concerns - once, after it is committed, only inside the same society, never the
 * admin who made it. The channel is the in-app bell: there is no approved email / SMS / WhatsApp template for rosters, so
 * nothing external is sent (asserted: no mail, and the messaging guard in TestCase blocks any provider request).
 *
 *   assign           -> the guard: "New shift assigned"
 *   remove           -> the guard: "Shift cancelled"
 *   replace          -> the outgoing guard: "Shift reassigned"; the incoming guard: "New shift assigned"
 *   mark absent      -> the guard: "Marked absent"
 *   refused / rolled back / repeated change -> nobody
 */
class ShiftRosterNotificationTest extends TestCase
{
    use RefreshDatabase;

    private $society;
    private $admin;
    private $guard;
    private $other;
    private Gate $gate;
    private Shift $morning;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->society = $this->makeSociety('Alpha');
        $this->admin = $this->makeUserWithRole($this->society, 'Admin');
        $this->guard = $this->makeUserWithRole($this->society, 'Guard', ['name' => 'Gopal']);
        $this->other = $this->makeUserWithRole($this->society, 'Guard', ['name' => 'Ravi']);
        $this->gate = Gate::create(['society_id' => $this->society->id, 'name' => 'Main Gate', 'type' => 'main', 'is_active' => true]);
        $this->morning = Shift::create(['society_id' => $this->society->id, 'name' => 'Morning', 'start_time' => '06:00', 'end_time' => '14:00', 'grace_period_minutes' => 15, 'is_active' => true]);
    }

    private function day(int $offset = 1): string
    {
        return SocietyClock::today($this->society->id)->addDays($offset)->toDateString();
    }

    private function titles($user): array
    {
        return $user->fresh()->notifications()->pluck('data')->map(fn ($d) => $d['title'])->sort()->values()->all();
    }

    private function assignment(array $over = []): ShiftAssignment
    {
        return ShiftAssignment::create($over + ['society_id' => $this->society->id, 'shift_id' => $this->morning->id, 'gate_id' => $this->gate->id,
            'guard_user_id' => $this->guard->id, 'date' => $this->day(), 'status' => 'scheduled']);
    }

    public function test_assigning_a_guard_tells_that_guard_once_and_not_the_admin(): void
    {
        $post = fn () => $this->actingAs($this->admin)->post(route('admin.shift-assignments.store'), [
            'guard_user_id' => $this->guard->id, 'shift_id' => $this->morning->id, 'gate_id' => $this->gate->id, 'date' => $this->day(),
        ]);

        $post()->assertRedirect();
        $this->assertSame(['New shift assigned'], $this->titles($this->guard));
        $body = $this->guard->fresh()->notifications()->first()->data['body'];
        $this->assertStringContainsString('Morning at Main Gate', $body);
        $this->assertSame([], $this->titles($this->admin));
        $this->assertSame([], $this->titles($this->other));

        $post()->assertSessionHasErrors('guard_user_id');   // the duplicate is refused ...
        $this->assertSame(['New shift assigned'], $this->titles($this->guard), '... and tells nobody twice');
    }

    public function test_a_refused_assignment_tells_nobody(): void
    {
        $this->actingAs($this->admin)->post(route('admin.shift-assignments.store'), [
            'guard_user_id' => $this->guard->id, 'shift_id' => $this->morning->id, 'gate_id' => $this->gate->id, 'date' => SocietyClock::today($this->society->id)->subDay()->toDateString(),
        ])->assertSessionHasErrors('date');

        $this->assertSame([], $this->titles($this->guard));
    }

    public function test_an_inactive_guard_is_not_given_a_shift_or_a_notice(): void
    {
        $this->guard->update(['status' => 'inactive']);
        $this->actingAs($this->admin)->post(route('admin.shift-assignments.store'), [
            'guard_user_id' => $this->guard->id, 'shift_id' => $this->morning->id, 'gate_id' => $this->gate->id, 'date' => $this->day(),
        ])->assertSessionHasErrors('guard_user_id');

        $this->assertSame(0, ShiftAssignment::count());
        $this->assertSame([], $this->titles($this->guard));
    }

    public function test_removing_a_shift_tells_the_guard_it_is_cancelled(): void
    {
        $a = $this->assignment();

        $this->actingAs($this->admin)->delete(route('admin.shift-assignments.destroy', $a))->assertRedirect();

        $this->assertNull(ShiftAssignment::find($a->id));
        $this->assertSame(['Shift cancelled'], $this->titles($this->guard));
        $this->assertStringContainsString('Morning at Main Gate', $this->guard->fresh()->notifications()->first()->data['body']);
        $this->assertSame([], $this->titles($this->admin));
    }

    public function test_a_shift_that_cannot_be_removed_tells_nobody(): void
    {
        $a = $this->assignment(['status' => 'completed']);

        $this->actingAs($this->admin)->delete(route('admin.shift-assignments.destroy', $a))->assertSessionHas('error');

        $this->assertNotNull(ShiftAssignment::find($a->id));
        $this->assertSame([], $this->titles($this->guard));
    }

    public function test_replacing_a_guard_tells_both_guards_and_the_history_stays(): void
    {
        $a = $this->assignment();

        $this->actingAs($this->admin)->post(route('admin.shift-assignments.replace', $a), ['replacement_guard_id' => $this->other->id, 'reason' => 'Sick leave'])->assertSessionHas('success');

        $this->assertSame(['Shift reassigned'], $this->titles($this->guard), 'the outgoing guard');
        $this->assertSame(['New shift assigned'], $this->titles($this->other), 'the incoming guard');
        $this->assertSame('replaced', $a->fresh()->status);

        // repeating it is refused (the assignment is no longer open) and tells nobody again
        $this->actingAs($this->admin)->post(route('admin.shift-assignments.replace', $a->fresh()), ['replacement_guard_id' => $this->other->id, 'reason' => 'again'])->assertSessionHas('error');
        $this->assertSame(['Shift reassigned'], $this->titles($this->guard));
        $this->assertSame(['New shift assigned'], $this->titles($this->other));
    }

    public function test_marking_absent_tells_the_guard_once(): void
    {
        $a = $this->assignment(['date' => SocietyClock::today($this->society->id)->subDay()->toDateString()]);

        $this->actingAs($this->admin)->post(route('admin.shift-assignments.absent', $a))->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('admin.shift-assignments.absent', $a->fresh()))->assertSessionHas('error');

        $this->assertSame(['Marked absent'], $this->titles($this->guard));
    }

    public function test_the_notice_waits_for_the_commit(): void
    {
        $seenInside = null;
        DB::transaction(function () use (&$seenInside) {
            app(GuardShiftService::class)->assign(['society_id' => $this->society->id, 'shift_id' => $this->morning->id, 'gate_id' => $this->gate->id,
                'guard_user_id' => $this->guard->id, 'date' => $this->day(), 'created_by' => $this->admin->id]);
            $seenInside = $this->titles($this->guard);
        });

        $this->assertSame([], $seenInside, 'nothing is announced while the transaction is still open');
        $this->assertSame(['New shift assigned'], $this->titles($this->guard), 'and it is announced once it commits');
    }

    public function test_a_rolled_back_roster_change_tells_nobody(): void
    {
        try {
            DB::transaction(function () {
                app(GuardShiftService::class)->assign(['society_id' => $this->society->id, 'shift_id' => $this->morning->id, 'gate_id' => $this->gate->id,
                    'guard_user_id' => $this->guard->id, 'date' => $this->day(), 'created_by' => $this->admin->id]);
                throw new \RuntimeException('something later in the request failed');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, ShiftAssignment::count());
        $this->assertSame([], $this->titles($this->guard));
    }

    public function test_the_notice_never_crosses_a_society(): void
    {
        $beta = $this->makeSociety('Beta');
        $betaGuard = $this->makeUserWithRole($beta, 'Guard');

        // a guard of another society cannot be rostered here, and is told nothing
        $this->actingAs($this->admin)->post(route('admin.shift-assignments.store'), [
            'guard_user_id' => $betaGuard->id, 'shift_id' => $this->morning->id, 'gate_id' => $this->gate->id, 'date' => $this->day(),
        ])->assertSessionHasErrors();

        $this->assertSame([], $this->titles($betaGuard));
        $this->assertSame(0, ShiftAssignment::count());

        // and a notice built for a guard that has since moved to another society is not delivered
        $a = $this->assignment();
        $this->guard->update(['society_id' => $beta->id]);
        \App\Support\BusinessNotifications::guardShiftChanged($a, 'assigned');
        $this->assertSame([], $this->titles($this->guard));
    }

    public function test_the_guard_sees_the_notice_in_the_bell_endpoint(): void
    {
        $this->actingAs($this->admin)->post(route('admin.shift-assignments.store'), [
            'guard_user_id' => $this->guard->id, 'shift_id' => $this->morning->id, 'gate_id' => $this->gate->id, 'date' => $this->day(),
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($this->guard);
        $this->getJson('/api/v1/notifications')->assertOk()->assertSee('New shift assigned');
    }
}
