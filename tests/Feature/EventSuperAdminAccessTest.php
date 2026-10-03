<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Society;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `EventController` compared `$event->society_id !== $user->society_id` with
 * no Super Admin exemption anywhere (index/calendar/show/edit/update/destroy/
 * getCalendarEvents). Super Admin's own `society_id` is null, so every one
 * of those checks failed closed: Super Admin was blocked from every event in
 * every society, the opposite of every other controller in this codebase
 * (`PaymentController`, `NoticeController`) which exempt Super Admin
 * explicitly, per CLAUDE.md's own documented rule.
 */
class EventSuperAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private function event(Society $society, array $overrides = []): Event
    {
        return Event::create(array_merge([
            'society_id'    => $society->id,
            'created_by'    => $this->makeUserWithRole($society, 'Admin')->id,
            'event_name'    => 'Diwali Party',
            'location'      => 'Clubhouse',
            'description'   => 'Annual celebration',
            'start_date'    => now()->addDay(),
            'end_date'      => now()->addDay()->addHours(3),
            'status'        => 'pending',
            'is_role_based' => false,
            'visible_users' => null,
        ], $overrides));
    }

    public function test_super_admin_can_list_events_across_societies(): void
    {
        $superAdmin = $this->makeUserWithRole($this->makeSociety(), 'Super Admin', ['society_id' => null]);
        $societyA = $this->makeSociety('A');
        $societyB = $this->makeSociety('B');
        $this->event($societyA, ['event_name' => 'Event A']);
        $this->event($societyB, ['event_name' => 'Event B']);

        $this->actingAs($superAdmin)->get(route('events.index'))
            ->assertOk()
            ->assertSee('Event A')
            ->assertSee('Event B');
    }

    public function test_super_admin_can_view_edit_update_and_delete_any_societys_event(): void
    {
        $superAdmin = $this->makeUserWithRole($this->makeSociety(), 'Super Admin', ['society_id' => null]);
        $event = $this->event($this->makeSociety('Other Estate'));

        $this->actingAs($superAdmin)->get(route('events.show', $event))->assertOk();
        $this->actingAs($superAdmin)->get(route('events.edit', $event))->assertOk();

        $this->actingAs($superAdmin)->put(route('events.update', $event), [
            'event_name' => 'Renamed', 'location' => 'Clubhouse', 'description' => 'Updated',
            'start_date' => now()->addDay()->toDateString(), 'start_time' => '18:00',
            'end_date' => now()->addDay()->toDateString(), 'end_time' => '21:00',
            'status' => 'pending', 'is_role_based' => '0',
        ])->assertRedirect(route('events.index'));
        $this->assertSame('Renamed', $event->fresh()->event_name);

        $this->actingAs($superAdmin)->delete(route('events.destroy', $event))
            ->assertRedirect(route('events.index'));
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_super_admin_calendar_endpoints_include_every_society(): void
    {
        $superAdmin = $this->makeUserWithRole($this->makeSociety(), 'Super Admin', ['society_id' => null]);
        $societyA = $this->makeSociety('A');
        $this->event($societyA, ['event_name' => 'Cross Society Event']);

        $this->actingAs($superAdmin)->get(route('events.calendar'))
            ->assertOk()
            ->assertSee('Cross Society Event');

        $this->actingAs($superAdmin)->getJson(route('api.events.calendar'))
            ->assertOk()
            ->assertJsonFragment(['title' => 'Cross Society Event']);
    }

    /**
     * A real, independent bug found while writing this test: `visible_users`/
     * `visible_roles` are `nullable`, so Laravel's `validate()` omits them
     * entirely from its result when the request doesn't send them at all
     * (not even as an empty value) - a plain non-role-based update with no
     * `visible_users` array present threw "Undefined array key" instead of
     * saving.
     */
    public function test_update_without_a_visible_users_field_does_not_crash(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $event = $this->event($society);

        $this->actingAs($admin)->put(route('events.update', $event), [
            'event_name' => 'Renamed', 'location' => 'Clubhouse', 'description' => 'Updated',
            'start_date' => now()->addDay()->toDateString(), 'start_time' => '18:00',
            'end_date' => now()->addDay()->toDateString(), 'end_time' => '21:00',
            'status' => 'pending', 'is_role_based' => '0',
            // No visible_users key at all.
        ])->assertRedirect(route('events.index'));

        $this->assertSame('Renamed', $event->fresh()->event_name);
    }

    public function test_admin_still_cannot_cross_societies(): void
    {
        $societyA = $this->makeSociety('A');
        $admin = $this->makeUserWithRole($societyA, 'Admin');
        $foreign = $this->event($this->makeSociety('B'));

        $this->actingAs($admin)->get(route('events.show', $foreign))->assertForbidden();
        $this->actingAs($admin)->get(route('events.edit', $foreign))->assertForbidden();
        $this->actingAs($admin)->delete(route('events.destroy', $foreign))->assertForbidden();
        $this->assertDatabaseHas('events', ['id' => $foreign->id]);

        $this->actingAs($admin)->get(route('events.index'))->assertDontSee($foreign->event_name);
    }
}
