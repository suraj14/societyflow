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
}
