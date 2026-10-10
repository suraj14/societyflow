<?php

namespace Database\Seeders;

use App\Helpers\TemporaryPassword;
use App\Models\AccessPass;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Event;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\Notice;
use App\Models\Society;
use App\Models\SocietyDocument;
use App\Models\User;
use App\Models\UtilityBill;
use App\Models\Visitor;
use App\Services\FacilityBookingService;
use App\Support\ExternalMessaging;
use App\Support\InAppNotifier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A realistic, clearly labelled demo dataset for the "Royal Eden" test society.
 *
 *     EXTERNAL_MESSAGING_ENABLED=false php artisan db:seed --class=RoyalEdenDemoSeeder
 *
 * Properties:
 *  - NOT part of the default db:seed path.
 *  - Refuses to run unless EXTERNAL_MESSAGING_ENABLED=false: this database holds
 *    real provider credentials per society, and nothing seeded here may ever be
 *    able to reach them.
 *  - Resolves Royal Eden by exact name and refuses if there is not exactly one.
 *  - Idempotent: every record is looked up by a natural key first; re-running
 *    creates nothing new. Existing valid Royal Eden records are never modified
 *    (society details are filled only where they still hold the "To be updated"
 *    placeholders).
 *  - Deterministic: fixed dates, no randomness except the unrecorded temporary
 *    passwords of the two demo staff accounts.
 *  - Demo records carry "[DEMO]" in their names/titles. Demo accounts use the
 *    reserved ".test" TLD and a 0-prefixed number that cannot be a real mobile.
 *  - Seeds through models only. Notification events are dispatched by
 *    controllers, never by model hooks, so nothing is emailed, texted or sent.
 *  - It reuses the owners, tenant and villas already in Royal Eden; it does not
 *    create a second identity for anyone who has a login.
 */
class RoyalEdenDemoSeeder extends Seeder
{
    private Society $society;

    /** @var array<string,int> */
    private array $created = [];

    public function run(): void
    {
        if (ExternalMessaging::enabled()) {
            throw new \RuntimeException('Refusing to seed: set EXTERNAL_MESSAGING_ENABLED=false first (this database holds real provider credentials).');
        }

        $matches = Society::where('name', 'Royal Eden')->get();
        if ($matches->count() !== 1) {
            throw new \RuntimeException("Expected exactly one society named 'Royal Eden', found {$matches->count()}.");
        }
        $this->society = $matches->first();

        DB::transaction(function () {
            \Illuminate\Database\Eloquent\Model::unguarded(function () {
                $this->societyDetails();
                [$admin, $accountant, $staff] = $this->staffAccounts();
                $people = $this->people();
                $this->committee($accountant, $staff);
                $facilities = $this->facilities();
                $this->bookings($facilities, $people);
                $categories = $this->complaintCategories();
                $this->complaints($categories, $people, $staff);
                $this->bills($people);
                $this->utilityBills($people);
                $this->notices($admin, $people);
                $this->events($admin);
                $this->visitorsAndPasses($admin, $people);
                $this->document($admin);
                $this->inAppNotifications($admin, $people);
            });
        });

        foreach ($this->created as $what => $n) {
            $this->command?->line(sprintf('  %-26s +%d', $what, $n));
        }
        $this->command?->info($this->created ? 'Royal Eden demo data seeded.' : 'Nothing to add - Royal Eden already has the demo data.');
    }

    private function once(string $label, $model, bool $wasCreated): void
    {
        if ($wasCreated) {
            $this->created[$label] = ($this->created[$label] ?? 0) + 1;
        }
    }

    private function findOrCreate(string $label, string $class, array $key, array $values)
    {
        $found = $class::where($key)->first();
        if ($found) {
            return $found;
        }
        $model = $class::create($key + $values);
        $this->once($label, $model, true);

        return $model;
    }

    // ------------------------------------------------------------------ people

    private function societyDetails(): void
    {
        $s = $this->society;
        $placeholder = fn ($v) => $v === null || $v === '' || $v === 'To be updated' || $v === '000000';
        $fill = [];
        foreach ([
            'address' => '[DEMO] 12 Eden Lane, Test Nagar',
            'city' => 'Jaipur',
            'state' => 'Rajasthan',
            'pincode' => '302001',
            'description' => '[DEMO] Royal Eden is a sample villa community used for testing SocietyFlow. All records marked [DEMO] are fictional.',
            'registration_number' => 'DEMO-REG-0001',
        ] as $column => $value) {
            if ($placeholder($s->{$column})) {
                $fill[$column] = $value;
            }
        }
        if ($placeholder($s->registered_on)) {
            $fill['registered_on'] = '2020-04-01';
        }
        if ($fill) {
            $s->forceFill($fill)->save();
            $this->created['society details filled'] = count($fill);
        }
    }

    /** @return array{0:User,1:User,2:User} */
    private function staffAccounts(): array
    {
        $admin = User::where('society_id', $this->society->id)->whereHas('roles', fn ($q) => $q->where('name', 'Admin'))
            ->whereNotNull('email')->where('email', 'not like', '%@test.local')->orderBy('id')->firstOrFail();

        $make = function (string $email, string $name, string $role, string $phone) {
            $user = User::where('email', $email)->first();
            if ($user) {
                return $user;
            }
            $user = User::create([
                'name' => $name, 'email' => $email, 'phone' => $phone, 'status' => 'active',
                'society_id' => $this->society->id,
                // Random and never recorded: sign in through "forgot password" / an admin reset.
                'password' => Hash::make(TemporaryPassword::generate()),
            ]);
            $user->assignRole($role);
            $this->once("user: {$role}", $user, true);

            return $user;
        };

        return [
            $admin,
            $make('demo.accountant@royaleden.test', '[DEMO] Accountant', 'Accountant', '0000000101'),
            $make('demo.staff@royaleden.test', '[DEMO] Maintenance Staff', 'Staff', '0000000102'),
        ];
    }

    /** The existing Royal Eden residents, by their stable identifiers. @return array<string,User> */
    private function people(): array
    {
        $sid = $this->society->id;
        $villa = fn (string $no) => Flat::where('society_id', $sid)->where('property_type', 'villa')->where('flat_number', $no)->firstOrFail();

        // The login of the owner whose OWN record names this villa (owners.villa_no),
        // so it works whether or not flats.owner_id has been linked yet.
        $ownerOf = function (string $no) use ($sid) {
            $owner = \App\Models\Owner::where('society_id', $sid)->where('property_type', 'villa')->where('villa_no', $no)->firstOrFail();

            return User::where('society_id', $sid)->where('owner_id', $owner->id)->firstOrFail();
        };

        $flat159 = $villa('159');
        $tenancy = \App\Models\Tenant::where('society_id', $sid)->where('flat_id', $flat159->id)->where('status', 'active')->whereNotNull('user_id')->firstOrFail();

        return [
            'suraj'  => $ownerOf('160'),
            'lokesh' => $ownerOf('159'),
            'naresh' => $ownerOf('158'),
            'piyus'  => User::where('society_id', $sid)->findOrFail($tenancy->user_id),   // current tenant of villa 159
            'flat160' => $villa('160'),
            'flat159' => $flat159,
            'flat158' => $villa('158'),
        ];
    }

    private function committee(User $accountant, User $staff): void
    {
        foreach ([[$accountant, 'Treasurer'], [$staff, 'Secretary']] as [$user, $designation]) {
            if (!$user->committee_designation) {
                $user->forceFill(['committee_designation' => $designation])->save();
                $this->once('committee members', $user, true);
            }
        }
    }

    // -------------------------------------------------------------- facilities

    private function facilities(): array
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $base = ['society_id' => $this->society->id];
        $f = fn (string $name, array $v) => $this->findOrCreate('facilities', Facility::class, $base + ['name' => $name], $v + [
            'available_days' => $days, 'advance_booking_days' => 30, 'max_booking_hours' => 4,
        ]);

        return [
            'club' => $f('[DEMO] Clubhouse', ['type' => 'clubhouse', 'description' => '[DEMO] Community clubhouse with a hall and kitchenette.', 'capacity' => 80, 'booking_charge' => 1500, 'opening_time' => '08:00', 'closing_time' => '22:00', 'requires_approval' => true, 'status' => 'active']),
            'gym'  => $f('[DEMO] Gym', ['type' => 'gym', 'description' => '[DEMO] Resident gym, instant booking.', 'capacity' => 20, 'booking_charge' => 0, 'opening_time' => '06:00', 'closing_time' => '22:00', 'requires_approval' => false, 'status' => 'active']),
            'pavilion' => $f('[DEMO] Garden Pavilion', ['type' => 'other', 'description' => '[DEMO] Closed for repairs - shows the "not bookable" state.', 'capacity' => 30, 'booking_charge' => 500, 'opening_time' => '08:00', 'closing_time' => '20:00', 'requires_approval' => true, 'status' => 'maintenance']),
        ];
    }

    private function bookings(array $facilities, array $p): void
    {
        $rows = [
            // facility, user, flat, date, start, end, status, guests, extras
            ['club', 'naresh', 'flat158', '2026-10-24', '18:00', '22:00', 'pending', 40, ['purpose' => '[DEMO] Family function']],
            ['club', 'suraj', 'flat160', '2026-10-25', '10:00', '13:00', 'approved', 25, ['purpose' => '[DEMO] Birthday gathering', 'approved_at' => '2026-10-09 10:00:00', 'admin_notes' => '[DEMO] Approved.']],
            ['club', 'lokesh', 'flat159', '2026-10-26', '18:00', '21:00', 'rejected', 60, ['purpose' => '[DEMO] Reception', 'admin_notes' => '[DEMO] Slot reserved for a society event.']],
            ['gym', 'piyus', 'flat159', '2026-10-18', '06:00', '07:00', 'cancelled', 1, ['purpose' => '[DEMO] Workout', 'cancelled_at' => '2026-10-09 18:00:00', 'cancellation_reason' => '[DEMO] Travelling that day.']],
            ['gym', 'piyus', 'flat159', '2026-10-05', '07:00', '08:00', 'completed', 1, ['purpose' => '[DEMO] Workout']],
        ];

        foreach ($rows as [$fac, $who, $flat, $date, $start, $end, $status, $guests, $extra]) {
            $facility = $facilities[$fac];
            $exists = FacilityBooking::where('facility_id', $facility->id)->where('booking_date', 'like', "{$date}%")->where('start_time', $start)->exists();
            if ($exists) {
                continue;
            }
            // Never seed a double booking: a slot that is still held (not rejected/cancelled) must be free.
            if (!in_array($status, FacilityBookingService::FREE_STATUSES, true)
                && FacilityBookingService::overlaps($facility, $date, FacilityBookingService::minutes($start), FacilityBookingService::minutes($end))) {
                throw new \RuntimeException("Demo booking {$fac} {$date} {$start} would double-book an existing one.");
            }
            $booking = FacilityBooking::create(array_merge([
                'society_id' => $this->society->id, 'facility_id' => $facility->id, 'user_id' => $p[$who]->id, 'flat_id' => $p[$flat]->id,
                'booking_date' => $date, 'start_time' => $start, 'end_time' => $end, 'expected_guests' => $guests,
                'booking_amount' => $facility->booking_charge ?? 0, 'security_deposit' => 0, 'payment_status' => 'pending', 'status' => $status,
            ], $extra));
            $this->once('facility bookings', $booking, true);
        }
    }

    // -------------------------------------------------------------- complaints

    private function complaintCategories(): array
    {
        $out = [];
        foreach (['Plumbing', 'Electrical', 'Cleanliness', 'Security', 'Noise', 'Garden & Common Area'] as $name) {
            $out[$name] = $this->findOrCreate('complaint categories', ComplaintCategory::class,
                ['society_id' => $this->society->id, 'name' => $name], ['description' => "[DEMO] {$name} issues", 'status' => 'active']);
        }

        return $out;
    }

    private function complaints(array $cat, array $p, User $staff): void
    {
        $rows = [
            ['[DEMO] Street light not working near villa 158', 'Electrical', 'naresh', 'flat158', 'medium', 'open', null, []],
            ['[DEMO] Water leakage at the common pump room', 'Plumbing', 'suraj', 'flat160', 'high', 'in_progress', $staff->id, []],
            ['[DEMO] Garbage not collected for two days', 'Cleanliness', 'piyus', 'flat159', 'low', 'resolved', $staff->id, ['resolution_notes' => '[DEMO] Collection rescheduled and area cleaned.', 'resolved_at' => '2026-10-08 12:00:00']],
            ['[DEMO] Loud music after midnight', 'Noise', 'lokesh', 'flat159', 'medium', 'closed', null, ['resolution_notes' => '[DEMO] Resident spoken to.', 'resolved_at' => '2026-10-07 09:00:00']],
        ];
        foreach ($rows as [$title, $category, $who, $flat, $priority, $status, $assignee, $extra]) {
            $this->findOrCreate('complaints', Complaint::class, ['society_id' => $this->society->id, 'title' => $title], array_merge([
                'complaint_category_id' => $cat[$category]->id, 'flat_id' => $p[$flat]->id, 'created_by' => $p[$who]->id,
                'assigned_to' => $assignee, 'description' => "{$title} - sample ticket for testing.", 'priority' => $priority, 'status' => $status,
            ], $extra));
        }
    }

    // ----------------------------------------------------------- bills / money

    private function bills(array $p): void
    {
        // one bill per flat per month (unique index). Flat 160 already has a September rent bill.
        $rows = [
            // flat, month, year, bill date, due date, amount, payment to record
            ['flat158', 'September', 2026, '2026-09-01', '2026-09-15', 2500, 2700, 'naresh'],
            ['flat158', 'October',   2026, '2026-10-01', '2026-10-20', 2500, 0, null],
            ['flat160', 'October',   2026, '2026-10-01', '2026-10-20', 2500, 0, null],
            ['flat159', 'September', 2026, '2026-09-01', '2026-09-15', 2500, 1000, 'lokesh'],
            ['flat159', 'October',   2026, '2026-10-01', '2026-10-05', 2500, 0, null],
        ];
        foreach ($rows as [$flat, $month, $year, $billDate, $due, $amount, $pay, $payer]) {
            $f = $p[$flat];
            $bill = MaintenanceBill::where('society_id', $this->society->id)->where('flat_id', $f->id)->where('month', $month)->where('year', $year)->first();
            if (!$bill) {
                $bill = MaintenanceBill::create([
                    'society_id' => $this->society->id, 'flat_id' => $f->id, 'month' => $month, 'year' => $year,
                    'bill_date' => $billDate, 'due_date' => $due, 'bill_type' => 'maintenance',
                    'maintenance_amount' => $amount, 'water_charges' => 150, 'electricity_charges' => 0, 'parking_charges' => 50,
                    'penalty_amount' => 0, 'other_charges' => 0, 'notes' => '[DEMO] Monthly maintenance',
                ]);
                $this->once('maintenance bills', $bill, true);
            }
            if ($pay > 0 && $bill->payments()->where('transaction_id', "DEMO-{$bill->id}")->doesntExist()) {
                $bill->addPayment((float) $pay, [
                    'user_id' => $p[$payer]->id, 'payment_method' => 'cash', 'payment_gateway' => 'manual',
                    'transaction_id' => "DEMO-{$bill->id}", 'payment_date' => $billDate === '2026-09-01' ? '2026-09-10 11:00:00' : '2026-09-12 11:00:00',
                    'notes' => '[DEMO] Recorded manually - no real payment.',
                ]);
                $this->once('payments', $bill, true);
            }
        }
    }

    private function utilityBills(array $p): void
    {
        $rows = [
            ['flat158', 'electricity', 1850, '2026-10-01', '2026-10-20', 'unpaid', null],
            ['flat159', 'water', 420, '2026-09-01', '2026-09-20', 'paid', '2026-09-15 10:00:00'],
        ];
        foreach ($rows as [$flat, $type, $amount, $billDate, $due, $status, $paidAt]) {
            // bill_date is stored as a datetime, so match on the date part.
            $exists = UtilityBill::where('society_id', $this->society->id)->where('flat_id', $p[$flat]->id)
                ->where('bill_type', $type)->whereDate('bill_date', $billDate)->exists();
            if (!$exists) {
                $this->once('utility bills', null, (bool) UtilityBill::create([
                    'society_id' => $this->society->id, 'flat_id' => $p[$flat]->id, 'bill_type' => $type, 'bill_date' => $billDate,
                    'bill_amount' => $amount, 'due_date' => $due, 'status' => $status, 'payment_date' => $paidAt, 'notes' => '[DEMO] sample utility bill',
                ]));
            }
        }
    }

    // ------------------------------------------------- notices / events / etc.

    private function notices(User $admin, array $p): void
    {
        $rows = [
            ['[DEMO] Water supply maintenance on Saturday', 'maintenance', 'high', 'published', '2026-10-09', '2026-10-20', $admin, true],
            ['[DEMO] Draft: Diwali celebration plan', 'event', 'medium', 'draft', null, null, $admin, false],
            ['[DEMO] Resident request: terrace usage', 'general', 'low', 'pending', null, null, $p['naresh'], false],
            ['[DEMO] Pest control completed last month', 'general', 'low', 'expired', '2026-08-25', '2026-09-05', $admin, true],
        ];
        foreach ($rows as [$title, $type, $priority, $status, $publish, $expiry, $by, $approved]) {
            $this->findOrCreate('notices', Notice::class, ['society_id' => $this->society->id, 'title' => $title], [
                'created_by' => $by->id, 'approved_by' => $approved ? $admin->id : null, 'approved_at' => $approved ? $publish . ' 09:00:00' : null,
                'content' => "{$title}. This is a fictional demo notice used for testing.", 'type' => $type, 'priority' => $priority,
                'status' => $status, 'publish_date' => $publish, 'expiry_date' => $expiry, 'send_email' => false, 'send_sms' => false,
            ]);
        }
    }

    private function events(User $admin): void
    {
        $rows = [
            ['[DEMO] Diwali get-together', '2026-10-30 18:00:00', '2026-10-30 21:00:00', 'pending'],
            ['[DEMO] Morning yoga session', '2026-10-04 06:30:00', '2026-10-04 07:30:00', 'completed'],
            ['[DEMO] Inter-villa cricket match', '2026-10-18 09:00:00', '2026-10-18 13:00:00', 'cancelled'],
        ];
        foreach ($rows as [$name, $start, $end, $status]) {
            $this->findOrCreate('events', Event::class, ['society_id' => $this->society->id, 'event_name' => $name], [
                'created_by' => $admin->id, 'location' => '[DEMO] Clubhouse lawn', 'description' => "{$name} - fictional demo event.",
                'start_date' => $start, 'end_date' => $end, 'status' => $status, 'is_role_based' => false,
            ]);
        }
    }

    private function visitorsAndPasses(User $admin, array $p): void
    {
        $this->findOrCreate('visitors', Visitor::class, ['society_id' => $this->society->id, 'visitor_name' => '[DEMO] Courier delivery'], [
            'flat_id' => $p['flat158']->id, 'host_user_id' => $p['naresh']->id, 'visitor_phone' => '0000000201', 'visitor_type' => 'delivery',
            'purpose' => '[DEMO] Parcel', 'expected_count' => 1, 'expected_entry_time' => '2026-10-12 11:00:00',
            'approval_status' => 'pending', 'entry_status' => 'pending',
        ]);
        $this->findOrCreate('visitors', Visitor::class, ['society_id' => $this->society->id, 'visitor_name' => '[DEMO] Door-to-door salesperson'], [
            'flat_id' => $p['flat160']->id, 'host_user_id' => $p['suraj']->id, 'visitor_phone' => '0000000202', 'visitor_type' => 'other',
            'purpose' => '[DEMO] Sales visit', 'expected_count' => 1, 'expected_entry_time' => '2026-10-09 16:00:00',
            'approval_status' => 'denied', 'entry_status' => 'pending', 'rejection_reason' => '[DEMO] Not expected by the resident.',
        ]);

        $this->findOrCreate('access passes', AccessPass::class, ['society_id' => $this->society->id, 'person_name' => '[DEMO] Weekly gardener', 'flat_id' => $p['flat158']->id], [
            'person_phone' => '0000000203', 'category' => 'Gardener', 'pass_type' => 'recurring_staff', 'access_scope' => 'property',
            'token' => Str::random(40), 'allowed_days' => ['1', '3', '5'], 'start_time' => '07:00', 'end_time' => '11:00',
            'valid_from' => '2026-10-01', 'valid_until' => '2026-12-31', 'status' => 'active', 'created_by' => $p['naresh']->id,
        ]);
    }

    private function document(User $admin): void
    {
        $title = '[DEMO] House Rules (sample)';
        if (SocietyDocument::where('society_id', $this->society->id)->where('title', $title)->exists()) {
            return;
        }
        // A minimal valid PDF that says plainly it is not an official document.
        $pdf = "%PDF-1.1\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 400 120]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj\n"
            . "4 0 obj<</Length 79>>stream\nBT /F1 12 Tf 20 60 Td (DEMO sample - not an official society document) Tj ET\nendstream endobj\n"
            . "5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\ntrailer<</Root 1 0 R/Size 6>>\n%%EOF";
        $path = "society-documents/{$this->society->id}/" . Str::uuid() . '.pdf';
        Storage::disk('local')->put($path, $pdf);

        $doc = SocietyDocument::create([
            'society_id' => $this->society->id, 'series_key' => (string) Str::uuid(), 'version_number' => 1, 'version' => 'v1',
            'title' => $title, 'category' => 'society_rules', 'description' => '[DEMO] Placeholder document for testing uploads and downloads.',
            'status' => 'published', 'visible_to_guards' => false, 'requires_acknowledgement' => false,
            'file_path' => $path, 'original_filename' => 'demo-house-rules.pdf', 'file_size' => strlen($pdf),
            'uploaded_by' => $admin->id, 'published_at' => '2026-10-09 09:00:00',
        ]);
        $this->once('society documents', $doc, true);
    }

    private function inAppNotifications(User $admin, array $p): void
    {
        $items = [
            [[$p['suraj']], 'booking', 'Booking approved', '[DEMO] Your Clubhouse booking on 25 Oct was approved.'],
            [[$p['piyus']], 'complaint', 'Complaint updated', '[DEMO] Your complaint about garbage collection is now Resolved.'],
            [[$admin], 'complaint', 'New complaint', '[DEMO] Street light not working near villa 158.'],
        ];
        foreach ($items as [$users, $category, $title, $body]) {
            foreach ($users as $user) {
                $already = DB::table('notifications')->where('notifiable_id', $user->id)->where('data', 'like', '%' . str_replace('"', '', substr($body, 0, 30)) . '%')->exists();
                if (!$already) {
                    $this->created['in-app notifications'] = ($this->created['in-app notifications'] ?? 0) + InAppNotifier::send([$user], $category, $title, $body);
                }
            }
        }
    }
}
