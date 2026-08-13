<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;

class InstagramSettingsService
{
    private const KEY_BUSINESS_ACCOUNT_ID = 'ig_business_account_id';

    private const KEY_ACCESS_TOKEN = 'ig_access_token';

    private const KEY_VERIFY_TOKEN = 'ig_webhook_verify_token';

    public function businessAccountId(): ?string
    {
        return Setting::get(self::KEY_BUSINESS_ACCOUNT_ID);
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
        return filled($this->businessAccountId()) && filled($this->accessToken());
    }

    public function maskedAccessToken(): ?string
    {
        $token = $this->accessToken();

        return $token ? str_repeat('•', 8).substr($token, -4) : null;
    }

    public function save(array $data, User $actor): void
    {
        Setting::put(self::KEY_BUSINESS_ACCOUNT_ID, $data['business_account_id'], $actor->id);

        if (filled($data['access_token'])) {
            Setting::put(self::KEY_ACCESS_TOKEN, $data['access_token'], $actor->id);
        }

        Setting::put(self::KEY_VERIFY_TOKEN, $data['webhook_verify_token'], $actor->id);
    }
}
