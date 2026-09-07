<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\EmailLog;
use Illuminate\Http\Request;

class EmailLogController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:Admin|Super Admin');
    }

    /**
     * Display email logs
     */
    public function index(Request $request)
    {
        $societyId = auth()->user()->hasRole('Super Admin') ? null : auth()->user()->society_id;
        
        $query = EmailLog::query();

        // Filter by society
        if ($societyId) {
            $query->where('society_id', $societyId);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by trigger action
        if ($request->filled('trigger')) {
            $query->where('trigger_action', $request->trigger);
        }

        // Search by email
        if ($request->filled('search')) {
            $query->where('recipient_email', 'like', '%' . $request->search . '%');
        }

        // Date range filter
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(20);

        // Get statistics
        $stats = [
            'total' => EmailLog::when($societyId, fn($q) => $q->where('society_id', $societyId))->count(),
            'sent' => EmailLog::sent()->when($societyId, fn($q) => $q->where('society_id', $societyId))->count(),
            'failed' => EmailLog::failed()->when($societyId, fn($q) => $q->where('society_id', $societyId))->count(),
            'pending' => EmailLog::pending()->when($societyId, fn($q) => $q->where('society_id', $societyId))->count(),
        ];

        return view('admin.email-logs.index', compact('logs', 'stats'));
    }

    /**
     * Show email log details
     */
    public function show(EmailLog $emailLog)
    {
        $this->checkEmailLogAccess($emailLog);
        return view('admin.email-logs.show', compact('emailLog'));
    }

    /**
     * Retry failed email
     */
    public function retry(EmailLog $emailLog)
    {
        $this->checkEmailLogAccess($emailLog);

        if ($emailLog->status !== 'failed') {
            return redirect()->back()->with('error', 'Only failed emails can be retried.');
        }

        if ($emailLog->retry_count >= 3) {
            return redirect()->back()->with('error', 'Maximum retry attempts exceeded.');
        }

        try {
            $template = $emailLog->emailTemplate;
            if ($template) {
                \App\Jobs\SendEmailJob::dispatch($emailLog, $template);
                return redirect()->back()->with('success', 'Email retry queued successfully.');
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to queue retry: ' . $e->getMessage());
        }
    }

    /**
     * Check email log access
     */
    private function checkEmailLogAccess(EmailLog $emailLog): void
    {
        if (!auth()->user()->hasRole('Super Admin') && $emailLog->society_id !== auth()->user()->society_id) {
            abort(403, 'Unauthorized access to email log.');
        }
    }
}
