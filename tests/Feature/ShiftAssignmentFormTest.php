<?php

namespace Tests\Feature;

use App\Models\Gate;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Support\SocietyClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin -> Guard Roster -> Assign Guard.
 *
 * The reported "HTTP 422 on /admin/shift-assignments": that URL is where the assign form POSTs. Every roster rule
 * (overlapping shift, inactive gate, a shift already started ...) was an abort(422), which the browser shows as a bare
 * "422" page at that address - the list page itself (GET) answers 200. Roster rules now come back as messages beside the
 * field they belong to, validation is limited to this society, and nothing in the history is touched.
 */
class ShiftAssignmentFormTest extends TestCase
{
    use RefreshDatabase;

    private $society;
    private $admin;
    private $guard;
    private Gate $gate;
    private Shift $morning;

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Alpha');
        $this->admin = $this->makeUserWithRole($this->society, 'Admin');
        $this->guard = $this->makeUserWithRole($this->society, 'Guard', ['name' => 'Gopal Guard']);
        $this->gate = $this->gate();
        $this->morning = $this->shift('Morning', '06:00', '14:00');
    }

    private function gate(string $name = 'Main Gate', bool $active = true, $society = null): Gate
    {
        return Gate::create(['society_id' => ($society ?? $this->society)->id, 'name' => $name, 'type' => 'main', 'is_active' => $active]);
    }

    private function shift(string $name, string $start, string $end, bool $active = true, $society = null): Shift
    {
        return Shift::create(['society_id' => ($society ?? $this->society)->id, 'name' => $name, 'start_time' => $start, 'end_time' => $end, 'grace_period_minutes' => 15, 'is_active' => $active]);
    }

    private function day(int $offset = 1): string
    {
        return SocietyClock::today($this->society->id)->addDays($offset)->toDateString();
    }

    private function submit(array $over = [], $as = null)
    {
        return $this->actingAs($as ?? $this->admin)->from(route('admin.shift-assignments.create'))->post(route('admin.shift-assignments.store'), $over + [
            'guard_user_id' => $this->guard->id, 'shift_id' => $this->morning->id, 'gate_id' => $this->gate->id, 'date' => $this->day(),
        ]);
    }

    private function assignment(array $over = []): ShiftAssignment
    {
        return ShiftAssignment::create($over + ['society_id' => $this->society->id, 'shift_id' => $this->morning->id, 'gate_id' => $this->gate->id,
            'guard_user_id' => $this->guard->id, 'date' => $this->day(), 'status' => 'scheduled']);
    }

    // ── the screens ─────────────────────────────────────────────────────────

    public function test_the_list_and_the_form_open_without_error(): void
    {
        $this->actingAs($this->admin)->get(route('admin.shift-assignments.index'))->assertOk()->assertSee('Guard Roster');
        $this->actingAs($this->admin)->get(route('admin.shift-assignments.create'))->assertOk()->assertSee('Gopal Guard')->assertSee('Morning')->assertSee('Main Gate');
        $this->actingAs($this->admin)->get(route('admin.shift-assignments.index', ['date' => 'not-a-date', 'gate_id' => 'x', 'status' => ['a']]))->assertOk();
    }

    public function test_the_form_offers_only_this_societys_active_guards_shifts_and_gates(): void
    {
        $this->makeUserWithRole($this->society, 'Guard', ['name' => 'Inactive Ivan', 'status' => 'inactive']);
        $other = $this->makeSociety('Beta');
        $this->makeUserWithRole($other, 'Guard', ['name' => 'Foreign Farhan']);
        $this->gate('Old Gate', false);
        $this->shift('Retired', '01:00', '02:00', false);
        $this->gate('Beta Gate', true, $other);

        $html = $this->actingAs($this->admin)->get(route('admin.shift-assignments.create'))->getContent();

        $this->assertStringContainsString('Gopal Guard', $html);
        foreach (['Inactive Ivan', 'Foreign Farhan', 'Old Gate', 'Retired', 'Beta Gate'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $html, "{$hidden} is not offered");
        }
    }

    // ── a valid submission ──────────────────────────────────────────────────

    public function test_a_valid_assignment_is_saved_and_listed_with_guard_shift_gate_date_and_status(): void
    {
        $date = $this->day(2);

        $this->submit(['date' => $date])->assertRedirect(route('admin.shift-assignments.index', ['date' => $date]))->assertSessionHas('success', 'Guard assigned.')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('shift_assignments', [
            'society_id' => $this->society->id, 'guard_user_id' => $this->guard->id, 'shift_id' => $this->morning->id, 'gate_id' => $this->gate->id,
            'date' => $date . ' 00:00:00', 'status' => 'scheduled', 'created_by' => $this->admin->id,
        ]);
        $page = $this->actingAs($this->admin)->get(route('admin.shift-assignments.index', ['date' => $date]))->assertOk();
        $page->assertSee('Gopal Guard')->assertSee('Morning')->assertSee('Main Gate')->assertSee(\Illuminate\Support\Carbon::parse($date)->format('d M Y'))->assertSee('Scheduled');
    }

    public function test_today_is_allowed(): void
    {
        $this->submit(['date' => $this->day(0)])->assertSessionHasNoErrors();

        $this->assertSame(1, ShiftAssignment::count());
    }

    // ── missing and invalid fields ──────────────────────────────────────────

    /** @dataProvider invalidInput */
    public function test_invalid_input_comes_back_as_a_message_beside_the_field_and_saves_nothing(array $override, string $field, string $message): void
    {
        $r = $this->submit($override);

        $r->assertRedirect(route('admin.shift-assignments.create'))->assertSessionHasErrors($field);
        $this->assertStringContainsString($message, session('errors')->first($field));
        $this->assertSame(0, ShiftAssignment::count());
    }

    public static function invalidInput(): array
    {
        return [
            'no guard'          => [['guard_user_id' => ''], 'guard_user_id', 'Choose a guard.'],
            'no shift'          => [['shift_id' => ''], 'shift_id', 'Choose a shift.'],
            'no gate'           => [['gate_id' => ''], 'gate_id', 'Choose a gate.'],
            'no date'           => [['date' => ''], 'date', 'Choose the date'],
            'a guard id that does not exist' => [['guard_user_id' => 999999], 'guard_user_id', 'Choose a guard from this society.'],
            'a shift id that does not exist' => [['shift_id' => 999999], 'shift_id', 'Choose a shift from this society.'],
            'a gate id that does not exist'  => [['gate_id' => 999999], 'gate_id', 'Choose a gate from this society.'],
            'text instead of an id'          => [['gate_id' => 'main'], 'gate_id', 'Choose a gate from this society.'],
            'a date in words'                => [['date' => 'tomorrow'], 'date', 'day, month and year'],
            'a date in the wrong layout'     => [['date' => '12/11/2030'], 'date', 'day, month and year'],
            'an impossible date'             => [['date' => '2030-02-31'], 'date', 'day, month and year'],
        ];
    }

    public function test_a_past_date_is_refused_because_that_shift_has_ended(): void
    {
        $this->submit(['date' => $this->day(-1)])->assertSessionHasErrors('date');

        $this->assertStringContainsString('today or a later date', session('errors')->first('date'));
        $this->assertSame(0, ShiftAssignment::count());
    }

    public function test_a_date_more_than_a_year_away_is_refused_as_a_typo(): void
    {
        $this->submit(['date' => $this->day(400)])->assertSessionHasErrors('date');
        $this->submit(['date' => $this->day(365)])->assertSessionHasNoErrors();
    }

    public function test_the_typed_values_are_kept_so_only_the_wrong_field_is_retyped(): void
    {

        $page = $this->actingAs($this->admin)->followingRedirects()->from(route('admin.shift-assignments.create'))
            ->post(route('admin.shift-assignments.store'), ['guard_user_id' => $this->guard->id, 'shift_id' => $this->morning->id, 'gate_id' => 999999, 'date' => $this->day(5)]);
        $page->assertOk()->assertSee('Choose a gate from this society.')->assertSee('The guard was not assigned', false)->assertSee($this->day(5), false);
        $this->assertMatchesRegularExpression('/<option value="' . $this->guard->id . '"\s+selected/', $page->getContent());
    }

    // ── another society's ids ───────────────────────────────────────────────

    public function test_ids_from_another_society_are_invalid_not_accepted_and_not_revealed(): void
    {
        $other = $this->makeSociety('Beta');
        $foreignGuard = $this->makeUserWithRole($other, 'Guard');
        $foreignGate = $this->gate('Beta Gate', true, $other);
        $foreignShift = $this->shift('Beta Shift', '06:00', '14:00', true, $other);

        $this->submit(['guard_user_id' => $foreignGuard->id])->assertSessionHasErrors('guard_user_id');
        $this->submit(['gate_id' => $foreignGate->id])->assertSessionHasErrors('gate_id');
        $this->submit(['shift_id' => $foreignShift->id])->assertSessionHasErrors('shift_id');

        $this->assertSame(0, ShiftAssignment::count());
    }

    // ── roster rules ────────────────────────────────────────────────────────

    public function test_an_overlapping_shift_is_a_message_on_the_guard_field_not_a_bare_422_page(): void
    {
        $this->assignment();
        $overlapping = $this->shift('Mid-day', '10:00', '18:00');

        $r = $this->submit(['shift_id' => $overlapping->id]);

        $this->assertNotSame(422, $r->getStatusCode(), 'the bare 422 error page was the reported defect');
        $r->assertRedirect(route('admin.shift-assignments.create'))->assertSessionHasErrors('guard_user_id');
        $this->assertStringContainsString('already has the Morning shift', session('errors')->first('guard_user_id'));
        $this->assertStringContainsString('overlaps', session('errors')->first('guard_user_id'));
        $this->assertSame(1, ShiftAssignment::count());
    }

    public function test_the_same_guard_cannot_be_given_the_same_shift_twice_on_one_date(): void
    {
        $this->assignment();

        $r = $this->submit();

        $r->assertSessionHasErrors('guard_user_id');
        $this->assertStringContainsString('already has the Morning shift', session('errors')->first('guard_user_id'));
        $this->assertSame(1, ShiftAssignment::count());
    }

    public function test_a_guard_who_was_replaced_on_a_shift_keeps_that_history_and_cannot_be_given_the_slot_again(): void
    {
        $this->assignment(['status' => 'replaced']);

        $r = $this->submit();

        $r->assertSessionHasErrors('guard_user_id');                       // not a 500 from the unique index
        $this->assertStringContainsString('status: replaced', session('errors')->first('guard_user_id'));
        $this->assertSame(1, ShiftAssignment::count());
        $this->assertSame('replaced', ShiftAssignment::first()->status, 'the history row is untouched');
    }

    public function test_an_overnight_shift_blocks_the_next_mornings_overlapping_shift(): void
    {
        $night = $this->shift('Night', '22:00', '06:00');
        $early = $this->shift('Early', '05:00', '13:00');
        $this->submit(['shift_id' => $night->id, 'date' => $this->day(1)])->assertSessionHasNoErrors();

        $this->submit(['shift_id' => $early->id, 'date' => $this->day(2)])->assertSessionHasErrors('guard_user_id');
        $this->assertSame(1, ShiftAssignment::count());
    }

    public function test_a_finished_assignment_does_not_block_a_later_overlapping_one_but_two_guards_can_share_a_gate(): void
    {
        $this->assignment(['status' => 'checked_out']);
        $overlapping = $this->shift('Mid-day', '10:00', '18:00');
        $this->submit(['shift_id' => $overlapping->id])->assertSessionHasNoErrors();

        $second = $this->makeUserWithRole($this->society, 'Guard');
        $this->submit(['guard_user_id' => $second->id, 'shift_id' => $overlapping->id])->assertSessionHasNoErrors();

        $this->assertSame(3, ShiftAssignment::count());
    }

    public function test_an_inactive_shift_or_gate_is_refused_on_its_own_field(): void
    {
        $retired = $this->shift('Retired', '01:00', '02:00', false);
        $oldGate = $this->gate('Old Gate', false);

        // an inactive record is not in the form's lists, so it can only arrive by a stale page or a forged request
        $this->submit(['shift_id' => $retired->id])->assertSessionHasErrors('shift_id');
        $this->assertStringContainsString('not active', session('errors')->first('shift_id'));
        $this->submit(['gate_id' => $oldGate->id])->assertSessionHasErrors('gate_id');
        $this->assertStringContainsString('not active', session('errors')->first('gate_id'));
        $this->assertSame(0, ShiftAssignment::count());
    }

    public function test_only_an_active_guard_account_can_be_rostered(): void
    {
        $this->guard->update(['status' => 'inactive']);

        $this->submit()->assertSessionHasErrors('guard_user_id');

        $this->assertStringContainsString('not active', session('errors')->first('guard_user_id'));
        $this->assertSame(0, ShiftAssignment::count());
    }

    public function test_only_a_guard_account_can_be_assigned(): void
    {
        $tenant = $this->makeUserWithRole($this->society, 'Tenant');

        $this->submit(['guard_user_id' => $tenant->id])->assertSessionHasErrors('guard_user_id');

        $this->assertStringContainsString('Only a Guard account', session('errors')->first('guard_user_id'));
    }

    // ── authorization ───────────────────────────────────────────────────────

    public function test_only_admins_can_use_the_roster(): void
    {
        foreach (['Tenant', 'Staff', 'Guard', 'Accountant'] as $role) {
            $user = $this->makeUserWithRole($this->society, $role);
            $this->actingAs($user)->get(route('admin.shift-assignments.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.shift-assignments.create'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.shift-assignments.store'), ['guard_user_id' => $this->guard->id, 'shift_id' => $this->morning->id, 'gate_id' => $this->gate->id, 'date' => $this->day()])->assertForbidden();
        }
        $this->assertSame(0, ShiftAssignment::count());

        auth()->logout();
        $this->post(route('admin.shift-assignments.store'), [])->assertRedirect(route('login'));
    }

    public function test_the_list_shows_only_this_societys_assignments(): void
    {
        $this->assignment();
        $other = $this->makeSociety('Beta');
        $foreignGuard = $this->makeUserWithRole($other, 'Guard', ['name' => 'Foreign Farhan']);
        ShiftAssignment::create(['society_id' => $other->id, 'shift_id' => $this->shift('Beta Shift', '06:00', '14:00', true, $other)->id, 'gate_id' => $this->gate('Beta Gate', true, $other)->id,
            'guard_user_id' => $foreignGuard->id, 'date' => $this->day(), 'status' => 'scheduled']);

        $this->actingAs($this->admin)->get(route('admin.shift-assignments.index', ['date' => $this->day()]))->assertOk()->assertSee('Gopal Guard')->assertDontSee('Foreign Farhan');
    }

    // ── the other roster actions explain themselves too, and history stays ──

    public function test_removing_a_started_shift_is_refused_with_a_message_and_the_history_stays(): void
    {
        $a = $this->assignment(['status' => 'present', 'checked_in_at' => now()]);

        $this->actingAs($this->admin)->delete(route('admin.shift-assignments.destroy', $a))->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseHas('shift_assignments', ['id' => $a->id, 'status' => 'present']);
    }

    public function test_a_scheduled_assignment_can_be_removed_and_a_replacement_keeps_the_original_as_history(): void
    {
        $gone = $this->assignment();
        $this->actingAs($this->admin)->delete(route('admin.shift-assignments.destroy', $gone))->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseMissing('shift_assignments', ['id' => $gone->id]);

        $original = $this->assignment();
        $replacement = $this->makeUserWithRole($this->society, 'Guard', ['name' => 'Ravi Relief']);
        $this->actingAs($this->admin)->post(route('admin.shift-assignments.replace', $original), ['replacement_guard_id' => $replacement->id, 'reason' => 'Fever'])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('replaced', $original->fresh()->status, 'the original row stays as history');
        $this->assertSame('Fever', $original->fresh()->replacement_reason);
        $this->assertDatabaseHas('shift_assignments', ['guard_user_id' => $replacement->id, 'shift_id' => $this->morning->id, 'status' => 'scheduled']);
        $this->actingAs($this->admin)->get(route('admin.shift-assignments.index', ['date' => $this->day()]))->assertSee('Replaced')->assertSee('Ravi Relief')->assertSee('Fever');
    }

    public function test_a_replacement_who_is_already_on_an_overlapping_shift_is_refused(): void
    {
        $original = $this->assignment();
        $busy = $this->makeUserWithRole($this->society, 'Guard', ['name' => 'Busy Bhanu']);
        $this->assignment(['guard_user_id' => $busy->id, 'shift_id' => $this->shift('Mid-day', '10:00', '18:00')->id]);

        $this->actingAs($this->admin)->post(route('admin.shift-assignments.replace', $original), ['replacement_guard_id' => $busy->id, 'reason' => 'Fever'])
            ->assertRedirect()->assertSessionHas('error', fn ($m) => str_contains($m, 'overlaps'));

        $this->assertSame('scheduled', $original->fresh()->status, 'a refused replacement changes nothing');
    }

    public function test_replace_needs_a_guard_of_this_society_and_a_reason(): void
    {
        $original = $this->assignment();
        $foreign = $this->makeUserWithRole($this->makeSociety('Beta'), 'Guard');

        $this->actingAs($this->admin)->post(route('admin.shift-assignments.replace', $original), ['replacement_guard_id' => $foreign->id, 'reason' => 'x'])->assertSessionHasErrors('replacement_guard_id');
        $this->actingAs($this->admin)->post(route('admin.shift-assignments.replace', $original), ['replacement_guard_id' => $this->makeUserWithRole($this->society, 'Guard')->id])->assertSessionHasErrors('reason');
        $this->assertSame('scheduled', $original->fresh()->status);
    }

    public function test_an_admin_cannot_touch_another_societys_assignment(): void
    {
        $other = $this->makeSociety('Beta');
        $theirs = ShiftAssignment::create(['society_id' => $other->id, 'shift_id' => $this->shift('Beta Shift', '06:00', '14:00', true, $other)->id, 'gate_id' => $this->gate('Beta Gate', true, $other)->id,
            'guard_user_id' => $this->makeUserWithRole($other, 'Guard')->id, 'date' => $this->day(), 'status' => 'scheduled']);

        $this->actingAs($this->admin)->delete(route('admin.shift-assignments.destroy', $theirs))->assertForbidden();
        $this->actingAs($this->admin)->post(route('admin.shift-assignments.absent', $theirs))->assertForbidden();
        $this->assertDatabaseHas('shift_assignments', ['id' => $theirs->id, 'status' => 'scheduled']);
    }
}
