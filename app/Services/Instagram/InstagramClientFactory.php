<?php

namespace App\Services\Instagram;

use App\Services\InstagramSettingsService;

class InstagramClientFactory
{
    public function __construct(private readonly InstagramSettingsService $settings)
    {
    }

    /**
     * Returns null when Instagram is not configured — callers must treat this as
     * "skip sending, degrade" (webhook fetches) or surface a clear error (admin replies).
     */
    public function make(): ?InstagramClientInterface
    {
        if (! $this->settings->isConfigured()) {
            return null;
        }

        return new GraphApiClient($this->settings->businessAccountId(), $this->settings->accessToken());
    }
}
