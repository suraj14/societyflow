<?php

namespace Tests\Feature;

use App\Enums\SocietyPropertyType;
use App\Models\Society;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A society only accepts the property types it declares.
 *
 * The forms filter their options, but filtering is cosmetic: anyone can POST
 * whatever they like. These tests drive the endpoints directly, skipping the
 * UI entirely, because that is how the attack arrives.
 *
 * Brief §2 ("Never trust frontend filtering alone") and §16.
 */
class PropertyTypeEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // OwnerController assigns one of these on create; without them the
        // whole create transaction rolls back on RoleDoesNotExist.
        $this->makeRole('Apartment Owner');
        $this->makeRole('Villa Owner');
    }

    private function societyOf(SocietyPropertyType $type): Society
    {
        $society = $this->makeSociety();
        $society->update(['property_type' => $type->value]);

        return $society->fresh();
    }

    /** @return array{0:Society,1:\App\Models\User} */
    private function adminOf(SocietyPropertyType $type): array
    {
        $society = $this->societyOf($type);

        return [$society, $this->makeUserWithRole($society, 'Admin')];
    }

    private function ownerPayload(array $overrides = []): array
    {
        return array_merge([
            'name'   => 'Test Owner',
            'email'  => 'owner' . uniqid() . '@test.local',
            'phone'  => '9876543210',
            'status' => 'active',
        ], $overrides);
    }

    // ---------------------------------------------------------------- owners

    public function test_apartment_society_rejects_a_villa_owner(): void
    {
        [, $admin] = $this->adminOf(SocietyPropertyType::Apartment);

        $this->actingAs($admin)
            ->post('/owners', $this->ownerPayload(['property_type' => 'villa']))
            ->assertSessionHasErrors('property_type');
    }

    public function test_villa_society_rejects_an_apartment_owner(): void
    {
        [, $admin] = $this->adminOf(SocietyPropertyType::Villa);

        $this->actingAs($admin)
            ->post('/owners', $this->ownerPayload(['property_type' => 'apartment']))
            ->assertSessionHasErrors('property_type');
    }

    public function test_villa_society_accepts_a_villa_owner(): void
    {
        [, $admin] = $this->adminOf(SocietyPropertyType::Villa);

        $this->actingAs($admin)
            ->post('/owners', $this->ownerPayload(['property_type' => 'villa']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('owners', ['property_type' => 'villa']);
    }

    public function test_mixed_society_accepts_both(): void
    {
        [, $admin] = $this->adminOf(SocietyPropertyType::Both);

        foreach (['villa', 'apartment'] as $type) {
            $this->actingAs($admin)
                ->post('/owners', $this->ownerPayload(['property_type' => $type]))
                ->assertSessionHasNoErrors();
        }

        $this->assertDatabaseHas('owners', ['property_type' => 'villa']);
        $this->assertDatabaseHas('owners', ['property_type' => 'apartment']);
    }

    /**
     * The society_id is taken from the authenticated user, never the request,
     * so naming a permissive society does not widen the rules.
     */
    public function test_forged_society_id_does_not_widen_the_allowed_types(): void
    {
        [, $admin] = $this->adminOf(SocietyPropertyType::Apartment);
        $permissive = $this->societyOf(SocietyPropertyType::Both);

        $this->actingAs($admin)
            ->post('/owners', $this->ownerPayload([
                'property_type' => 'villa',
                'society_id'    => $permissive->id,
            ]))
            ->assertSessionHasErrors('property_type');

        $this->assertDatabaseMissing('owners', ['property_type' => 'villa']);
    }

    // ----------------------------------------------------------------- forms

    public function test_owner_form_offers_only_the_supported_type(): void
    {
        [, $admin] = $this->adminOf(SocietyPropertyType::Villa);

        $html = $this->actingAs($admin)->get('/owners/create')->assertOk()->getContent();

        $this->assertStringContainsString('value="villa"', $html);
        $this->assertStringNotContainsString('value="apartment"', $html);
    }

    /**
     * Narrowing a society must not rewrite owners that already exist. The
     * edit form keeps the stored value selectable and flags it instead.
     */
    public function test_edit_form_keeps_a_now_unsupported_stored_value(): void
    {
        [$society, $admin] = $this->adminOf(SocietyPropertyType::Both);

        $this->actingAs($admin)
            ->post('/owners', $this->ownerPayload(['property_type' => 'villa']))
            ->assertSessionHasNoErrors();

        $owner = \App\Models\Owner::where('property_type', 'villa')->firstOrFail();

        $society->update(['property_type' => SocietyPropertyType::Apartment->value]);

        $html = $this->actingAs($admin)->get("/owners/{$owner->id}/edit")->assertOk()->getContent();

        $this->assertStringContainsString('value="villa"', $html);
        $this->assertStringContainsString('no longer supported', $html);
    }
}
