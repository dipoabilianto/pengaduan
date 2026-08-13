<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;

class PushSettingsService
{
    private const KEY_PROJECT_ID = 'fcm_project_id';

    private const KEY_SERVICE_ACCOUNT_JSON = 'fcm_service_account_json';

    public function projectId(): ?string
    {
        return Setting::get(self::KEY_PROJECT_ID);
    }

    public function serviceAccountJson(): ?string
    {
        return Setting::get(self::KEY_SERVICE_ACCOUNT_JSON);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function serviceAccount(): ?array
    {
        $json = $this->serviceAccountJson();

        if (blank($json)) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function isConfigured(): bool
    {
        $account = $this->serviceAccount();

        return filled($this->projectId()) && $account !== null
            && filled($account['client_email'] ?? null) && filled($account['private_key'] ?? null);
    }

    /**
     * @param  array{project_id:string, service_account_json:?string}  $data
     */
    public function save(array $data, User $actor): void
    {
        Setting::put(self::KEY_PROJECT_ID, $data['project_id'], $actor->id);

        if (filled($data['service_account_json'])) {
            Setting::put(self::KEY_SERVICE_ACCOUNT_JSON, $data['service_account_json'], $actor->id);
        }
    }
}
