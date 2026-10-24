<?php

namespace Tests\Feature;

use App\Models\AccessPass;
use App\Models\Complaint;
use App\Models\Event;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\Notice;
use App\Models\Owner;
use App\Models\Society;
use App\Models\SocietyDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UtilityBill;
use App\Models\Visitor;
use App\Models\VillaArea;
use App\Services\FacilityBookingService;
use Database\Seeders\RoyalEdenDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The Royal Eden demo seeder must be safe (never reaches a provider, never
 * touches another society), repeatable (a second run adds nothing) and
 * coherent (every record belongs to Royal Eden and to a real relationship).
 */
class RoyalEdenDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private Society $eden;
    private Society $other;

    protected function setUp(): void
    {
        parent::setUp();
        // The seeder is a snapshot of mid-October 2026 with fixed due dates (5 and 20 Oct), and a bill's status is derived from today's
        // date (an unpaid bill past its due date is "overdue"). Without a frozen clock this class passed on 17 Oct and failed from 21 Oct
        // on, for no change in the code. Freeze the clock at the date the data was designed for.
        \Carbon\Carbon::setTestNow('2026-10-16 12:00:00');
        Storage::fake('local');
        config(['messaging.enabled' => false]);
        foreach (['Admin', 'Owner', 'Tenant', 'Accountant', 'Staff', 'Guard'] as $role) {
            $this->makeRole($role);
        }

        // Another society that must be left exactly as it is.
        $this->other = $this->makeSociety('Neighbour Heights');
        Facility::create(['society_id' => $this->other->id, 'name' => 'Neighbour Pool', 'type' => 'swimming_pool', 'status' => 'active']);

        // Royal Eden as it exists in the dev database: three villas, three owners with
        // logins (flats.owner_id deliberately unlinked), one tenant on villa 159, an admin.
        $this->eden = $this->makeSociety('Royal Eden');
        $this->eden->forceFill(['address' => 'To be updated', 'city' => 'To be updated', 'pincode' => '000000'])->save();
        $area = VillaArea::create(['society_id' => $this->eden->id, 'name' => 'Size 25x50', 'status' => 'active']);
        foreach (['158' => 'Naresh', '159' => 'Lokesh', '160' => 'Suraj'] as $no => $name) {
            Flat::create(['society_id' => $this->eden->id, 'villa_area_id' => $area->id, 'property_type' => 'villa', 'flat_number' => $no, 'status' => 'vacant']);
            $owner = Owner::create(['society_id' => $this->eden->id, 'name' => $name, 'email' => strtolower($name) . '@owner.test', 'phone' => '0000000' . $no, 'status' => 'active', 'property_type' => 'villa', 'villa_area_id' => $area->id, 'villa_no' => $no]);
            $this->makeUserWithRole($this->eden, 'Owner', ['name' => $name, 'email' => strtolower($name) . '@owner.test', 'owner_id' => $owner->id]);
        }
        $tenantUser = $this->makeUserWithRole($this->eden, 'Tenant', ['name' => 'Piyus', 'email' => 'piyus@tenant.test']);
        $tenant = Tenant::create(['society_id' => $this->eden->id, 'flat_id' => Flat::where('flat_number', '159')->value('id'), 'user_id' => $tenantUser->id, 'name' => 'Piyus', 'email' => 'piyus@tenant.test', 'phone' => '0000000001', 'move_in_date' => '2026-09-22', 'status' => 'active']);
        $tenantUser->update(['tenant_id' => $tenant->id]);
        $this->makeUserWithRole($this->eden, 'Admin', ['name' => 'Eden Admin', 'email' => 'admin@eden.test']);
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        parent::tearDown();
    }

    private function seed_(): void
    {
        $this->seed(RoyalEdenDemoSeeder::class);
    }

    /** Row counts of every table the seeder writes, for Royal Eden. */
    private function counts(): array
    {
        $s = $this->eden->id;

        return [
            'users' => User::where('society_id', $s)->count(),
            'facilities' => Facility::where('society_id', $s)->count(),
            'bookings' => FacilityBooking::where('society_id', $s)->count(),
            'complaints' => Complaint::where('society_id', $s)->count(),
            'bills' => MaintenanceBill::where('society_id', $s)->count(),
            'utility' => UtilityBill::where('society_id', $s)->count(),
            'notices' => Notice::where('society_id', $s)->count(),
            'events' => Event::where('society_id', $s)->count(),
            'visitors' => Visitor::where('society_id', $s)->count(),
            'passes' => AccessPass::where('society_id', $s)->count(),
            'documents' => SocietyDocument::where('society_id', $s)->count(),
            'notifications' => \DB::table('notifications')->count(),
            'categories' => \DB::table('complaint_categories')->where('society_id', $s)->count(),
        ];
    }

    public function test_it_refuses_to_run_unless_external_messaging_is_off(): void
    {
        config(['messaging.enabled' => true]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('EXTERNAL_MESSAGING_ENABLED=false');
        $this->seed_();
    }

    public function test_it_refuses_if_royal_eden_is_missing_or_ambiguous(): void
    {
        $this->makeSociety('Royal Eden');   // a second one

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('exactly one society');
        $this->seed_();
    }

    public function test_it_creates_every_module_and_a_second_run_adds_nothing(): void
    {
        Mail::fake();
        $this->seed_();
        $first = $this->counts();

        foreach (['facilities' => 3, 'bookings' => 5, 'complaints' => 4, 'bills' => 5, 'utility' => 2, 'notices' => 4, 'events' => 3, 'visitors' => 2, 'passes' => 1, 'documents' => 1, 'categories' => 6] as $key => $n) {
            $this->assertSame($n, $first[$key], "{$key}");
        }
        $this->assertSame(3 + 1 + 1 + 2, $first['users'], '3 owners + tenant + admin + the two demo staff accounts');
        $this->assertGreaterThan(0, $first['notifications']);

        $this->seed_();
        $this->seed_();
        $this->assertSame($first, $this->counts(), 'a re-run must not create duplicates');
        Mail::assertNothingSent();
    }

    public function test_demo_accounts_are_safe_and_do_not_duplicate_anyone(): void
    {
        $this->seed_();

        foreach (['demo.accountant@royaleden.test' => 'Accountant', 'demo.staff@royaleden.test' => 'Staff'] as $email => $role) {
            $user = User::where('email', $email)->firstOrFail();
            $this->assertTrue($user->hasRole($role));
            $this->assertSame($this->eden->id, (int) $user->society_id);
            $this->assertStringStartsWith('0', $user->phone, 'not a dialable mobile number');
            $this->assertNotNull($user->committee_designation);
        }
        // Owners and the tenant already had logins: no second identity was created.
        $this->assertSame(1, User::where('owner_id', Owner::where('villa_no', '160')->value('id'))->count());
        $this->assertSame(0, User::selectRaw('lower(email) e, count(*) n')->groupBy('e')->havingRaw('count(*) > 1')->get()->count());
        $this->assertSame('DEMO-REG-0001', $this->eden->fresh()->registration_number);
        $this->assertSame('Jaipur', $this->eden->fresh()->city);
    }

    public function test_every_record_belongs_to_royal_eden_and_to_a_real_relationship(): void
    {
        $this->seed_();
        $s = $this->eden->id;

        // Nothing leaked into, or was taken from, the other society.
        $this->assertSame(1, Facility::where('society_id', $this->other->id)->count());
        $this->assertSame(0, FacilityBooking::where('society_id', $this->other->id)->count());

        foreach (FacilityBooking::where('society_id', $s)->get() as $b) {
            $this->assertSame($s, (int) Facility::find($b->facility_id)->society_id, "booking {$b->id} facility");
            $this->assertSame($s, (int) User::find($b->user_id)->society_id, "booking {$b->id} user");
            $this->assertSame($s, (int) Flat::find($b->flat_id)->society_id, "booking {$b->id} flat");
        }
        foreach (MaintenanceBill::where('society_id', $s)->get() as $bill) {
            $this->assertSame($s, (int) Flat::find($bill->flat_id)->society_id);
            $this->assertTrue($bill->ledgerAgrees(), "bill {$bill->id} money agrees with its payments");
        }
        foreach (Complaint::where('society_id', $s)->get() as $c) {
            $this->assertSame($s, (int) User::find($c->created_by)->society_id);
            $this->assertSame($s, (int) \App\Models\ComplaintCategory::find($c->complaint_category_id)->society_id);
            if ($c->assigned_to) {
                $this->assertSame($s, (int) User::find($c->assigned_to)->society_id);
            }
        }
        foreach (Visitor::where('society_id', $s)->get() as $v) {
            $this->assertSame($s, (int) User::find($v->host_user_id)->society_id);
            $this->assertSame($s, (int) Flat::find($v->flat_id)->society_id);
        }
        foreach (UtilityBill::where('society_id', $s)->get() as $u) {
            $this->assertSame($s, (int) Flat::find($u->flat_id)->society_id);
        }
        Storage::disk('local')->assertExists(SocietyDocument::where('society_id', $s)->value('file_path'));
    }

    public function test_seeded_documents_use_a_real_category_and_residents_can_actually_see_them(): void
    {
        $this->seed_();

        foreach (SocietyDocument::where('society_id', $this->eden->id)->get() as $doc) {
            $this->assertArrayHasKey($doc->category, SocietyDocument::CATEGORIES, "document '{$doc->title}' has an unknown category and would be hidden from Society Info");
        }

        // End to end: the resident's Society Info > Documents tab lists it. (An unknown category
        // was silently dropped from that page before, while the admin page still showed it.)
        $owner = User::where('society_id', $this->eden->id)->whereHas('roles', fn ($q) => $q->where('name', 'Owner'))->firstOrFail();
        $this->actingAs($owner)->get(route('society-rules.index', ['tab' => 'documents']))
            ->assertOk()->assertSee('[DEMO] House Rules (sample)');
    }

    public function test_bookings_cover_every_status_and_never_double_book(): void
    {
        $this->seed_();

        $statuses = FacilityBooking::where('society_id', $this->eden->id)->pluck('status')->unique()->sort()->values()->all();
        $this->assertSame(['approved', 'cancelled', 'completed', 'pending', 'rejected'], $statuses);

        $held = FacilityBooking::where('society_id', $this->eden->id)->whereNotIn('status', FacilityBookingService::FREE_STATUSES)->get();
        foreach ($held as $a) {
            foreach ($held as $b) {
                if ($a->id < $b->id && $a->facility_id === $b->facility_id && substr((string) $a->booking_date, 0, 10) === substr((string) $b->booking_date, 0, 10)) {
                    $overlap = FacilityBookingService::minutes($a->start_time) < FacilityBookingService::minutes($b->end_time)
                        && FacilityBookingService::minutes($b->start_time) < FacilityBookingService::minutes($a->end_time);
                    $this->assertFalse($overlap, "bookings {$a->id} and {$b->id} overlap");
                }
            }
        }
        // The maintenance facility is not bookable, and is shown as such.
        $this->assertSame('maintenance', Facility::where('name', '[DEMO] Garden Pavilion')->value('status'));
    }

    public function test_notices_events_and_bills_cover_their_states(): void
    {
        $this->seed_();
        $s = $this->eden->id;

        $this->assertEqualsCanonicalizing(['draft', 'expired', 'pending', 'published'], Notice::where('society_id', $s)->pluck('status')->all());
        $this->assertEqualsCanonicalizing(['cancelled', 'completed', 'pending'], Event::where('society_id', $s)->pluck('status')->all());
        $this->assertEqualsCanonicalizing(['overdue', 'paid', 'partial', 'pending', 'pending'], MaintenanceBill::where('society_id', $s)->pluck('status')->all());
        $this->assertEqualsCanonicalizing(['closed', 'in_progress', 'open', 'resolved'], Complaint::where('society_id', $s)->pluck('status')->all());
        $this->assertEqualsCanonicalizing(['paid', 'unpaid'], UtilityBill::where('society_id', $s)->pluck('status')->all());
    }
}
