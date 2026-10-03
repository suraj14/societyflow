<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Payment;
use App\Models\Society;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PaymentController::index() scopes with an if/elseif chain naming Admin,
 * Tenant, Villa Owner and Apartment Owner, then falls through to a comment
 * reading "Super Admin sees all". Guard, Accountant and Staff are in neither
 * group, so they fell out of the chain completely unscoped - the exact
 * failure mode CLAUDE.md §3 describes ("every role you did not name falls
 * through unscoped").
 *
 * RestrictStaffAccess blocks /payments, but only for Staff: its first guard
 * is `if (!$user->hasRole('Staff')) return $next($request)`. Guard and
 * Accountant walk straight through it.
 */
class PaymentListingScopeTest extends TestCase
{
    use RefreshDatabase;

    private function paymentIn(Society $society, string $flatNumber, float $amount): Payment
    {
        $flat = $this->makeFlat($society, $this->makeBuilding($society), $flatNumber);

        return Payment::create([
            'society_id'     => $society->id,
            'flat_id'        => $flat->id,
            'amount'         => $amount,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
            'bill_type'      => 'Maintenance',
            'due_date'       => now()->addDays(7)->toDateString(),
            'status'         => 'success',
        ]);
    }

    /**
     * @return array{0: Society, 1: Society}
     */
    private function twoSocietiesWithPayments(): array
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');

        $this->paymentIn($societyA, 'A-101', 1111.00);
        $this->paymentIn($societyB, 'B-101', 9999.00);

        return [$societyA, $societyB];
    }

    /** @dataProvider unscopedRoles */
    public function test_role_does_not_see_another_societys_payments(string $role): void
    {
        [$societyA] = $this->twoSocietiesWithPayments();

        $user = $this->makeUserWithRole($societyA, $role);

        $response = $this->actingAs($user)->get('/payments');

        // Either the role may not see the page at all, or it sees only its
        // own society. What it must never do is render society B's figure.
        if ($response->status() === 403) {
            $this->assertTrue(true);

            return;
        }

        $response->assertOk();

        // The view renders number_format($amount, 2), so the raw "9999" never
        // appears even when the row is leaking. Assert the rendered string.
        $response->assertDontSee('9,999.00');
        $response->assertSee('1,111.00');
    }

    public static function unscopedRoles(): array
    {
        return [
            'Guard'      => ['Guard'],
            'Accountant' => ['Accountant'],
            'Staff'      => ['Staff'],
        ];
    }

    public function test_admin_still_sees_only_their_own_societys_payments(): void
    {
        [$societyA] = $this->twoSocietiesWithPayments();

        $admin = $this->makeUserWithRole($societyA, 'Admin');

        $this->actingAs($admin)
            ->get('/payments')
            ->assertOk()
            ->assertDontSee('9,999.00');
    }
}
