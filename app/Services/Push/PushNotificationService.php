<?php

namespace App\Services\Push;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Titik tunggal pengiriman push keluar — sama filosofi WhatsAppNotificationService:
 * kegagalan kirim TIDAK PERNAH melempar exception ke pemanggil, cukup dicatat log.
 */
class PushNotificationService
{
    public function __construct(private readonly PushClientFactory $factory)
    {
    }

    /**
     * @param  array<string,string>  $data
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        $client = $this->factory->make();

        if (! $client) {
            return;
        }

        foreach ($user->deviceTokens as $deviceToken) {
            try {
                $client->send($deviceToken->token, $title, $body, $data);
            } catch (Throwable $e) {
                Log::warning('Push send failed.', [
                    'user_id' => $user->id,
                    'device_token_id' => $deviceToken->id,
                    'error' => $e->getMessage(),
                ]);

                if ($this->isUnregistered($e)) {
                    $deviceToken->delete();
                }
            }
        }
    }

    private function isUnregistered(Throwable $e): bool
    {
        return str_contains($e->getMessage(), 'FCM API error: 404')
            || str_contains($e->getMessage(), 'UNREGISTERED');
    }
}
