<?php

namespace Tests\Feature\Jobs;

use App\Jobs\NotifyRedCodeReportJob;
use App\Models\Report;
use App\Models\User;
use App\Services\ReportAdminService;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotifyRedCodeReportJobTest extends TestCase
{
    use RefreshDatabase;

    private function report(): Report
    {
        return Report::create([
            'ticket_no' => 'TCK-'.fake()->unique()->numerify('####'),
            'type' => 'pengaduan',
            'category' => 'Pelayanan Administrasi Kependudukan',
            'channel' => 'web',
            'status' => 'baru_masuk',
            'what' => 'isi laporan',
        ]);
    }

    public function test_dispatched_when_a_report_transitions_into_red_code(): void
    {
        Queue::fake();

        $this->seed(RolesTableSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $report = $this->report();

        app(ReportAdminService::class)->updateStatus($report, 'baru_masuk', 'red_code', $admin);

        Queue::assertPushed(NotifyRedCodeReportJob::class, fn ($job) => $job->report->is($report));
    }

    public function test_not_dispatched_again_when_report_is_already_red_code(): void
    {
        Queue::fake();

        $this->seed(RolesTableSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $report = $this->report();

        app(ReportAdminService::class)->updateStatus($report, 'baru_masuk', 'red_code', $admin);
        Queue::assertPushed(NotifyRedCodeReportJob::class, 1);

        // Re-saving with a note but urgency unchanged (still red_code) must not re-notify.
        app(ReportAdminService::class)->updateStatus($report->fresh(), 'baru_masuk', 'red_code', $admin, 'catatan tambahan');

        Queue::assertPushed(NotifyRedCodeReportJob::class, 1);
    }

    public function test_not_dispatched_for_non_red_code_changes(): void
    {
        Queue::fake();

        $this->seed(RolesTableSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $report = $this->report();

        app(ReportAdminService::class)->updateStatus($report, 'terverifikasi_admin', 'sedang', $admin);

        Queue::assertNotPushed(NotifyRedCodeReportJob::class);
    }

    public function test_handle_does_not_throw_when_not_configured(): void
    {
        $this->seed(RolesTableSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $report = $this->report();

        // QUEUE_CONNECTION=sync — runs synchronously; reaching here without an exception
        // is the assertion.
        app(ReportAdminService::class)->updateStatus($report, 'baru_masuk', 'red_code', $admin);

        $this->assertTrue(true);
    }
}
