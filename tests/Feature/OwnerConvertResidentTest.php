<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Owner;
use App\Models\Resident;
use App\Models\Society;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Owner Management > "convert resident to owner". It had no society check, overwrote a user's existing owner link
 * and a unit's existing owner, could create a second Owner for the same email, and was not atomic.
 */
class OwnerConvertResidentTest extends TestCase
{
    use RefreshDatabase;

    private Society $a;
    private Society $b;
    private User $adminA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = $this->makeSociety('Alpha');
        $this->b = $this->makeSociety('Beta');
        $this->adminA = $this->makeUserWithRole($this->a, 'Admin');
    }

    private function resident(Society $s, string $type = 'owner', array $user = []): Resident
    {
        $flat = $this->makeFlat($s, $this->makeBuilding($s, 'B' . uniqid()), (string) random_int(100, 999));
        $login = $this->makeUserWithRole($s, 'Owner', $user + ['name' => 'Rita']);

        return Resident::create(['society_id' => $s->id, 'flat_id' => $flat->id, 'user_id' => $login->id, 'name' => 'Rita', 'email' => $login->email, 'phone' => '0000000001', 'type' => $type, 'move_in_date' => '2026-01-01', 'status' => 'active']);
    }

    private function convert(Resident $r, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->adminA)->post(route('owners.convert-resident'), ['resident_id' => $r->id]);
    }

    public function test_an_owner_type_resident_becomes_an_owner_linked_to_their_unit_and_login(): void
    {
        $r = $this->resident($this->a);
        $flat = $r->flat;
        $login = $r->user;

        $response = $this->convert($r);

        $owner = Owner::where('society_id', $this->a->id)->sole();
        $response->assertRedirect(route('owners.show', $owner))->assertSessionHas('success');
        $this->assertSame($owner->id, (int) $flat->fresh()->owner_id);
        $this->assertSame($owner->id, (int) $login->fresh()->owner_id);
        $this->assertNull(Resident::find($r->id));
    }

    public function test_another_societys_resident_cannot_be_converted(): void
    {
        $theirs = $this->resident($this->b);

        $this->convert($theirs)->assertStatus(403);

        $this->assertNotNull(Resident::find($theirs->id));
        $this->assertSame(0, Owner::count());
        $this->assertNull($theirs->flat->fresh()->owner_id);
    }

    public function test_a_tenant_type_resident_is_not_converted(): void
    {
        $r = $this->resident($this->a, 'tenant');

        $this->convert($r)->assertSessionHas('error');

        $this->assertSame(0, Owner::count());
        $this->assertNotNull(Resident::find($r->id));
    }

    public function test_a_person_who_already_has_an_owner_record_is_not_given_a_second_one(): void
    {
        $existing = Owner::create(['society_id' => $this->a->id, 'name' => 'Rita', 'email' => 'old@t.local', 'phone' => '0000000009', 'status' => 'active']);
        $r = $this->resident($this->a, 'owner', ['owner_id' => $existing->id]);

        $this->convert($r)->assertSessionHas('error');

        $this->assertSame(1, Owner::count());
        $this->assertSame($existing->id, (int) $r->user->fresh()->owner_id, 'the login keeps its link');
        $this->assertNotNull(Resident::find($r->id));
    }

    public function test_a_unit_that_already_has_an_owner_is_not_taken_over(): void
    {
        $r = $this->resident($this->a);
        $someone = Owner::create(['society_id' => $this->a->id, 'name' => 'Recorded', 'email' => 'rec@t.local', 'phone' => '0000000008', 'status' => 'active']);
        $r->flat->update(['owner_id' => $someone->id]);

        $this->convert($r)->assertSessionHas('error');

        $this->assertSame($someone->id, (int) $r->flat->fresh()->owner_id);
        $this->assertSame(1, Owner::count());
    }

    public function test_an_existing_owner_with_the_same_email_blocks_a_duplicate(): void
    {
        $r = $this->resident($this->a);
        Owner::create(['society_id' => $this->a->id, 'name' => 'Same email', 'email' => $r->email, 'phone' => '0000000007', 'status' => 'active']);

        $this->convert($r)->assertSessionHas('error');

        $this->assertSame(1, Owner::count());
    }

    public function test_the_same_email_in_a_different_society_is_refused_cleanly_not_with_a_database_error(): void
    {
        // owners.email is unique across the whole system, so this used to throw a raw QueryException (500).
        $r = $this->resident($this->a);
        Owner::create(['society_id' => $this->b->id, 'name' => 'Elsewhere', 'email' => $r->email, 'phone' => '0000000006', 'status' => 'active']);

        $this->convert($r)->assertSessionHas('error');

        $this->assertSame(0, Owner::where('society_id', $this->a->id)->count());
        $this->assertNotNull(Resident::find($r->id), 'the resident is untouched');
    }

    public function test_a_missing_resident_is_refused_and_nothing_changes(): void
    {
        $this->actingAs($this->adminA)->post(route('owners.convert-resident'), ['resident_id' => 999999])->assertSessionHasErrors('resident_id');
        $this->actingAs($this->adminA)->post(route('owners.convert-resident'), [])->assertSessionHasErrors('resident_id');
        $this->assertSame(0, Owner::count());
    }

    public function test_only_office_roles_can_convert(): void
    {
        $r = $this->resident($this->a);
        $tenant = $this->makeUserWithRole($this->a, 'Tenant');

        $this->convert($r, $tenant)->assertStatus(403);
        $this->assertSame(0, Owner::count());
    }
}
