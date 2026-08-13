<?php

namespace Tests\Feature\Services;

use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function baseData(array $overrides = []): array
    {
        return array_merge([
            'type' => 'pengaduan',
            'category' => 'Pelayanan Administrasi Kependudukan',
            'name' => 'Warga',
            'phone' => '081234567890',
            'what' => 'Uraian laporan.',
        ], $overrides);
    }

    public function test_channel_defaults_to_web_when_not_provided(): void
    {
        $report = app(ReportService::class)->submit($this->baseData());

        $this->assertSame('web', $report->channel);
    }

    public function test_channel_respects_the_given_value(): void
    {
        $report = app(ReportService::class)->submit($this->baseData(['channel' => 'whatsapp']));

        $this->assertSame('whatsapp', $report->channel);
    }
}
