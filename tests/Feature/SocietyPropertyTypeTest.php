<?php

namespace Tests\Feature;

use App\Enums\PropertyType;
use App\Enums\SocietyPropertyType;
use App\Models\Owner;
use App\Models\Society;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A society decides which property types exist inside it.
 *
 * The property type used to be a free choice on every owner form
 * (`in:apartment,villa`, hardcoded in the Blade select), so an
 * apartment-only society could be given a villa owner and a villa township
 * an apartment owner. Both the form and the validator now read the society's
 * own configuration, and the backend enforces it independently of the UI -
 * a forged POST is rejected the same way.
 */
class SocietyPropertyTypeTest extends TestCase
{
    use RefreshDatabase;

    private function societyOfType(SocietyPropertyType $type, string $name = 'S'): Society
    {
        $society = $this->makeSociety($name . ' ' . uniqid());
        $society->update(['property_type' => $type->value]);

        return $society->fresh();
    }

    // ── Enum behaviour ───────────────────────────────────────────────────────

    public function test_enum_reports_what_each_society_type_allows(): void
    {
        $this->assertSame(['apartment'], SocietyPropertyType::Apartment->allowedValues());
        $this->assertSame(['villa'], SocietyPropertyType::Villa->allowedValues());
        $this->assertSame(['apartment', 'villa'], SocietyPropertyType::Both->allowedValues());

        $this->assertTrue(SocietyPropertyType::Villa->supports(PropertyType::Villa));
        $this->assertFalse(SocietyPropertyType::Villa->supports(PropertyType::Apartment));
        $this->assertFalse(SocietyPropertyType::Villa->supports(null));
        $this->assertFalse(SocietyPropertyType::Villa->supports('nonsense'));

        $this->assertTrue(SocietyPropertyType::Both->isMixed());
        $this->assertFalse(SocietyPropertyType::Apartment->isMixed());
    }

    public function test_society_helpers_reflect_the_configured_type(): void
    {
        $villa = $this->societyOfType(SocietyPropertyType::Villa);
        $this->assertTrue($villa->supportsVillas());
        $this->assertFalse($villa->supportsApartments());
        $this->assertSame(['villa'], $villa->allowedPropertyTypeValues());

        $mixed = $this->societyOfType(SocietyPropertyType::Both);
        $this->assertTrue($mixed->supportsVillas());
        $this->assertTrue($mixed->supportsApartments());
        $this->assertTrue($mixed->isMixedProperty());
    }

    /**
     * A society can never end up without a property type.
     *
     * The column is NOT NULL with a default of 'both', so the "legacy row"
     * case cannot occur in the database at all - an earlier version of this
     * test tried to force a NULL and the constraint refused it, which is the
     * behaviour we want. What still needs covering is the accessor on a model
     * whose attribute was never set (a fresh, unsaved instance), because
     * callers use propertyType() before persisting.
     */
    public function test_a_society_always_has_a_usable_property_type(): void
    {
        // Persisted rows get the default.
        $society = $this->makeSociety();
        $this->assertSame(SocietyPropertyType::Both, $society->fresh()->propertyType());

        // And the column refuses to be emptied.
        $this->expectException(\Illuminate\Database\QueryException::class);
        \DB::table('societies')->where('id', $society->id)->update(['property_type' => null]);
    }

    public function test_accessor_falls_back_to_both_on_an_unsaved_model(): void
    {
        $society = new Society();

        $this->assertSame(SocietyPropertyType::Both, $society->propertyType());
        $this->assertTrue($society->supportsApartments());
        $this->assertTrue($society->supportsVillas());
    }

    public function test_property_config_shape_for_the_api(): void
    {
        $config = $this->societyOfType(SocietyPropertyType::Villa)->propertyConfig();

        $this->assertSame('villa', $config['property_type']);
        $this->assertTrue($config['supports_villas']);
        $this->assertFalse($config['supports_apartments']);
        $this->assertFalse($config['is_mixed']);
        $this->assertArrayHasKey('property_type_label', $config);
    }

    // ── Owner creation is constrained by the society ─────────────────────────

    private function ownerPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Owner',
            'email' => 'owner' . uniqid() . '@test.local',
            'phone' => '9876500000',
            'status' => 'active',
        ], $overrides);
    }

    private function seedRoles(): void
    {
        $this->makeRole('Apartment Owner');
        $this->makeRole('Villa Owner');
    }

    public function test_villa_society_rejects_an_apartment_owner(): void
    {
        $this->seedRoles();
        $society = $this->societyOfType(SocietyPropertyType::Villa, 'Royal Prime');
        $admin = $this->makeUserWithRole($society, 'Admin');
        $building = $this->makeBuilding($society);

        $this->actingAs($admin)->post('/owners', $this->ownerPayload([
            'property_type' => 'apartment',
            'building_id' => $building->id,
            'flat_no' => 'A-101',
        ]))->assertSessionHasErrors('property_type');

        $this->assertSame(0, Owner::count());
    }

    public function test_apartment_society_rejects_a_villa_owner(): void
    {
        $this->seedRoles();
        $society = $this->societyOfType(SocietyPropertyType::Apartment, 'Mahalaxmi');
        $admin = $this->makeUserWithRole($society, 'Admin');

        $this->actingAs($admin)->post('/owners', $this->ownerPayload([
            'property_type' => 'villa',
            'villa_no' => 'V-1',
        ]))->assertSessionHasErrors('property_type');

        $this->assertSame(0, Owner::count());
    }

    public function test_villa_society_accepts_a_villa_owner(): void
    {
        $this->seedRoles();
        $society = $this->societyOfType(SocietyPropertyType::Villa);
        $admin = $this->makeUserWithRole($society, 'Admin');

        $this->actingAs($admin)->post('/owners', $this->ownerPayload([
            'name' => 'Villa Person',
            'property_type' => 'villa',
            'villa_no' => 'V-101',
        ]))->assertRedirect(route('owners.index'));

        $this->assertDatabaseHas('owners', [
            'name' => 'Villa Person',
            'property_type' => 'villa',
            'society_id' => $society->id,
        ]);
    }

    public function test_mixed_society_accepts_both_types(): void
    {
        $this->seedRoles();
        $society = $this->societyOfType(SocietyPropertyType::Both);
        $admin = $this->makeUserWithRole($society, 'Admin');
        $building = $this->makeBuilding($society);

        $this->actingAs($admin)->post('/owners', $this->ownerPayload([
            'name' => 'Mixed Villa',
            'property_type' => 'villa',
            'villa_no' => 'V-1',
        ]))->assertRedirect();

        $this->actingAs($admin)->post('/owners', $this->ownerPayload([
            'name' => 'Mixed Apartment',
            'property_type' => 'apartment',
            'building_id' => $building->id,
            'flat_no' => 'A-1',
        ]))->assertRedirect();

        $this->assertDatabaseHas('owners', ['name' => 'Mixed Villa', 'property_type' => 'villa']);
        $this->assertDatabaseHas('owners', ['name' => 'Mixed Apartment', 'property_type' => 'apartment']);
    }

    /** No property type at all stays legal - it means "not assigned yet". */
    public function test_owner_without_a_property_type_is_still_allowed(): void
    {
        $this->seedRoles();
        $society = $this->societyOfType(SocietyPropertyType::Villa);
        $admin = $this->makeUserWithRole($society, 'Admin');

        $this->actingAs($admin)->post('/owners', $this->ownerPayload([
            'name' => 'Unassigned',
        ]))->assertRedirect();

        $this->assertDatabaseHas('owners', ['name' => 'Unassigned', 'property_type' => null]);
    }

    /**
     * The form is not the control. A client that skips the dropdown entirely
     * must still be refused.
     */
    public function test_forged_property_type_is_rejected_without_the_form(): void
    {
        $this->seedRoles();
        $society = $this->societyOfType(SocietyPropertyType::Villa);
        $admin = $this->makeUserWithRole($society, 'Admin');

        $this->actingAs($admin)
            ->post('/owners', $this->ownerPayload(['property_type' => 'apartment']))
            ->assertSessionHasErrors('property_type');

        $this->assertSame(0, Owner::count());
    }

    /** An Admin cannot post another society's id to escape their own config. */
    public function test_admin_cannot_write_an_owner_into_another_society(): void
    {
        $this->seedRoles();
        $villaSociety = $this->societyOfType(SocietyPropertyType::Villa, 'Mine');
        $otherSociety = $this->societyOfType(SocietyPropertyType::Both, 'Theirs');
        $admin = $this->makeUserWithRole($villaSociety, 'Admin');

        $this->actingAs($admin)->post('/owners', $this->ownerPayload([
            'name' => 'Boundary Test',
            'society_id' => $otherSociety->id,   // must be ignored
            'property_type' => 'villa',
            'villa_no' => 'V-9',
        ]))->assertRedirect();

        $this->assertDatabaseHas('owners', [
            'name' => 'Boundary Test',
            'society_id' => $villaSociety->id,
        ]);
        $this->assertDatabaseMissing('owners', [
            'name' => 'Boundary Test',
            'society_id' => $otherSociety->id,
        ]);
    }

    /** Editing an owner is constrained by the owner's own society. */
    public function test_edit_cannot_switch_to_an_unsupported_property_type(): void
    {
        $this->seedRoles();
        $society = $this->societyOfType(SocietyPropertyType::Villa);
        $admin = $this->makeUserWithRole($society, 'Admin');

        $owner = Owner::create([
            'society_id' => $society->id,
            'name' => 'Villa Owner',
            'email' => 'villaowner' . uniqid() . '@test.local',
            'status' => 'active',
            'property_type' => 'villa',
            'villa_no' => 'V-1',
        ]);

        $this->actingAs($admin)->put("/owners/{$owner->id}", [
            'name' => 'Villa Owner',
            'email' => $owner->email,
            'status' => 'active',
            'property_type' => 'apartment',
        ])->assertSessionHasErrors('property_type');

        $this->assertSame('villa', $owner->fresh()->property_type);
    }

    // ── Super Admin configures the society ───────────────────────────────────

    /**
     * A complete society update payload.
     *
     * subscription_plan_id is `required|exists`, so a society created without
     * one makes the whole update fail validation and redirect straight back -
     * which looks like success to assertRedirect(). A real plan is created so
     * the request under test is the property_type rule, not this.
     */
    private function societyPayload(Society $society, array $overrides = []): array
    {
        $planId = $society->subscription_plan_id ?? \App\Models\SubscriptionPlan::create([
            'name' => 'Test Plan',
            'slug' => 'test-plan-' . uniqid(),
            'monthly_price' => 0,
            'yearly_price' => 0,
            'max_flats' => 100,
            'max_users' => 100,
            'max_staff' => 10,
            'trial_days' => 30,
            'status' => 'active',
        ])->id;

        return array_merge([
            'name' => $society->name,
            'email' => $society->email,
            'phone' => '9876500000',
            'address' => 'Some address',
            'city' => 'City',
            'state' => 'State',
            'country' => 'India',
            'pincode' => '000000',
            'subscription_plan_id' => $planId,
            'status' => 'active',
        ], $overrides);
    }

    public function test_super_admin_can_change_a_societys_property_type(): void
    {
        $society = $this->societyOfType(SocietyPropertyType::Both);
        $superAdmin = $this->makeUserWithRole($society, 'Super Admin', ['society_id' => null]);

        $this->actingAs($superAdmin)
            ->get("/super-admin/societies/{$society->public_id}/edit")
            ->assertOk()
            ->assertSee('Property Type', false);

        $this->actingAs($superAdmin)
            ->put("/super-admin/societies/{$society->public_id}", $this->societyPayload($society, [
                'property_type' => 'villa',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(SocietyPropertyType::Villa, $society->fresh()->propertyType());
    }

    public function test_society_rejects_an_unknown_property_type(): void
    {
        $society = $this->societyOfType(SocietyPropertyType::Both);
        $superAdmin = $this->makeUserWithRole($society, 'Super Admin', ['society_id' => null]);

        $this->actingAs($superAdmin)
            ->put("/super-admin/societies/{$society->public_id}", $this->societyPayload($society, [
                'property_type' => 'penthouse',
            ]))
            ->assertSessionHasErrors('property_type');

        $this->assertSame(SocietyPropertyType::Both, $society->fresh()->propertyType());
    }

    /**
     * Changing the society type immediately changes what owners may be
     * created - the whole point of making it the source of truth.
     */
    public function test_narrowing_the_society_type_starts_rejecting_the_other_type(): void
    {
        $this->seedRoles();
        $society = $this->societyOfType(SocietyPropertyType::Both);
        $admin = $this->makeUserWithRole($society, 'Admin');

        // Allowed while the society is mixed.
        $this->actingAs($admin)
            ->post('/owners', $this->ownerPayload(['name' => 'Before', 'property_type' => 'apartment']))
            ->assertRedirect();

        $society->update(['property_type' => SocietyPropertyType::Villa->value]);

        // Refused once it is villa-only.
        $this->actingAs($admin)
            ->post('/owners', $this->ownerPayload(['name' => 'After', 'property_type' => 'apartment']))
            ->assertSessionHasErrors('property_type');

        $this->assertDatabaseHas('owners', ['name' => 'Before']);
        $this->assertDatabaseMissing('owners', ['name' => 'After']);
    }

    // ── The create screen only offers supported types ────────────────────────

    public function test_create_form_offers_only_the_supported_type(): void
    {
        $this->seedRoles();
        $villaSociety = $this->societyOfType(SocietyPropertyType::Villa);
        $admin = $this->makeUserWithRole($villaSociety, 'Admin');

        $html = $this->actingAs($admin)->get('/owners/create')->assertOk()->getContent();

        // The <select> itself must not carry an apartment option.
        preg_match('/<select[^>]*name="property_type".*?<\/select>/s', $html, $m);
        $this->assertNotEmpty($m, 'property_type select not found on the page');

        $this->assertStringContainsString('value="villa"', $m[0]);
        $this->assertStringNotContainsString('value="apartment"', $m[0]);
    }

    public function test_create_form_offers_both_types_for_a_mixed_society(): void
    {
        $this->seedRoles();
        $society = $this->societyOfType(SocietyPropertyType::Both);
        $admin = $this->makeUserWithRole($society, 'Admin');

        $html = $this->actingAs($admin)->get('/owners/create')->assertOk()->getContent();

        preg_match('/<select[^>]*name="property_type".*?<\/select>/s', $html, $m);
        $this->assertNotEmpty($m);
        $this->assertStringContainsString('value="villa"', $m[0]);
        $this->assertStringContainsString('value="apartment"', $m[0]);
    }
}
