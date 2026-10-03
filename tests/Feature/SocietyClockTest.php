<?php

namespace Tests\Feature;

use App\Models\AccessPass;
use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\Visitor;
use App\Services\AccessPassService;
use App\Support\ReportPeriod;
use App\Support\SocietyClock;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Storage is UTC (the suite's app.timezone), societies run on Asia/Kolkata.
 * "Today", the current month/year, overdue, and access-pass hours must be
 * read on the society's clock, which is 5½ hours ahead of storage.
 */
class SocietyClockTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_societies_default_to_india_and_may_override(): void
    {
        $society = $this->makeSociety();
        $this->assertSame('Asia/Kolkata', $society->fresh()->clockTimezone());

        $society->update(['timezone' => 'Asia/Dubai']);
        $this->assertSame('Asia/Dubai', SocietyClock::timezoneFor($society->id));

        $society->update(['timezone' => 'Not/AZone']);
        $this->assertSame('Asia/Kolkata', SocietyClock::timezoneFor($society), 'junk falls back');
    }

    public function test_utc_to_ist_at_midnight_month_and_year_boundaries(): void
    {
        $society = $this->makeSociety();

        // 2025-12-31 18:30 UTC is exactly 2026-01-01 00:00 IST.
        Carbon::setTestNow(Carbon::parse('2025-12-31 18:30:00', 'UTC'));

        $this->assertSame('2025-12-31', now()->toDateString(), 'storage clock still says last year');
        $this->assertSame('2026-01-01', SocietyClock::today($society)->toDateString());
        $this->assertSame(1, SocietyClock::now($society)->month);
        $this->assertSame(2026, SocietyClock::now($society)->year);

        [$start, $end] = SocietyClock::dayBounds($society);
        $this->assertSame('2025-12-31 18:30:00', $start, 'IST day start, expressed in storage time');
        $this->assertSame('2026-01-01 18:29:59', $end);

        [$mStart, $mEnd] = SocietyClock::monthBounds($society);
        $this->assertSame('2025-12-31 18:30:00', $mStart);
        $this->assertSame('2026-01-31 18:29:59', $mEnd);

        $period = ReportPeriod::fromRequest(new Request(['report_type' => 'monthly']));
        $this->assertSame(2026, $period->year);
        $this->assertSame(1, $period->month);

        // One second earlier it is still 2025 in India too.
        Carbon::setTestNow(Carbon::parse('2025-12-31 18:29:59', 'UTC'));
        $this->assertSame('2025-12-31', SocietyClock::today($society)->toDateString());
        $this->assertSame(12, SocietyClock::now($society)->month);
    }

    public function test_todays_visitors_are_counted_on_the_society_day(): void
    {
        $society  = $this->makeSociety();
        $building = $this->makeBuilding($society);
        $flat     = $this->makeFlat($society, $building);
        $admin    = $this->makeUserWithRole($society, 'Admin');
        $host     = $this->makeResidentUser($society, $flat);

        $make = fn (string $utc) => Visitor::create([
            'society_id' => $society->id, 'flat_id' => $flat->id, 'host_user_id' => $host->id,
            'visitor_name' => 'V ' . $utc, 'visitor_phone' => '9000000000', 'visitor_type' => 'guest',
            'purpose' => 'x', 'expected_entry_time' => $utc, 'approval_status' => 'pending', 'entry_status' => 'pending',
        ]);

        $make('2026-09-30 19:00:00'); // 00:30 IST on 1 Oct  -> today
        $make('2026-10-01 18:00:00'); // 23:30 IST on 1 Oct  -> today
        $make('2026-10-01 18:45:00'); // 00:15 IST on 2 Oct  -> tomorrow
        $make('2026-09-30 18:00:00'); // 23:30 IST on 30 Sep -> yesterday

        Carbon::setTestNow(Carbon::parse('2026-10-01 05:00:00', 'UTC')); // 10:30 IST, 1 Oct

        $this->actingAs($admin);
        $this->assertSame(2, Visitor::where('society_id', $society->id)->whereBetween('expected_entry_time', SocietyClock::dayBounds())->count());

        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertViewHas('stats', fn ($s) => $s['visitors_today'] === 2);
    }

    public function test_bill_is_overdue_on_the_society_calendar(): void
    {
        $society  = $this->makeSociety();
        $building = $this->makeBuilding($society);
        $flat     = $this->makeFlat($society, $building);

        // 00:30 IST on 11 Sep: the bill due on the 10th is overdue in India,
        // while the storage clock still reads 19:00 on the 10th.
        Carbon::setTestNow(Carbon::parse('2026-09-10 19:00:00', 'UTC'));

        $bill = MaintenanceBill::create([
            'society_id' => $society->id, 'flat_id' => $flat->id, 'month' => 'September', 'year' => 2026,
            'bill_date' => '2026-09-01', 'due_date' => '2026-09-10', 'maintenance_amount' => 100, 'paid_amount' => 0, 'bill_type' => 'maintenance',
        ]);

        $this->assertSame('overdue', $bill->status);

        Carbon::setTestNow(Carbon::parse('2026-09-10 10:00:00', 'UTC')); // 15:30 IST on the 10th
        $bill->touch();
        $bill->save();
        $this->assertSame('pending', $bill->fresh()->status);
    }

    public function test_access_pass_time_window_is_checked_in_society_local_time(): void
    {
        $society  = $this->makeSociety();
        $building = $this->makeBuilding($society);
        $flat     = $this->makeFlat($society, $building);
        $guard    = $this->makeUserWithRole($society, 'Guard');
        $admin    = $this->makeUserWithRole($society, 'Admin');

        $pass = AccessPass::create([
            'society_id' => $society->id, 'flat_id' => $flat->id, 'created_by' => $admin->id,
            'pass_type' => 'recurring_staff', 'access_scope' => 'property', 'person_name' => 'Cook', 'person_phone' => '9000000009', 'category' => 'Cook',
            'token' => AccessPass::generateToken(),
            'valid_from' => '2026-09-01', 'valid_until' => '2026-12-31',
            'allowed_days' => [0, 1, 2, 3, 4, 5, 6], 'start_time' => '06:00', 'end_time' => '10:00',
            'status' => 'active',
        ]);

        $service = app(AccessPassService::class);

        // 07:00 IST = 01:30 UTC: inside the window in India, outside it on the storage clock.
        $at = Carbon::parse('2026-09-15 01:30:00', 'UTC');
        $this->assertTrue($service->verify($pass->token, $society->id, $at)['ok']);

        // 11:00 IST = 05:30 UTC: outside the window.
        $at = Carbon::parse('2026-09-15 05:30:00', 'UTC');
        $result = $service->verify($pass->token, $society->id, $at);
        $this->assertFalse($result['ok']);
        $this->assertSame(AccessPassService::REASON_WRONG_TIME, $result['reason']);
    }
}
