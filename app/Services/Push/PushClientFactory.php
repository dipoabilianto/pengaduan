<?php

namespace App\Services\Push;

use App\Services\PushSettingsService;

class PushClientFactory
{
    public function __construct(private readonly PushSettingsService $settings)
    {
    }

    /**
     * Returns null when FCM is not configured — callers must treat this as
     * "skip sending, degrade silently", never as an error.
     */
    public function make(): ?PushClientInterface
    {
        if (! $this->settings->isConfigured()) {
            return null;
        }

        return new FirebaseCloudMessagingClient($this->settings->projectId(), $this->settings->serviceAccount());
    }
}
