<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MaintenanceBill;
use App\Models\UtilityBill;
use App\Services\EmailNotificationService;
use Carbon\Carbon;

class SendBillReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bills:send-reminders {--days=3 : Days before due date to send reminder}';

    /**
     * The console command description.
     */
    protected $description = 'Send email reminders for bills that are due soon or overdue';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        // Due dates are calendar dates on the societies' clock (IST), not
        // the storage clock.
        $reminderDate = \App\Support\SocietyClock::now()->addDays($days)->format('Y-m-d');
        $today = \App\Support\SocietyClock::now()->format('Y-m-d');

        $this->info("Sending bill reminders for bills due on or before {$reminderDate}...");

        // Send maintenance bill reminders
        $maintenanceBills = MaintenanceBill::where('status', 'pending')
            ->where('due_date', '<=', $reminderDate)
            ->with(['flat', 'society'])
            ->get();

        $maintenanceCount = 0;
        foreach ($maintenanceBills as $bill) {
            try {
                $recipients = EmailNotificationService::getRecipients('bill_due_reminder', [
                    'society_id' => $bill->society_id,
                    'flat_id' => $bill->flat_id,
                ]);

                if (!empty($recipients)) {
                    // This previously called EmailNotificationService::send(),
                    // a method that does not exist on that class - every
                    // attempt threw an uncaught Error (not an Exception, so
                    // the catch below never saw it) and no bill reminder was
                    // ever sent, for any society, since the command shipped.
                    // sendByTrigger() is the method that actually exists and
                    // matches the 'bill-due-reminder' template's own
                    // placeholders.
                    $variables = [
                        'user_name'      => $recipients[0]['name'] ?? 'Resident',
                        'bill_type'      => 'maintenance',
                        'amount'         => $bill->total_amount,
                        'due_date'       => $bill->due_date,
                        'days_remaining' => (int) Carbon::parse($bill->due_date)->diffInDays($today),
                        'view_url'       => route('payments.show', $bill->id),
                    ];

                    EmailNotificationService::sendByTrigger('bill-due-reminder', $recipients, $variables, $bill->society_id);
                    $maintenanceCount++;
                }
            } catch (\Throwable $e) {
                $this->error("Failed to send reminder for maintenance bill {$bill->id}: " . $e->getMessage());
            }
        }

        // Send utility bill reminders. utility_bills has no payment_status
        // column - that name belongs to a different table entirely
        // (facility_bookings). The real column is status
        // (unpaid|partial|paid); != 'paid' matches UtilityBill::scopeOverdue
        // so a partially paid bill still gets reminded. This previously
        // errored on MySQL (unknown column) and silently matched zero rows
        // on SQLite, where an unrecognised identifier is treated as a
        // string literal rather than raising - no utility bill reminder has
        // ever sent, on either engine.
        $utilityBills = UtilityBill::where('status', '!=', 'paid')
            ->where('due_date', '<=', $reminderDate)
            ->with(['flat', 'society'])
            ->get();

        $utilityCount = 0;
        foreach ($utilityBills as $bill) {
            try {
                $recipients = EmailNotificationService::getRecipients('bill_due_reminder', [
                    'society_id' => $bill->society_id,
                    'flat_id' => $bill->flat_id,
                ]);

                if (!empty($recipients)) {
                    $variables = [
                        'user_name'      => $recipients[0]['name'] ?? 'Resident',
                        'bill_type'      => 'utility',
                        'amount'         => $bill->bill_amount,
                        'due_date'       => $bill->due_date,
                        'days_remaining' => (int) Carbon::parse($bill->due_date)->diffInDays($today),
                        'view_url'       => route('utility-bills.show', $bill->id),
                    ];

                    EmailNotificationService::sendByTrigger('bill-due-reminder', $recipients, $variables, $bill->society_id);
                    $utilityCount++;
                }
            } catch (\Throwable $e) {
                $this->error("Failed to send reminder for utility bill {$bill->id}: " . $e->getMessage());
            }
        }

        $this->info("Sent {$maintenanceCount} maintenance bill reminders and {$utilityCount} utility bill reminders.");

        return Command::SUCCESS;
    }
}
