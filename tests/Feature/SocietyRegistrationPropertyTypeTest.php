<?php

namespace Tests\Feature;

use App\Enums\SocietyPropertyType;
use App\Models\Society;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Registering a society asks what it contains, once.
 *
 * Before this, /register created a society with no property_type at all,
 * so the column default made every new society "both" - which then offered
 * villa menus to apartment blocks and apartment menus to villa townships,
 * the exact outcome the architecture exists to prevent (brief §1).
 */
class SocietyRegistrationPropertyTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // register() assigns this role to the new admin.
        $this->makeRole('Admin');

        SubscriptionPlan::create([
            'name'          => 'Free Forever',
            'slug'          => 'free-forever',
            'monthly_price' => 0,
            'yearly_price'  => 0,
            'status'        => 'active',
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'society_name'          => 'Test Society ' . uniqid(),
            'name'                  => 'Admin Person',
            'email'                 => 'admin' . uniqid() . '@test.local',
            'phone'                 => '9876543210',
            'password'              => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
            'terms'                 => '1',
            'property_type'         => 'villa',
        ], $overrides);
    }

    /** @dataProvider propertyTypes */
    public function test_the_chosen_property_type_is_stored(string $type): void
    {
        $payload = $this->payload(['property_type' => $type]);

        $this->post('/register', $payload)->assertSessionHasNoErrors();

        $society = Society::where('name', $payload['society_name'])->firstOrFail();

        $this->assertSame($type, $society->property_type->value);
    }

    public static function propertyTypes(): array
    {
        return [['apartment'], ['villa'], ['both']];
    }

    /**
     * The regression that matters: omitting the field must not quietly fall
     * back to the column default.
     */
    public function test_registration_without_a_property_type_is_rejected(): void
    {
        $payload = $this->payload();
        unset($payload['property_type']);

        $this->post('/register', $payload)->assertSessionHasErrors('property_type');

        $this->assertDatabaseMissing('societies', ['name' => $payload['society_name']]);
    }

    public function test_an_unknown_property_type_is_rejected(): void
    {
        $payload = $this->payload(['property_type' => 'penthouse']);

        $this->post('/register', $payload)->assertSessionHasErrors('property_type');

        $this->assertDatabaseMissing('societies', ['name' => $payload['society_name']]);
    }

    /** The form offers exactly the three supported choices. */
    public function test_the_form_offers_every_supported_choice(): void
    {
        $html = $this->get('/register')->assertOk()->getContent();

        foreach (SocietyPropertyType::values() as $value) {
            $this->assertStringContainsString(
                'value="' . $value . '"',
                $html,
                "the registration form does not offer '{$value}'"
            );
        }
    }
}
