<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use App\Models\MaintenanceBill;
use App\Models\Owner;
use App\Models\User;
use App\Models\UtilityBill;
use App\Services\EmailNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * `bills:send-reminders` called `EmailNotificationService::send(...)` - a
 * method that does not exist on that class. Every call threw an uncaught
 * Error (PHP's Error, not Exception - the command's `catch (\Exception $e)`
 * never saw it), so no bill reminder, for any society, has ever actually
 * sent since this command shipped. This is a separate defect from the
 * Notice email root cause: a wrong method name, not a missing template -
 * `bill-due-reminder` was already an active template.
 */
class BillReminderCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_command_completes_without_the_previous_undefined_method_error(): void
    {
        Mail::fake();

        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));

        MaintenanceBill::create([
            'society_id'   => $society->id,
            'flat_id'      => $flat->id,
            'bill_number'  => 'MB-TEST-0001',
            'month'        => now()->format('F'),
            'year'         => now()->year,
            'bill_date'    => now()->toDateString(),
            'total_amount' => 2500,
            'due_date'     => now()->addDays(2)->toDateString(),
            'status'       => 'pending',
        ]);

        // No owner/tenant is wired to the flat, so getRecipients() finds
        // nobody and the send branch is skipped - the regression this pins
        // is that the command used to fatal before it could even reach that
        // decision for every bill, recipients or not.
        $this->artisan('bills:send-reminders', ['--days' => 3])
            ->assertExitCode(0);
    }

    public function test_sendbytrigger_actually_sends_using_the_bill_due_reminder_template(): void
    {
        // Isolates the fix itself: the correct method, called with the
        // correct (flat) variable shape the 'bill-due-reminder' template
        // expects, independent of the owner/tenant relationship
        // SendBillReminders uses to find who to send to.
        Mail::fake();

        $society = $this->makeSociety();

        $result = EmailNotificationService::sendByTrigger(
            'bill-due-reminder',
            [['email' => 'owner@test.local', 'name' => 'Test Owner', 'role' => 'Owner']],
            [
                'user_name'      => 'Test Owner',
                'bill_type'      => 'maintenance',
                'amount'         => 2500,
                'due_date'       => now()->addDays(3)->toDateString(),
                'days_remaining' => 3,
                'view_url'       => '#',
            ],
            $society->id,
        );

        $this->assertTrue($result);
        $this->assertDatabaseHas('email_logs', [
            'recipient_email' => 'owner@test.local',
            'trigger_action'  => 'bill-due-reminder',
            'status'          => 'sent',
        ]);
    }

    /**
     * The utility-bill half of SendBillReminders had its own, independent
     * defect: UtilityBill::where('payment_status', 'pending') - that column
     * does not exist on utility_bills at all (it belongs to a different
     * table, facility_bookings). On MySQL this throws (unknown column),
     * failing the command outright before the per-bill try/catch is ever
     * reached. On SQLite the unrecognised identifier is treated as a string
     * literal rather than raising (see CLAUDE.md §8), so the query silently
     * matched zero rows forever - no utility bill reminder has ever sent,
     * on either engine. Fixed to query the real column (`status`,
     * unpaid|partial|paid) and the real amount column (`bill_amount`, not
     * `amount`). Demonstrated end to end, not just "doesn't crash".
     */
    public function test_an_unpaid_utility_bill_due_soon_sends_a_real_reminder(): void
    {
        Mail::fake();

        $society  = $this->makeSociety();
        $flat     = $this->makeFlat($society, $this->makeBuilding($society));
        $owner    = Owner::create([
            'society_id' => $society->id,
            'name'       => 'Utility Owner',
            'email'      => 'utilityowner' . uniqid() . '@test.local',
            'phone'      => '9' . random_int(100000000, 999999999),
            'status'     => 'active',
        ]);
        $flat->update(['owner_id' => $owner->id]);
        $recipient = User::create([
            'society_id' => $society->id,
            'owner_id'   => $owner->id,
            'name'       => $owner->name,
            'email'      => $owner->email,
            'phone'      => $owner->phone,
            'password'   => bcrypt('password123'),
            'status'     => 'active',
        ]);
        // shouldReceiveEmail() allowlists by role; a recipient with none
        // falls back to 'user', which bill-due-reminder is not allowed for.
        $recipient->assignRole($this->makeRole('Apartment Owner'));

        $due = UtilityBill::create([
            'society_id' => $society->id,
            'flat_id'    => $flat->id,
            'bill_type'  => 'Water Bill',
            'bill_amount' => 450,
            'bill_date'  => now()->toDateString(),
            'due_date'   => now()->addDays(2)->toDateString(),
            'status'     => 'unpaid',
        ]);

        // A bill already paid must not be reminded.
        UtilityBill::create([
            'society_id' => $society->id,
            'flat_id'    => $flat->id,
            'bill_type'  => 'Electricity Bill',
            'bill_amount' => 900,
            'bill_date'  => now()->toDateString(),
            'due_date'   => now()->addDays(2)->toDateString(),
            'status'     => 'paid',
        ]);

        $this->artisan('bills:send-reminders', ['--days' => 3])
            ->assertExitCode(0);

        $this->assertDatabaseHas('email_logs', [
            'recipient_email' => $recipient->email,
            'trigger_action'  => 'bill-due-reminder',
            'status'          => 'sent',
        ]);
        $this->assertSame(1, EmailLog::where('trigger_action', 'bill-due-reminder')->count(), 'only the unpaid bill should have been reminded');
    }
}
