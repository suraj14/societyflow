<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FirebaseSettingsController extends Controller
{
    /**
     * Show Firebase settings page
     */
    public function index()
    {
        $isConfigured = FirebaseService::isConfigured();
        $totalDevices = PushSubscription::count();
        $activeDevices = PushSubscription::where('is_active', true)->count();

        return view('admin.firebase-settings.index', compact('isConfigured', 'totalDevices', 'activeDevices'));
    }

    /**
     * Upload Firebase credentials
     */
    public function uploadCredentials(Request $request)
    {
        try {
            $request->validate([
                'credentials_file' => 'required|file|mimes:json|max:100',
            ], [
                'credentials_file.required' => 'Please upload your Firebase service account JSON file',
                'credentials_file.mimes' => 'File must be a JSON file',
                'credentials_file.max' => 'File size must not exceed 100KB',
            ]);

            $file = $request->file('credentials_file');
            $content = file_get_contents($file->getRealPath());

            // Validate credentials
            $validation = FirebaseService::validateCredentials($content);

            if (!$validation['valid']) {
                return back()->with('error', 'Invalid Firebase credentials: ' . $validation['error']);
            }

            // Create private storage directory if it doesn't exist
            if (!Storage::disk('local')->exists('private')) {
                Storage::disk('local')->makeDirectory('private');
            }

            // Store credentials securely
            Storage::disk('local')->put('private/firebase-credentials.json', $content);

            Log::info('Firebase credentials uploaded', [
                'project_id' => $validation['project_id'],
                'client_email' => $validation['client_email'],
            ]);

            return back()->with('success', '✅ Firebase Connected Successfully! Project: ' . $validation['project_id']);
        } catch (\Exception $e) {
            Log::error('Firebase credentials upload failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'Failed to upload credentials: ' . $e->getMessage());
        }
    }

    /**
     * Test Firebase connection
     */
    public function testConnection()
    {
        try {
            if (!FirebaseService::isConfigured()) {
                return response()->json(['success' => false, 'message' => 'Firebase not configured']);
            }

            $firebase = new FirebaseService();
            $token = $firebase->getAccessToken();

            if (!$token) {
                return response()->json(['success' => false, 'message' => 'Failed to get access token']);
            }

            return response()->json([
                'success' => true,
                'message' => '✅ Firebase connection successful!',
                'project_id' => $firebase->getProjectId(),
            ]);
        } catch (\Exception $e) {
            Log::error('Firebase test connection failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Send test notification
     */
    public function sendTestNotification(Request $request)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:100',
                'body' => 'required|string|max:200',
            ]);

            if (!FirebaseService::isConfigured()) {
                return response()->json(['success' => false, 'message' => 'Firebase not configured']);
            }

            // Get a test device
            $device = PushSubscription::where('is_active', true)->first();

            if (!$device) {
                return response()->json(['success' => false, 'message' => 'No active devices found. Please register a device first.']);
            }

            $firebase = new FirebaseService();
            $result = $firebase->sendNotification(
                $device->fcm_token,
                $request->title,
                $request->body,
                ['type' => 'test']
            );

            if ($result) {
                return response()->json(['success' => true, 'message' => '✅ Test notification sent successfully!']);
            } else {
                return response()->json(['success' => false, 'message' => 'Failed to send test notification']);
            }
        } catch (\Exception $e) {
            Log::error('Test notification failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Remove Firebase credentials
     */
    public function removeCredentials()
    {
        try {
            Storage::disk('local')->delete('private/firebase-credentials.json');
            Log::info('Firebase credentials removed');
            return back()->with('success', 'Firebase credentials removed');
        } catch (\Exception $e) {
            Log::error('Failed to remove Firebase credentials', ['error' => $e->getMessage()]);
            return back()->with('error', 'Failed to remove credentials');
        }
    }
}
