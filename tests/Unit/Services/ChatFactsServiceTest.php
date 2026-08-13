<?php

namespace Tests\Unit\Services;

use App\Services\ChatFactsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatFactsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_the_default_facts_when_nothing_has_been_saved(): void
    {
        $service = app(ChatFactsService::class);

        $this->assertSame($service->defaultFacts(), $service->get());
    }

    public function test_returns_the_saved_facts_once_customized(): void
    {
        $service = app(ChatFactsService::class);

        $service->save('Syarat perekaman KTP-el: KK asli, sudah 17 tahun.');

        $this->assertSame('Syarat perekaman KTP-el: KK asli, sudah 17 tahun.', $service->get());
    }
}
