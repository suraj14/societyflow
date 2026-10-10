<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\Society;
use App\Models\User;
use App\Models\VillaArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The legacy owner portal's "My requests" (POST /my/requests). It wrote `category_id`, which is not a fillable
 * column (the column is `complaint_category_id`), so every request lost its category; it accepted any society's
 * category id; it never told the office; and the form offered the society's inactive categories.
 */
class OwnerPortalRequestTest extends TestCase
{
    use RefreshDatabase;

    private Society $a;
    private Society $b;
    private User $owner;
    private User $admin;
    private Flat $villa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = $this->makeSociety('Alpha');
        $this->b = $this->makeSociety('Beta');
        $area = VillaArea::create(['society_id' => $this->a->id, 'name' => 'A', 'status' => 'active']);
        $record = Owner::create(['society_id' => $this->a->id, 'name' => 'Suraj', 'email' => 's@t.local', 'phone' => '0000000001', 'status' => 'active', 'property_type' => 'villa']);
        $this->villa = Flat::create(['society_id' => $this->a->id, 'villa_area_id' => $area->id, 'property_type' => 'villa', 'flat_number' => '160', 'status' => 'occupied', 'owner_id' => $record->id]);
        $this->owner = $this->makeUserWithRole($this->a, 'Owner', ['owner_id' => $record->id]);
        $this->admin = $this->makeUserWithRole($this->a, 'Admin');
    }

    private function category(Society $s, string $name, string $status = 'active'): ComplaintCategory
    {
        return ComplaintCategory::create(['society_id' => $s->id, 'name' => $name, 'status' => $status]);
    }

    private function submit(array $over = [])
    {
        return $this->actingAs($this->owner)->post(route('villa-owner.requests.store'), array_merge([
            'title' => 'Tap leaking', 'description' => 'The kitchen tap drips', 'priority' => 'medium',
        ], $over));
    }

    public function test_a_request_keeps_its_category_unit_and_society_and_is_listed_for_the_owner(): void
    {
        $plumbing = $this->category($this->a, 'Plumbing');

        $this->submit(['category_id' => $plumbing->id])->assertRedirect(route('villa-owner.requests'))->assertSessionHas('success');

        $c = Complaint::sole();
        $this->assertSame($plumbing->id, (int) $c->complaint_category_id, 'the chosen category was lost before');
        $this->assertSame($this->villa->id, (int) $c->flat_id);
        $this->assertSame($this->a->id, (int) $c->society_id);
        $this->assertSame($this->owner->id, (int) $c->created_by);
        $this->actingAs($this->owner)->get(route('villa-owner.requests'))->assertOk()->assertSee('Plumbing')->assertSee('Tap leaking');
    }

    public function test_a_request_without_a_category_is_still_accepted(): void
    {
        $this->submit()->assertSessionHas('success');

        $this->assertNull(Complaint::sole()->complaint_category_id);
    }

    public function test_the_office_is_told_about_a_new_request(): void
    {
        $this->submit();

        $titles = $this->admin->fresh()->notifications()->pluck('data')->map(fn ($d) => $d['title'])->all();
        $this->assertSame(['New complaint'], $titles);
        $this->assertSame([], $this->makeUserWithRole($this->b, 'Admin')->notifications()->pluck('id')->all(), 'another society hears nothing');
    }

    public function test_another_societys_or_an_inactive_category_is_refused(): void
    {
        $foreign = $this->category($this->b, 'Their category');
        $inactive = $this->category($this->a, 'Retired', 'inactive');

        $this->submit(['category_id' => $foreign->id])->assertSessionHasErrors('category_id');
        $this->submit(['category_id' => $inactive->id])->assertSessionHasErrors('category_id');

        $this->assertSame(0, Complaint::count());
    }

    public function test_the_form_offers_only_active_categories_of_this_society(): void
    {
        $mine = $this->category($this->a, 'Mine');
        $this->category($this->a, 'Retired', 'inactive');
        $this->category($this->b, 'Theirs');

        $offered = $this->actingAs($this->owner)->get(route('villa-owner.requests.create'))->assertOk()->viewData('categories')->pluck('id')->all();

        $this->assertSame([$mine->id], $offered, 'complaint_categories.society_id is NOT NULL: there are no shared categories');
    }

    public function test_invalid_input_is_refused_and_nothing_is_saved(): void
    {
        foreach ([['title' => ''], ['description' => ''], ['priority' => 'whenever']] as $bad) {
            $this->submit($bad)->assertSessionHasErrors(array_keys($bad));
        }
        $this->assertSame(0, Complaint::count());
    }

    public function test_an_owner_cannot_open_someone_elses_request(): void
    {
        $this->submit();
        $request = Complaint::sole();
        $other = $this->makeUserWithRole($this->a, 'Owner', ['owner_id' => null]);

        $this->actingAs($this->owner)->get(route('villa-owner.requests.show', $request->id))->assertOk();
        $this->actingAs($other)->get(route('villa-owner.requests.show', $request->id))->assertStatus(403);
    }
}
