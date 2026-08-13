<?php

namespace App\Services;

use App\Jobs\NotifyRedCodeReportJob;
use App\Jobs\NotifyReportEscalatedJob;
use App\Models\AuditLog;
use App\Models\Report;
use App\Models\ReportAiAssessment;
use App\Models\ReportAssignment;
use App\Models\ReportStatusLog;
use App\Models\User;
use App\Services\Ai\AiAssessmentResult;
use InvalidArgumentException;

class ReportAdminService
{
    /**
     * Bab 5.4 PDR: every status/flag change is recorded to report_status_logs for the audit trail.
     * $actor is null for system-triggered transitions (e.g. the AI scoring job).
     *
     * @throws InvalidArgumentException if $newStatus isn't reachable from the report's current status.
     */
    public function updateStatus(Report $report, string $newStatus, ?string $urgencyFlag, ?User $actor, ?string $note = null): Report
    {
        $this->guardNotLocked($report, $actor);

        // Superuser may correct/reopen a locked ("Selesai"/"Ditolak") report — everyone
        // else is bound by the normal sequential state machine.
        if (! $report->canTransitionTo($newStatus) && ! $actor?->hasRole('superuser')) {
            throw new InvalidArgumentException("Laporan berstatus \"{$report->statusLabel()}\" tidak dapat diubah langsung ke \"".(Report::STATUS_LABELS[$newStatus] ?? $newStatus).'".');
        }

        // Urgensi "Tidak Valid" selalu berarti status "Ditolak" — jangan cuma andalkan
        // FormRequest, karena service ini bisa dipanggil langsung oleh pemanggil lain
        // (assignToPejabat, command, dsb) yang mungkin lupa menegakkan aturan ini.
        if ($urgencyFlag === 'tidak_valid' && $newStatus !== 'ditolak') {
            throw new InvalidArgumentException('Urgensi "Tidak Valid" hanya dapat disimpan dengan status "Ditolak".');
        }

        $oldStatus = $report->status;
        $oldUrgencyFlag = $report->urgency_flag;

        // Nothing actually changed (e.g. "Simpan Perubahan" clicked again with the same
        // status/urgency and no new note) — skip writing a redundant audit trail entry.
        if (! $report->wouldChangeWith($newStatus, $urgencyFlag, $note)) {
            return $report;
        }

        $report->update([
            'status' => $newStatus,
            'urgency_flag' => $urgencyFlag,
        ]);

        ReportStatusLog::create([
            'report_id' => $report->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'actor_id' => $actor?->id,
            'note' => $note,
            'created_at' => now(),
        ]);

        // Bab 6 PDR: notifikasi prioritas tinggi HANYA saat laporan baru masuk red_code,
        // bukan setiap kali laporan yang sudah red_code disimpan ulang.
        if ($urgencyFlag === 'red_code' && $oldUrgencyFlag !== 'red_code') {
            NotifyRedCodeReportJob::dispatch($report->fresh());
        }

        return $report->fresh();
    }

    /**
     * Bab 5.3 PDR: persists the AI's recommendation (never the final decision). Runs entirely
     * in the background and never touches the report's status — the report stays "Baru Masuk"
     * whether AI has finished or not, since Admin is the sole approver regardless (via
     * approveAiAssessment()), not a step that's "unlocked" once AI responds.
     */
    public function recordAiAssessment(Report $report, ?AiAssessmentResult $result): Report
    {
        if ($result) {
            ReportAiAssessment::create([
                'report_id' => $report->id,
                'ai_score' => $result->score,
                'ai_suggested_flag' => $result->flag,
                'ai_raw_response' => $result->raw,
                'ai_reasoning' => $result->reasoning,
            ]);
        }

        return $report;
    }

    /**
     * Bab 5.3 PDR: human-in-the-loop — Admin approves or adjusts the AI's suggested flag.
     */
    public function approveAiAssessment(Report $report, string $finalFlag, User $admin, ?string $note = null, ?string $reply = null): Report
    {
        $assessment = $report->aiAssessment;

        if ($assessment) {
            $assessment->update([
                'approved_flag' => $finalFlag,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);
        }

        // "Tidak Valid" is AI's recommendation to reject outright — Admin approving it here
        // sends the report straight to Ditolak instead of the normal Terverifikasi Admin step.
        $targetStatus = $finalFlag === 'tidak_valid' ? 'ditolak' : 'terverifikasi_admin';

        // Reply is written BEFORE the status transition — the report is still unlocked at this
        // point (it's the pending "Baru Masuk" report awaiting this very decision), whereas
        // updateStatus() below may lock it (target "Ditolak"). Writing in the other order would
        // have the lock guard reject this reply as an edit to an already-final report.
        if (filled($reply)) {
            $this->updateReply($report, $admin, $reply);
        }

        return $this->updateStatus($report, $targetStatus, $finalFlag, $admin, $note);
    }

    /**
     * A reply is entirely optional and can be edited/removed anytime (deleteReply) — it's a
     * courtesy message to the reporter, not part of the audit trail. Still bound by the same
     * "final report" lock as the status itself: once "Selesai"/"Ditolak", only Superuser may
     * touch it.
     */
    public function updateReply(Report $report, User $admin, string $reply): Report
    {
        $this->guardNotLocked($report, $admin);

        $report->update([
            'public_reply' => $reply,
            'public_reply_by' => $admin->id,
            'public_reply_at' => now(),
        ]);

        return $report->fresh();
    }

    public function deleteReply(Report $report, ?User $actor = null): Report
    {
        $this->guardNotLocked($report, $actor);

        $report->update([
            'public_reply' => null,
            'public_reply_by' => null,
            'public_reply_at' => null,
        ]);

        return $report->fresh();
    }

    /**
     * "Selesai"/"Ditolak" adalah status akhir — laporan tidak berubah lagi (status, urgensi,
     * maupun balasan) kecuali oleh Superuser yang sengaja membuka/mengoreksinya kembali.
     */
    private function guardNotLocked(Report $report, ?User $actor): void
    {
        if ($report->isLocked() && ! $actor?->hasRole('superuser')) {
            throw new InvalidArgumentException("Laporan berstatus \"{$report->statusLabel()}\" sudah final dan tidak dapat diubah lagi, kecuali oleh Superuser.");
        }
    }

    /**
     * Bab 5.2 PDR: "Teruskan ke Pejabat" — records the assignment and moves the report forward.
     */
    public function assignToPejabat(Report $report, User $pejabat, User $admin, ?string $note = null): Report
    {
        ReportAssignment::create([
            'report_id' => $report->id,
            'assigned_to' => $pejabat->id,
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);

        NotifyReportEscalatedJob::dispatch($report, $pejabat);

        // Re-assigning a report already past this stage must not regress its status.
        $targetStatus = $report->status === 'dalam_penanganan'
            ? $report->status
            : 'dalam_penanganan';

        return $this->updateStatus($report, $targetStatus, $report->urgency_flag, $admin, $note ?? "Diteruskan ke {$pejabat->name}");
    }

    /**
     * Bab 5.6 & Bab 7 PDR: Superuser-only reporter identity access, mandatory reason, logged to audit_logs.
     *
     * @return array{name: ?string, phone: string}
     */
    public function revealReporterIdentity(Report $report, User $superuser, string $reason, ?string $ip): array
    {
        $reporter = $report->reporter;

        AuditLog::create([
            'user_id' => $superuser->id,
            'action' => "Buka data pelapor. Alasan: {$reason}",
            'subject_type' => Report::class,
            'subject_id' => $report->id,
            'ip_address' => $ip,
            'created_at' => now(),
        ]);

        return [
            'name' => $reporter?->name_enc,
            'phone' => $reporter?->phone_enc,
        ];
    }
}
