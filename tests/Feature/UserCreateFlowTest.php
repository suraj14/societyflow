<?php

namespace Tests\Feature;

use App\Events\UserCreated;
use App\Models\Owner;
use App\Models\Society;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ProfileImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Add User is now the standard full-page form (users.create), not a drawer on
 * the list. Covers the whole create flow end to end over HTTP.
 */
class UserCreateFlowTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Eden');
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'si_usercreate_' . uniqid();
        mkdir($this->root, 0777, true);
        ProfileImage::useRoot($this->root);
        foreach (['Super Admin', 'Admin', 'Owner', 'Tenant', 'Guard', 'Staff', 'Accountant'] as $role) {
            $this->makeRole($role);
        }
    }

    protected function tearDown(): void
    {
        ProfileImage::useRoot(null);
        $this->rrmdir($this->root);
        parent::tearDown();
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $e) {
            if ($e !== '.' && $e !== '..') {
                is_dir("$dir/$e") ? $this->rrmdir("$dir/$e") : @unlink("$dir/$e");
            }
        }
        @rmdir($dir);
    }

    private function png(): UploadedFile
    {
        $w = $h = 8;
        $raw = '';
        for ($y = 0; $y < $h; $y++) {
            $raw .= "\0" . str_repeat(chr(90), $w);
        }
        $chunk = fn (string $t, string $d) => pack('N', strlen($d)) . $t . $d . pack('N', crc32($t . $d));
        $bytes = "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', $w, $h, 8, 0, 0, 0, 0)) . $chunk('IDAT', gzcompress($raw)) . $chunk('IEND', '');
        $path = $this->root . DIRECTORY_SEPARATOR . uniqid('up_') . '.png';
        file_put_contents($path, $bytes);

        return new UploadedFile($path, 'me.png', 'image/png', null, true);
    }

    /** Bytes that are not an image, named like one: validation must look at the content. */
    private function garbage(): UploadedFile
    {
        $path = $this->root . DIRECTORY_SEPARATOR . uniqid('bad_') . '.png';
        file_put_contents($path, 'MZ this is a program, not a picture');

        return new UploadedFile($path, 'x.png', 'image/png', null, true);
    }

    private function payload(array $over = []): array
    {
        return array_merge(['name' => 'New Person', 'email' => 'new.person@test.local', 'phone' => '9876501234', 'role' => 'Tenant'], $over);
    }

    private function admin(): User
    {
        return $this->makeUserWithRole($this->society, 'Admin');
    }

    // --------------------------------------------------- the drawer is gone

    public function test_the_list_links_to_the_create_page_and_no_longer_embeds_a_drawer(): void
    {
        $html = $this->actingAs($this->admin())->get(route('users.index'))->assertOk()->getContent();

        $this->assertStringContainsString(route('users.create'), $html);
        $this->assertStringNotContainsString('addUserDrawer', $html);
        $this->assertStringNotContainsString('openAddUserDrawer', $html);

        $this->actingAs($this->admin())->get(route('users.create'))->assertOk()->assertSee('Create New User')->assertSee('committee_designation', false);
    }

    // --------------------------------------------------- create succeeds

    /** @dataProvider creatableRoles */
    public function test_an_admin_can_create_a_user_with_each_assignable_role(string $role): void
    {
        Event::fake([UserCreated::class]);

        $response = $this->actingAs($this->admin())->post(route('users.store'), $this->payload(['role' => $role, 'email' => strtolower($role) . '@test.local']));

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');
        $user = User::where('email', strtolower($role) . '@test.local')->firstOrFail();
        $this->assertTrue($user->hasRole($role));
        $this->assertSame($this->society->id, (int) $user->society_id);
        $this->assertSame('active', $user->status);
        Event::assertDispatched(UserCreated::class, fn ($e) => $e->user->is($user));

        // The flash message must not reveal the password the user was given.
        $this->assertStringNotContainsString('password:', strtolower((string) session('success')));
    }

    public static function creatableRoles(): array
    {
        return array_map(fn ($r) => [$r], ['Admin', 'Owner', 'Tenant', 'Guard', 'Staff', 'Accountant']);
    }

    public function test_a_profile_image_and_committee_designation_persist_and_are_served(): void
    {
        Event::fake([UserCreated::class]);

        $this->actingAs($this->admin())->post(route('users.store'), $this->payload([
            'avatar' => $this->png(), 'committee_designation' => ' Treasurer ',
        ]))->assertRedirect(route('users.index'));

        $user = User::where('email', 'new.person@test.local')->firstOrFail();
        $this->assertStringStartsWith('avatars/', $user->avatar);
        $this->assertFileExists($this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $user->avatar));
        $this->assertSame('Treasurer', $user->committee_designation);
        $this->assertStringEndsWith($user->avatar, $user->avatar_web_url);
    }

    public function test_super_admin_must_choose_a_society_and_it_is_used(): void
    {
        Event::fake([UserCreated::class]);
        $super = $this->makeUserWithRole($this->society, 'Super Admin', ['society_id' => null]);
        $other = $this->makeSociety('Other');

        $this->actingAs($super)->post(route('users.store'), $this->payload())->assertSessionHasErrors('society_id');
        $this->assertSame(0, User::where('email', 'new.person@test.local')->count());

        $this->actingAs($super)->post(route('users.store'), $this->payload(['society_id' => $other->id]))->assertRedirect(route('users.index'));
        $this->assertSame($other->id, (int) User::where('email', 'new.person@test.local')->value('society_id'));
    }

    public function test_an_admin_cannot_place_a_user_in_another_society(): void
    {
        Event::fake([UserCreated::class]);
        $other = $this->makeSociety('Other');

        $this->actingAs($this->admin())->post(route('users.store'), $this->payload(['society_id' => $other->id]));

        $this->assertSame($this->society->id, (int) User::where('email', 'new.person@test.local')->value('society_id'));
    }

    public function test_creating_a_user_does_not_invent_owner_or_tenant_records(): void
    {
        Event::fake([UserCreated::class]);
        $admin = $this->admin();

        foreach (['Owner', 'Tenant'] as $role) {
            $this->actingAs($admin)->post(route('users.store'), $this->payload(['role' => $role, 'email' => "$role@test.local"]));
        }

        $this->assertSame(0, Owner::count());
        $this->assertSame(0, Tenant::count());
        $this->assertNull(User::where('email', 'Owner@test.local')->value('owner_id'));
        $this->assertNull(User::where('email', 'Tenant@test.local')->value('tenant_id'));
    }

    // --------------------------------------------------- validation and rejection

    public function test_invalid_input_returns_clear_errors_keeps_the_input_and_creates_nothing(): void
    {
        $this->makeUserWithRole($this->society, 'Tenant', ['email' => 'taken@test.local']);
        $cases = [
            'missing everything' => [[], ['name', 'email', 'phone', 'role']],
            'bad email'          => [$this->payload(['email' => 'not-an-email']), ['email']],
            'duplicate email'    => [$this->payload(['email' => 'taken@test.local']), ['email']],
            'unknown role'       => [$this->payload(['role' => 'Wizard']), ['role']],
            'overlong name'      => [$this->payload(['name' => str_repeat('a', 300)]), ['name']],
            'overlong designation' => [$this->payload(['committee_designation' => str_repeat('d', 150)]), ['committee_designation']],
            'avatar not an image'  => [$this->payload(['avatar' => $this->garbage()]), ['avatar']],
        ];

        $admin = $this->admin();
        $before = User::count();
        foreach ($cases as $label => [$data, $fields]) {
            $response = $this->actingAs($admin)->from(route('users.create'))->post(route('users.store'), $data);
            $this->assertSame(route('users.create'), $response->headers->get('Location'), "{$label}: not bounced back to the form");
            $response->assertSessionHasErrors($fields);
            $this->assertSame($before, User::count(), "{$label}: a user was created");
        }

        $this->actingAs($admin)->from(route('users.create'))->post(route('users.store'), $this->payload(['name' => '']))
            ->assertSessionHasInput('email', 'new.person@test.local');
        $this->assertSame([], glob($this->root . '/avatars/*') ?: [], 'no stray files from rejected submissions');
    }

    public function test_unauthorised_role_assignments_are_rejected(): void
    {
        Event::fake([UserCreated::class]);

        // An Admin cannot mint a Super Admin (nor the legacy owner roles, which are not offered).
        foreach (['Super Admin', 'Villa Owner', 'Apartment Owner'] as $role) {
            $this->actingAs($this->admin())->post(route('users.store'), $this->payload(['role' => $role]))->assertSessionHasErrors('role');
        }
        $this->assertSame(0, User::where('email', 'new.person@test.local')->count());

        // The picker does not even offer it.
        $roles = $this->actingAs($this->admin())->get(route('users.create'))->viewData('roles')->pluck('name')->all();
        $this->assertNotContains('Super Admin', $roles);
    }

    /** @dataProvider nonAdminRoles */
    public function test_only_admins_can_reach_the_form_or_submit_it(string $role): void
    {
        Event::fake([UserCreated::class]);
        $user = $this->makeUserWithRole($this->society, $role);

        $this->actingAs($user)->get(route('users.create'))->assertStatus(403);
        $this->actingAs($user)->post(route('users.store'), $this->payload())->assertStatus(403);
        $this->assertSame(0, User::where('email', 'new.person@test.local')->count());
    }

    public static function nonAdminRoles(): array
    {
        return array_map(fn ($r) => [$r], ['Owner', 'Tenant', 'Guard', 'Staff', 'Accountant']);
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get(route('users.create'))->assertRedirect(route('login'));
        $this->post(route('users.store'), $this->payload())->assertRedirect(route('login'));
    }

    // --------------------------------------------------- failure leaves nothing behind

    public function test_a_failure_after_the_row_is_written_leaves_no_user_and_no_orphaned_image(): void
    {
        Event::listen(UserCreated::class, function () {
            throw new \RuntimeException('mail transport exploded');
        });

        $response = $this->actingAs($this->admin())->from(route('users.create'))
            ->post(route('users.store'), $this->payload(['avatar' => $this->png()]));

        $response->assertRedirect(route('users.create'));
        $response->assertSessionHasErrors('error');
        $this->assertSame(0, User::where('email', 'new.person@test.local')->count(), 'user row rolled back');
        $this->assertSame([], glob($this->root . '/avatars/*') ?: [], 'uploaded image removed');
    }
}
