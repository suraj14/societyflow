<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Owner;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VillaArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The villa owner "add visitor" form could never have worked.
 *
 * VillaOwner\VisitorController::store validated visit_date, expected_time,
 * notes and status and passed the lot straight to Visitor::create(). None of
 * those are columns on visitors, and the one column that is NOT NULL with no
 * default - expected_entry_time - was never set, so the insert always failed.
 * Four Blade views then read $visitor->visit_date, which does not exist.
 */
class VillaOwnerVisitorTest extends TestCase
{
    use RefreshDatabase;

    private function makeVillaOwner(): array
    {
        $society = $this->makeSociety();

        $area = VillaArea::create([
            'society_id' => $society->id,
            'name'       => 'Palm Grove',
            'status'     => 'active',
        ]);

        $owner = Owner::create([
            'society_id'    => $society->id,
            'name'          => 'Villa Owner',
            'email'         => 'villaowner' . uniqid() . '@test.local',
            'status'        => 'active',
            'property_type' => 'villa',
            'villa_area_id' => $area->id,
            'villa_no'      => 'V-1',
        ]);

        $villa = Flat::create([
            'society_id'    => $society->id,
            'property_type' => 'villa',
            'villa_area_id' => $area->id,
            'owner_id'      => $owner->id,
            'flat_number'   => 'V-1',
            'villa_name'    => 'Rose Villa',
            'status'        => 'occupied',
        ]);

        $user = User::create([
            'society_id' => $society->id,
            'owner_id'   => $owner->id,
            'name'       => 'Villa Owner',
            'email'      => 'villaowneruser' . uniqid() . '@test.local',
            'password'   => bcrypt('x'),
            'status'     => 'active',
        ]);
        $user->assignRole($this->makeRole('Villa Owner')->name);

        return [$user, $villa];
    }

    public function test_villa_owner_can_register_a_visitor(): void
    {
        [$user, $villa] = $this->makeVillaOwner();

        $response = $this->actingAs($user)->post('/my/visitors', [
            'visitor_name'   => 'Amit Verma',
            'visitor_phone'  => '9876512345',
            'visitor_email'  => 'amit@example.com',
            'purpose'        => 'Personal Visit',
            'visit_date'     => now()->addDay()->toDateString(),
            'expected_time'  => '14:30',
            'vehicle_number' => 'RJ20AB1234',
            'notes'          => 'Arriving by car',
        ]);

        $response->assertRedirect(route('villa-owner.visitors'));

        $visitor = Visitor::where('visitor_phone', '9876512345')->first();
        $this->assertNotNull($visitor, 'the visitor row was never created');

        $this->assertSame($villa->id, $visitor->flat_id);
        $this->assertSame($user->id, $visitor->host_user_id);
        $this->assertSame('Amit Verma', $visitor->visitor_name);
        $this->assertSame('amit@example.com', $visitor->visitor_email);
        $this->assertSame('Arriving by car', $visitor->security_notes);
        $this->assertSame('guest', $visitor->visitor_type);

        // Owner-registered guests are pre-approved, matching the mobile API.
        $this->assertSame('allowed', $visitor->approval_status);
        $this->assertSame('pending', $visitor->entry_status);

        // The date and time from two separate form fields must combine.
        $this->assertNotNull($visitor->expected_entry_time);
        $this->assertSame(
            now()->addDay()->format('Y-m-d') . ' 14:30',
            $visitor->expected_entry_time->format('Y-m-d H:i')
        );
    }

    public function test_visitor_time_is_optional(): void
    {
        [$user] = $this->makeVillaOwner();

        $this->actingAs($user)->post('/my/visitors', [
            'visitor_name'  => 'No Time Given',
            'visitor_phone' => '9876500999',
            'purpose'       => 'Delivery',
            'visit_date'    => now()->toDateString(),
        ])->assertRedirect();

        $visitor = Visitor::where('visitor_phone', '9876500999')->first();
        $this->assertNotNull($visitor);
        $this->assertSame(now()->format('Y-m-d') . ' 00:00', $visitor->expected_entry_time->format('Y-m-d H:i'));
    }

    /** The listing and detail views read a column that did not exist. */
    public function test_villa_owner_visitor_screens_render(): void
    {
        [$user] = $this->makeVillaOwner();

        $this->actingAs($user)->post('/my/visitors', [
            'visitor_name'  => 'Render Check',
            'visitor_phone' => '9876500888',
            'purpose'       => 'Personal Visit',
            'visit_date'    => now()->toDateString(),
            'expected_time' => '09:00',
        ]);

        $visitor = Visitor::where('visitor_phone', '9876500888')->firstOrFail();

        $this->actingAs($user)->get('/my/visitors')->assertOk()->assertSee('Render Check');
        $this->actingAs($user)->get("/my/visitors/{$visitor->id}")->assertOk()->assertSee('Render Check');
    }

    public function test_a_bad_time_is_rejected_rather_than_stored_wrong(): void
    {
        [$user] = $this->makeVillaOwner();

        $this->actingAs($user)->post('/my/visitors', [
            'visitor_name'  => 'Bad Time',
            'visitor_phone' => '9876500777',
            'purpose'       => 'Personal Visit',
            'visit_date'    => now()->toDateString(),
            'expected_time' => 'not-a-time',
        ])->assertSessionHasErrors('expected_time');

        $this->assertDatabaseMissing('visitors', ['visitor_phone' => '9876500777']);
    }
}
