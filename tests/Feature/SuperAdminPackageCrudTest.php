<?php

namespace Tests\Feature;

use App\Models\Society;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Super Admin > Packages: create, edit, delete with real payloads (none of it was referenced by a test). */
class SuperAdminPackageCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $super;
    private Society $society;

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Eden');
        $this->super = $this->makeUserWithRole($this->society, 'Super Admin', ['society_id' => null]);
    }

    private function payload(array $over = []): array
    {
        return array_merge([
            'name' => 'Platinum Plus', 'description' => 'Everything', 'monthly_price' => 1499, 'annual_price' => 14990,
            'max_flats' => 200, 'features' => ['Visitors', 'Billing', 'Reports'], 'status' => 'active',
        ], $over);
    }

    public function test_a_package_is_created_with_its_features_readable_as_a_list(): void
    {
        $this->actingAs($this->super)->post(route('super-admin.packages.store'), $this->payload())
            ->assertRedirect(route('super-admin.packages.index'))->assertSessionHas('success');

        $plan = SubscriptionPlan::where('name', 'Platinum Plus')->firstOrFail();
        $this->assertSame('platinum-plus', $plan->slug);
        $this->assertEquals(14990, $plan->yearly_price, 'annual_price is stored as yearly_price');
        $this->assertSame(['Visitors', 'Billing', 'Reports'], $plan->features, 'features round-trip as a list, not a JSON string inside JSON');
    }

    public function test_a_created_package_appears_on_the_index_and_detail_pages(): void
    {
        $this->actingAs($this->super)->post(route('super-admin.packages.store'), $this->payload());
        $plan = SubscriptionPlan::firstOrFail();

        $this->actingAs($this->super)->get(route('super-admin.packages.index'))->assertOk()->assertSee('Platinum Plus');
        $this->actingAs($this->super)->get(route('super-admin.packages.show', $plan))->assertOk()->assertSee('Platinum Plus');
        $this->actingAs($this->super)->get(route('super-admin.packages.edit', $plan))->assertOk()->assertSee('Platinum Plus');
    }

    public function test_invalid_packages_are_refused_with_field_errors_and_nothing_is_saved(): void
    {
        foreach ([
            [['name' => ''], 'name'],
            [['monthly_price' => -1], 'monthly_price'],
            [['annual_price' => 'abc'], 'annual_price'],
            [['features' => []], 'features'],
            [['status' => 'archived'], 'status'],
            [['max_flats' => -5], 'max_flats'],
        ] as [$over, $field]) {
            $this->actingAs($this->super)->post(route('super-admin.packages.store'), $this->payload($over))->assertSessionHasErrors($field);
        }
        $this->assertSame(0, SubscriptionPlan::count());
    }

    public function test_two_packages_with_the_same_name_get_distinct_slugs(): void
    {
        $this->actingAs($this->super)->post(route('super-admin.packages.store'), $this->payload());
        $this->actingAs($this->super)->post(route('super-admin.packages.store'), $this->payload());

        $this->assertEqualsCanonicalizing(['platinum-plus', 'platinum-plus-1'], SubscriptionPlan::pluck('slug')->all());
    }

    public function test_updating_a_package_changes_it_and_keeps_its_own_slug_free(): void
    {
        $this->actingAs($this->super)->post(route('super-admin.packages.store'), $this->payload());
        $plan = SubscriptionPlan::firstOrFail();

        $this->actingAs($this->super)->put(route('super-admin.packages.update', $plan), $this->payload(['monthly_price' => 1999, 'features' => ['Only one'], 'status' => 'inactive']))
            ->assertRedirect(route('super-admin.packages.index'))->assertSessionHas('success');

        $plan->refresh();
        $this->assertEquals(1999, $plan->monthly_price);
        $this->assertSame(['Only one'], $plan->features);
        $this->assertSame('inactive', $plan->status);
        $this->assertSame('platinum-plus', $plan->slug, 'saving a package under its own name does not rename its slug');
    }

    public function test_a_package_in_use_cannot_be_deleted_and_an_unused_one_can(): void
    {
        $this->actingAs($this->super)->post(route('super-admin.packages.store'), $this->payload());
        $plan = SubscriptionPlan::firstOrFail();
        $sub = Subscription::create(['society_id' => $this->society->id, 'subscription_plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'amount' => 1, 'start_date' => '2026-10-01', 'end_date' => '2026-11-01', 'status' => 'active']);

        $this->actingAs($this->super)->delete(route('super-admin.packages.destroy', $plan))->assertSessionHas('error');
        $this->assertNotNull($plan->fresh());

        $sub->delete();
        $this->actingAs($this->super)->delete(route('super-admin.packages.destroy', $plan))->assertSessionHas('success');
        $this->assertNull($plan->fresh());
    }

    public function test_a_package_with_no_flat_limit_is_shown_as_unlimited(): void
    {
        $this->actingAs($this->super)->post(route('super-admin.packages.store'), $this->payload(['max_flats' => null]));
        $plan = SubscriptionPlan::firstOrFail();

        $this->actingAs($this->super)->get(route('super-admin.packages.show', $plan))->assertOk()->assertSee('Unlimited');
    }
}
