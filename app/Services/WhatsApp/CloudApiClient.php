<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * WhatsApp Cloud API resmi (Meta for Developers) — bukan mitra gateway tidak resmi,
 * sesuai Bab 6.2 PDR ("WhatsApp Business API resmi/mitra gateway berizin").
 */
class CloudApiClient implements WhatsAppClientInterface
{
    public function __construct(
        private readonly string $phoneNumberId,
        private readonly string $accessToken,
    ) {
    }

    public function sendTextMessage(string $toPhoneE164, string $message): void
    {
        $response = Http::withToken($this->accessToken)
            ->timeout(15)
            ->post("https://graph.facebook.com/v20.0/{$this->phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $toPhoneE164,
                'type' => 'text',
                'text' => ['body' => $message],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('WhatsApp Cloud API error: '.$response->status().' '.$response->body());
        }
    }
}
