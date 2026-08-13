<?php

namespace Tests\Feature\Public;

use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusCheckReplyTest extends TestCase
{
    use RefreshDatabase;

    private function report(array $attributes = []): Report
    {
        return Report::create(array_merge([
            'ticket_no' => 'TCK-'.fake()->unique()->numerify('####'),
            'type' => 'pengaduan',
            'category' => 'Pelayanan Administrasi Kependudukan',
            'channel' => 'web',
            'status' => 'dalam_penanganan',
            'what' => 'Contoh kronologi kejadian untuk pengujian.',
        ], $attributes));
    }

    public function test_public_status_page_shows_the_reply_when_present(): void
    {
        $report = $this->report([
            'public_reply' => 'Sudah kami tindak lanjuti ke bidang terkait.',
            'public_reply_at' => now(),
        ]);

        $response = $this->get(route('public.status.result', ['query' => $report->ticket_no]));

        $response->assertOk();
        $response->assertSee('Balasan Petugas');
        $response->assertSee('Sudah kami tindak lanjuti ke bidang terkait.');
    }

    public function test_public_status_page_hides_the_reply_section_when_absent(): void
    {
        $report = $this->report();

        $response = $this->get(route('public.status.result', ['query' => $report->ticket_no]));

        $response->assertOk();
        $response->assertDontSee('Balasan Petugas');
    }
}
