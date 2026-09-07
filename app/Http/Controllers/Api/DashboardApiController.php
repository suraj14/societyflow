<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceBill;
use App\Models\Visitor;
use App\Models\Complaint;
use App\Models\Notice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user      = $request->user();
        $societyId = $user->society_id;
        $resident  = $user->resident()->with('flat')->first();
        $flatId    = $resident?->flat_id;

        // Pending bills for this flat
        $pendingBills = $flatId
            ? MaintenanceBill::where('flat_id', $flatId)
                ->whereIn('status', ['pending', 'overdue', 'partial'])
                ->count()
            : 0;

        // Visitors today for this user
        $visitorsToday = Visitor::where('society_id', $societyId)
            ->where('host_user_id', $user->id)
            ->whereDate('expected_entry_time', today())
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
        $recentActivities = $this->getRecentActivities($user, $flatId, $societyId);

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

    private function getRecentActivities($user, $flatId, $societyId): array
    {
        $activities = [];

        // Recent payment
        $payment = Payment::where('user_id', $user->id)
            ->where('status', 'success')
            ->latest('payment_date')
            ->first();
        if ($payment) {
            $activities[] = [
                'type'   => 'payment',
                'title'  => 'Maintenance bill paid',
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
