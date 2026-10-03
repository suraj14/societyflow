<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Payment;
use App\Models\Society;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PaymentController's write paths repeated the `&&` short-circuit that
 * CLAUDE.md §3 calls out:
 *
 *     if ($user->hasRole('Admin') && $flat->society_id != $user->society_id)
 *
 * Every role the condition does not name - Apartment Owner, Villa Owner,
 * Tenant, Guard, Accountant, Staff - skipped the check entirely and could
 * post another society's flat_id. A prior session fixed the identical bug in
 * show(); store() and update() were left behind.
 *
 * A second, unrelated defect shared the same form: an owner holding both a
 * villa and an apartment was sent a hidden property_type of 'mixed', which
 * is a display label and not a PropertyType, so it failed the
 * in:apartment,villa rule with no visible field to correct.
 */
class PaymentCrossSocietyWriteTest extends TestCase
{
    use RefreshDatabase;

    /** A flat in its own society, with an Admin for that society. */
    private function flatIn(Society $society, string $number = '101'): Flat
    {
        return $this->makeFlat($society, $this->makeBuilding($society), $number);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'property_type'  => 'apartment',
            'amount'         => 1500,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
            'bill_type'      => 'Maintenance',
            'due_date'       => now()->addDays(7)->toDateString(),
        ], $overrides);
    }

    // ------------------------------------------------------------- store

    /**
     * The core leak: a resident of society A naming a flat in society B.
     * Before the fix this stored a payment row inside society B.
     */
    public function test_resident_cannot_create_a_payment_against_another_societys_flat(): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');

        $flatA = $this->flatIn($societyA, 'A-101');
        $flatB = $this->flatIn($societyB, 'B-101');

        $residentA = $this->makeResidentUser($societyA, $flatA);
        $this->makeRole('Apartment Owner');
        $residentA->assignRole('Apartment Owner');

        $response = $this->actingAs($residentA)
            ->post('/payments', $this->validPayload(['flat_id' => $flatB->id]));

        // Asserted before the status code: this is the leak itself. The old
        // code answered 302 here too, but only after writing the row into
        // society B, so a status assertion alone would not tell the two
        // apart.
        $this->assertDatabaseMissing('payments', ['flat_id' => $flatB->id]);

        $response->assertForbidden();
    }

    /** The named role was always blocked; it must stay blocked. */
    public function test_admin_cannot_create_a_payment_against_another_societys_flat(): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');

        $this->flatIn($societyA, 'A-101');
        $flatB = $this->flatIn($societyB, 'B-101');

        $adminA = $this->makeUserWithRole($societyA, 'Admin');

        $this->actingAs($adminA)
            ->post('/payments', $this->validPayload(['flat_id' => $flatB->id]))
            ->assertForbidden();

        $this->assertDatabaseMissing('payments', ['flat_id' => $flatB->id]);
    }

    /** A legitimate in-society payment must still succeed. */
    public function test_admin_can_create_a_payment_in_their_own_society(): void
    {
        $society = $this->makeSociety('Society A');
        $flat    = $this->flatIn($society, 'A-101');
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $this->actingAs($admin)
            ->post('/payments', $this->validPayload(['flat_id' => $flat->id]))
            ->assertRedirect(route('payments.index'));

        $this->assertDatabaseHas('payments', [
            'flat_id'    => $flat->id,
            'society_id' => $society->id,
        ]);
    }

    // ------------------------------------------------------------ update

    public function test_admin_cannot_repoint_a_payment_at_another_societys_flat(): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');

        $flatA = $this->flatIn($societyA, 'A-101');
        $flatB = $this->flatIn($societyB, 'B-101');

        $adminA = $this->makeUserWithRole($societyA, 'Admin');

        $payment = Payment::create([
            'society_id'     => $societyA->id,
            'flat_id'        => $flatA->id,
            'amount'         => 1000,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
            'bill_type'      => 'Maintenance',
            'due_date'       => now()->addDays(7)->toDateString(),
            'status'         => 'success',
        ]);

        $this->actingAs($adminA)
            ->put("/payments/{$payment->id}", $this->validPayload(['flat_id' => $flatB->id]))
            ->assertForbidden();

        $this->assertSame($flatA->id, $payment->fresh()->flat_id);
    }

    // ------------------------------- 'mixed' property_type dead end

    /**
     * An owner with one villa and one apartment sees a single combined unit
     * list and no property_type field. The request therefore carries no
     * property_type at all, and the chosen flat decides the real type.
     */
    public function test_owner_of_both_property_kinds_can_record_a_payment_without_a_property_type(): void
    {
        $society  = $this->makeSociety('Mixed Society');
        $building = $this->makeBuilding($society);

        $apartment = $this->makeFlat($society, $building, 'M-101');

        $owner = $this->makeResidentUser($society, $apartment);
        $this->makeRole('Apartment Owner');
        $owner->assignRole('Apartment Owner');

        $payload = $this->validPayload(['flat_id' => $apartment->id]);
        unset($payload['property_type']);

        $this->actingAs($owner)
            ->post('/payments', $payload)
            ->assertRedirect(route('payments.index'));

        $this->assertDatabaseHas('payments', [
            'flat_id'    => $apartment->id,
            'society_id' => $society->id,
        ]);
    }

    /** The literal label must never be accepted as a type. */
    public function test_the_mixed_label_is_rejected_as_a_property_type(): void
    {
        $society = $this->makeSociety('Mixed Society');
        $flat    = $this->flatIn($society, 'M-102');
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $this->actingAs($admin)
            ->post('/payments', $this->validPayload([
                'flat_id'       => $flat->id,
                'property_type' => 'mixed',
            ]))
            ->assertSessionHasErrors('property_type');
    }
}
