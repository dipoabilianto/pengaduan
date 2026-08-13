<?php

namespace App\Services\WhatsApp;

interface WhatsAppClientInterface
{
    /**
     * @throws \RuntimeException on API/network/parse failure — caller decides how to degrade.
     */
    public function sendTextMessage(string $toPhoneE164, string $message): void;
}
