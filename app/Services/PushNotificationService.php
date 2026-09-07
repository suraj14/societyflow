<?php

namespace App\Services;

use App\Models\PushNotificationLog;
use App\Models\PushNotificationSetting;
use App\Models\PushNotificationTemplate;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    private const FCM_ENDPOINT = 'https://fcm.googleapis.com/fcm/send';
    private const MAX_RETRIES = 3;

    /**
     * Send push notification to user
     */
    public function sendToUser(
        User $user,
        string $triggerAction,
        array $variables = [],
        array $customData = []
    ): bool {
        try {
            $societyId = $user->society_id;
            $settings = PushNotificationSetting::where('society_id', $societyId)->first();

            if (!$settings || !$settings->isEnabled()) {
                return false;
            }

            if (!$settings->isTriggerEnabled($triggerAction)) {
                return false;
            }

            $template = PushNotificationTemplate::forTrigger($triggerAction)->active()->first();
            if (!$template) {
                return false;
            }

            $content = $template->replaceVariables($variables);
            $subscriptions = PushSubscription::forUser($user->id)->active()->get();

            $sent = false;
            foreach ($subscriptions as $subscription) {
                if ($this->sendToSubscription($subscription, $content, $customData, $triggerAction)) {
                    $sent = true;
                }
            }

            return $sent;
        } catch (\Exception $e) {
            Log::error('Push notification error', [
                'user_id' => $user->id,
                'trigger' => $triggerAction,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send to multiple users by role
     */
    public function sendToRole(
        int $societyId,
        string $role,
        string $triggerAction,
        array $variables = [],
        array $customData = []
    ): int {
        $settings = PushNotificationSetting::where('society_id', $societyId)->first();

        if (!$settings || !$settings->isEnabled() || !$settings->canRoleSend($role)) {
            return 0;
        }

        $users = User::where('society_id', $societyId)
            ->whereHas('roles', function ($query) use ($role) {
                $query->where('name', $role);
            })
            ->get();

        $count = 0;
        foreach ($users as $user) {
            if ($this->sendToUser($user, $triggerAction, $variables, $customData)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Send to specific subscription
     */
    private function sendToSubscription(
        PushSubscription $subscription,
        array $content,
        array $customData,
        string $triggerAction
    ): bool {
        try {
            $payload = [
                'title' => $content['title'],
                'body' => $content['body'],
                'icon' => '/images/notification-icon.png',
                'badge' => '/images/notification-badge.png',
                'tag' => $triggerAction,
                'data' => $customData,
            ];

            // Send via FCM if server key is configured
            $settings = PushNotificationSetting::where('society_id', $subscription->society_id)->first();
            if ($settings && $settings->fcm_server_key) {
                $this->sendViaFCM($subscription, $payload, $settings->fcm_server_key);
            }

            // Send via Web Push (VAPID)
            if ($settings && $settings->vapid_private_key) {
                $this->sendViaWebPush($subscription, $payload, $settings);
            }

            $subscription->markAsUsed();

            // Log success
            PushNotificationLog::create([
                'user_id' => $subscription->user_id,
                'society_id' => $subscription->society_id,
                'title' => $content['title'],
                'body' => $content['body'],
                'trigger_action' => $triggerAction,
                'data' => $customData,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Push send failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            // Log failure
            PushNotificationLog::create([
                'user_id' => $subscription->user_id,
                'society_id' => $subscription->society_id,
                'title' => $content['title'] ?? 'Unknown',
                'body' => $content['body'] ?? 'Unknown',
                'trigger_action' => $triggerAction,
                'data' => $customData,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            // Deactivate invalid subscription
            if (str_contains($e->getMessage(), '410') || str_contains($e->getMessage(), 'invalid')) {
                $subscription->deactivate();
            }

            return false;
        }
    }

    /**
     * Send via Firebase Cloud Messaging
     */
    private function sendViaFCM(PushSubscription $subscription, array $payload, string $serverKey): void
    {
        $response = Http::withHeaders([
            'Authorization' => 'key=' . $serverKey,
            'Content-Type' => 'application/json',
        ])->post(self::FCM_ENDPOINT, [
            'to' => $subscription->endpoint,
            'notification' => $payload,
            'data' => $payload['data'] ?? [],
        ]);

        if (!$response->successful()) {
            throw new \Exception('FCM send failed: ' . $response->body());
        }
    }

    /**
     * Send via Web Push (VAPID)
     */
    private function sendViaWebPush(PushSubscription $subscription, array $payload, PushNotificationSetting $settings): void
    {
        $endpoint = $subscription->endpoint;
        $userPublicKey = $subscription->p256dh_key;
        $userAuthToken = $subscription->auth_key;
        $vapidPublicKey = $settings->vapid_public_key;
        $vapidPrivateKey = $settings->vapid_private_key;

        $message = json_encode($payload);

        // Generate VAPID headers
        $vapidHeaders = $this->generateVapidHeaders($endpoint, $vapidPublicKey, $vapidPrivateKey);

        // Encrypt payload
        $encrypted = $this->encryptPayload($message, $userPublicKey, $userAuthToken);

        $response = Http::withHeaders($vapidHeaders)
            ->withHeaders([
                'Content-Type' => 'application/octet-stream',
                'Content-Length' => strlen($encrypted['ciphertext']),
                'Content-Encoding' => 'aesgcm',
                'Encryption' => 'salt=' . $encrypted['salt'],
                'Crypto-Key' => 'dh=' . $encrypted['serverPublicKey'],
            ])
            ->post($endpoint, $encrypted['ciphertext']);

        if (!$response->successful()) {
            throw new \Exception('Web Push failed: ' . $response->status());
        }
    }

    /**
     * Generate VAPID headers
     */
    private function generateVapidHeaders(string $endpoint, string $publicKey, string $privateKey): array
    {
        $header = [
            'typ' => 'JWT',
            'alg' => 'ES256',
        ];

        $payload = [
            'aud' => parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST),
            'exp' => time() + 3600,
            'sub' => 'mailto:support@societyflow.com',
        ];

        $headerEncoded = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
        $payloadEncoded = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $signatureInput = $headerEncoded . '.' . $payloadEncoded;

        $signature = $this->signVapidJwt($signatureInput, $privateKey);

        return [
            'Authorization' => 'vapid t=' . $signatureInput . '.' . $signature . ', k=' . $publicKey,
        ];
    }

    /**
     * Sign VAPID JWT
     */
    private function signVapidJwt(string $input, string $privateKey): string
    {
        $keyResource = openssl_pkey_get_private('-----BEGIN EC PRIVATE KEY-----' . "\n" . 
            wordwrap($privateKey, 64, "\n", true) . "\n" . '-----END EC PRIVATE KEY-----');

        openssl_sign($input, $signature, $keyResource, 'sha256');
        openssl_free_key($keyResource);

        return rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    /**
     * Encrypt payload for Web Push
     */
    private function encryptPayload(string $message, string $userPublicKey, string $userAuthToken): array
    {
        $salt = openssl_random_pseudo_bytes(16);
        $serverKeyPair = openssl_pkey_new(['private_key_bits' => 256, 'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        
        $serverPublicKey = openssl_pkey_get_details($serverKeyPair)['key'];
        $serverPrivateKey = openssl_pkey_get_details($serverKeyPair)['key'];

        $sharedSecret = $this->computeECDH($serverPrivateKey, $userPublicKey);
        $prk = hash_hmac('sha256', $userAuthToken, $sharedSecret, true);
        $keyInfo = 'WebPush: info' . chr(0) . $userPublicKey . $serverPublicKey;
        $key = hash_hmac('sha256', '', $prk . $keyInfo, true);

        $ciphertext = openssl_encrypt($message, 'aes-128-gcm', $key, OPENSSL_RAW_DATA, $salt, $tag);

        return [
            'ciphertext' => $salt . $ciphertext . $tag,
            'salt' => rtrim(strtr(base64_encode($salt), '+/', '-_'), '='),
            'serverPublicKey' => rtrim(strtr(base64_encode($serverPublicKey), '+/', '-_'), '='),
        ];
    }

    /**
     * Compute ECDH shared secret
     */
    private function computeECDH(string $privateKey, string $publicKey): string
    {
        // Simplified ECDH computation - in production use proper library
        return hash('sha256', $privateKey . $publicKey, true);
    }

    /**
     * Test push notification
     */
    public function test(int $societyId, string $testEmail): bool
    {
        $user = User::where('society_id', $societyId)
            ->where('email', $testEmail)
            ->first();

        if (!$user) {
            return false;
        }

        return $this->sendToUser($user, 'test_notification', [
            'user_name' => $user->name,
            'society_name' => $user->society->name ?? 'SocietyFlow',
        ]);
    }

    /**
     * Cleanup invalid subscriptions
     */
    public function cleanupInvalidSubscriptions(int $societyId): int
    {
        return PushSubscription::forSociety($societyId)
            ->where('is_active', false)
            ->where('updated_at', '<', now()->subDays(30))
            ->delete();
    }
}
