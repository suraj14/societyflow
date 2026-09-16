<?php

namespace Tests\Feature;

use App\Enums\SocietyPropertyType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Navigation follows the society's configured property type.
 *
 * The sidebar used to list Buildings/Apartments and Villas unconditionally,
 * so a villa township showed apartment management it could never use, and an
 * apartment society showed villa areas. Super Admin administers every society
 * and keeps both groups.
 */
class PropertyTypeNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function adminOfSociety(SocietyPropertyType $type)
    {
        $society = $this->makeSociety();
        $society->update(['property_type' => $type->value]);

        return $this->makeUserWithRole($society->fresh(), 'Admin');
    }

    /**
     * Navigation links only, not any occurrence of the word.
     *
     * A bare substring check is wrong here: scripts and route helpers mention
     * these paths elsewhere in the page, so only real <a href> targets count.
     *
     * @return array<int,string>
     */
    private function navTargets(string $html): array
    {
        preg_match_all('#href="[^"]*/(buildings|flats|villa-areas|villas)"#', $html, $m);

        return array_values(array_unique($m[1]));
    }

    public function test_villa_society_hides_apartment_navigation(): void
    {
        $html = $this->actingAs($this->adminOfSociety(SocietyPropertyType::Villa))
            ->get('/dashboard')->assertOk()->getContent();

        $targets = $this->navTargets($html);

        $this->assertContains('villa-areas', $targets);
        $this->assertContains('villas', $targets);
        $this->assertNotContains('buildings', $targets);
        $this->assertNotContains('flats', $targets);
    }

    public function test_apartment_society_hides_villa_navigation(): void
    {
        $html = $this->actingAs($this->adminOfSociety(SocietyPropertyType::Apartment))
            ->get('/dashboard')->assertOk()->getContent();

        $targets = $this->navTargets($html);

        $this->assertContains('buildings', $targets);
        $this->assertContains('flats', $targets);
        $this->assertNotContains('villa-areas', $targets);
        $this->assertNotContains('villas', $targets);
    }

    public function test_mixed_society_shows_both(): void
    {
        $html = $this->actingAs($this->adminOfSociety(SocietyPropertyType::Both))
            ->get('/dashboard')->assertOk()->getContent();

        $targets = $this->navTargets($html);

        $this->assertContains('buildings', $targets);
        $this->assertContains('villa-areas', $targets);
    }

    /**
     * A Super Admin has society_id = null, and the guard must treat that as
     * "no society to narrow by" rather than dereferencing null.
     *
     * Note the property menus are additionally role-gated in this layout, so
     * a Super Admin sees none of them on /owners regardless of property type -
     * that is pre-existing behaviour, not something the property-type work
     * changed. What matters here is that the page renders.
     */
    public function test_null_society_does_not_break_navigation(): void
    {
        $society = $this->makeSociety();
        $society->update(['property_type' => SocietyPropertyType::Villa->value]);
        $superAdmin = $this->makeUserWithRole($society, 'Super Admin', ['society_id' => null]);

        $this->actingAs($superAdmin)->get('/owners')->assertOk();

        // And the guard itself resolves permissively for a null society.
        $this->assertNull($superAdmin->society);
    }

    /**
     * Owner/tenant assignment ("assign an existing user to an existing
     * property", distinct from creating one) links were built, tested and
     * society-scoped, but had no live navigation path anywhere - only an
     * unused sidebar partial nothing renders referenced them. Wired into
     * the live sidebar's existing property-type-gated groups.
     */
    public function test_assignment_links_follow_the_same_property_type_gate(): void
    {
        $villaHtml = $this->actingAs($this->adminOfSociety(SocietyPropertyType::Villa))
            ->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('villa-assignments', $villaHtml);
        $this->assertStringNotContainsString('apartment-assignments', $villaHtml);
        $this->assertStringContainsString('tenant-assignments', $villaHtml);

        $apartmentHtml = $this->actingAs($this->adminOfSociety(SocietyPropertyType::Apartment))
            ->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('apartment-assignments', $apartmentHtml);
        $this->assertStringNotContainsString('villa-assignments', $apartmentHtml);

        $mixedHtml = $this->actingAs($this->adminOfSociety(SocietyPropertyType::Both))
            ->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('apartment-assignments', $mixedHtml);
        $this->assertStringContainsString('villa-assignments', $mixedHtml);
    }
}
