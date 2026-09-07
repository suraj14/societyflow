<?php

namespace App\Listeners;

use App\Events\TenantOwnerAdded;
use App\Services\DynamicEmailService;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SendTenantOwnerAddedEmail
{
    /**
     * Handle the event
     * 
     * Role-based recipient resolution for Tenant/Owner Added:
     * - Owner: ✅
     * - Tenant: ✅
     * - Admin: ✅
     * - Staff: ❌
     * - Accountant: ❌
     */
    public function handle(TenantOwnerAdded $event): void
    {
        try {
            $tenant = $event->tenant;
            $societyId = $tenant->society_id;

            // Allowed roles for tenant/owner added event
            $allowedRoles = ['Owner', 'Villa Owner', 'Apartment Owner', 'Tenant', 'Admin'];

            $recipients = [];

            // Add tenant user
            if ($tenant->user_id) {
                $user = User::find($tenant->user_id);
                if ($user && $user->hasAnyRole($allowedRoles)) {
                    $recipients[] = [
                        'email' => $user->email,
                        'name' => $user->name,
                        'role' => $user->getRoleNames()->first() ?? 'user',
                    ];
                }
            }

            // Add property owner
            if ($tenant->flat_id) {
                $owners = User::whereHas('ownedProperties', function($q) use ($tenant) {
                    $q->where('id', $tenant->flat_id);
                })->where('society_id', $societyId)
                  ->whereHas('roles', function($q) use ($allowedRoles) {
                      $q->whereIn('name', $allowedRoles);
                  })
                  ->get();

                foreach ($owners as $owner) {
                    $recipients[] = [
                        'email' => $owner->email,
                        'name' => $owner->name,
                        'role' => $owner->getRoleNames()->first() ?? 'user',
                    ];
                }
            }

            // Add admin
            $admin = User::where('society_id', $societyId)
                ->whereHas('roles', function($q) {
                    $q->where('name', 'Admin');
                })
                ->first();
            
            if ($admin) {
                $recipients[] = [
                    'email' => $admin->email,
                    'name' => $admin->name,
                    'role' => 'Admin',
                ];
            }

            if (empty($recipients)) {
                Log::info('No recipients for tenant/owner added email', [
                    'tenant_id' => $tenant->id,
                ]);
                return;
            }

            $baseVariables = [
                'tenant_name' => $tenant->name ?? '',
                'tenant_phone' => $tenant->phone ?? '',
                'property_number' => $tenant->flat?->flat_number ?? '',
                'lease_start_date' => $tenant->lease_start_date ?? '',
                'lease_end_date' => $tenant->lease_end_date ?? '',
            ];

            // Send email to each recipient with their name
            foreach ($recipients as $recipient) {
                $variables = array_merge($baseVariables, [
                    'user_name' => $recipient['name'],
                ]);

                DynamicEmailService::sendByEvent(
                    'tenant_owner_added',
                    [$recipient],
                    $variables,
                    $societyId
                );
            }

            Log::info('Tenant/Owner added email sent', [
                'tenant_id' => $tenant->id,
                'recipient_count' => count($recipients),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send tenant/owner added email', [
                'tenant_id' => $event->tenant->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
