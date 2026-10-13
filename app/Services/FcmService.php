<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    /**
     * Send an FCM notification using the HTTP v1 API.
     */
    public function sendPushNotification(string $fcmToken, string $title, string $body, array $data = [])
    {
        $credentialsFilePath = storage_path('app/firebase-service-account.json');
        
        if (!file_exists($credentialsFilePath)) {
            Log::warning('FCM Service Account file not found at ' . $credentialsFilePath);
            return false;
        }

        try {
            $client = new GoogleClient();
            $client->setAuthConfig($credentialsFilePath);
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            
            $client->fetchAccessTokenWithAssertion();
            $accessToken = $client->getAccessToken();

            if (!isset($accessToken['access_token'])) {
                Log::error('Failed to obtain FCM access token');
                return false;
            }

            // Parse project ID from the JSON file
            $jsonContent = file_get_contents($credentialsFilePath);
            $serviceAccount = json_decode($jsonContent, true);
            $projectId = $serviceAccount['project_id'] ?? null;

            if (!$projectId) {
                Log::error('Project ID not found in FCM service account JSON');
                return false;
            }

            $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

            $message = [
                'message' => [
                    'token' => $fcmToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => array_merge($data, [
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ]),
                ]
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken['access_token'],
                'Content-Type' => 'application/json',
            ])->post($url, $message);

            if ($response->successful()) {
                Log::info('FCM notification sent successfully', ['token' => $fcmToken]);
                return true;
            } else {
                Log::error('FCM notification failed: ' . $response->body());
                return false;
            }
        } catch (\Exception $e) {
            Log::error('FCM error: ' . $e->getMessage());
            return false;
        }
    }
}
