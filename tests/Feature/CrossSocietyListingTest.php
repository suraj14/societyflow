<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Event;
use App\Models\Facility;
use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\Payment;
use App\Models\Society;
use App\Models\User;
use App\Models\UtilityBill;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * No listing screen may show a user another society's records.
 *
 * The recurring defect is a role branch that scopes SOME roles and lets the
 * rest fall through unscoped, e.g.
 *
 *     if ($user->hasRole('Admin'))         { scope by society }
 *     elseif ($user->hasRole('Tenant'))    { scope by flat }
 *     elseif ($user->hasRole('Villa Owner')) { ... }
 *     // Super Admin sees all
 *
 * Roles nobody thought about - Guard, Accountant - reach the end of the
 * chain with no filter and see everything in the database. The correct
 * shape is to scope by default and exempt Super Admin explicitly.
 *
 * Each record in society B is seeded with a distinctive marker; the test
 * asserts a society-A user never sees it.
 */
class CrossSocietyListingTest extends TestCase
{
    use RefreshDatabase;

    private const MARKER = 'ZZSECRETBEE';

    private Society $societyA;
    private Society $societyB;
    private Flat $flatA;
    private Flat $flatB;
    private User $residentB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->societyA = $this->makeSociety('Society A');
        $this->societyB = $this->makeSociety('Society B');

        $this->flatA = $this->makeFlat($this->societyA, $this->makeBuilding($this->societyA), '101');
        $this->flatB = $this->makeFlat($this->societyB, $this->makeBuilding($this->societyB), '201');

        $this->residentB = $this->makeResidentUser($this->societyB, $this->flatB);

        $this->seedSocietyBRecords();
    }

    private function seedSocietyBRecords(): void
    {
        $categoryB = ComplaintCategory::create([
            'society_id' => $this->societyB->id, 'name' => 'Plumbing', 'status' => 'active',
        ]);

        Complaint::create([
            'society_id' => $this->societyB->id, 'complaint_category_id' => $categoryB->id,
            'flat_id' => $this->flatB->id, 'created_by' => $this->residentB->id,
            'title' => self::MARKER . ' complaint', 'description' => 'x',
            'status' => 'open', 'priority' => 'medium',
        ]);

        Visitor::create([
            'society_id' => $this->societyB->id, 'flat_id' => $this->flatB->id,
            'host_user_id' => $this->residentB->id, 'visitor_name' => self::MARKER . ' visitor',
            'visitor_phone' => '9876500123', 'visitor_type' => 'guest',
            'expected_entry_time' => now(), 'approval_status' => 'pending', 'entry_status' => 'pending',
        ]);

        Payment::create([
            'society_id' => $this->societyB->id, 'user_id' => $this->residentB->id,
            'flat_id' => $this->flatB->id, 'payment_id' => self::MARKER . '-PAY',
            'amount' => 4242, 'status' => 'success', 'payment_date' => now()->toDateString(),
            'bill_type' => 'Maintenance', 'due_date' => now()->toDateString(),
        ]);

        UtilityBill::create([
            'society_id' => $this->societyB->id, 'flat_id' => $this->flatB->id,
            'bill_type' => self::MARKER . 'Electric', 'bill_amount' => 999,
            'bill_date' => now()->toDateString(), 'due_date' => now()->addDays(10)->toDateString(),
            'status' => 'unpaid',
        ]);

        MaintenanceBill::create([
            'society_id' => $this->societyB->id, 'flat_id' => $this->flatB->id,
            'month' => 'January', 'year' => 2026,
            'bill_date' => now()->toDateString(), 'due_date' => now()->addDays(10)->toDateString(),
            'total_amount' => 3333, 'balance_amount' => 3333, 'status' => 'pending',
            'notes' => self::MARKER,
        ]);

        Facility::create([
            'society_id' => $this->societyB->id, 'name' => self::MARKER . ' Gym',
            'type' => 'gym', 'status' => 'active',
        ]);

        Event::create([
            'society_id' => $this->societyB->id, 'event_name' => self::MARKER . ' Party',
            'location' => 'Hall B', 'description' => 'x',
            'start_date' => now()->addWeek(), 'end_date' => now()->addWeek()->addHour(),
            'status' => 'pending', 'is_role_based' => false, 'visible_users' => [$this->residentB->id],
        ]);
    }

    /** A society-A user holding $role. */
    private function userInA(string $role): User
    {
        $user = $this->makeResidentUser($this->societyA, $this->flatA);
        $user->assignRole($this->makeRole($role)->name);

        return $user->fresh();
    }

    public static function roleAndPath(): array
    {
        $paths = [
            'complaints'        => '/complaints',
            'visitors'          => '/visitors',
            'payments'          => '/payments',
            'utility bills'     => '/utility-bills',
            'facilities'        => '/facilities',
            'events'            => '/events',
            'notices'           => '/notices',
        ];
        $roles = ['Tenant', 'Apartment Owner', 'Villa Owner', 'Guard', 'Accountant', 'Staff'];

        $cases = [];
        foreach ($roles as $role) {
            foreach ($paths as $label => $path) {
                $cases["{$role} @ {$label}"] = [$role, $path];
            }
        }

        return $cases;
    }

    /**
     * @dataProvider roleAndPath
     */
    public function test_listing_never_shows_another_societys_records(string $role, string $path): void
    {
        $user = $this->userInA($role);

        $response = $this->actingAs($user)->get($path);

        // 403 is a perfectly good answer - the role may not have the module
        // at all. What must never happen is a 200 containing society B.
        if ($response->getStatusCode() !== 200) {
            $this->assertLessThan(500, $response->getStatusCode(), "{$path} errored for {$role}");

            return;
        }

        $response->assertDontSee(self::MARKER, false);
    }

    /** An Admin of society A must not see society B either. */
    public function test_admin_listings_are_scoped_to_their_own_society(): void
    {
        $adminA = $this->makeUserWithRole($this->societyA, 'Admin');

        foreach (['/complaints', '/visitors', '/payments', '/utility-bills', '/facilities', '/events', '/notices'] as $path) {
            $response = $this->actingAs($adminA)->get($path);

            if ($response->getStatusCode() !== 200) {
                continue;
            }

            $response->assertDontSee(self::MARKER, false);
        }
    }
}
