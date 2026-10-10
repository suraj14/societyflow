<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Resident;
use App\Models\Tenant;
use App\Support\DataIntegrityRepair;
use App\Support\UserFlatResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `data:integrity-audit` now reports a tenant whose legacy residency row
 * names a different unit than their tenancy. It is report-only because the
 * data cannot say which is right; UserFlatResolver unions the two sources, so
 * such a tenant is shown BOTH units until a person decides.
 */
class ResidencyTenancyAuditTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOn(Flat $tenancyFlat, Flat $residencyFlat, array $residentOverrides = []): array
    {
        $society = $tenancyFlat->society;
        $user    = $this->makeUserWithRole($society, 'Tenant');
        $tenant  = Tenant::create([
            'society_id' => $society->id, 'user_id' => $user->id, 'flat_id' => $tenancyFlat->id, 'name' => 'T',
            'email' => uniqid() . '@test.local', 'move_in_date' => '2026-01-01', 'status' => 'active',
        ]);
        $user->update(['tenant_id' => $tenant->id]);
        $resident = Resident::create(array_merge([
            'society_id' => $society->id, 'user_id' => $user->id, 'flat_id' => $residencyFlat->id, 'type' => 'tenant',
            'move_in_date' => '2026-01-01', 'status' => 'active',
        ], $residentOverrides));

        return [$user, $tenant, $resident];
    }

    private function flats(): array
    {
        $society  = $this->makeSociety();
        $building = $this->makeBuilding($society);

        return [$this->makeFlat($society, $building, '159'), $this->makeFlat($society, $building, '160')];
    }

    public function test_a_residency_row_naming_another_unit_is_reported_and_never_auto_repaired(): void
    {
        [$rented, $other] = $this->flats();
        [$user, $tenant, $resident] = $this->tenantOn($rented, $other);

        $found = DataIntegrityRepair::residentsContradictingTenancy();
        $this->assertCount(1, $found);
        $this->assertSame($tenant->id, $found[0]->tenant_id);
        $this->assertSame($rented->id, $found[0]->tenancy_flat_id);
        $this->assertSame($resident->id, $found[0]->resident_id);
        $this->assertSame($other->id, $found[0]->resident_flat_id);

        // The symptom this audit exists for: the tenant is shown both units.
        $this->assertEqualsCanonicalizing([$rented->id, $other->id], UserFlatResolver::flatIdsFor($user));

        $this->artisan('data:integrity-audit')->assertExitCode(1);
        $this->artisan('data:integrity-audit', ['--fix' => true])->assertExitCode(1); // --fix cannot decide this

        $this->assertSame($other->id, $resident->fresh()->flat_id, '--fix must not touch it');
        $this->assertSame($rented->id, $tenant->fresh()->flat_id);
    }

    public function test_agreeing_inactive_and_non_tenant_rows_are_not_reported(): void
    {
        [$rented, $other] = $this->flats();

        $this->tenantOn($rented, $rented);                                         // agrees
        $this->tenantOn($rented, $other, ['status' => 'inactive']);                // a closed residency
        $this->tenantOn($rented, $other, ['type' => 'owner']);                     // an owner's row, not a tenant's

        $this->assertCount(0, DataIntegrityRepair::residentsContradictingTenancy());
        $this->artisan('data:integrity-audit')->assertExitCode(0);
    }

    public function test_an_ended_tenancy_is_not_reported(): void
    {
        [$rented, $other] = $this->flats();
        [, $tenant] = $this->tenantOn($rented, $other);
        $tenant->update(['status' => 'inactive']);

        $this->assertCount(0, DataIntegrityRepair::residentsContradictingTenancy());
    }

    // ---------------------------------------------------- linked profile images

    public function test_a_linked_user_and_owner_with_different_photos_is_reported(): void
    {
        $society = $this->makeSociety();
        $owner = \App\Models\Owner::create(['society_id' => $society->id, 'name' => 'O', 'email' => uniqid() . '@t.local', 'phone' => '9000000099', 'status' => 'active', 'profile_image' => 'owners/old.jpg']);
        $user = $this->makeUserWithRole($society, 'Owner', ['owner_id' => $owner->id, 'avatar' => 'owners/new.jpg']);

        $found = DataIntegrityRepair::mismatchedProfileImages();
        $this->assertCount(1, $found);
        $this->assertSame($user->id, $found[0]->user_id);
        $this->assertSame($owner->id, $found[0]->owner_id);
        $this->assertSame('owners/old.jpg', $found[0]->owner_profile_image);

        $this->artisan('data:integrity-audit')->assertExitCode(1);
        $this->artisan('data:integrity-audit', ['--fix' => true])->assertExitCode(1); // never auto-fixed

        $this->assertSame('owners/new.jpg', $user->fresh()->avatar, '--fix must not touch it');
    }

    public function test_a_linked_pair_with_the_same_photo_or_both_blank_is_not_reported(): void
    {
        $society = $this->makeSociety();
        $agree = \App\Models\Owner::create(['society_id' => $society->id, 'name' => 'A', 'email' => uniqid() . '@t.local', 'phone' => '9000000098', 'status' => 'active', 'profile_image' => 'owners/same.jpg']);
        $this->makeUserWithRole($society, 'Owner', ['owner_id' => $agree->id, 'avatar' => 'owners/same.jpg']);
        $blank = \App\Models\Owner::create(['society_id' => $society->id, 'name' => 'B', 'email' => uniqid() . '@t.local', 'phone' => '9000000097', 'status' => 'active']);
        $this->makeUserWithRole($society, 'Owner', ['owner_id' => $blank->id]);

        $this->assertCount(0, DataIntegrityRepair::mismatchedProfileImages());
        $this->artisan('data:integrity-audit')->assertExitCode(0);
    }

    public function test_an_unlinked_user_or_owner_is_not_reported(): void
    {
        $society = $this->makeSociety();
        $this->makeUserWithRole($society, 'Owner', ['owner_id' => null, 'avatar' => 'avatars/solo.jpg']);
        \App\Models\Owner::create(['society_id' => $society->id, 'name' => 'Solo', 'email' => uniqid() . '@t.local', 'phone' => '9000000096', 'status' => 'active', 'profile_image' => 'owners/solo.jpg']);

        $this->assertCount(0, DataIntegrityRepair::mismatchedProfileImages());
    }

    public function test_profileimage_change_never_produces_a_mismatch(): void
    {
        $society = $this->makeSociety();
        $owner = \App\Models\Owner::create(['society_id' => $society->id, 'name' => 'O', 'email' => uniqid() . '@t.local', 'phone' => '9000000095', 'status' => 'active']);
        $user = $this->makeUserWithRole($society, 'Owner', ['owner_id' => $owner->id]);

        \App\Support\ProfileImage::change($user, null, false);
        $this->assertCount(0, DataIntegrityRepair::mismatchedProfileImages());
    }
}
