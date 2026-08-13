<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;

class WhatsAppSettingsService
{
    private const KEY_PHONE_NUMBER_ID = 'wa_phone_number_id';

    private const KEY_ACCESS_TOKEN = 'wa_access_token';

    private const KEY_VERIFY_TOKEN = 'wa_webhook_verify_token';

    public function phoneNumberId(): ?string
    {
        return Setting::get(self::KEY_PHONE_NUMBER_ID);
    }

    public function accessToken(): ?string
    {
        return Setting::get(self::KEY_ACCESS_TOKEN);
    }

    public function webhookVerifyToken(): ?string
    {
        return Setting::get(self::KEY_VERIFY_TOKEN);
    }

    public function isConfigured(): bool
    {
        return filled($this->phoneNumberId()) && filled($this->accessToken());
    }

    /**
     * Last 4 characters of the stored token, for display only — never expose the full value.
     */
    public function maskedAccessToken(): ?string
    {
        $token = $this->accessToken();

        return $token ? str_repeat('•', 8).substr($token, -4) : null;
    }

    /**
     * @param  array{phone_number_id:string, access_token:?string, webhook_verify_token:string}  $data
     */
    public function save(array $data, User $actor): void
    {
        Setting::put(self::KEY_PHONE_NUMBER_ID, $data['phone_number_id'], $actor->id);

        if (filled($data['access_token'])) {
            Setting::put(self::KEY_ACCESS_TOKEN, $data['access_token'], $actor->id);
        }

        Setting::put(self::KEY_VERIFY_TOKEN, $data['webhook_verify_token'], $actor->id);
    }
}
