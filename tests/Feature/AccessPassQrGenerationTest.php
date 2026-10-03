<?php

namespace Tests\Feature;

use App\Models\AccessPass;
use App\Models\Flat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "The QR must be generated automatically, entirely by SocietyFlow, never
 * by hand on a third-party website" - the specific claim this file exists
 * to hold the backend up to. The token itself is the QR's whole payload
 * (access-passes/show.blade.php renders it client-side with the `qrcode`
 * package - nothing server-side to test there), so what matters here is
 * that the token this page will encode is always present, unique, and not
 * something a guesser could derive from the pass's own visible fields.
 */
class AccessPassQrGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_access_pass_always_has_a_token_the_moment_it_is_created(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $owner   = $this->makeResidentUser($society, $flat);
        $this->makeRole('Apartment Owner');
        $owner->assignRole('Apartment Owner');

        $this->actingAs($owner)->post('/access-passes', [
            'flat_id'      => $flat->id,
            'pass_type'    => 'recurring_staff',
            'person_name'  => 'Ramraj',
            'allowed_days' => [1, 2, 3, 4, 5],
        ])->assertRedirect();

        $pass = AccessPass::where('person_name', 'Ramraj')->firstOrFail();

        $this->assertNotEmpty($pass->token);
        // A token short enough to be guessed or read over someone's
        // shoulder digit-by-digit defeats the point of using one at all.
        $this->assertGreaterThanOrEqual(32, strlen($pass->token));
    }

    public function test_every_generated_token_is_unique_across_many_passes(): void
    {
        $tokens = [];
        for ($i = 0; $i < 200; $i++) {
            $tokens[] = AccessPass::generateToken();
        }

        $this->assertCount(200, array_unique($tokens), 'generateToken() produced a duplicate within 200 calls');
    }

    /**
     * Not a proof of cryptographic randomness (that's Str::random()'s job,
     * which is what generateToken() delegates to), just the specific,
     * checkable claim the brief makes: the token is not derived from the
     * pass's own name/phone/id, so reading those off the pass tells a
     * guesser nothing about the token.
     */
    public function test_token_is_not_derived_from_the_persons_visible_details(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $pass = AccessPass::create([
            'society_id'   => $society->id,
            'flat_id'      => $flat->id,
            'person_name'  => 'Ramraj',
            'person_phone' => '9876500001',
            'pass_type'    => 'recurring_staff',
            'token'        => AccessPass::generateToken(),
            'allowed_days' => [1, 2, 3, 4, 5],
            'valid_from'   => now()->toDateString(),
            'status'       => 'active',
            'created_by'   => $admin->id,
        ]);

        // Not a check on $pass->id: a short id is common enough that a
        // random 40-char string containing it as a substring is expected
        // by chance and proves nothing either way.
        $token = strtolower($pass->token);
        $this->assertStringNotContainsString('ramraj', $token);
        $this->assertStringNotContainsString('9876500001', $token);
    }

    public function test_the_database_itself_rejects_a_duplicate_token(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $sharedToken = AccessPass::generateToken();

        AccessPass::create([
            'society_id' => $society->id, 'flat_id' => $flat->id, 'person_name' => 'First',
            'pass_type' => 'recurring_staff', 'token' => $sharedToken, 'allowed_days' => [1],
            'valid_from' => now()->toDateString(), 'status' => 'active', 'created_by' => $admin->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        AccessPass::create([
            'society_id' => $society->id, 'flat_id' => $flat->id, 'person_name' => 'Second',
            'pass_type' => 'recurring_staff', 'token' => $sharedToken, 'allowed_days' => [1],
            'valid_from' => now()->toDateString(), 'status' => 'active', 'created_by' => $admin->id,
        ]);
    }

    /** The web gate route, not just the mobile API, rejects a role with no gate business. */
    public function test_web_scan_endpoint_rejects_a_non_gate_role(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $tenant  = $this->makeResidentUser($society, $flat);
        $tenant->assignRole($this->makeRole('Tenant')->name);

        $this->actingAs($tenant)->post('/guard/scan', ['token' => 'whatever'])
            ->assertForbidden();
    }

    /** The page never asks anyone to generate the QR - it renders the moment a pass exists. */
    public function test_show_page_renders_the_token_for_client_side_qr_generation(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $pass = AccessPass::create([
            'society_id' => $society->id, 'flat_id' => $flat->id, 'person_name' => 'Ramraj',
            'pass_type' => 'recurring_staff', 'token' => AccessPass::generateToken(), 'allowed_days' => [1],
            'valid_from' => now()->toDateString(), 'status' => 'active', 'created_by' => $admin->id,
        ]);

        $html = $this->actingAs($admin)->get("/access-passes/{$pass->id}")->assertOk()->getContent();

        // The QR container and the token it will encode are both present -
        // no "Generate QR" action exists to click, and no external QR
        // service URL is referenced anywhere on the page.
        $this->assertStringContainsString('id="qr-code"', $html);
        $this->assertStringContainsString('data-token="' . $pass->token . '"', $html);
        $this->assertStringContainsString('Show this QR at the gate', $html);
        $this->assertStringNotContainsString('the-qrcode-generator.com', $html);
        $this->assertStringNotContainsString('qrcode-monkey', $html);
        $this->assertStringNotContainsString('goqr.me', $html);
    }

    public function test_show_page_does_not_render_the_token_as_the_primary_visible_text(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $pass = AccessPass::create([
            'society_id' => $society->id, 'flat_id' => $flat->id, 'person_name' => 'Ramraj',
            'pass_type' => 'recurring_staff', 'token' => AccessPass::generateToken(), 'allowed_days' => [1],
            'valid_from' => now()->toDateString(), 'status' => 'active', 'created_by' => $admin->id,
        ]);

        $html = $this->actingAs($admin)->get("/access-passes/{$pass->id}")->assertOk()->getContent();

        // The token is still in the markup (inside the collapsed <details>
        // fallback) but must not be the headline instruction any more.
        $this->assertStringContainsString('<details', $html);
        $this->assertStringContainsString('enter manually', $html);
    }
}
