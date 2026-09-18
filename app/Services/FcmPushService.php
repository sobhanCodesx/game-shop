<?php

namespace App\Services;

use App\Models\MobileDevice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class FcmPushService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{accepted:bool,disable:bool,error:?string}
     */
    public function send(MobileDevice $device, array $payload): array
    {
        $credentials = $this->credentials();
        $projectId = (string) (config('services.firebase_messaging.project_id') ?: ($credentials['project_id'] ?? ''));

        if ($projectId === '') {
            throw new RuntimeException('Firebase project_id is missing from the service account configuration.');
        }

        $response = $this->sendRequest($projectId, $device, $payload, $this->accessToken($credentials));

        if ($response->status() === 401) {
            Cache::forget($this->accessTokenCacheKey($credentials));
            $response = $this->sendRequest($projectId, $device, $payload, $this->accessToken($credentials));
        }

        if ($response->successful()) {
            return ['accepted' => true, 'disable' => false, 'error' => null];
        }

        $details = $response->json('error.details', []);
        $errorCode = collect(is_array($details) ? $details : [])
            ->pluck('errorCode')
            ->filter()
            ->first();
        $status = (string) $response->json('error.status', '');
        $message = (string) $response->json('error.message', 'Unknown FCM error');

        $disable = in_array($errorCode, ['UNREGISTERED', 'SENDER_ID_MISMATCH'], true)
            || ($response->status() === 404 && $status === 'NOT_FOUND');

        return [
            'accepted' => false,
            'disable' => $disable,
            'error' => trim(($errorCode ? $errorCode.': ' : '').$message),
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function accessToken(array $credentials): string
    {
        $cacheKey = $this->accessTokenCacheKey($credentials);
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $clientEmail = (string) ($credentials['client_email'] ?? '');
        $privateKey = (string) ($credentials['private_key'] ?? '');
        $tokenUri = (string) ($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token');

        if ($clientEmail === '' || $privateKey === '') {
            throw new RuntimeException('Firebase service account client_email/private_key is missing.');
        }

        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $tokenUri,
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));
        $unsigned = $header.'.'.$claims;

        if (! openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign Firebase service account assertion.');
        }

        $assertion = $unsigned.'.'.$this->base64UrlEncode($signature);

        $response = Http::asForm()
            ->acceptJson()
            ->connectTimeout((int) config('services.firebase_messaging.connect_timeout', 3))
            ->timeout((int) config('services.firebase_messaging.timeout', 10))
            ->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ])
            ->throw();

        $accessToken = (string) $response->json('access_token', '');
        $expiresIn = max(60, (int) $response->json('expires_in', 3600) - 300);

        if ($accessToken === '') {
            throw new RuntimeException('Firebase OAuth token endpoint returned no access_token.');
        }

        Cache::put($cacheKey, $accessToken, now()->addSeconds($expiresIn));

        return $accessToken;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function accessTokenCacheKey(array $credentials): string
    {
        return 'firebase_messaging_access_token:'.sha1((string) ($credentials['client_email'] ?? 'unknown'));
    }

    /**
     * @return array<string, mixed>
     */
    private function credentials(): array
    {
        $path = (string) config('services.firebase_messaging.credentials');

        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('GOOGLE_APPLICATION_CREDENTIALS must point to a readable Firebase service-account JSON file.');
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('Unable to read Firebase service-account JSON file.');
        }

        $credentials = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($credentials)) {
            throw new RuntimeException('Firebase service-account JSON is invalid.');
        }

        return $credentials;
    }

    private function sendRequest(string $projectId, MobileDevice $device, array $payload, string $accessToken)
    {
        $endpoint = 'https://fcm.googleapis.com/v1/projects/'.rawurlencode($projectId).'/messages:send';

        return Http::withToken($accessToken)
            ->acceptJson()
            ->asJson()
            ->connectTimeout((int) config('services.firebase_messaging.connect_timeout', 3))
            ->timeout((int) config('services.firebase_messaging.timeout', 10))
            ->post($endpoint, [
                'message' => [
                    'token' => $device->push_token,
                    'notification' => [
                        'title' => (string) ($payload['title'] ?? 'PlayNexus'),
                        'body' => (string) ($payload['message'] ?? ''),
                    ],
                    'data' => [
                        'url' => (string) ($payload['url'] ?? '/'),
                        'notification' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'channel_id' => 'default',
                            'sound' => 'default',
                        ],
                    ],
                ],
            ]);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
