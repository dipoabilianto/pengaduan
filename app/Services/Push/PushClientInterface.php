<?php

namespace App\Services\Push;

interface PushClientInterface
{
    /**
     * @param  array<string,string>  $data
     *
     * @throws \RuntimeException on API/network/parse failure — caller decides how to degrade.
     */
    public function send(string $deviceToken, string $title, string $body, array $data = []): void;
}
