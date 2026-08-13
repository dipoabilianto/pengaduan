<?php

namespace App\Services\WhatsApp;

use App\Services\WhatsAppSettingsService;

class WhatsAppClientFactory
{
    public function __construct(private readonly WhatsAppSettingsService $settings)
    {
    }

    /**
     * Returns null when WhatsApp is not configured — callers must treat this as
     * "skip sending, degrade silently", never as an error.
     */
    public function make(): ?WhatsAppClientInterface
    {
        if (! $this->settings->isConfigured()) {
            return null;
        }

        return new CloudApiClient($this->settings->phoneNumberId(), $this->settings->accessToken());
    }
}
