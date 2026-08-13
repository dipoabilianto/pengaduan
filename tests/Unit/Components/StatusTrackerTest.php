<?php

namespace Tests\Unit\Components;

use App\Models\Report;
use App\Models\ReportStatusLog;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Collection;
use Tests\TestCase;

class StatusTrackerTest extends TestCase
{
    private function reportWithStatus(string $status, ?Collection $statusLogs = null): Report
    {
        $report = new Report(['status' => $status]);
        $report->setRelation('statusLogs', $statusLogs ?? new Collection());

        return $report;
    }

    private function render(Report $report): string
    {
        return Blade::render('<x-status-tracker :report="$report" />', ['report' => $report]);
    }

    public function test_selesai_step_is_marked_done_not_sedang_berjalan(): void
    {
        $html = $this->render($this->reportWithStatus('selesai'));

        $this->assertStringNotContainsString('Sedang berjalan', $html);
    }

    public function test_current_in_progress_step_still_shows_sedang_berjalan(): void
    {
        // Internal status "terverifikasi_admin" maps to the public group "diproses".
        $html = $this->render($this->reportWithStatus('terverifikasi_admin'));

        $this->assertStringContainsString('Sedang berjalan', $html);
    }

    public function test_ditolak_shows_rejected_caption_not_sedang_berjalan(): void
    {
        $html = $this->render($this->reportWithStatus('ditolak'));

        $this->assertStringNotContainsString('Sedang berjalan', $html);
        $this->assertStringContainsString('tidak dilanjutkan', $html);
    }

    /**
     * Bab 5.3/5.4 PDR: "Tidak Valid" can reject a report straight from "Baru Masuk" —
     * the tracker must not claim "Diproses" happened when it never did.
     */
    public function test_ditolak_straight_from_baru_masuk_does_not_show_diproses_as_done(): void
    {
        $report = $this->reportWithStatus('ditolak', new Collection([
            new ReportStatusLog(['old_status' => 'baru_masuk', 'new_status' => 'ditolak']),
        ]));

        $html = $this->render($report);

        $this->assertStringContainsString('Diterima', $html);
        $this->assertStringNotContainsString('Diproses', $html);
        $this->assertStringContainsString('Ditolak', $html);
    }

    public function test_ditolak_after_reaching_diproses_shows_diproses_as_done(): void
    {
        $report = $this->reportWithStatus('ditolak', new Collection([
            new ReportStatusLog(['old_status' => 'baru_masuk', 'new_status' => 'terverifikasi_admin']),
            new ReportStatusLog(['old_status' => 'terverifikasi_admin', 'new_status' => 'ditolak']),
        ]));

        $html = $this->render($report);

        $this->assertStringContainsString('Diterima', $html);
        $this->assertStringContainsString('Diproses', $html);
        $this->assertStringContainsString('Ditolak', $html);
    }
}
