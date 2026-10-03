<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The settings page must not swallow its own markup.
 *
 * A stray unclosed <script> before the Super Admin tab block meant every
 * byte from there to the next </script> was parsed as JavaScript: the
 * browser reported "Unexpected token '<'", and the Language, Storage and
 * Currency tabs never rendered at all. Nothing failed loudly - the page
 * still returned 200 - so only a browser console showed it.
 */
class SettingsPageIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function settingsHtml(string $role): string
    {
        $society = $this->makeSociety();
        $user    = $this->makeUserWithRole($society, $role);

        return $this->actingAs($user)->get('/settings')->assertOk()->getContent();
    }

    /** Every opened script tag is closed, or the markup after it disappears. */
    public function test_script_tags_are_balanced(): void
    {
        $html = $this->settingsHtml('Admin');

        $open  = preg_match_all('/<script\b/i', $html);
        $close = preg_match_all('/<\/script>/i', $html);

        $this->assertSame($close, $open, "settings page has {$open} <script> and {$close} </script>");
    }

    /** No script block may contain raw markup - that is the swallow signature. */
    public function test_no_inline_script_contains_html_markup(): void
    {
        $html = $this->settingsHtml('Admin');

        preg_match_all('/<script\b[^>]*>(.*?)<\/script>/is', $html, $matches);

        foreach ($matches[1] as $i => $code) {
            $this->assertDoesNotMatchRegularExpression(
                '/<\/(div|form|nav|section)>/i',
                $code,
                "inline script block {$i} contains HTML - a tag above it is unclosed"
            );
        }
    }

    /**
     * The three Super Admin tabs that the stray script had hidden. Their
     * partials existed the whole time; nothing rendered them.
     */
    public function test_super_admin_sees_language_storage_and_currency_tabs(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Super Admin');

        $html = $this->actingAs($admin)->get('/settings')->assertOk()->getContent();

        foreach (['language', 'storage', 'currency'] as $tab) {
            $this->assertStringContainsString(
                "activeTab === '{$tab}'",
                $html,
                "the {$tab} settings tab is missing from the page"
            );
        }
    }

    /**
     * The Role & Module Permissions tab listed Admin/Villa Owner/Apartment
     * Owner/Tenant/Staff only - Accountant and Guard are real, current roles
     * with real modules (Bills, Visitors) and had no row to configure at all.
     *
     * Villa Owner/Apartment Owner were later normalized into the single
     * canonical 'Owner' role (see OwnerRoleNormalizationTest) - the page
     * shows 'Owner' instead of the two legacy names.
     *
     * The page briefly used a redesigned compact matrix (Alpine + a JSON
     * payload) instead of the original one-card-per-module layout with a
     * plain checkbox per role; that redesign was explicitly reverted at the
     * user's request, so this checks the classic
     * name="permissions[module][role]" markup, not a JSON blob.
     */
    public function test_role_permissions_tab_includes_every_current_role(): void
    {
        $html = $this->settingsHtml('Admin');

        foreach (['Admin', 'Owner', 'Tenant', 'Staff', 'Accountant', 'Guard'] as $role) {
            $this->assertStringContainsString(
                "permissions[facilities][{$role}]",
                $html,
                "the {$role} column is missing from Role & Module Permissions"
            );
        }

        $this->assertStringNotContainsString('permissions[facilities][Villa Owner]', $html);
        $this->assertStringNotContainsString('permissions[facilities][Apartment Owner]', $html);

        // The original 8 module keys must still be present under their
        // original names - existing saved data is keyed by these exact
        // strings, so silently renaming or dropping one would orphan it.
        foreach (['facilities', 'visitors', 'complaints', 'bills', 'services', 'notices', 'reports', 'advanced_settings'] as $moduleKey) {
            $this->assertStringContainsString(
                "permissions[{$moduleKey}]",
                $html,
                "the existing '{$moduleKey}' module is missing from the permissions grid"
            );
        }
    }

    /**
     * SettingsController::updatePermissions() saves under
     * permissions.module_permissions (SystemSetting::set('permissions',
     * 'module_permissions', ...)), but SystemSetting::getGroup() returns a
     * group keyed by its setting keys, one level shallower than the Blade
     * used to read - so a saved toggle could never re-render as checked for
     * any role, on any refresh.
     */
    public function test_saved_permission_toggle_persists_across_a_refresh(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $this->actingAs($admin)->post('/settings/permissions', [
            'permissions' => [
                'bills' => ['Accountant' => '1'],
            ],
        ])->assertRedirect();

        $html = $this->actingAs($admin)->get('/settings')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/name="permissions\[bills\]\[Accountant\]"[^>]*checked/',
            $html,
            'a saved permission toggle did not remain checked after a refresh'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/name="permissions\[bills\]\[Admin\]"[^>]*checked/',
            $html,
            'a toggle that was never saved should not appear checked'
        );
    }

    /** The AJAX save path (Accept: application/json) returns JSON, not a redirect. */
    public function test_permission_save_returns_json_when_requested(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $this->actingAs($admin)
            ->postJson('/settings/permissions', [
                'permissions' => ['bills' => ['Accountant' => true]],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);
    }
}
