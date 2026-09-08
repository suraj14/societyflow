<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\Society;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Notice listings must not cross society boundaries.
 *
 * NoticeController::index/pending/history each scoped by society with:
 *
 *     if ($user->hasRole('Admin')) { $query->where('society_id', ...); }
 *
 * Super Admin is deliberately global, but every OTHER role - Tenant,
 * Apartment Owner, Villa Owner, Staff, Guard, Accountant - fell through
 * that branch with no filter at all and saw every notice in the database,
 * including other societies' drafts and rejected submissions.
 *
 * Found by browser sweep: /notices-history returned 200 for a Tenant and
 * listed unscoped rows.
 */
class NoticeIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Society $societyA;
    private Society $societyB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->societyA = $this->makeSociety('Society A');
        $this->societyB = $this->makeSociety('Society B');
    }

    private function makeNotice(Society $society, User $author, string $title, string $status = 'published'): Notice
    {
        return Notice::create([
            'society_id'   => $society->id,
            'created_by'   => $author->id,
            'title'        => $title,
            'content'      => 'Body of ' . $title,
            'type'         => 'general',
            'priority'     => 'medium',
            'status'       => $status,
            'publish_date' => now()->subDay(),
        ]);
    }

    /** @return array{0: User, 1: User} resident in A, author in B */
    private function seedTwoSocieties(string $role, string $status = 'published'): array
    {
        $flatA = $this->makeFlat($this->societyA, $this->makeBuilding($this->societyA), '101');
        $flatB = $this->makeFlat($this->societyB, $this->makeBuilding($this->societyB), '201');

        $residentA = $this->makeResidentUser($this->societyA, $flatA);
        $residentA->assignRole($this->makeRole($role)->name);

        $authorB = $this->makeResidentUser($this->societyB, $flatB);

        $this->makeNotice($this->societyA, $residentA, 'Notice for A', $status);
        $this->makeNotice($this->societyB, $authorB, 'SECRET Notice for B', $status);

        return [$residentA->fresh(), $authorB];
    }

    public static function nonAdminRoles(): array
    {
        return [
            'Tenant'          => ['Tenant'],
            'Apartment Owner' => ['Apartment Owner'],
            'Villa Owner'     => ['Villa Owner'],
            'Staff'           => ['Staff'],
            'Guard'           => ['Guard'],
            'Accountant'      => ['Accountant'],
        ];
    }

    /**
     * @dataProvider nonAdminRoles
     */
    public function test_notice_index_does_not_leak_other_societies(string $role): void
    {
        [$resident] = $this->seedTwoSocieties($role);

        $response = $this->actingAs($resident)->get('/notices');
        $response->assertOk();
        $response->assertDontSee('SECRET Notice for B', false);
    }

    /**
     * @dataProvider nonAdminRoles
     */
    public function test_notice_history_does_not_leak_other_societies(string $role): void
    {
        [$resident] = $this->seedTwoSocieties($role);

        $response = $this->actingAs($resident)->get('/notices-history');
        $response->assertOk();
        $response->assertDontSee('SECRET Notice for B', false);
    }

    /**
     * @dataProvider nonAdminRoles
     */
    public function test_pending_notices_do_not_leak_other_societies(string $role): void
    {
        [$resident] = $this->seedTwoSocieties($role, 'pending');

        $response = $this->actingAs($resident)->get('/notices-pending');
        $response->assertOk();
        $response->assertDontSee('SECRET Notice for B', false);
    }

    public function test_admin_still_sees_only_their_own_society(): void
    {
        $this->seedTwoSocieties('Tenant');
        $adminA = $this->makeUserWithRole($this->societyA, 'Admin');

        $response = $this->actingAs($adminA)->get('/notices-history');
        $response->assertOk();
        $response->assertSee('Notice for A', false);
        $response->assertDontSee('SECRET Notice for B', false);
    }

    /** Super Admin is cross-society by design and must keep that. */
    public function test_super_admin_still_sees_every_society(): void
    {
        $this->seedTwoSocieties('Tenant');
        $superAdmin = $this->makeUserWithRole($this->societyA, 'Super Admin', ['society_id' => null]);

        $response = $this->actingAs($superAdmin)->get('/notices-history');
        $response->assertOk();
        $response->assertSee('Notice for A', false);
        $response->assertSee('SECRET Notice for B', false);
    }

    /** The history screen itself must render - its view was missing entirely. */
    public function test_notice_history_screen_renders_with_date_filters(): void
    {
        [$resident] = $this->seedTwoSocieties('Tenant');

        $this->actingAs($resident)
            ->get('/notices-history?date_from=' . now()->subMonth()->toDateString() . '&date_to=' . now()->toDateString())
            ->assertOk()
            ->assertSee('Notice History', false);
    }
}
