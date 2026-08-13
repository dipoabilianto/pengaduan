<?php

namespace Tests\Feature\Jobs;

use App\Jobs\NotifyReportEscalatedJob;
use App\Models\Report;
use App\Models\User;
use App\Services\ReportAdminService;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotifyReportEscalatedJobTest extends TestCase
{
    use RefreshDatabase;

    private function report(): Report
    {
        return Report::create([
            'ticket_no' => 'TCK-'.fake()->unique()->numerify('####'),
            'type' => 'pengaduan',
            'category' => 'Pelayanan Administrasi Kependudukan',
            'channel' => 'web',
            'status' => 'terverifikasi_admin',
            'what' => 'isi laporan',
        ]);
    }

    public function test_dispatched_with_the_assigned_pejabat_when_escalating(): void
    {
        Queue::fake();

        $this->seed(RolesTableSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');
        $pejabat = User::factory()->create();
        $pejabat->assignRole('Pelaksana');

        $report = $this->report();

        app(ReportAdminService::class)->assignToPejabat($report, $pejabat, $admin);

        Queue::assertPushed(NotifyReportEscalatedJob::class, fn ($job) => $job->report->is($report) && $job->pejabat->is($pejabat));
    }

    public function test_handle_does_not_throw_when_not_configured(): void
    {
        $this->seed(RolesTableSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');
        $pejabat = User::factory()->create();
        $pejabat->assignRole('Pelaksana');

        $report = $this->report();

        // QUEUE_CONNECTION=sync — this runs the job synchronously; reaching here without
        // an exception is the assertion.
        app(ReportAdminService::class)->assignToPejabat($report, $pejabat, $admin);

        $this->assertTrue(true);
    }
}
