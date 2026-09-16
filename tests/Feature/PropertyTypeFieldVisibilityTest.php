<?php

namespace Tests\Feature;

use App\Enums\SocietyPropertyType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A single-type society never shows a choice with exactly one option in it.
 *
 * Earlier sessions filtered the Property Type <select> down to the society's
 * own allowed types (so an apartment-only society could never post "villa"),
 * but the select itself stayed visible even with one option - still a
 * pointless "Select Property Type" dropdown to click through. This hides
 * the select and shows a plain read-only line instead, for the common case:
 * an Admin fixed to their own society. Super Admin - who can switch which
 * society a form applies to live, via a selector this behaviour deliberately
 * leaves alone - keeps the dropdown, since the value is not fixed under them.
 */
class PropertyTypeFieldVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // OwnerController::store() assigns one of these on create; without
        // them the create transaction rolls back on RoleDoesNotExist.
        $this->makeRole('Apartment Owner');
        $this->makeRole('Villa Owner');
    }

    private function adminOf(SocietyPropertyType $type)
    {
        $society = $this->makeSociety();
        $society->update(['property_type' => $type->value]);

        return $this->makeUserWithRole($society->fresh(), 'Admin');
    }

    public function test_villa_only_society_hides_the_dropdown_and_shows_read_only_text(): void
    {
        $html = $this->actingAs($this->adminOf(SocietyPropertyType::Villa))
            ->get('/owners/create')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<select[^>]*id="property_type"[^>]*class="[^"]*\bhidden\b/',
            $html,
            'the select must carry the hidden class for a single-type society'
        );
        $this->assertStringContainsString('Property type: Villa', $html);
        $this->assertStringNotContainsString('>Apartment<', $html);
    }

    public function test_apartment_only_society_hides_the_dropdown_and_shows_read_only_text(): void
    {
        $html = $this->actingAs($this->adminOf(SocietyPropertyType::Apartment))
            ->get('/owners/create')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<select[^>]*id="property_type"[^>]*class="[^"]*\bhidden\b/',
            $html
        );
        $this->assertStringContainsString('Property type: Apartment', $html);
        $this->assertStringNotContainsString('>Villa<', $html);
    }

    /** The hidden select still carries the right value and it still submits. */
    public function test_the_fixed_value_is_still_present_and_selected_in_the_markup(): void
    {
        $html = $this->actingAs($this->adminOf(SocietyPropertyType::Villa))
            ->get('/owners/create')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<option value="villa" selected>/',
            $html,
            'the single option must be pre-selected so the hidden select still posts villa'
        );
    }

    /** Mixed society keeps the visible, required dropdown - a real choice exists. */
    public function test_mixed_society_keeps_the_visible_dropdown(): void
    {
        $html = $this->actingAs($this->adminOf(SocietyPropertyType::Both))
            ->get('/owners/create')->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/<select[^>]*id="property_type"[^>]*class="[^"]*\bhidden\b/',
            $html
        );
        $this->assertStringContainsString('Property Type', $html);
        $this->assertStringNotContainsString('Property type: Villa', $html);
        $this->assertStringNotContainsString('Property type: Apartment', $html);
    }

    /** Super Admin can switch society live, so the dropdown stays visible for them too. */
    public function test_super_admin_always_keeps_the_visible_dropdown(): void
    {
        $society = $this->makeSociety();
        $society->update(['property_type' => SocietyPropertyType::Villa->value]);
        $superAdmin = $this->makeUserWithRole($society->fresh(), 'Super Admin');

        $html = $this->actingAs($superAdmin)->get('/owners/create')->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/<select[^>]*id="property_type"[^>]*class="[^"]*\bhidden\b/',
            $html
        );
    }

    /**
     * Submitting the hidden field's value is exactly the same request the
     * visible dropdown would have produced - the backend still enforces it
     * independently either way (PropertyTypeEnforcementTest covers a forged
     * value in more depth); this just confirms the happy path the hidden
     * field is meant to drive actually works end to end.
     */
    public function test_submitting_the_hidden_fields_value_creates_the_owner(): void
    {
        $admin = $this->adminOf(SocietyPropertyType::Villa);

        $this->actingAs($admin)->post('/owners', [
            'name'          => 'Villa Owner Via Hidden Field',
            'email'         => 'hidden' . uniqid() . '@test.local',
            'phone'         => '9876543210',
            'status'        => 'active',
            'property_type' => 'villa',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('owners', [
            'name'          => 'Villa Owner Via Hidden Field',
            'property_type' => 'villa',
        ]);
    }

    // --------------------------------------------------------------- edit

    private function ownerIn($society, string $propertyType = 'villa'): \App\Models\Owner
    {
        return \App\Models\Owner::create([
            'society_id'    => $society->id,
            'property_type' => $propertyType,
            'name'          => 'Existing Owner',
            'email'         => 'existing' . uniqid() . '@test.local',
            'phone'         => '9876543210',
            'status'        => 'active',
        ]);
    }

    public function test_edit_hides_the_dropdown_for_a_single_type_society(): void
    {
        $admin = $this->adminOf(SocietyPropertyType::Villa);
        $owner = $this->ownerIn($admin->society);

        $html = $this->actingAs($admin)
            ->get("/owners/{$owner->id}/edit")->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<select[^>]*id="property_type"[^>]*class="[^"]*\bhidden\b/',
            $html
        );
        $this->assertStringContainsString('Property type: Villa', $html);
    }

    public function test_edit_keeps_the_dropdown_visible_for_a_mixed_society(): void
    {
        $admin = $this->adminOf(SocietyPropertyType::Both);
        $owner = $this->ownerIn($admin->society, 'apartment');

        $html = $this->actingAs($admin)
            ->get("/owners/{$owner->id}/edit")->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/<select[^>]*id="property_type"[^>]*class="[^"]*\bhidden\b/',
            $html
        );
    }

    /**
     * The one case where a single "real" type is not the whole story: the
     * owner's stored value is no longer supported, so the controller adds a
     * second, flagged option. The dropdown must stay visible so the admin
     * can actually see and resolve the mismatch, not have it hidden away.
     */
    public function test_edit_keeps_the_dropdown_visible_when_the_stored_value_is_now_unsupported(): void
    {
        $admin = $this->adminOf(SocietyPropertyType::Villa);
        $owner = $this->ownerIn($admin->society, 'apartment'); // narrowed after the fact

        $html = $this->actingAs($admin)
            ->get("/owners/{$owner->id}/edit")->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/<select[^>]*id="property_type"[^>]*class="[^"]*\bhidden\b/',
            $html,
            'a flagged, no-longer-supported value must keep the dropdown visible'
        );
    }
}
