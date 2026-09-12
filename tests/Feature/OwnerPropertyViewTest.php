<?php

namespace Tests\Feature;

use App\Enums\SocietyPropertyType;
use App\Models\Society;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A resident is never shown a property kind their society does not have.
 *
 * Brief §5 ("Do not show meaningless cards for modules that do not exist")
 * and §7 - an owner in a villa township should not read "Apartments: 0".
 */
class OwnerPropertyViewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The portfolio card only renders for an owner holding more than one
     * property, so the fixture has to give them two.
     */
    private function ownerIn(SocietyPropertyType $type): \App\Models\User
    {
        $society = $this->makeSociety();
        $society->update(['property_type' => $type->value]);
        $society = $society->fresh();

        $owner = \App\Models\Owner::create([
            'society_id' => $society->id,
            'name'       => 'Portfolio Owner',
            'email'      => 'portfolio' . uniqid() . '@test.local',
            'phone'      => '9876543210',
            'status'     => 'active',
        ]);

        $isVilla  = $type === SocietyPropertyType::Villa;
        $building = $isVilla ? null : $this->makeBuilding($society);

        foreach (['U-1', 'U-2'] as $number) {
            \App\Models\Flat::create([
                'society_id'    => $society->id,
                'building_id'   => $building?->id,
                'owner_id'      => $owner->id,
                'property_type' => $isVilla ? 'villa' : 'apartment',
                'flat_number'   => $number,
                'floor'         => 1,
                'status'        => 'occupied',
            ]);
        }

        return $this->makeUserWithRole($society, 'Apartment Owner', ['owner_id' => $owner->id]);
    }

    public function test_villa_society_owner_sees_no_apartment_card(): void
    {
        $html = $this->actingAs($this->ownerIn(SocietyPropertyType::Villa))
            ->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('My Villas', $html);
        $this->assertStringNotContainsString('>Apartments<', $html);
    }

    public function test_apartment_society_owner_sees_no_villa_card(): void
    {
        $html = $this->actingAs($this->ownerIn(SocietyPropertyType::Apartment))
            ->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('My Apartments', $html);
        $this->assertStringNotContainsString('>Villas<', $html);
    }

    public function test_mixed_society_owner_still_sees_both_split_out(): void
    {
        $html = $this->actingAs($this->ownerIn(SocietyPropertyType::Both))
            ->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('Apartments', $html);
        $this->assertStringContainsString('Villas', $html);
        $this->assertStringContainsString('Total Units', $html);
    }
}
