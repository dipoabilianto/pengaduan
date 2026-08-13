<?php

namespace Tests\Feature\Services;

use App\Models\Report;
use App\Models\ReportStatusLog;
use App\Models\User;
use App\Services\ReportAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ReportAdminServiceTest extends TestCase
{
    use RefreshDatabase;

    private function report(array $attributes = []): Report
    {
        return Report::create(array_merge([
            'ticket_no' => 'TCK-'.fake()->unique()->numerify('####'),
            'type' => 'pengaduan',
            'category' => 'Pelayanan Administrasi Kependudukan',
            'channel' => 'web',
            'status' => 'terverifikasi_admin',
            'urgency_flag' => 'sedang',
            'what' => 'Contoh kronologi kejadian untuk pengujian.',
        ], $attributes));
    }

    /**
     * Repeatedly clicking "Simpan Perubahan" with nothing actually changed (same status,
     * same urgency, no note) must not keep spamming the audit trail with redundant rows.
     */
    public function test_updating_status_with_no_real_change_does_not_create_a_duplicate_log_entry(): void
    {
        $report = $this->report();
        $admin = User::factory()->create();
        $service = app(ReportAdminService::class);

        $service->updateStatus($report, 'terverifikasi_admin', 'sedang', $admin, null);
        $service->updateStatus($report, 'terverifikasi_admin', 'sedang', $admin, null);
        $service->updateStatus($report, 'terverifikasi_admin', 'sedang', $admin, null);

        $this->assertSame(0, ReportStatusLog::where('report_id', $report->id)->count());
    }

    public function test_updating_status_with_a_real_status_change_still_logs(): void
    {
        $report = $this->report(['status' => 'baru_masuk', 'urgency_flag' => null]);
        $admin = User::factory()->create();
        $service = app(ReportAdminService::class);

        $service->updateStatus($report, 'terverifikasi_admin', 'sedang', $admin, null);

        $this->assertSame(1, ReportStatusLog::where('report_id', $report->id)->count());
    }

    public function test_updating_with_a_new_note_still_logs_even_if_status_and_urgency_are_unchanged(): void
    {
        $report = $this->report();
        $admin = User::factory()->create();
        $service = app(ReportAdminService::class);

        $service->updateStatus($report, 'terverifikasi_admin', 'sedang', $admin, 'Catatan tambahan.');

        $this->assertSame(1, ReportStatusLog::where('report_id', $report->id)->count());
    }

    public function test_non_superuser_cannot_update_status_on_a_locked_report(): void
    {
        $report = $this->report(['status' => 'selesai', 'urgency_flag' => 'sedang']);
        $admin = User::factory()->create();
        $service = app(ReportAdminService::class);

        $this->expectException(InvalidArgumentException::class);

        $service->updateStatus($report, 'selesai', 'sedang', $admin, 'Mencoba mengubah catatan.');
    }

    public function test_non_superuser_cannot_edit_reply_on_a_locked_report(): void
    {
        $report = $this->report(['status' => 'ditolak', 'urgency_flag' => 'tidak_valid']);
        $admin = User::factory()->create();
        $service = app(ReportAdminService::class);

        $this->expectException(InvalidArgumentException::class);

        $service->updateReply($report, $admin, 'Mencoba mengubah balasan.');
    }

    public function test_non_superuser_cannot_delete_reply_on_a_locked_report(): void
    {
        $report = $this->report([
            'status' => 'ditolak',
            'urgency_flag' => 'tidak_valid',
            'public_reply' => 'Balasan lama.',
        ]);
        $admin = User::factory()->create();
        $service = app(ReportAdminService::class);

        $this->expectException(InvalidArgumentException::class);

        $service->deleteReply($report, $admin);
    }

    public function test_superuser_can_still_update_a_locked_report(): void
    {
        $this->seed(\Database\Seeders\RolesTableSeeder::class);
        $report = $this->report([
            'status' => 'selesai',
            'urgency_flag' => 'sedang',
            'public_reply' => 'Balasan lama.',
        ]);
        $superuser = User::factory()->create();
        $superuser->assignRole('superuser');
        $service = app(ReportAdminService::class);

        $service->updateReply($report, $superuser, 'Balasan yang dikoreksi Superuser.');

        $this->assertSame('Balasan yang dikoreksi Superuser.', $report->fresh()->public_reply);
    }
}
