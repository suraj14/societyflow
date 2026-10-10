<?php

namespace Tests\Feature;

use App\Models\AccessPass;
use App\Models\Visitor;
use App\Support\IdProof;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * ID proof from the gate's document scanner: stored minimally, looked up
 * safely.
 *
 * The scanning itself lives in the Flutter app, so what is pinned here is
 * the part the server is responsible for: that a full Aadhaar number cannot
 * be stored however it is sent, and that the duplicate-visitor lookup cannot
 * be used to read another society's visitors or to enumerate numbers from a
 * resident account.
 */
class VisitorIdProofTest extends TestCase
{
    use RefreshDatabase;

    private function guard(): array
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $guard   = $this->makeUserWithRole($society, 'Guard');

        return [$guard, $flat, $society];
    }

    private function registerVisitor(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        [$guard, $flat] = $this->guard();
        Sanctum::actingAs($guard);

        return $this->postJson('/api/v1/visitors', array_merge([
            'visitor_phone' => '9876543210',
            'visitor_type'  => 'guest',
            'flat_id'       => $flat->id,
        ], $overrides));
    }

    // ── Aadhaar redaction ────────────────────────────────────────────────

    public function test_a_full_aadhaar_number_is_never_stored(): void
    {
        $this->registerVisitor([
            'visitor_id_proof'  => 'aadhaar',
            'visitor_id_number' => '1234 5678 9012',
        ])->assertCreated();

        $stored = Visitor::latest('id')->first();

        $this->assertSame('aadhaar', $stored->visitor_id_proof);
        $this->assertSame('XXXX XXXX 9012', $stored->visitor_id_number);
        $this->assertStringNotContainsString('1234', (string) $stored->visitor_id_number);
        $this->assertStringNotContainsString('5678', (string) $stored->visitor_id_number);
    }

    public function test_an_unspaced_aadhaar_is_redacted_too(): void
    {
        $this->registerVisitor([
            'visitor_id_proof'  => 'aadhaar',
            'visitor_id_number' => '123456789012',
        ])->assertCreated();

        $this->assertSame('XXXX XXXX 9012', Visitor::latest('id')->first()->visitor_id_number);
    }

    public function test_other_document_types_keep_their_reference(): void
    {
        $this->registerVisitor([
            'visitor_id_proof'  => 'pan',
            'visitor_id_number' => 'ABCDE1234F',
        ])->assertCreated();

        $this->assertSame('ABCDE1234F', Visitor::latest('id')->first()->visitor_id_number);
    }

    public function test_an_unknown_document_type_is_rejected(): void
    {
        $this->registerVisitor(['visitor_id_proof' => 'voter_card'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('visitor_id_proof');
    }

    public function test_a_visitor_with_no_id_proof_still_registers(): void
    {
        $this->registerVisitor()->assertCreated();

        $stored = Visitor::latest('id')->first();
        $this->assertNull($stored->visitor_id_proof);
        $this->assertNull($stored->visitor_id_number);
    }

    public function test_the_redactor_drops_a_number_too_short_to_mask(): void
    {
        $this->assertNull(IdProof::redact('aadhaar', '901'));
        $this->assertNull(IdProof::redact('aadhaar', ''));
        $this->assertNull(IdProof::redact('aadhaar', null));
        $this->assertSame('ABCDE1234F', IdProof::redact('pan', 'ABCDE1234F'));
    }

    // ── Duplicate lookup ─────────────────────────────────────────────────

    public function test_lookup_finds_a_previous_visitor_in_the_same_society(): void
    {
        [$guard, $flat, $society] = $this->guard();
        Visitor::create([
            'society_id'          => $society->id,
            'flat_id'             => $flat->id,
            'visitor_name'        => 'Ramesh Kumar',
            'visitor_phone'       => '9876543210',
            'visitor_type'        => 'delivery',
            'expected_entry_time' => now(),
            'approval_status'     => 'allowed',
            'entry_status'        => 'pending',
        ]);

        Sanctum::actingAs($guard);
        $resp = $this->postJson('/api/v1/visitors/lookup', ['phone' => '9876543210'])->assertOk();

        $this->assertTrue($resp->json('data.found'));
        $this->assertSame('Ramesh Kumar', $resp->json('data.visitor.visitor_name'));
    }

    public function test_lookup_matches_a_number_saved_with_a_country_code(): void
    {
        [$guard, $flat, $society] = $this->guard();
        Visitor::create([
            'society_id'          => $society->id,
            'flat_id'             => $flat->id,
            'visitor_name'        => 'Ramesh Kumar',
            'visitor_phone'       => '+91 98765 43210',
            'visitor_type'        => 'guest',
            'expected_entry_time' => now(),
            'approval_status'     => 'allowed',
            'entry_status'        => 'pending',
        ]);

        Sanctum::actingAs($guard);

        $this->postJson('/api/v1/visitors/lookup', ['phone' => '9876543210'])
            ->assertOk()
            ->assertJsonPath('data.found', true);
    }

    public function test_lookup_does_not_reach_into_another_society(): void
    {
        $other     = $this->makeSociety();
        $otherFlat = $this->makeFlat($other, $this->makeBuilding($other));
        Visitor::create([
            'society_id'          => $other->id,
            'flat_id'             => $otherFlat->id,
            'visitor_name'        => 'SECRET Visitor B',
            'visitor_phone'       => '9876543210',
            'visitor_type'        => 'guest',
            'expected_entry_time' => now(),
            'approval_status'     => 'allowed',
            'entry_status'        => 'pending',
        ]);

        [$guard] = $this->guard();
        Sanctum::actingAs($guard);

        $resp = $this->postJson('/api/v1/visitors/lookup', ['phone' => '9876543210'])->assertOk();

        $this->assertFalse($resp->json('data.found'));
        $this->assertStringNotContainsString('SECRET Visitor B', $resp->getContent());
    }

    public function test_lookup_reports_an_active_access_pass_without_its_token(): void
    {
        [$guard, $flat, $society] = $this->guard();
        $pass = AccessPass::create([
            'society_id'  => $society->id,
            'flat_id'     => $flat->id,
            'person_name' => 'Daily Maid',
            'person_phone' => '9876543210',
            'category'    => 'maid',
            'pass_type'   => 'recurring_staff',
            'valid_from'  => now()->subDay()->toDateString(),
            'valid_until' => now()->addMonth()->toDateString(),
            'status'      => 'active',
            'created_by'  => $guard->id,
            // access_passes.token is NOT NULL and is generated by
            // AccessPassService, not a model hook, so a direct create()
            // supplies its own.
            'token'       => 'TESTPASSTOKEN0001',
        ]);

        Sanctum::actingAs($guard);
        $resp = $this->postJson('/api/v1/visitors/lookup', ['phone' => '9876543210'])->assertOk();

        $this->assertTrue($resp->json('data.found'));
        $this->assertSame('Daily Maid', $resp->json('data.access_pass.person_name'));
        $this->assertStringNotContainsString($pass->token, $resp->getContent());
    }

    public function test_lookup_returns_not_found_for_an_unknown_number(): void
    {
        [$guard] = $this->guard();
        Sanctum::actingAs($guard);

        $this->postJson('/api/v1/visitors/lookup', ['phone' => '9000000001'])
            ->assertOk()
            ->assertJsonPath('data.found', false)
            ->assertJsonPath('data.visitor', null);
    }

    public function test_a_resident_cannot_look_up_numbers(): void
    {
        $society  = $this->makeSociety();
        $resident = $this->makeUserWithRole($society, 'Tenant');
        Sanctum::actingAs($resident);

        $this->postJson('/api/v1/visitors/lookup', ['phone' => '9876543210'])->assertStatus(403);
    }

    public function test_lookup_requires_a_phone_number(): void
    {
        [$guard] = $this->guard();
        Sanctum::actingAs($guard);

        $this->postJson('/api/v1/visitors/lookup', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');
    }

    public function test_lookup_requires_authentication(): void
    {
        $this->postJson('/api/v1/visitors/lookup', ['phone' => '9876543210'])->assertStatus(401);
    }
}
