<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\Society;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NoticeController::index() was fixed in an earlier session, but show() kept
 * the `$user->hasRole('Admin') && ...` form. Unlike edit/update/destroy/
 * approve/reject - which all call authorize('manage_notices') first and so
 * only ever admit Admin and Super Admin - show() has no such gate, so every
 * authenticated role reached it and the && short-circuited the society check
 * away for all of them.
 */
class NoticeShowIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function noticeIn(Society $society, string $title): Notice
    {
        return Notice::create([
            'society_id'   => $society->id,
            'title'        => $title,
            'content'      => 'Body of ' . $title,
            'type'         => 'general',
            'priority'     => 'medium',
            'publish_date' => now()->toDateString(),
            'status'       => 'published',
            'created_by'   => $this->makeUserWithRole($society, 'Admin')->id,
        ]);
    }

    /** @dataProvider nonAdminRoles */
    public function test_role_cannot_view_another_societys_notice(string $role): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');

        $noticeB = $this->noticeIn($societyB, 'Society B Private Notice');

        $user = $this->makeUserWithRole($societyA, $role);

        $this->actingAs($user)
            ->get("/notices/{$noticeB->id}")
            ->assertForbidden();
    }

    public static function nonAdminRoles(): array
    {
        return [
            'Tenant'          => ['Tenant'],
            'Apartment Owner' => ['Apartment Owner'],
            'Villa Owner'     => ['Villa Owner'],
            'Guard'           => ['Guard'],
            'Accountant'      => ['Accountant'],
        ];
    }

    public function test_admin_cannot_view_another_societys_notice(): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');

        $noticeB = $this->noticeIn($societyB, 'Society B Private Notice');
        $adminA  = $this->makeUserWithRole($societyA, 'Admin');

        $this->actingAs($adminA)
            ->get("/notices/{$noticeB->id}")
            ->assertForbidden();
    }

    /** The legitimate case must keep working. */
    public function test_resident_can_view_their_own_societys_notice(): void
    {
        $society = $this->makeSociety('Society A');
        $notice  = $this->noticeIn($society, 'Welcome Notice');

        $tenant = $this->makeUserWithRole($society, 'Tenant');

        $this->actingAs($tenant)
            ->get("/notices/{$notice->id}")
            ->assertOk()
            ->assertSee('Welcome Notice');
    }
}
