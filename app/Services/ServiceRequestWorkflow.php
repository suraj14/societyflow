<?php

namespace App\Services;

use App\Models\ServiceProvider;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\BusinessNotifications;

/**
 * pending -> assigned -> in_progress -> completed, or cancelled while open.
 *
 * One definition of the lifecycle for the web service-requests screen and the
 * mobile admin API (they used to be the controller's own code). Rules refuse
 * with an HTTP exception carrying the message, as the web controller always
 * did, so both clients answer the same way.
 */
class ServiceRequestWorkflow
{
    /** Hand a pending (or already assigned) request to a provider of the same society and service. */
    public function assign(ServiceRequest $request, int $providerId, User $by): ServiceRequest
    {
        abort_unless(
            in_array($request->status, ['pending', 'assigned'], true),
            422,
            'This service request is already ' . str_replace('_', ' ', $request->status) . ' and cannot be assigned.'
        );

        $provider = ServiceProvider::where('id', $providerId)
            ->where('society_id', $request->society_id)
            ->where('service_id', $request->service_id)
            ->where('status', 'active')
            ->first();
        abort_unless($provider, 422, 'Choose an active provider of this service in your society.');

        $request->update([
            'assigned_provider_id' => $provider->id,
            'assigned_by'          => $by->id,
            'assigned_at'          => now(),
            'status'               => 'assigned',
        ]);

        $this->notify($request, 'assigned');

        return $request->refresh();
    }

    /**
     * Move a request on, or cancel it. $isStaff false = the resident who raised
     * it, who may only cancel their own while it is open.
     */
    public function changeStatus(ServiceRequest $request, string $status, User $by, ?string $cancellationReason = null): ServiceRequest
    {
        $isStaff = $by->hasAnyRole(['Super Admin', 'Admin', 'Staff']);

        if (!$isStaff) {
            abort_if((int) $request->user_id !== (int) $by->id, 403, 'You can only manage your own service requests.');
            abort_if($status !== 'cancelled', 403, 'Only society staff can change a service request to that status.');
        }

        abort_unless($request->isOpen(), 422, 'This service request is already ' . str_replace('_', ' ', $request->status) . '.');

        if ($status === 'in_progress' && $request->status !== 'assigned') {
            abort(422, 'A request must be assigned to a provider before it can start.');
        }
        if ($status === 'completed' && !in_array($request->status, ['assigned', 'in_progress'], true)) {
            abort(422, 'A request must be assigned before it can be completed.');
        }

        $update = ['status' => $status];
        if ($status === 'in_progress') {
            $update['started_at'] = now();
        }
        if ($status === 'completed') {
            $update['completed_at'] = now();
        }
        if ($status === 'cancelled') {
            $update['cancelled_at']        = now();
            $update['cancellation_reason'] = $cancellationReason;
        }

        $request->update($update);
        $this->notify($request, $status);

        return $request->refresh();
    }

    private function notify(ServiceRequest $request, string $action): void
    {
        EmailNotificationService::sendServiceRequestNotification($request, $action);
        BusinessNotifications::serviceRequestUpdated($request, $action);
    }
}
