<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Owner;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ProfileImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Profile pictures: who can change whose, on web and mobile.
 *
 * Before App\Support\ProfileImage there was no way for a person to change
 * their own picture at all (the web profile form and PUT /api/v1/profile had
 * no image field), an owner's picture never reached the owner's own login
 * account, the web header always drew a generated badge instead of the
 * stored picture, and replaced tenant/owner pictures were left on disk.
 *
 * Files are written to a scratch directory (ProfileImage::useRoot), never to
 * the real storage folder. The image fixtures are real PNG bytes built
 * without GD (this PHP has no gd extension), so `mimes:` is judged on
 * content exactly as it is in production.
 */
class ProfileImageTest extends TestCase
{
    use RefreshDatabase;

    private string $root;
    private string $scratch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root    = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'si_profile_' . uniqid();
        $this->scratch = $this->root . '_uploads';
        mkdir($this->root, 0777, true);
        mkdir($this->scratch, 0777, true);
        ProfileImage::useRoot($this->root);
    }

    protected function tearDown(): void
    {
        ProfileImage::useRoot(null);
        $this->removeTree($this->root);
        $this->removeTree($this->scratch);

        parent::tearDown();
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            is_dir($path) ? $this->removeTree($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    // ---------------------------------------------------------------- fixtures

    private function pngBytes(int $shade = 120): string
    {
        $width = $height = 40;
        $raw = '';
        for ($y = 0; $y < $height; $y++) {
            $raw .= "\0" . str_repeat(chr($shade), $width);
        }
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));

        return "\x89PNG\r\n\x1a\n"
            . $chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 0, 0, 0, 0))
            . $chunk('IDAT', gzcompress($raw))
            . $chunk('IEND', '');
    }

    private function image(string $name = 'photo.png', int $shade = 120): UploadedFile
    {
        $path = $this->scratch . DIRECTORY_SEPARATOR . uniqid('up_') . '.png';
        file_put_contents($path, $this->pngBytes($shade));

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    private function onDisk(?string $relative): string
    {
        return $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $relative);
    }

    /** @return string[] file names currently stored under avatars/ */
    private function stored(): array
    {
        $dir = $this->root . DIRECTORY_SEPARATOR . 'avatars';

        return is_dir($dir) ? array_values(array_diff(scandir($dir), ['.', '..'])) : [];
    }

    private function userFor(string $role, $society = null, array $over = []): User
    {
        $society ??= $this->makeSociety('Photo Society ' . uniqid());

        return $this->makeUserWithRole(
            $society,
            $role,
            $role === 'Super Admin' ? array_merge(['society_id' => null], $over) : $over
        );
    }

    /** @return array<string, array{string}> */
    public static function roles(): array
    {
        return array_combine(
            ['Super Admin', 'Admin', 'Owner', 'Villa Owner', 'Apartment Owner', 'Tenant', 'Guard', 'Staff', 'Accountant'],
            array_map(fn ($r) => [$r], ['Super Admin', 'Admin', 'Owner', 'Villa Owner', 'Apartment Owner', 'Tenant', 'Guard', 'Staff', 'Accountant'])
        );
    }

    // ============================================== web: own profile, per role

    /** @dataProvider roles */
    public function test_every_role_can_upload_replace_keep_and_remove_their_own_photo_on_the_web(string $role): void
    {
        $user = $this->userFor($role);
        $base = ['name' => $user->name, 'email' => $user->email, 'phone' => '9000000000'];

        // The edit form offers the field, as multipart.
        $this->actingAs($user)->get('/profile/edit')->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="avatar"', false);

        // 1. first upload
        $this->actingAs($user)->put('/profile', $base + ['avatar' => $this->image('first.png', 90)])
            ->assertSessionDoesntHaveErrors()->assertRedirect(route('profile.show'));
        $first = $user->fresh()->avatar;
        $this->assertMatchesRegularExpression('#^avatars/\d+_[0-9a-f]{16}\.png$#', (string) $first);
        $this->assertFileExists($this->onDisk($first));

        // ... and it is what the page and the header now show.
        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('storage/' . $first, false);
        $this->actingAs($user)->get('/profile/edit')->assertOk()
            ->assertSee('storage/' . $first, false)->assertSee('Remove current photo');

        // 2. a second upload REPLACES it (this is the "cannot change it" case)
        $this->actingAs($user)->put('/profile', $base + ['avatar' => $this->image('second.png', 200)])
            ->assertSessionDoesntHaveErrors();
        $second = $user->fresh()->avatar;
        $this->assertNotSame($first, $second);
        $this->assertFileExists($this->onDisk($second));
        $this->assertFileDoesNotExist($this->onDisk($first), 'the replaced picture must not be left behind');

        // 3. saving without choosing a file keeps the current one
        $this->actingAs($user)->put('/profile', array_merge($base, ['phone' => '9111111111']))->assertSessionDoesntHaveErrors();
        $this->assertSame($second, $user->fresh()->avatar);
        $this->assertSame('9111111111', $user->fresh()->phone);
        $this->assertFileExists($this->onDisk($second));

        // 4. an explicit remove clears it and its file
        $this->actingAs($user)->put('/profile', $base + ['remove_avatar' => '1'])->assertSessionDoesntHaveErrors();
        $this->assertNull($user->fresh()->avatar);
        $this->assertSame([], $this->stored());
    }

    public function test_a_new_file_wins_over_remove_when_both_are_sent(): void
    {
        $user = $this->userFor('Tenant');
        $base = ['name' => $user->name, 'email' => $user->email];

        $this->actingAs($user)->put('/profile', $base + ['avatar' => $this->image(), 'remove_avatar' => '1'])
            ->assertSessionDoesntHaveErrors();

        $this->assertNotNull($user->fresh()->avatar);
        $this->assertCount(1, $this->stored());
    }

    // ============================================ API: own profile, per role

    /** @dataProvider roles */
    public function test_every_role_can_upload_replace_keep_and_remove_their_own_photo_through_the_api(string $role): void
    {
        $user = $this->userFor($role);
        Sanctum::actingAs($user);
        $json = ['Accept' => 'application/json'];

        $first = $this->post('/api/v1/profile', ['name' => 'Renamed', 'avatar' => $this->image('a.png', 70)], $json)
            ->assertOk()->assertJsonPath('success', true);
        $path1 = $user->fresh()->avatar;
        $this->assertFileExists($this->onDisk($path1));

        // The URL contract: root-relative, resolved by the app against its base URL.
        $first->assertJsonPath('data.avatar', '/storage/' . $path1);
        $this->getJson('/api/v1/profile')->assertOk()->assertJsonPath('data.avatar', '/storage/' . $path1);
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.avatar', '/storage/' . $path1);

        // Replace.
        $this->post('/api/v1/profile', ['name' => 'Renamed', 'avatar' => $this->image('b.png', 210)], $json)->assertOk();
        $path2 = $user->fresh()->avatar;
        $this->assertNotSame($path1, $path2);
        $this->assertFileDoesNotExist($this->onDisk($path1));

        // The pre-existing JSON PUT (no image) still works and keeps the picture.
        $this->putJson('/api/v1/profile', ['name' => 'Renamed Again', 'phone' => '9222222222'])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('message', 'Profile updated successfully');
        $this->assertSame($path2, $user->fresh()->avatar);
        $this->assertSame('Renamed Again', $user->fresh()->name);

        // Remove.
        $this->post('/api/v1/profile', ['name' => 'Renamed Again', 'remove_avatar' => '1'], $json)->assertOk();
        $this->assertNull($user->fresh()->avatar);
        $this->assertSame([], $this->stored());
    }

    // ============================================================ validation

    public function test_invalid_oversized_disguised_and_failed_uploads_are_rejected_and_change_nothing(): void
    {
        $user = $this->userFor('Tenant');
        $this->actingAs($user)->put('/profile', ['name' => $user->name, 'email' => $user->email, 'avatar' => $this->image('keep.png')]);
        $kept = $user->fresh()->avatar;
        $this->assertNotNull($kept);

        $textAsPng = $this->scratch . DIRECTORY_SEPARATOR . 'evil.png';
        file_put_contents($textAsPng, "<?php echo 'not an image';");

        $partial = $this->scratch . DIRECTORY_SEPARATOR . 'partial.png';
        file_put_contents($partial, $this->pngBytes());

        $bad = [
            'a PDF'                            => UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf'),
            'a script renamed .png (content)'  => new UploadedFile($textAsPng, 'evil.png', 'image/png', null, true),
            'over 2 MB'                        => UploadedFile::fake()->create('big.png', 3000, 'image/png'),
            'an interrupted upload'            => new UploadedFile($partial, 'partial.png', 'image/png', UPLOAD_ERR_PARTIAL, true),
        ];

        foreach ($bad as $case => $file) {
            $this->actingAs($user)->put('/profile', ['name' => $user->name, 'email' => $user->email, 'avatar' => $file])
                ->assertSessionHasErrors('avatar');

            Sanctum::actingAs($user);
            $this->post('/api/v1/profile', ['name' => $user->name, 'avatar' => $file], ['Accept' => 'application/json'])
                ->assertStatus(422);

            $this->assertSame($kept, $user->fresh()->avatar, "profile changed after $case");
            $this->assertSame([basename($kept)], $this->stored(), "stray file after $case");
        }
    }

    public function test_a_rejected_upload_does_not_save_the_other_fields_either(): void
    {
        $user = $this->userFor('Staff');

        $this->actingAs($user)->put('/profile', [
            'name' => 'Should Not Stick', 'email' => $user->email,
            'avatar' => UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf'),
        ])->assertSessionHasErrors('avatar');

        $this->assertNotSame('Should Not Stick', $user->fresh()->name);
    }

    // ===================================================== admin edits a person

    public function test_admin_can_set_replace_and_remove_the_photo_of_staff_guard_accountant_tenant_and_owner_accounts(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->userFor('Admin', $society);

        foreach (['Staff', 'Guard', 'Accountant', 'Tenant', 'Owner'] as $role) {
            $target = $this->userFor($role, $society);
            $form   = ['name' => $target->name, 'phone' => '9333333333', 'role' => $role, 'status' => 'active'];

            $this->actingAs($admin)->put("/users/{$target->id}", $form + ['avatar' => $this->image("$role-1.png", 80)])
                ->assertSessionDoesntHaveErrors();
            $one = $target->fresh()->avatar;
            $this->assertFileExists($this->onDisk($one), "$role first upload");

            $this->actingAs($admin)->put("/users/{$target->id}", $form + ['avatar' => $this->image("$role-2.png", 160)])
                ->assertSessionDoesntHaveErrors();
            $two = $target->fresh()->avatar;
            $this->assertNotSame($one, $two, "$role replace");
            $this->assertFileDoesNotExist($this->onDisk($one), "$role old file");

            $this->actingAs($admin)->put("/users/{$target->id}", $form)->assertSessionDoesntHaveErrors();
            $this->assertSame($two, $target->fresh()->avatar, "$role keep");

            $this->actingAs($admin)->put("/users/{$target->id}", $form + ['remove_avatar' => '1'])->assertSessionDoesntHaveErrors();
            $this->assertNull($target->fresh()->avatar, "$role remove");
        }

        $this->assertSame([], $this->stored());
    }

    public function test_admin_edit_form_offers_the_current_photo_and_a_way_to_change_or_remove_it(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->userFor('Admin', $society);
        $target  = $this->userFor('Staff', $society);
        $this->actingAs($admin)->put("/users/{$target->id}", [
            'name' => $target->name, 'phone' => '9333333333', 'role' => 'Staff', 'status' => 'active',
            'avatar' => $this->image(),
        ]);

        $this->actingAs($admin)->get("/users/{$target->id}/edit")->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="avatar"', false)
            ->assertSee('storage/' . $target->fresh()->avatar, false)
            ->assertSee('Remove current photo');
    }

    public function test_an_admin_cannot_change_a_photo_in_another_society_and_a_resident_cannot_change_anyone_elses(): void
    {
        $societyA = $this->makeSociety('A');
        $societyB = $this->makeSociety('B');
        $adminA   = $this->userFor('Admin', $societyA);
        $victim   = $this->userFor('Staff', $societyB);
        $form     = ['name' => $victim->name, 'phone' => '9333333333', 'role' => 'Staff', 'status' => 'active'];

        $this->actingAs($adminA)->put("/users/{$victim->id}", $form + ['avatar' => $this->image()])->assertNotFound();
        $this->assertNull($victim->fresh()->avatar);

        foreach (['Tenant', 'Owner', 'Guard', 'Staff', 'Accountant'] as $role) {
            $actor = $this->userFor($role, $societyB);
            $this->actingAs($actor)->put("/users/{$victim->id}", $form + ['avatar' => $this->image()]);
            $this->assertNull($victim->fresh()->avatar, "$role changed another person's photo");
        }

        $this->assertSame([], $this->stored());
    }

    public function test_super_admin_can_change_a_photo_in_any_society_and_nobody_can_pick_a_user_via_the_profile_form(): void
    {
        $society = $this->makeSociety();
        $super   = $this->userFor('Super Admin');
        $target  = $this->userFor('Tenant', $society);
        $bystander = $this->userFor('Tenant', $society);

        $this->actingAs($super)->put("/users/{$target->id}", [
            'name' => $target->name, 'phone' => '9333333333', 'role' => 'Tenant', 'status' => 'active', 'avatar' => $this->image(),
        ])->assertSessionDoesntHaveErrors();
        $this->assertNotNull($target->fresh()->avatar);

        // The self-service form only ever edits the signed-in account, whatever the client adds.
        $this->actingAs($bystander)->put('/profile', [
            'name' => $bystander->name, 'email' => $bystander->email, 'avatar' => $this->image(),
            'id' => $target->id, 'user_id' => $target->id,
        ]);
        $this->assertNotNull($bystander->fresh()->avatar);
        $this->assertNotSame($target->fresh()->avatar, $bystander->fresh()->avatar);
    }

    // ================================================== owner and tenant records

    public function test_an_owners_photo_reaches_the_owners_own_login_and_stays_in_step(): void
    {
        $this->makeRole('Owner');
        $society = $this->makeSociety();
        $admin   = $this->userFor('Admin', $society);

        $this->actingAs($admin)->post('/owners', [
            'name' => 'Pic Owner', 'email' => 'pic.owner@test.local', 'phone' => '9876500123',
            'property_type' => 'villa', 'status' => 'active', 'profile_image' => $this->image('o1.png', 60),
        ])->assertSessionDoesntHaveErrors();

        $owner = Owner::where('email', 'pic.owner@test.local')->firstOrFail();
        $login = User::where('owner_id', $owner->id)->firstOrFail();
        $first = $owner->profile_image;
        $this->assertSame($first, $login->avatar, 'the login must show the picture the admin uploaded');
        $this->assertFileExists($this->onDisk($first));

        // The owner's own API now serves it.
        Sanctum::actingAs($login);
        $this->getJson('/api/v1/profile')->assertJsonPath('data.avatar', '/storage/' . $first);

        $form = ['name' => 'Pic Owner', 'email' => 'pic.owner@test.local', 'phone' => '9876500123', 'property_type' => 'villa', 'status' => 'active'];

        // Replace via the admin edit form: both rows move, old file goes.
        $this->actingAs($admin)->put("/owners/{$owner->id}", $form + ['profile_image' => $this->image('o2.png', 180)])
            ->assertSessionDoesntHaveErrors();
        $second = $owner->fresh()->profile_image;
        $this->assertNotSame($first, $second);
        $this->assertSame($second, $login->fresh()->avatar);
        $this->assertFileDoesNotExist($this->onDisk($first));
        $this->assertFileExists($this->onDisk($second));

        // Keep.
        $this->actingAs($admin)->put("/owners/{$owner->id}", $form)->assertSessionDoesntHaveErrors();
        $this->assertSame($second, $owner->fresh()->profile_image);
        $this->assertSame($second, $login->fresh()->avatar);

        // The owner changes their own photo: the owner record follows.
        $this->actingAs($login)->put('/profile', ['name' => 'Pic Owner', 'email' => 'pic.owner@test.local', 'avatar' => $this->image('o3.png', 30)])
            ->assertSessionDoesntHaveErrors();
        $third = $login->fresh()->avatar;
        $this->assertSame($third, $owner->fresh()->profile_image);
        $this->assertFileDoesNotExist($this->onDisk($second));

        // Remove through the admin form: both cleared.
        $this->actingAs($admin)->put("/owners/{$owner->id}", $form + ['remove_profile_image' => '1'])->assertSessionDoesntHaveErrors();
        $this->assertNull($owner->fresh()->profile_image);
        $this->assertNull($login->fresh()->avatar);
        $this->assertSame([], $this->stored());
    }

    public function test_an_admin_cannot_change_an_owners_photo_in_another_society(): void
    {
        $this->makeRole('Owner');
        $societyA = $this->makeSociety('A');
        $societyB = $this->makeSociety('B');
        $adminB   = $this->userFor('Admin', $societyB);
        $owner    = Owner::create(['society_id' => $societyA->id, 'name' => 'Theirs', 'email' => 'theirs@test.local', 'phone' => '9876500999', 'status' => 'active', 'property_type' => 'villa']);

        $response = $this->actingAs($adminB)->put("/owners/{$owner->id}", [
            'name' => 'Theirs', 'email' => 'theirs@test.local', 'phone' => '9876500999', 'property_type' => 'villa', 'status' => 'active',
            'profile_image' => $this->image(),
        ]);

        $this->assertContains($response->getStatusCode(), [403, 404]);
        $this->assertNull($owner->fresh()->profile_image);
        $this->assertSame([], $this->stored());
    }

    public function test_a_tenants_photo_can_be_set_replaced_and_removed_by_the_admin(): void
    {
        $this->makeRole('Tenant'); // TenantController assigns it; a fresh test DB has no roles seeded
        $society = $this->makeSociety();
        $admin   = $this->userFor('Admin', $society);
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $email   = 'tenant.pic' . uniqid() . '@test.local';

        $this->actingAs($admin)->post(route('admin.tenants.store'), [
            'name' => 'Pic Tenant', 'email' => $email, 'phone' => '9000000077',
            'password' => 'Str0ngPassw0rd!', 'password_confirmation' => 'Str0ngPassw0rd!', 'status' => 'active',
            'contract_start_date' => '2026-01-01', 'contract_end_date' => '2027-01-01',
            'rent_amount' => 10000, 'rent_billing_cycle' => 'Monthly', 'move_in_date' => '2026-09-01',
            'flat_id' => $flat->id, 'property_type' => 'apartment', 'profile_image' => $this->image('t1.png', 50),
        ])->assertSessionDoesntHaveErrors();

        $user   = User::where('email', $email)->firstOrFail();
        $tenant = Tenant::where('user_id', $user->id)->firstOrFail();
        $first  = $user->avatar;
        $this->assertFileExists($this->onDisk($first));

        $form = [
            'name' => 'Pic Tenant', 'email' => $email, 'phone' => '9000000077', 'status' => 'active',
            'contract_start_date' => '2026-01-01', 'contract_end_date' => '2027-01-01',
            'rent_amount' => 10000, 'rent_billing_cycle' => 'Monthly', 'flat_id' => $flat->id, 'property_type' => 'apartment',
        ];

        $this->actingAs($admin)->put(route('admin.tenants.update', $tenant), $form + ['profile_image' => $this->image('t2.png', 220)])
            ->assertSessionDoesntHaveErrors();
        $second = $user->fresh()->avatar;
        $this->assertNotSame($first, $second);
        $this->assertFileDoesNotExist($this->onDisk($first), 'replaced tenant pictures used to be left on disk');
        $this->assertFileExists($this->onDisk($second));

        $this->actingAs($admin)->put(route('admin.tenants.update', $tenant), $form)->assertSessionDoesntHaveErrors();
        $this->assertSame($second, $user->fresh()->avatar);

        $this->actingAs($admin)->put(route('admin.tenants.update', $tenant), $form + ['remove_profile_image' => '1'])->assertSessionDoesntHaveErrors();
        $this->assertNull($user->fresh()->avatar);
        $this->assertSame([], $this->stored());
    }

    // ================================================ file safety and atomicity

    public function test_a_picture_still_used_by_someone_else_is_never_deleted(): void
    {
        $society = $this->makeSociety();
        $a = $this->userFor('Tenant', $society);
        $b = $this->userFor('Tenant', $society);

        mkdir($this->root . DIRECTORY_SEPARATOR . 'avatars', 0777, true);
        file_put_contents($this->onDisk('avatars/shared.png'), $this->pngBytes());
        $a->forceFill(['avatar' => 'avatars/shared.png'])->save();
        $b->forceFill(['avatar' => 'avatars/shared.png'])->save();

        ProfileImage::change($a, $this->image());
        $this->assertFileExists($this->onDisk('avatars/shared.png'), 'B still shows this file');

        ProfileImage::change($b, null, true);
        $this->assertFileDoesNotExist($this->onDisk('avatars/shared.png'), 'nobody references it any more');
    }

    public function test_only_plain_paths_inside_our_own_folders_are_ever_touched(): void
    {
        $outside = $this->root . '_outside.txt';
        file_put_contents($outside, 'precious');

        foreach (['../' . basename($outside), 'avatars/../../' . basename($outside), '/etc/passwd', 'C:\\Windows\\win.ini',
                  'avatars\\x.png', 'https://example.com/a.png', 'avatars/a b.png', "avatars/x.png\0.txt", '', null] as $path) {
            $this->assertFalse(ProfileImage::isManagedPath($path), var_export($path, true));
            ProfileImage::delete($path);
            ProfileImage::deleteIfUnreferenced($path);
        }
        $this->assertFileExists($outside);
        @unlink($outside);

        foreach (['avatars/1700000000_0123456789abcdef.png', 'owners/x.jpg', 'tenants/y.png'] as $ok) {
            $this->assertTrue(ProfileImage::isManagedPath($ok), $ok);
        }
    }

    public function test_a_failed_save_leaves_the_profile_and_its_old_picture_intact_and_no_orphan(): void
    {
        $user = $this->userFor('Tenant');
        ProfileImage::change($user, $this->image('old.png', 77));
        $old = $user->fresh()->avatar;
        $this->assertFileExists($this->onDisk($old));

        $explode = true;
        User::saving(function () use (&$explode) {
            if ($explode) {
                throw new \RuntimeException('simulated database failure');
            }
        });

        try {
            ProfileImage::change($user->fresh(), $this->image('new.png', 99));
            $this->fail('the simulated failure should have propagated');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated database failure', $e->getMessage());
        } finally {
            $explode = false;
        }

        $this->assertSame($old, $user->fresh()->avatar);
        $this->assertFileExists($this->onDisk($old), 'the old picture must survive a failed replace');
        $this->assertSame([basename($old)], $this->stored(), 'the new file must not be orphaned');
    }

    public function test_deleting_an_owners_login_keeps_the_picture_the_owner_record_still_uses(): void
    {
        $this->makeRole('Owner');
        $society = $this->makeSociety();
        $admin   = $this->userFor('Admin', $society);

        $this->actingAs($admin)->post('/owners', [
            'name' => 'Keep Pic', 'email' => 'keep.pic@test.local', 'phone' => '9876500321',
            'property_type' => 'villa', 'status' => 'active', 'profile_image' => $this->image(),
        ])->assertSessionDoesntHaveErrors();
        $owner = Owner::where('email', 'keep.pic@test.local')->firstOrFail();
        $login = User::where('owner_id', $owner->id)->firstOrFail();

        $this->actingAs($admin)->delete("/users/{$login->id}");

        $this->assertFileExists($this->onDisk($owner->fresh()->profile_image));
    }

    // ===================================================== data + display

    public function test_the_backfill_copies_an_owners_picture_to_a_login_that_has_none_and_overwrites_nothing(): void
    {
        $society = $this->makeSociety();
        $mk = fn (string $img = null) => Owner::create([
            'society_id' => $society->id, 'name' => 'O' . uniqid(), 'email' => uniqid() . '@test.local',
            'phone' => '9' . random_int(100000000, 999999999), 'status' => 'active', 'profile_image' => $img,
        ]);

        $needs   = $mk('owners/legacy.png');
        $hasOwn  = $mk('owners/other.png');
        $noImage = $mk(null);
        $uNeeds   = $this->userFor('Owner', $society, ['owner_id' => $needs->id]);
        $uHasOwn  = $this->userFor('Owner', $society, ['owner_id' => $hasOwn->id, 'avatar' => 'avatars/mine.png']);
        $uNoImage = $this->userFor('Owner', $society, ['owner_id' => $noImage->id]);

        $migration = require base_path('database/migrations/2026_10_10_000001_backfill_owner_login_avatars.php');
        $migration->up();
        $migration->up(); // idempotent

        $this->assertSame('owners/legacy.png', $uNeeds->fresh()->avatar);
        $this->assertSame('avatars/mine.png', $uHasOwn->fresh()->avatar, 'an existing picture is never overwritten');
        $this->assertNull($uNoImage->fresh()->avatar);
    }

    public function test_the_web_header_shows_the_stored_picture_instead_of_a_generated_badge(): void
    {
        foreach (['Tenant' => '/profile', 'Super Admin' => '/profile'] as $role => $url) {
            $user = $this->userFor($role);
            $this->actingAs($user)->put('/profile', ['name' => $user->name, 'email' => $user->email, 'avatar' => $this->image()]);
            $stored = $user->fresh()->avatar;

            $html = $this->actingAs($user)->get($url)->assertOk()->getContent();

            $this->assertGreaterThanOrEqual(2, substr_count($html, 'storage/' . $stored), "$role: header + profile card should both use the stored picture");
            $this->assertStringNotContainsString('ui-avatars.com', $html, "$role: no generated badge once a picture exists");
        }
    }

    // ============================ User Management: one identity, one picture

    public function test_the_users_list_has_no_update_drawer_and_sends_update_to_the_standard_edit_page(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->userFor('Admin', $society);
        $target  = $this->userFor('Staff', $society);

        $html = $this->actingAs($admin)->get(route('users.index'))->assertOk()->getContent();

        // The right-side drawer hid its own validation errors and never showed the photo.
        $this->assertStringNotContainsString('updateUserDrawer', $html);
        $this->assertStringNotContainsString('openUpdateUserDrawer', $html);
        $this->assertStringContainsString('href="' . route('users.edit', $target) . '"', $html);
        // Resetting a password / deleting / adding still work from the list.
        $this->assertStringContainsString('openResetPasswordModal(' . $target->id . ')', $html);
        $this->assertStringContainsString('openDeleteConfirmation(' . $target->id, $html);
    }

    public function test_an_owner_has_the_same_picture_in_owner_management_user_management_and_the_app(): void
    {
        $this->makeRole('Owner');
        $society = $this->makeSociety();
        $admin   = $this->userFor('Admin', $society);

        $this->actingAs($admin)->post('/owners', [
            'name' => 'Suraj Suman', 'email' => 'suraj@test.local', 'phone' => '9876500777',
            'property_type' => 'villa', 'status' => 'active', 'profile_image' => $this->image('suraj.png', 111),
        ])->assertSessionDoesntHaveErrors();

        $owner = Owner::where('email', 'suraj@test.local')->firstOrFail();
        $login = User::where('owner_id', $owner->id)->firstOrFail();
        $path  = $owner->profile_image;
        $this->assertNotNull($path);
        $needle = 'storage/' . $path;

        $this->actingAs($admin)->get(route('owners.edit', $owner))->assertOk()->assertSee($needle, false);
        $this->actingAs($admin)->get(route('users.index'))->assertOk()->assertSee($needle, false);
        $this->actingAs($admin)->get(route('users.edit', $login))->assertOk()->assertSee($needle, false);
        $this->assertSame($path, $login->fresh()->avatar, 'one canonical file, not a copy');

        Sanctum::actingAs($login);
        $this->getJson('/api/v1/profile')->assertJsonPath('data.avatar', '/storage/' . $path);

        // Replacing it from User Management moves the Owner record too - they cannot diverge.
        $this->actingAs($admin)->put(route('users.update', $login), [
            'name' => 'Suraj Suman', 'phone' => '9876500777', 'role' => 'Owner', 'status' => 'active', 'avatar' => $this->image('suraj2.png', 201),
        ])->assertSessionDoesntHaveErrors();

        $new = $login->fresh()->avatar;
        $this->assertNotSame($path, $new);
        $this->assertSame($new, $owner->fresh()->profile_image);
        $this->actingAs($admin)->get(route('owners.edit', $owner))->assertSee('storage/' . $new, false)->assertDontSee($needle, false);
        $this->assertFileDoesNotExist($this->onDisk($path));
    }

    public function test_a_failed_save_on_the_edit_page_shows_the_errors_in_place_and_keeps_the_input_and_the_photo(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->userFor('Admin', $society);
        $target  = $this->userFor('Staff', $society, ['phone' => '9000000001']);
        $form    = ['name' => $target->name, 'phone' => '9000000001', 'role' => 'Staff', 'status' => 'active'];

        $this->actingAs($admin)->put(route('users.update', $target), $form + ['avatar' => $this->image('keep.png')]);
        $kept = $target->fresh()->avatar;
        $this->assertNotNull($kept);

        $html = $this->actingAs($admin)->followingRedirects()->from(route('users.edit', $target))
            ->put(route('users.update', $target), [
                'name' => 'Typed But Not Saved', 'phone' => '', 'role' => 'Staff', 'status' => 'active',
                'avatar' => $this->image('replacement.png', 222),
            ])->assertOk()->getContent();

        $this->assertStringContainsString('Please fix the following errors', $html);
        $this->assertStringContainsString('The phone field is required', $html);
        $this->assertStringContainsString('value="Typed But Not Saved"', $html, 'what was typed is not lost');
        $this->assertStringContainsString('storage/' . $kept, $html, 'the existing photo is still shown');

        $target->refresh();
        $this->assertSame($kept, $target->avatar);
        $this->assertNotSame('Typed But Not Saved', $target->name);
        $this->assertSame([basename($kept)], $this->stored(), 'the rejected replacement was never stored');
    }

    public function test_without_a_picture_the_header_still_falls_back_to_the_generated_badge(): void
    {
        $user = $this->userFor('Tenant');

        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('ui-avatars.com', false);
    }
}
