<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PushSubscriptionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Subscribe to push notifications
     */
    public function subscribe(Request $request)
    {
        try {
            $validated = $request->validate([
                'endpoint' => 'required|string',
                'auth_key' => 'required|string',
                'p256dh_key' => 'required|string',
                'device_type' => 'nullable|string|in:web,mobile',
                'browser' => 'nullable|string',
            ]);

            $user = auth()->user();
            $societyId = $user->society_id;

            // Check if subscription already exists
            $subscription = PushSubscription::where('user_id', $user->id)
                ->where('endpoint', $validated['endpoint'])
                ->first();

            if ($subscription) {
                $subscription->update([
                    'is_active' => true,
                    'device_type' => $validated['device_type'] ?? 'web',
                    'browser' => $validated['browser'],
                ]);
            } else {
                $subscription = PushSubscription::create([
                    'user_id' => $user->id,
                    'society_id' => $societyId,
                    'endpoint' => $validated['endpoint'],
                    'auth_key' => $validated['auth_key'],
                    'p256dh_key' => $validated['p256dh_key'],
                    'device_type' => $validated['device_type'] ?? 'web',
                    'browser' => $validated['browser'],
                    'is_active' => true,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Push subscription saved successfully',
                'subscription_id' => $subscription->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Push subscription error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save push subscription',
            ], 500);
        }
    }

    /**
     * Unsubscribe from push notifications
     */
    public function unsubscribe(Request $request)
    {
        try {
            $validated = $request->validate([
                'endpoint' => 'required|string',
            ]);

            $user = auth()->user();

            PushSubscription::where('user_id', $user->id)
                ->where('endpoint', $validated['endpoint'])
                ->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Unsubscribed from push notifications',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unsubscribe',
            ], 500);
        }
    }

    /**
     * Get VAPID public key
     */
    public function getVapidPublicKey()
    {
        try {
            $user = auth()->user();
            $societyId = $user->society_id;

            $setting = \App\Models\PushNotificationSetting::where('society_id', $societyId)->first();

            if (!$setting || !$setting->vapid_public_key) {
                return response()->json([
                    'success' => false,
                    'message' => 'Push notifications not configured',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'vapid_public_key' => $setting->vapid_public_key,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving VAPID key',
            ], 500);
        }
    }
}
