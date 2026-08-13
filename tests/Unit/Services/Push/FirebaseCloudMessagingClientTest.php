<?php

namespace Tests\Unit\Services\Push;

use App\Services\Push\FirebaseCloudMessagingClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class FirebaseCloudMessagingClientTest extends TestCase
{
    private function serviceAccount(): array
    {
        $keyResource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($keyResource, $privateKey);

        return [
            'client_email' => 'fcm@sidumas-tubaba.iam.gserviceaccount.com',
            'private_key' => $privateKey,
        ];
    }

    public function test_sends_a_push_notification_successfully(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-access-token'], 200),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/x/messages/1'], 200),
        ]);

        $client = new FirebaseCloudMessagingClient('sidumas-tubaba', $this->serviceAccount());
        $client->send('device-token-123', 'Judul', 'Isi pesan');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'oauth2.googleapis.com/token')
                && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer';
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'fcm.googleapis.com')
                && $request['message']['token'] === 'device-token-123'
                && $request['message']['notification']['title'] === 'Judul';
        });
    }

    public function test_throws_when_fcm_responds_with_an_error(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-access-token'], 200),
            'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'NOT_FOUND']], 404),
        ]);

        $client = new FirebaseCloudMessagingClient('sidumas-tubaba', $this->serviceAccount());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/FCM API error: 404/');

        $client->send('stale-token', 'Judul', 'Isi pesan');
    }
}
