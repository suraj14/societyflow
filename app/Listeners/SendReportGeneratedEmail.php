<?php

namespace App\Listeners;

use App\Events\ReportGenerated;
use App\Services\DynamicEmailService;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SendReportGeneratedEmail
{
    /**
     * Handle the event
     * 
     * Role-based recipient resolution for Report Generated:
     * - Owner: ❌
     * - Tenant: ❌
     * - Admin: ✅
     * - Staff: ❌
     * - Accountant: ✅
     */
    public function handle(ReportGenerated $event): void
    {
        try {
            $societyId = $event->societyId;

            // Allowed roles for report generated event
            $allowedRoles = ['Admin', 'Accountant'];

            $recipients = [];

            // Get admin and accountant users
            $staff = User::where('society_id', $societyId)
                ->whereHas('roles', function($q) use ($allowedRoles) {
                    $q->whereIn('name', $allowedRoles);
                })
                ->get();
            
            foreach ($staff as $member) {
                $recipients[] = [
                    'email' => $member->email,
                    'name' => $member->name,
                    'role' => $member->getRoleNames()->first() ?? 'user',
                ];
            }

            if (empty($recipients)) {
                Log::info('No recipients for report generated email', [
                    'report_type' => $event->reportType,
                    'society_id' => $societyId,
                ]);
                return;
            }

            $baseVariables = [
                'report_type' => $event->reportType,
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'report_data' => json_encode($event->reportData),
            ];

            // Send email to each recipient with their name
            foreach ($recipients as $recipient) {
                $variables = array_merge($baseVariables, [
                    'user_name' => $recipient['name'],
                ]);

                DynamicEmailService::sendByEvent(
                    'report_generated',
                    [$recipient],
                    $variables,
                    $societyId
                );
            }

            Log::info('Report generated email sent', [
                'report_type' => $event->reportType,
                'recipient_count' => count($recipients),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send report generated email', [
                'report_type' => $event->reportType ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
