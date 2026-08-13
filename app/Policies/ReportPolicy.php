<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    /**
     * Bab 2 & 5.6 PDR: Superuser has full access — every ability below is
     * granted automatically, including ones added later. This is the ONE role
     * name intentionally left hardcoded: "superuser" is a fixed, protected role
     * (not editable/deletable via the Role management UI) — everything else is
     * expressed as dynamic permissions so it can be configured freely per role.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('superuser') ? true : null;
    }

    /**
     * Access to the reports list exists the moment a role can see at least one
     * status — there's no separate "can view the section at all" permission.
     */
    public function viewAny(User $user): bool
    {
        return collect(Report::STATUS_LABELS)
            ->keys()
            ->contains(fn (string $status) => $user->can('laporan.status.'.$status));
    }

    public function view(User $user, Report $report): bool
    {
        return $this->canSeeReport($user, $report);
    }

    /**
     * Bab 5.2 PDR: begitu laporan diteruskan ke Pejabat, laporan itu keluar dari ranah Admin —
     * cuma Pejabat yang benar-benar dipegang (via report_assignments) yang boleh bertindak
     * (ubah status, simpan/hapus balasan), bukan siapa pun yang kebetulan role-nya diberi hak
     * "ubah-status" secara luas. Ini struktural (sama seperti assign()), bukan permission yang
     * bisa dikonfigurasi per role — di status sebelumnya (mis. Terverifikasi Admin, saat Admin
     * baru MEMILIH mau diteruskan ke siapa) belum ada assignment sama sekali, jadi tidak dibatasi.
     */
    public function updateStatus(User $user, Report $report): bool
    {
        return $user->can('laporan.ubah-status')
            && $this->canSeeReport($user, $report)
            && $this->canActOnAssignedStage($user, $report);
    }

    /**
     * "Alihkan ke Pejabat Lain" — this is a peer handoff, not an administrative routing
     * decision (that's the separate first-time "Diteruskan ke Pejabat" step in updateStatus).
     * Only whoever currently holds the report may delegate it onward; Superuser can always
     * override via before().
     */
    public function assign(User $user, Report $report): bool
    {
        return $user->can('laporan.teruskan') && $this->isAssignedTo($report, $user);
    }

    public function approveAi(User $user, Report $report): bool
    {
        return $user->can('laporan.setujui-ai') && $this->canSeeReport($user, $report);
    }

    /**
     * Bab 7 PDR: only Superuser (via before()) may decrypt reporter identity, and only
     * with a logged reason. This is fixed by regulation, not a configurable permission —
     * every other role always gets false here regardless of what's granted elsewhere.
     */
    public function viewReporterIdentity(User $user, Report $report): bool
    {
        return false;
    }

    /**
     * A role must (a) be allowed to see the report's current status, and (b), if the role
     * is restricted to "assigned to me only", actually be the assignee.
     */
    private function canSeeReport(User $user, Report $report): bool
    {
        if (! $user->can('laporan.status.'.$report->status)) {
            return false;
        }

        if ($user->can('laporan.assigned-saja') && ! $this->isAssignedTo($report, $user)) {
            return false;
        }

        return true;
    }

    private function isAssignedTo(Report $report, User $user): bool
    {
        return $report->assignments()->where('assigned_to', $user->id)->exists();
    }

    /**
     * @see updateStatus() docblock — restricts action (not view) once a report is in Pejabat's
     * hands ("Dalam Penanganan"). Every earlier status has no assignment yet, so nothing to
     * restrict against.
     */
    private function canActOnAssignedStage(User $user, Report $report): bool
    {
        if ($report->status !== 'dalam_penanganan') {
            return true;
        }

        return $this->isAssignedTo($report, $user);
    }
}
