<?php
namespace Tests\Feature;

use App\Models\AccessPass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessPassViewRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_and_show_screens_render_for_active_expired_and_blocked_passes(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $make = fn (array $o) => AccessPass::create(array_merge([
            'society_id'   => $society->id,
            'flat_id'      => $flat->id,
            'person_name'  => 'Ramraj',
            'category'     => 'Cleaner',
            'pass_type'    => 'recurring_staff',
            'access_scope' => 'property',
            'token'        => AccessPass::generateToken(),
            'allowed_days' => [1, 2, 3],
            'start_time'   => '08:00',
            'end_time'     => '20:00',
            'valid_from'   => now()->subMonth()->toDateString(),
            'valid_until'  => now()->addMonth()->toDateString(),
            'status'       => 'active',
            'created_by'   => $admin->id,
        ], $o));

        $active  = $make([]);
        $expired = $make(['valid_until' => now()->subDay()->toDateString()]);

        $blocked = $make([]);
        $blocked->blockToday();

        foreach ([$active, $expired, $blocked] as $pass) {
            $this->actingAs($admin)->get(route('access-passes.show', $pass))->assertOk();
            $this->actingAs($admin)->get(route('access-passes.edit', $pass))->assertOk();
        }

        // The expired pass's show screen must offer the one-field extend form.
        $this->actingAs($admin)->get(route('access-passes.show', $expired))
            ->assertSee('Extend Pass')
            ->assertSee(route('access-passes.extend', $expired));

        // The blocked pass offers Unblock rather than Block.
        $this->actingAs($admin)->get(route('access-passes.show', $blocked))
            ->assertSee('Unblock Today');

        // A revoked pass offers neither edit nor extend, but does offer a new pass.
        $active->revoke();
        $this->actingAs($admin)->get(route('access-passes.show', $active))
            ->assertOk()
            ->assertDontSee(route('access-passes.edit', $active))
            ->assertSee('Create a new pass');
    }
}
