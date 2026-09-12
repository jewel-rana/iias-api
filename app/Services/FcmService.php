<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    public function isConfigured(): bool
    {
        return $this->projectId() !== '' && $this->clientEmail() !== '' && $this->privateKey() !== '';
    }

    /**
     * @param  array<string, string>  $data
     * @return bool|null true sent, false invalid token, null skipped/failed
     */
    public function sendToToken(string $token, string $title, string $body, array $data = []): ?bool
    {
        if (! $this->isConfigured() || $token === '') {
            return null;
        }

        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => array_map('strval', $data),
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => 'iias_push',
                        'sound' => 'default',
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                        ],
                    ],
                ],
            ],
        ];

        try {
            $res = Http::withToken($this->accessToken())
                ->acceptJson()
                ->timeout(15)
                ->post(
                    'https://fcm.googleapis.com/v1/projects/'.$this->projectId().'/messages:send',
                    $payload,
                );

            if ($res->successful()) {
                return true;
            }

            $error = $res->json('error.status') ?? $res->body();
            if (in_array($error, ['NOT_FOUND', 'UNREGISTERED'], true)
                || str_contains((string) $res->body(), 'UNREGISTERED')) {
                return false;
            }

            Log::warning('FCM send failed', [
                'status' => $res->status(),
                'body' => $res->json() ?? $res->body(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('FCM send error', ['message' => $e->getMessage()]);
        }

        return null;
    }

    private function accessToken(): string
    {
        return Cache::remember('fcm_access_token', 50 * 60, function () {
            $now = time();
            $header = $this->b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
            $claims = $this->b64url(json_encode([
                'iss' => $this->clientEmail(),
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], JSON_THROW_ON_ERROR));

            $unsigned = $header.'.'.$claims;
            $ok = openssl_sign($unsigned, $signature, $this->privateKey(), OPENSSL_ALGO_SHA256);
            if (! $ok) {
                throw new \RuntimeException('Unable to sign Firebase JWT.');
            }

            $jwt = $unsigned.'.'.$this->b64url($signature);
            $res = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (! $res->successful() || ! $res->json('access_token')) {
                throw new \RuntimeException('Unable to obtain Firebase access token.');
            }

            return (string) $res->json('access_token');
        });
    }

    private function credentials(): array
    {
        static $cached;

        if (is_array($cached)) {
            return $cached;
        }

        $path = (string) config('services.firebase.credentials');
        if ($path !== '') {
            $full = str_starts_with($path, '/') ? $path : base_path($path);
            if (is_file($full)) {
                $cached = json_decode((string) file_get_contents($full), true) ?: [];

                return $cached;
            }
        }

        $cached = [
            'project_id' => (string) config('services.firebase.project_id'),
            'client_email' => (string) config('services.firebase.client_email'),
            'private_key' => (string) config('services.firebase.private_key'),
        ];

        return $cached;
    }

    private function projectId(): string
    {
        return (string) ($this->credentials()['project_id'] ?? '');
    }

    private function clientEmail(): string
    {
        return (string) ($this->credentials()['client_email'] ?? '');
    }

    private function privateKey(): string
    {
        $key = (string) ($this->credentials()['private_key'] ?? '');

        return str_replace('\\n', "\n", $key);
    }

    private function b64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
