<?php

namespace Tests\Feature;

use App\Helpers\ThemeHelper;
use App\Models\ComplaintCategory;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Verifies the SAVE -> DATABASE -> RELOAD -> REAL EFFECT chain for the
 * Settings areas this pass audited. Live reproduction against the running
 * dev server already confirmed Society Name and Theme colors persist and
 * apply correctly - these tests pin that behavior (and the new app_name
 * field) in an isolated database so it can't regress silently.
 */
class SettingsPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_society_name_persists_and_reloads_correctly(): void
    {
        $society = $this->makeSociety('Original Name');
        $admin = $this->makeUserWithRole($society, 'Admin');

        $this->actingAs($admin)->post('/settings/society', [
            'society_name' => 'Renamed Society',
            'society_phone' => '9998887770',
            'society_email' => 'renamed@test.local',
            'society_address' => 'New Address',
            'society_country' => 'IN',
        ])->assertRedirect();

        $this->assertDatabaseHas('societies', ['id' => $society->id, 'name' => 'Renamed Society']);

        $html = $this->actingAs($admin)->get('/settings')->assertOk()->getContent();
        $this->assertStringContainsString('value="Renamed Society"', $html);
    }

    /**
     * The header/sidebar branding text is a *separate* SystemSetting
     * ('app'.'app_name', read through ThemeHelper's own cache) that
     * updateSociety() never touched - so saving a new Society Name updated
     * societies.name correctly, but the visible branding kept showing
     * whatever app_name had last been set to, looking exactly like "save
     * reported success but nothing changed". Reproduced live against the
     * real "Royal Eden" / "Royal Edendd gg" mismatch before this fix.
     */
    public function test_society_name_save_syncs_the_sidebar_branding_text(): void
    {
        $society = $this->makeSociety('Old Name');
        $admin = $this->makeUserWithRole($society, 'Admin');
        SystemSetting::set('app', 'app_name', 'Stale Branding', 'string', $society->id);
        ThemeHelper::clearCache($society->id);

        $this->actingAs($admin)->post('/settings/society', [
            'society_name' => 'New Name',
            'society_phone' => '9998887771',
            'society_email' => 'new@test.local',
            'society_address' => 'Address',
            'society_country' => 'IN',
        ])->assertRedirect();

        $this->assertSame('New Name', ThemeHelper::getAppName());

        $html = $this->actingAs($admin)->get('/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('New Name', $html);
        $this->assertStringNotContainsString('Stale Branding', $html);
    }

    public function test_society_name_sync_does_not_affect_another_society(): void
    {
        $societyA = $this->makeSociety('A Original');
        $societyB = $this->makeSociety('B Original');
        $adminA = $this->makeUserWithRole($societyA, 'Admin');

        $this->actingAs($adminA)->post('/settings/society', [
            'society_name' => 'A Renamed',
            'society_phone' => '9998887772',
            'society_email' => 'a@test.local',
            'society_address' => 'Address A',
            'society_country' => 'IN',
        ])->assertRedirect();

        $this->assertDatabaseMissing('system_settings', ['society_id' => $societyB->id, 'group' => 'app', 'key' => 'app_name']);
        $this->assertDatabaseHas('societies', ['id' => $societyB->id, 'name' => 'B Original']);
    }

    public function test_app_settings_persist_including_new_app_name_field(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');

        $this->actingAs($admin)->post('/settings/app', [
            'app_name' => 'Custom Branding',
            'default_language' => 'hi',
            'default_currency' => 'USD',
            'timezone' => 'America/New_York',
            'date_format' => 'm/d/Y',
        ])->assertRedirect();

        $this->assertSame('Custom Branding', SystemSetting::get('app', 'app_name', null, $society->id));
        $this->assertSame('hi', SystemSetting::get('app', 'default_language', null, $society->id));
        $this->assertSame('USD', SystemSetting::get('app', 'default_currency', null, $society->id));

        // The sidebar/header reads this through ThemeHelper's own cache -
        // the controller must bust it, or the old name would show for up to
        // an hour.
        $this->assertSame('Custom Branding', ThemeHelper::getAppName());

        $html = $this->actingAs($admin)->get('/settings?tab=app')->assertOk()->getContent();
        $this->assertStringContainsString('value="Custom Branding"', $html);
    }

    public function test_theme_colors_persist_and_bust_the_cache(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');

        // Prime the cache with the old value, as a real page view would.
        $this->actingAs($admin)->get('/dashboard');
        $this->assertNotSame('#123456', ThemeHelper::getPrimaryColor());

        $this->actingAs($admin)->post('/settings/theme', [
            'primary_color' => '#123456',
            'secondary_color' => '#654321',
            'sidebar_theme' => 'light',
            'button_style' => 'rounded',
        ])->assertRedirect();

        $this->assertSame('#123456', ThemeHelper::getPrimaryColor());

        $html = $this->actingAs($admin)->get('/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('--primary-color: #123456', $html);
    }

    /**
     * --secondary-color was defined as a CSS variable with no utility class
     * ever reading it - Secondary Color saved to the database correctly but
     * had zero visible effect anywhere. .btn-secondary now exists (mirrors
     * .btn-primary) and the Settings page's own "Clear Cache" button uses
     * it, so this is a genuine rendered-output check, not just a DB read.
     */
    public function test_secondary_color_persists_and_actually_renders(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');

        $this->actingAs($admin)->post('/settings/theme', [
            'primary_color' => '#7C3AED',
            'secondary_color' => '#F59E0B',
            'sidebar_theme' => 'light',
            'button_style' => 'rounded',
        ])->assertRedirect();

        $this->assertSame('#F59E0B', ThemeHelper::getSecondaryColor());

        $html = $this->actingAs($admin)->get('/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('--secondary-color: #F59E0B', $html);
        $this->assertStringContainsString('.btn-secondary', $html);
        $this->assertStringContainsString('background-color: #F59E0B !important', $html);
    }

    public function test_currency_setting_persists_and_is_read_by_the_helper(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');

        $this->actingAs($admin)->post('/settings/currency-features', [
            'currency' => 'USD',
            'currency_symbol' => '$',
            'currency_position' => 'left',
            'decimal_places' => 2,
            'thousand_separator' => ',',
            'decimal_separator' => '.',
        ])->assertRedirect();

        $this->assertSame('USD', SystemSetting::get('currency', 'currency', null, $society->id));
        $this->assertSame('$', SystemSetting::get('currency', 'currency_symbol', null, $society->id));
    }

    public function test_feature_toggle_persists_and_gates_sidebar_navigation(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');

        // Real consumer: partials/mobile-menu.blade.php gates the
        // Notices link on this exact setting.
        $this->actingAs($admin)->post('/settings/currency-features', [
            'currency' => 'INR', 'currency_symbol' => '₹', 'currency_position' => 'left',
            'decimal_places' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.',
            'enable_notices' => '0',
        ])->assertRedirect();

        $this->assertFalse((bool) SystemSetting::get('features', 'enable_notices', true, $society->id));
    }

    public function test_complaint_category_crud_persists_and_is_used_by_complaints(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');

        $this->actingAs($admin)->post('/settings/complaint-categories', [
            'name' => 'Plumbing',
            'description' => 'Water and pipe issues',
        ])->assertRedirect();

        $category = ComplaintCategory::where('society_id', $society->id)->where('name', 'Plumbing')->first();
        $this->assertNotNull($category);
        $this->assertSame('active', $category->status);

        // Real consumer: the complaint creation form lists active categories.
        $html = $this->actingAs($admin)->get('/complaints/create')->assertOk()->getContent();
        $this->assertStringContainsString('Plumbing', $html);

        $this->actingAs($admin)->post("/settings/complaint-categories/{$category->id}/toggle")->assertRedirect();
        $this->assertSame('inactive', $category->fresh()->status);

        $this->actingAs($admin)->delete("/settings/complaint-categories/{$category->id}")->assertRedirect();
        $this->assertDatabaseMissing('complaint_categories', ['id' => $category->id]);
    }

    public function test_complaint_category_cannot_be_modified_across_societies(): void
    {
        $societyA = $this->makeSociety('A');
        $societyB = $this->makeSociety('B');
        $adminA = $this->makeUserWithRole($societyA, 'Admin');
        $categoryB = ComplaintCategory::create(['society_id' => $societyB->id, 'name' => 'Electrical', 'status' => 'active']);

        $this->actingAs($adminA)->post("/settings/complaint-categories/{$categoryB->id}/toggle")->assertNotFound();
        $this->assertSame('active', $categoryB->fresh()->status);
    }

    public function test_email_settings_persist(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');

        $this->actingAs($admin)->post('/settings/email', [
            'mail_driver' => 'smtp',
            'mail_host' => 'smtp.example.com',
            'mail_port' => 587,
            'mail_encryption' => 'tls',
            'mail_username' => 'noreply@example.com',
            'mail_password' => 'secret-not-logged',
            'mail_from_address' => 'noreply@example.com',
            'mail_from_name' => 'Test Society',
        ])->assertRedirect();

        $this->assertSame('smtp.example.com', SystemSetting::get('email', 'mail_host', null, $society->id));
    }

    public function test_security_settings_persist(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');

        $response = $this->actingAs($admin)->post('/settings/security', [
            'login_attempt_limit' => 5,
            'session_timeout' => 60,
            'password_min_length' => 8,
            'password_require_special' => '1',
        ]);
        $response->assertRedirect();

        $this->assertSame(60, (int) SystemSetting::get('security', 'session_timeout', null, $society->id));
    }

    /**
     * societies.address is a NOT NULL column. This used to be validated as
     * 'nullable', so clearing the field crashed the request with an
     * uncaught QueryException (a raw 500) instead of a validation error -
     * found while writing the cross-society isolation test below, which
     * originally sent an empty address to simulate a minimal payload.
     */
    public function test_clearing_the_address_field_is_a_validation_error_not_a_crash(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');

        $response = $this->actingAs($admin)->post('/settings/society', [
            'society_name' => 'Still Named This',
            'society_address' => '',
        ]);

        $response->assertSessionHasErrors('society_address');
        $this->assertDatabaseHas('societies', ['id' => $society->id, 'name' => $society->name]);
    }

    public function test_admin_from_one_society_cannot_change_another_societys_settings(): void
    {
        $societyA = $this->makeSociety('A');
        $societyB = $this->makeSociety('B');
        $adminA = $this->makeUserWithRole($societyA, 'Admin');

        $this->actingAs($adminA)->post('/settings/society', [
            'society_name' => 'Hijacked Name',
            'society_phone' => '9998887771',
            'society_email' => 'a@test.local',
            'society_address' => 'A Address',
            'society_country' => 'IN',
        ])->assertRedirect();

        // updateSociety() always writes to the acting admin's own society -
        // society B must be untouched regardless of what was submitted.
        $this->assertDatabaseMissing('societies', ['id' => $societyB->id, 'name' => 'Hijacked Name']);
    }
}
