<?php

namespace App\Services\Notifications;

use App\Models\Report;
use App\Models\User;
use App\Support\ReportPermissions;
use Illuminate\Support\Collection;

/**
 * Kebalikan logika Report::scopeVisibleTo()/visibleStatusesFor() — dari SATU laporan,
 * cari SIAPA yang berhak melihatnya, bukan sebaliknya. Tidak ada konsep bidang/departemen
 * di app ini; audiens ditentukan murni dari permission status + narrowing assigned-saja,
 * persis aturan yang sudah dipakai scopeVisibleTo.
 */
class NotificationAudienceService
{
    /**
     * @return Collection<int,User>
     */
    public function usersVisibleToReport(Report $report): Collection
    {
        $superusers = User::role('superuser')->get();

        $eligible = User::permission(ReportPermissions::statusPermission($report->status))->get();

        $filtered = $eligible->filter(function (User $user) use ($report) {
            if (! $user->can(ReportPermissions::ASSIGNED_ONLY)) {
                return true;
            }

            return $report->assignments()->where('assigned_to', $user->id)->exists();
        });

        return $superusers->merge($filtered)->unique('id')->values();
    }
}
