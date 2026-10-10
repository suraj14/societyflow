<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Confirmed live against the running dev server: a profile-image upload
 * whose client filename contains a colon - an ordinary name for a phone
 * photo or a WhatsApp/share-sheet export, e.g.
 * "IMG_2026-10-03 17:30:00.jpg" - crashed Admin > Users > Create/Edit with
 * an uncaught Symfony FileException (colons are illegal in Windows
 * filenames; move() threw, the one retry in the old code called the exact
 * same move() again and also threw, uncaught). The fix
 * (HandlesFormSubmissions::handleFileUpload) never uses the client's
 * filename for anything beyond its already-whitelisted extension.
 *
 * The same copy-pasted pattern existed in five other controllers
 * (Facility, Notice, Payment, Settings, Visitor); one representative case
 * from a second controller is pinned here to prove the fix generalised
 * rather than being a one-file patch.
 */
class FileUploadSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function dangerousFilenames(): array
    {
        return [
            'colon (Windows-illegal, real phone/export filenames)' => 'IMG_2026-10-03 17:30:00.jpg',
            'question mark' => 'photo?.jpg',
            'pipe' => 'photo|1.jpg',
            'asterisk' => 'photo*.jpg',
            'path traversal' => '../../etc/passwd.jpg',
            'backslash' => 'a\\b\\photo.jpg',
        ];
    }

    // ------------------------------------------------------------------
    // The confirmed bug: Admin > Users > Create/Edit avatar upload
    // ------------------------------------------------------------------

    public function test_user_avatar_upload_survives_every_windows_illegal_filename_on_create(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $this->makeRole('Staff');

        foreach ($this->dangerousFilenames() as $case => $name) {
            $response = $this->actingAs($admin)->post('/users', [
                'name' => 'Upload Test ' . uniqid(),
                'email' => 'upload' . uniqid() . '@test.local',
                'phone' => '9' . random_int(100000000, 999999999),
                'role' => 'Staff',
                'avatar' => UploadedFile::fake()->create($name, 50, 'image/jpeg'),
            ]);

            $response->assertSessionDoesntHaveErrors();
            $response->assertRedirect(route('users.index'));
        }
    }

    public function test_user_avatar_upload_survives_a_dangerous_filename_on_update_and_removes_the_old_file(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $target  = $this->makeUserWithRole($society, 'Staff');

        $this->actingAs($admin)->put("/users/{$target->id}", [
            'name' => 'Renamed', 'phone' => '9888888888', 'role' => 'Staff', 'status' => 'active',
            'avatar' => UploadedFile::fake()->create('first.jpg', 20, 'image/jpeg'),
        ])->assertSessionDoesntHaveErrors();

        $firstAvatar = $target->fresh()->avatar;
        $this->assertNotNull($firstAvatar);
        $this->assertFileExists(public_path('storage/' . $firstAvatar));

        $response = $this->actingAs($admin)->put("/users/{$target->id}", [
            'name' => 'Renamed', 'phone' => '9888888888', 'role' => 'Staff', 'status' => 'active',
            'avatar' => UploadedFile::fake()->create('IMG_2026-10-03 17:30:00.jpg', 20, 'image/jpeg'),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $target->refresh();
        $this->assertNotNull($target->avatar);
        $this->assertNotSame($firstAvatar, $target->avatar);
        $this->assertFileExists(public_path('storage/' . $target->avatar));
        $this->assertFileDoesNotExist(public_path('storage/' . $firstAvatar));

        @unlink(public_path('storage/' . $target->avatar));
    }

    public function test_generated_avatar_filename_never_contains_any_byte_the_client_supplied(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $this->makeRole('Staff');

        $this->actingAs($admin)->post('/users', [
            'name' => 'Filename Check', 'email' => 'fn' . uniqid() . '@test.local',
            'phone' => '9' . random_int(100000000, 999999999), 'role' => 'Staff',
            'avatar' => UploadedFile::fake()->create('IMG_2026-10-03 17:30:00.jpg', 20, 'image/jpeg'),
        ])->assertSessionDoesntHaveErrors();

        $user = User::where('name', 'Filename Check')->firstOrFail();
        $this->assertNotNull($user->avatar);
        $this->assertStringNotContainsString(':', $user->avatar);
        $this->assertStringNotContainsString(' ', $user->avatar);
        $this->assertMatchesRegularExpression('#^avatars/\d+_[0-9a-f]{16}\.jpg$#', $user->avatar);

        @unlink(public_path('storage/' . $user->avatar));
    }

    public function test_deleting_a_user_removes_their_avatar_file(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $target  = $this->makeUserWithRole($society, 'Staff');

        $this->actingAs($admin)->put("/users/{$target->id}", [
            'name' => 'To Delete', 'phone' => '9888888887', 'role' => 'Staff', 'status' => 'active',
            'avatar' => UploadedFile::fake()->create('photo.jpg', 20, 'image/jpeg'),
        ])->assertSessionDoesntHaveErrors();

        $path = public_path('storage/' . $target->fresh()->avatar);
        $this->assertFileExists($path);

        $this->actingAs($admin)->delete("/users/{$target->id}")->assertRedirect(route('users.index'));

        $this->assertFileDoesNotExist($path);
    }

    // ------------------------------------------------------------------
    // The second, missing-route half of the confirmed bug
    // ------------------------------------------------------------------

    public function test_the_users_show_route_no_longer_500s_and_redirects_to_edit(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $target  = $this->makeUserWithRole($society, 'Staff');

        $this->actingAs($admin)->get("/users/{$target->id}")
            ->assertRedirect(route('users.edit', $target));
    }

    public function test_the_users_show_route_still_enforces_society_isolation(): void
    {
        $societyA = $this->makeSociety('A');
        $societyB = $this->makeSociety('B');
        $adminA   = $this->makeUserWithRole($societyA, 'Admin');
        $userB    = $this->makeUserWithRole($societyB, 'Staff');

        $this->actingAs($adminA)->get("/users/{$userB->id}")->assertNotFound();
    }

    // ------------------------------------------------------------------
    // The same copy-pasted bug class, confirmed fixed in a second controller
    // ------------------------------------------------------------------

    public function test_facility_image_upload_survives_a_dangerous_filename_and_replaces_the_old_one(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $this->actingAs($admin)->post('/facilities', [
            'name' => 'Club House', 'type' => 'clubhouse', 'status' => 'active',
            'image' => UploadedFile::fake()->create('first.jpg', 20, 'image/jpeg'),
        ])->assertRedirect(route('facilities.index'));

        $facility = Facility::where('name', 'Club House')->firstOrFail();
        $firstImage = $facility->image;
        $this->assertFileExists(public_path('storage/' . $firstImage));

        $this->actingAs($admin)->put("/facilities/{$facility->id}", [
            'name' => 'Club House', 'type' => 'clubhouse', 'status' => 'active',
            'image' => UploadedFile::fake()->create('IMG_2026-10-03 17:30:00.jpg', 20, 'image/jpeg'),
        ])->assertRedirect();

        $facility->refresh();
        $this->assertNotSame($firstImage, $facility->image);
        $this->assertFileExists(public_path('storage/' . $facility->image));
        $this->assertFileDoesNotExist(public_path('storage/' . $firstImage));

        @unlink(public_path('storage/' . $facility->image));
    }

    // ------------------------------------------------------------------
    // Invalid uploads must still be rejected cleanly, not crash
    // ------------------------------------------------------------------

    public function test_an_oversized_avatar_is_a_validation_error_not_a_crash(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $this->makeRole('Staff');

        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'Too Big', 'email' => 'big' . uniqid() . '@test.local',
            'phone' => '9' . random_int(100000000, 999999999), 'role' => 'Staff',
            'avatar' => UploadedFile::fake()->create('big.jpg', 3000, 'image/jpeg'),
        ]);

        $response->assertSessionHasErrors('avatar');
        $this->assertDatabaseMissing('users', ['name' => 'Too Big']);
    }
}
