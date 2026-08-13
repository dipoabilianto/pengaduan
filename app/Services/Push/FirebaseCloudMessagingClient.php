<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * FCM HTTP v1 API — bukan legacy server-key API (sudah dihentikan Google). Otentikasi
 * pakai OAuth2 JWT-bearer yang ditandatangani dari service account, tanpa dependency
 * Composer tambahan (openssl_sign native), konsisten dengan gaya integrasi lain di app
 * ini yang hand-roll HTTP daripada memakai SDK berat.
 */
class FirebaseCloudMessagingClient implements PushClientInterface
{
    /**
     * @param  array<string,mixed>  $serviceAccount
     */
    public function __construct(
        private readonly string $projectId,
        private readonly array $serviceAccount,
    ) {
    }

    public function send(string $deviceToken, string $title, string $body, array $data = []): void
    {
        $response = Http::withToken($this->accessToken())
            ->timeout(15)
            ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => $data,
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('FCM API error: '.$response->status().' '.$response->body());
        }
    }

    /**
     * Cached just under the token's 3600s lifetime so we don't re-sign a JWT on every push.
     */
    private function accessToken(): string
    {
        return Cache::remember('fcm_access_token_'.md5($this->serviceAccount['client_email'] ?? ''), 3300, function () {
            $now = time();

            $header = $this->base64UrlEncode(['alg' => 'RS256', 'typ' => 'JWT']);
            $claims = $this->base64UrlEncode([
                'iss' => $this->serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]);

            openssl_sign("{$header}.{$claims}", $signature, $this->serviceAccount['private_key'], 'SHA256');
            $jwt = "{$header}.{$claims}.".rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

            $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->failed()) {
                throw new RuntimeException('FCM OAuth error: '.$response->status().' '.$response->body());
            }

            return $response->json('access_token');
        });
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function base64UrlEncode(array $payload): string
    {
        return rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
    }
}
