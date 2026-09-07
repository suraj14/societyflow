<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\PushNotificationLog;
use App\Models\PushNotificationSetting;
use App\Models\PushNotificationTemplate;
use App\Services\PushNotificationService;
use App\Traits\HandlesFormSubmissions;
use Illuminate\Http\Request;

class PushNotificationController extends BaseController
{
    use HandlesFormSubmissions;

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:Admin|Super Admin');
    }

    /**
     * Show push notification settings
     */
    public function settings()
    {
        $societyId = auth()->user()->hasRole('Super Admin') ? null : auth()->user()->society_id;
        
        if ($societyId) {
            $setting = PushNotificationSetting::where('society_id', $societyId)->first();
        } else {
            $setting = null;
        }

        $templates = PushNotificationTemplate::when($societyId, function($query) use ($societyId) {
            return $query->where('society_id', $societyId)->orWhereNull('society_id');
        })->get();

        return view('admin.push-notifications.settings', compact('setting', 'templates'));
    }

    /**
     * Update push notification settings
     */
    public function updateSettings(Request $request)
    {
        return $this->handleFormSubmission(function() use ($request) {
            $societyId = auth()->user()->hasRole('Super Admin') ? null : auth()->user()->society_id;
            
            if (!$societyId) {
                return redirect()->back()->with('error', 'Invalid society');
            }

            $validated = $request->validate([
                'enabled' => 'required|boolean',
                'fcm_server_key' => 'nullable|string',
                'vapid_public_key' => 'nullable|string',
                'vapid_private_key' => 'nullable|string',
                'enabled_triggers' => 'nullable|array',
                'role_permissions' => 'nullable|array',
            ]);

            $setting = PushNotificationSetting::firstOrCreate(
                ['society_id' => $societyId],
                ['enabled' => false]
            );

            $setting->update([
                'enabled' => $validated['enabled'],
                'fcm_server_key' => $validated['fcm_server_key'] ?? $setting->fcm_server_key,
                'vapid_public_key' => $validated['vapid_public_key'] ?? $setting->vapid_public_key,
                'vapid_private_key' => $validated['vapid_private_key'] ?? $setting->vapid_private_key,
                'enabled_triggers' => $validated['enabled_triggers'] ?? [],
                'role_permissions' => $validated['role_permissions'] ?? [],
            ]);

            return redirect()->route('admin.push-notifications.settings')
                           ->with('success', 'Push notification settings updated successfully');
        });
    }

    /**
     * Show push notification logs
     */
    public function logs()
    {
        $societyId = auth()->user()->hasRole('Super Admin') ? null : auth()->user()->society_id;
        
        $logs = PushNotificationLog::when($societyId, function($query) use ($societyId) {
            return $query->where('society_id', $societyId);
        })
        ->orderBy('created_at', 'desc')
        ->paginate(20);

        return view('admin.push-notifications.logs', compact('logs'));
    }

    /**
     * Test push notification
     */
    public function test(Request $request, PushNotificationService $service)
    {
        $request->validate([
            'test_email' => 'required|email',
        ]);

        try {
            $societyId = auth()->user()->hasRole('Super Admin') ? null : auth()->user()->society_id;
            
            if (!$societyId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid society',
                ], 400);
            }

            $success = $service->test($societyId, $request->test_email);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Test push notification sent successfully',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send test push notification',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Manage push notification templates
     */
    public function templates()
    {
        $societyId = auth()->user()->hasRole('Super Admin') ? null : auth()->user()->society_id;
        
        $templates = PushNotificationTemplate::when($societyId, function($query) use ($societyId) {
            return $query->where('society_id', $societyId)->orWhereNull('society_id');
        })->paginate(15);

        return view('admin.push-notifications.templates', compact('templates'));
    }

    /**
     * Edit push notification template
     */
    public function editTemplate(PushNotificationTemplate $template)
    {
        $societyId = auth()->user()->society_id;
        
        if ($template->society_id && $template->society_id !== $societyId && !auth()->user()->hasRole('Super Admin')) {
            abort(403);
        }

        return view('admin.push-notifications.edit-template', compact('template'));
    }

    /**
     * Update push notification template
     */
    public function updateTemplate(Request $request, PushNotificationTemplate $template)
    {
        return $this->handleFormSubmission(function() use ($request, $template) {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'body' => 'required|string',
                'icon' => 'nullable|string',
                'badge' => 'nullable|string',
                'click_action' => 'nullable|string',
                'is_active' => 'required|boolean',
            ]);

            $template->update($validated);

            return redirect()->route('admin.push-notifications.templates')
                           ->with('success', 'Push notification template updated successfully');
        });
    }

    /**
     * Get push notification statistics
     */
    public function statistics()
    {
        $societyId = auth()->user()->hasRole('Super Admin') ? null : auth()->user()->society_id;
        
        $stats = [
            'total_sent' => PushNotificationLog::when($societyId, function($query) use ($societyId) {
                return $query->where('society_id', $societyId);
            })->where('status', 'sent')->count(),
            
            'total_failed' => PushNotificationLog::when($societyId, function($query) use ($societyId) {
                return $query->where('society_id', $societyId);
            })->where('status', 'failed')->count(),
            
            'total_pending' => PushNotificationLog::when($societyId, function($query) use ($societyId) {
                return $query->where('society_id', $societyId);
            })->where('status', 'pending')->count(),
            
            'by_trigger' => PushNotificationLog::when($societyId, function($query) use ($societyId) {
                return $query->where('society_id', $societyId);
            })
            ->selectRaw('trigger_action, COUNT(*) as count, SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) as sent')
            ->groupBy('trigger_action')
            ->get(),
        ];

        return response()->json($stats);
    }
}
