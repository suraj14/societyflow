<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Owner;
use App\Models\Society;
use App\Models\User;
use App\Models\VillaArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The owner form's unit pickers: what the lookups behind them may list, and what saving the form does to a unit
 * the owner already holds.
 */
class OwnerUnitPickerTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;
    private User $admin;
    private VillaArea $area;
    private Owner $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->society = $this->makeSociety('Alpha');
        $this->society->update(['property_type' => 'villa']);
        $this->admin = $this->makeUserWithRole($this->society, 'Admin');
        $this->area = VillaArea::create(['society_id' => $this->society->id, 'name' => 'Alpha Villas', 'status' => 'active']);
        Flat::create(['society_id' => $this->society->id, 'property_type' => 'villa', 'villa_area_id' => $this->area->id, 'flat_number' => '160', 'floor' => 0, 'status' => 'occupied']);
        $this->owner = Owner::create(['society_id' => $this->society->id, 'name' => 'Villa Owner', 'email' => 'villa.owner@test.local', 'phone' => '9000000160',
            'status' => 'active', 'property_type' => 'villa', 'villa_area_id' => $this->area->id, 'villa_no' => '160']);
    }

    private function otherSocietyArea(): VillaArea
    {
        $other = $this->makeSociety('Beta');
        $area = VillaArea::create(['society_id' => $other->id, 'name' => 'Beta Villas', 'status' => 'active']);
        Flat::create(['society_id' => $other->id, 'property_type' => 'villa', 'villa_area_id' => $area->id, 'flat_number' => '999', 'floor' => 0, 'status' => 'occupied']);

        return $area;
    }

    private function save(array $overrides)
    {
        return $this->actingAs($this->admin)->put(route('owners.update', $this->owner), array_merge([
            'name' => 'Villa Owner', 'email' => 'villa.owner@test.local', 'phone' => '9000000160', 'status' => 'active',
            'property_type' => 'villa', 'villa_area_id' => (string) $this->area->id, 'villa_no' => '160',
        ], $overrides));
    }

    // ── saving the form ─────────────────────────────────────────────────────

    public function test_saving_while_the_villa_pickers_are_empty_keeps_the_owners_villa(): void
    {
        // what the browser posted while the pickers could not load: the type, with blank area and number
        $this->save(['villa_area_id' => '', 'villa_no' => ''])->assertRedirect(route('owners.index'));

        $owner = $this->owner->fresh();
        $this->assertSame($this->area->id, (int) $owner->villa_area_id);
        $this->assertSame('160', (string) $owner->villa_no);
        $this->assertSame('villa', $owner->property_type);
    }

    public function test_a_chosen_villa_is_still_saved(): void
    {
        Flat::create(['society_id' => $this->society->id, 'property_type' => 'villa', 'villa_area_id' => $this->area->id, 'flat_number' => '161', 'floor' => 0, 'status' => 'vacant']);

        $this->save(['villa_no' => '161'])->assertRedirect(route('owners.index'));

        $this->assertSame('161', (string) $this->owner->fresh()->villa_no);
    }

    public function test_emptying_the_property_type_still_removes_the_unit(): void
    {
        $this->save(['property_type' => '', 'villa_area_id' => '', 'villa_no' => ''])->assertRedirect(route('owners.index'));

        $owner = $this->owner->fresh();
        $this->assertNull($owner->property_type);
        $this->assertNull($owner->villa_area_id);
        $this->assertNull($owner->villa_no);
    }

    public function test_another_societys_villa_area_is_refused(): void
    {
        $foreign = $this->otherSocietyArea();

        $this->save(['villa_area_id' => (string) $foreign->id, 'villa_no' => '999'])->assertSessionHasErrors('villa_area_id');

        $this->assertSame($this->area->id, (int) $this->owner->fresh()->villa_area_id);
    }

    // ── the lookups behind the pickers ──────────────────────────────────────

    public function test_an_admin_lists_only_their_own_societys_villa_areas_whatever_society_id_is_sent(): void
    {
        $foreign = $this->otherSocietyArea();

        $own = $this->actingAs($this->admin)->getJson(route('api.owners.villa-areas', ['society_id' => $this->society->id]))->assertOk()->json();
        $asked = $this->actingAs($this->admin)->getJson(route('api.owners.villa-areas', ['society_id' => $foreign->society_id]))->assertOk()->json();

        $this->assertSame(['Alpha Villas'], array_column($own, 'name'));
        $this->assertSame(['Alpha Villas'], array_column($asked, 'name'), 'another society named in the request is not listed');
    }

    public function test_an_admin_cannot_list_another_societys_villa_numbers(): void
    {
        $foreign = $this->otherSocietyArea();

        $this->actingAs($this->admin)->getJson(route('api.owners.villa-numbers', ['villa_area_id' => $this->area->id]))->assertOk()->assertExactJson(['160']);
        $this->actingAs($this->admin)->getJson(route('api.owners.villa-numbers', ['villa_area_id' => $foreign->id]))->assertOk()->assertExactJson([]);
    }

    public function test_a_super_admin_may_name_the_society(): void
    {
        $foreign = $this->otherSocietyArea();
        $root = User::create(['society_id' => null, 'name' => 'Root', 'email' => 'root@test.local', 'password' => bcrypt('x'), 'status' => 'active']);
        $root->assignRole($this->makeRole('Super Admin'));

        $listed = $this->actingAs($root)->getJson(route('api.owners.villa-areas', ['society_id' => $foreign->society_id]))->assertOk()->json();

        $this->assertSame(['Beta Villas'], array_column($listed, 'name'));
    }
}
