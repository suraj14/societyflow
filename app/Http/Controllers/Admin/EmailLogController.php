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

        // Filter by channel (email/sms/whatsapp/push)
        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }

        // Search by email or phone - whichever this row's channel used.
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('recipient_email', 'like', '%' . $request->search . '%')
                  ->orWhere('recipient_phone', 'like', '%' . $request->search . '%');
            });
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
            // "Skipped" rows (never attempted) are stored as failed + a marker; they are not failures.
            'failed' => EmailLog::failed()->where(fn ($q) => $q->whereNull('error_message')->orWhere('error_message', 'not like', EmailLog::SKIPPED_PREFIX . '%'))->when($societyId, fn($q) => $q->where('society_id', $societyId))->count(),
            'skipped' => EmailLog::failed()->where('error_message', 'like', EmailLog::SKIPPED_PREFIX . '%')->when($societyId, fn($q) => $q->where('society_id', $societyId))->count(),
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

        // A password or reset link is never kept in the log, so there is nothing to rebuild the message from.
        if ($emailLog->hasHiddenValues()) {
            return redirect()->back()->with('error', 'This email carried a password or link that is not stored, so it cannot be re-sent from here. Reset the user\'s password to send a new one.');
        }

        $template = $emailLog->emailTemplate;
        if (!$template) {
            return redirect()->back()->with('error', 'The template for this email no longer exists, so it cannot be re-sent.');
        }

        try {
            \App\Jobs\SendEmailJob::dispatch($emailLog, $template);

            return redirect()->back()->with('success', 'Email retry queued successfully.');
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
