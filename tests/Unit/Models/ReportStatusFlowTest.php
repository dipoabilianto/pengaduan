<?php

namespace Tests\Unit\Models;

use App\Models\Report;
use PHPUnit\Framework\TestCase;

class ReportStatusFlowTest extends TestCase
{
    /**
     * Bab 5.4 PDR: the flow is strictly sequential, one step at a time.
     * "diteruskan_ke_pejabat" merged into "dalam_penanganan" — picking a pejabat and
     * starting to handle the report now happen in the same step.
     */
    private const SEQUENCE = [
        'baru_masuk',
        'terverifikasi_admin',
        'dalam_penanganan',
        'selesai',
    ];

    private function reportWithStatus(string $status): Report
    {
        return new Report(['status' => $status]);
    }

    public function test_each_step_can_only_move_to_the_next_step_in_sequence(): void
    {
        foreach (self::SEQUENCE as $i => $status) {
            $report = $this->reportWithStatus($status);
            $next = self::SEQUENCE[$i + 1] ?? null;

            foreach (self::SEQUENCE as $candidate) {
                if ($candidate === $status) {
                    $this->assertTrue($report->canTransitionTo($candidate), "{$status} should allow staying on itself (no-op).");
                } elseif ($candidate === $next) {
                    $this->assertTrue($report->canTransitionTo($candidate), "{$status} should be able to move to the next step {$candidate}.");
                } else {
                    $this->assertFalse($report->canTransitionTo($candidate), "{$status} should NOT be able to jump to {$candidate}.");
                }
            }
        }
    }

    public function test_cannot_skip_terverifikasi_admin_from_baru_masuk(): void
    {
        $report = $this->reportWithStatus('baru_masuk');

        $this->assertFalse($report->canTransitionTo('dalam_penanganan'));
        $this->assertTrue($report->canTransitionTo('terverifikasi_admin'));
    }

    public function test_ditolak_is_reachable_from_any_non_terminal_status(): void
    {
        foreach (['baru_masuk', 'terverifikasi_admin', 'dalam_penanganan'] as $status) {
            $this->assertTrue($this->reportWithStatus($status)->canTransitionTo('ditolak'), "{$status} should be able to reject.");
        }
    }

    public function test_ditolak_and_selesai_are_terminal(): void
    {
        $this->assertFalse($this->reportWithStatus('ditolak')->canTransitionTo('terverifikasi_admin'));
        $this->assertTrue($this->reportWithStatus('ditolak')->canTransitionTo('ditolak')); // self-transition still allowed

        $this->assertFalse($this->reportWithStatus('selesai')->canTransitionTo('ditolak'));
        $this->assertTrue($this->reportWithStatus('selesai')->canTransitionTo('selesai'));
    }

    public function test_valid_next_statuses_for_terverifikasi_admin_is_dalam_penanganan(): void
    {
        $next = $this->reportWithStatus('terverifikasi_admin')->validNextStatuses();

        $this->assertContains('dalam_penanganan', $next);
        $this->assertNotContains('selesai', $next);
    }

    public function test_would_change_with_is_false_when_nothing_is_actually_different(): void
    {
        $report = new Report(['status' => 'terverifikasi_admin', 'urgency_flag' => 'sedang']);

        $this->assertFalse($report->wouldChangeWith('terverifikasi_admin', 'sedang', null));
        $this->assertFalse($report->wouldChangeWith('terverifikasi_admin', 'sedang', ''));
    }

    public function test_would_change_with_is_true_when_status_differs(): void
    {
        $report = new Report(['status' => 'terverifikasi_admin', 'urgency_flag' => 'sedang']);

        $this->assertTrue($report->wouldChangeWith('dalam_penanganan', 'sedang', null));
    }

    public function test_would_change_with_is_true_when_urgency_differs(): void
    {
        $report = new Report(['status' => 'terverifikasi_admin', 'urgency_flag' => 'sedang']);

        $this->assertTrue($report->wouldChangeWith('terverifikasi_admin', 'tinggi', null));
    }

    public function test_would_change_with_is_true_when_a_note_is_given(): void
    {
        $report = new Report(['status' => 'terverifikasi_admin', 'urgency_flag' => 'sedang']);

        $this->assertTrue($report->wouldChangeWith('terverifikasi_admin', 'sedang', 'Catatan baru.'));
    }
}
