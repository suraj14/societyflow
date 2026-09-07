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
        $reminderDate = Carbon::now()->addDays($days)->format('Y-m-d');
        $today = Carbon::now()->format('Y-m-d');

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
                    $data = [
                        'subject' => "Bill Due Reminder - " . $bill->society->name,
                        'bill' => $bill,
                        'bill_type' => 'maintenance',
                        'amount' => $bill->total_amount,
                        'due_date' => $bill->due_date,
                        'view_url' => route('payments.show', $bill->id),
                        'user' => (object) ['name' => $recipients[0]['name'] ?? 'Resident'],
                    ];

                    EmailNotificationService::send('bill-due-reminder', $data, $recipients, $bill->society_id);
                    $maintenanceCount++;
                }
            } catch (\Exception $e) {
                $this->error("Failed to send reminder for maintenance bill {$bill->id}: " . $e->getMessage());
            }
        }

        // Send utility bill reminders
        $utilityBills = UtilityBill::where('payment_status', 'pending')
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
                    $data = [
                        'subject' => "Utility Bill Due Reminder - " . $bill->society->name,
                        'bill' => $bill,
                        'bill_type' => 'utility',
                        'amount' => $bill->amount,
                        'due_date' => $bill->due_date,
                        'view_url' => route('utility-bills.show', $bill->id),
                        'user' => (object) ['name' => $recipients[0]['name'] ?? 'Resident'],
                    ];

                    EmailNotificationService::send('bill-due-reminder', $data, $recipients, $bill->society_id);
                    $utilityCount++;
                }
            } catch (\Exception $e) {
                $this->error("Failed to send reminder for utility bill {$bill->id}: " . $e->getMessage());
            }
        }

        $this->info("Sent {$maintenanceCount} maintenance bill reminders and {$utilityCount} utility bill reminders.");
        
        return Command::SUCCESS;
    }
}