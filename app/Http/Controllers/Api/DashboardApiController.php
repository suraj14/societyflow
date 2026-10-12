<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ResolvesUserFlats;
use App\Models\MaintenanceBill;
use App\Models\Visitor;
use App\Models\Complaint;
use App\Models\Notice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardApiController extends Controller
{
    use ResolvesUserFlats;

    public function index(Request $request): JsonResponse
    {
        $user      = $request->user();
        $societyId = $user->society_id;
        $flatIds   = $this->userFlatIds($user);

        // Pending bills across all units the user resides in or owns
        $pendingBills = $flatIds
            ? MaintenanceBill::where('society_id', $societyId)->whereIn('flat_id', $flatIds)
                ->whereIn('status', ['pending', 'overdue', 'partial'])
                ->count()
            : 0;

        // Visitors today for this user
        $visitorsToday = Visitor::where('society_id', $societyId)
            ->where('host_user_id', $user->id)
            ->whereBetween('expected_entry_time', \App\Support\SocietyClock::dayBounds())
            ->count();

        // Open complaints by this user
        $openComplaints = Complaint::where('created_by', $user->id)
            ->whereIn('status', ['open', 'in_progress'])
            ->count();

        // Active notices for this society
        $activeNotices = Notice::where('society_id', $societyId)
            ->published()
            ->count();

        // Latest notices (3)
        $notices = Notice::where('society_id', $societyId)
            ->published()
            ->latest()
            ->take(3)
            ->get()
            ->map(fn($n) => [
                'id'    => $n->id,
                'title' => $n->title,
            ]);

        // Recent activities
        $recentActivities = $this->getRecentActivities($user, $flatIds, $societyId);

        return response()->json([
            'success' => true,
            'data'    => [
                'pending_bills'      => $pendingBills,
                'visitors_today'     => $visitorsToday,
                'open_complaints'    => $openComplaints,
                'active_notices'     => $activeNotices,
                'notices'            => $notices,
                'recent_activities'  => $recentActivities,
            ],
        ]);
    }

    private function getRecentActivities($user, $flatIds, $societyId): array
    {
        $activities = [];

        // Recent payment: one the user made, or one recorded against a unit they own or rent (an admin recording a
        // tenant's rent at the office writes user_id = admin) - the same rule as the bills screens.
        $payment = Payment::where('society_id', $societyId)
            ->where(function ($q) use ($user, $flatIds) {
                $q->where('user_id', $user->id);
                if ($flatIds) {
                    $q->orWhereIn('flat_id', $flatIds);
                }
            })
            ->where('status', 'success')
            ->latest('payment_date')
            ->first();
        if ($payment) {
            $kind = strtolower((string) $payment->bill_type) === 'rent' ? 'Rent' : 'Maintenance';
            $activities[] = [
                'type'   => 'payment',
                'title'  => $kind . ' bill paid',
                'time'   => $payment->payment_date->diffForHumans(),
                'status' => 'paid',
            ];
        }

        // Recent visitor
        $visitor = Visitor::where('host_user_id', $user->id)
            ->latest()
            ->first();
        if ($visitor) {
            $activities[] = [
                'type'   => 'visitor',
                'title'  => 'Visitor: ' . $visitor->visitor_name,
                'time'   => $visitor->created_at->diffForHumans(),
                'status' => $visitor->approval_status,
            ];
        }

        // Recent complaint
        $complaint = Complaint::where('created_by', $user->id)
            ->latest()
            ->first();
        if ($complaint) {
            $activities[] = [
                'type'   => 'complaint',
                'title'  => $complaint->title,
                'time'   => $complaint->created_at->diffForHumans(),
                'status' => $complaint->status,
            ];
        }

        return $activities;
    }
}
