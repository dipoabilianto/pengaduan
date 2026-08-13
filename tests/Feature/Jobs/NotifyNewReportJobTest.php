<?php

namespace Tests\Feature\Jobs;

use App\Jobs\NotifyNewReportJob;
use App\Models\User;
use App\Services\ReportService;
use App\Services\WhatsAppSettingsService;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotifyNewReportJobTest extends TestCase
{
    use RefreshDatabase;

    private function submitReport(): \App\Models\Report
    {
        return app(ReportService::class)->submit([
            'type' => 'pengaduan',
            'category' => 'Pelayanan Administrasi Kependudukan',
            'name' => 'Warga',
            'phone' => '081234567890',
            'what' => 'Uraian laporan.',
        ]);
    }

    public function test_dispatched_when_a_report_is_submitted(): void
    {
        Queue::fake();

        $report = $this->submitReport();

        Queue::assertPushed(NotifyNewReportJob::class, fn ($job) => $job->report->is($report));
    }

    public function test_handle_does_not_throw_when_whatsapp_and_push_are_not_configured(): void
    {
        $this->seed(RolesTableSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $report = $this->submitReport();

        // QUEUE_CONNECTION=sync in phpunit.xml already ran the job synchronously during
        // submitReport() above — reaching here without an exception is the assertion.
        $this->assertNotNull($report->id);
    }

    public function test_handle_does_not_throw_when_notification_apis_fail(): void
    {
        $this->seed(RolesTableSeeder::class);
        $admin = User::factory()->create(['phone' => '081234567890']);
        $admin->assignRole('Administrator');

        app(WhatsAppSettingsService::class)->save([
            'phone_number_id' => '109876543210987',
            'access_token' => 'fake-token',
            'webhook_verify_token' => 'verify-token',
        ], $admin);

        Http::fake(['*' => Http::response('error', 500)]);

        // Even fully configured + failing outbound calls must not throw out of submit().
        $report = $this->submitReport();

        $this->assertNotNull($report->id);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));
    }
}
