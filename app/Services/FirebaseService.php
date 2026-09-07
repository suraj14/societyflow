<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class FirebaseService
{
    private $projectId;
    private $clientEmail;
    private $privateKey;
    private $tokenCacheKey = 'firebase_access_token';
    private $tokenExpiryKey = 'firebase_token_expiry';

    public function __construct()
    {
        $this->loadCredentials();
    }

    /**
     * Load Firebase credentials from storage
     */
    private function loadCredentials(): void
    {
        try {
            $credentialsPath = storage_path('app/private/firebase-credentials.json');
            
            if (!file_exists($credentialsPath)) {
                Log::warning('Firebase credentials file not found');
                return;
            }

            $credentials = json_decode(file_get_contents($credentialsPath), true);
            
            if (!$credentials) {
                Log::error('Invalid Firebase credentials JSON');
                return;
            }

            $this->projectId = $credentials['project_id'] ?? null;
            $this->clientEmail = $credentials['client_email'] ?? null;
            $this->privateKey = $credentials['private_key'] ?? null;
        } catch (Exception $e) {
            Log::error('Failed to load Firebase credentials', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Validate Firebase credentials
     */
    public static function validateCredentials(string $jsonContent): array
    {
        try {
            $credentials = json_decode($jsonContent, true);

            if (!$credentials) {
                return ['valid' => false, 'error' => 'Invalid JSON format'];
            }

            $required = ['project_id', 'client_email', 'private_key', 'type'];
            foreach ($required as $field) {
                if (empty($credentials[$field])) {
                    return ['valid' => false, 'error' => "Missing required field: $field"];
                }
            }

            if ($credentials['type'] !== 'service_account') {
                return ['valid' => false, 'error' => 'Invalid service account type'];
            }

            return [
                'valid' => true,
                'project_id' => $credentials['project_id'],
                'client_email' => $credentials['client_email']
            ];
        } catch (Exception $e) {
            return ['valid' => false, 'error' => 'Failed to parse credentials: ' . $e->getMessage()];
        }
    }

    /**
     * Get or refresh OAuth access token
     */
    public function getAccessToken(): ?string
    {
        if (!$this->projectId || !$this->clientEmail || !$this->privateKey) {
            Log::error('Firebase credentials not loaded');
            return null;
        }

        // Check if token exists and is still valid
        $token = Cache::get($this->tokenCacheKey);
        $expiry = Cache::get($this->tokenExpiryKey);

        if ($token && $expiry && time() < $expiry) {
            return $token;
        }

        // Generate new token
        return $this->generateAccessToken();
    }

    /**
     * Generate new OAuth access token
     */
    private function generateAccessToken(): ?string
    {
        try {
            $now = time();
            $expiry = $now + 3600; // 1 hour

            $payload = [
                'iss' => $this->clientEmail,
                'scope' => 'https://www.googleapis.com/auth/cloud-platform',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => $expiry,
                'iat' => $now,
            ];

            $token = $this->createJWT($payload);

            if (!$token) {
                return null;
            }

            // Exchange JWT for access token
            $response = $this->requestAccessToken($token);

            if (!$response || !isset($response['access_token'])) {
                Log::error('Failed to get access token from Google');
                return null;
            }

            $accessToken = $response['access_token'];
            $tokenExpiry = $response['expires_in'] ?? 3600;

            // Cache token
            Cache::put($this->tokenCacheKey, $accessToken, $tokenExpiry - 300); // Refresh 5 min before expiry
            Cache::put($this->tokenExpiryKey, $now + $tokenExpiry, $tokenExpiry);

            return $accessToken;
        } catch (Exception $e) {
            Log::error('Failed to generate access token', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Create JWT token
     */
    private function createJWT(array $payload): ?string
    {
        try {
            $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
            $payload = json_encode($payload);

            $headerEncoded = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
            $payloadEncoded = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');

            $signatureInput = "$headerEncoded.$payloadEncoded";

            $signature = '';
            openssl_sign($signatureInput, $signature, $this->privateKey, 'sha256');

            $signatureEncoded = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

            return "$signatureInput.$signatureEncoded";
        } catch (Exception $e) {
            Log::error('Failed to create JWT', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Request access token from Google
     */
    private function requestAccessToken(string $jwt): ?array
    {
        try {
            $ch = curl_init('https://oauth2.googleapis.com/token');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]),
                CURLOPT_TIMEOUT => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                Log::error('Google OAuth request failed', ['http_code' => $httpCode]);
                return null;
            }

            return json_decode($response, true);
        } catch (Exception $e) {
            Log::error('Failed to request access token', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Send push notification via Firebase HTTP v1 API
     */
    public function sendNotification(string $fcmToken, string $title, string $body, array $data = []): bool
    {
        try {
            $accessToken = $this->getAccessToken();

            if (!$accessToken) {
                Log::error('Failed to get Firebase access token');
                return false;
            }

            $message = [
                'token' => $fcmToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
            ];

            if (!empty($data)) {
                $message['data'] = $data;
            }

            $payload = [
                'message' => $message,
            ];

            $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_TIMEOUT => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                Log::error('Firebase send failed', ['http_code' => $httpCode, 'response' => $response]);
                return false;
            }

            Log::info('Push notification sent', ['token' => substr($fcmToken, 0, 20) . '...']);
            return true;
        } catch (Exception $e) {
            Log::error('Failed to send push notification', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send to multiple tokens
     */
    public function sendToMultiple(array $fcmTokens, string $title, string $body, array $data = []): array
    {
        $results = ['success' => 0, 'failed' => 0];

        foreach ($fcmTokens as $token) {
            if ($this->sendNotification($token, $title, $body, $data)) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Check if Firebase is configured
     */
    public static function isConfigured(): bool
    {
        $credentialsPath = storage_path('app/private/firebase-credentials.json');
        return file_exists($credentialsPath);
    }

    /**
     * Get Firebase project ID
     */
    public function getProjectId(): ?string
    {
        return $this->projectId;
    }
}
