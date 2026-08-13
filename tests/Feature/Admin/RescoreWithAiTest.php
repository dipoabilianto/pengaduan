<?php

namespace Tests\Feature\Admin;

use App\Jobs\ScoreReportUrgencyJob;
use App\Models\Report;
use App\Models\ReportAiAssessment;
use App\Models\User;
use App\Services\AiSettingsService;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RescoreWithAiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesTableSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Administrator');

        return $user;
    }

    private function superuser(): User
    {
        $this->seed(RolesTableSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('superuser');

        return $user;
    }

    private function configureAi(): void
    {
        app(AiSettingsService::class)->save(
            ['provider' => 'gemini', 'api_key' => 'test-key', 'model' => 'gemini-flash-latest'],
            User::factory()->create(),
        );
    }

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

    public function test_admin_can_trigger_manual_rescore_when_ai_is_configured_and_no_assessment_exists(): void
    {
        Queue::fake();
        $this->configureAi();
        $report = $this->report();

        $response = $this->actingAs($this->admin())->post(route('admin.reports.rescore-ai', $report));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        Queue::assertPushed(ScoreReportUrgencyJob::class, fn ($job) => $job->report->is($report));
    }

    public function test_manual_rescore_is_rejected_when_ai_is_not_configured(): void
    {
        Queue::fake();
        $report = $this->report();

        $response = $this->actingAs($this->admin())->post(route('admin.reports.rescore-ai', $report));

        $response->assertSessionHasErrors('ai');
        Queue::assertNotPushed(ScoreReportUrgencyJob::class);
    }

    public function test_manual_rescore_is_rejected_when_report_already_has_an_assessment(): void
    {
        Queue::fake();
        $this->configureAi();
        $report = $this->report();
        ReportAiAssessment::create([
            'report_id' => $report->id,
            'ai_score' => 50,
            'ai_suggested_flag' => 'sedang',
            'ai_raw_response' => [],
        ]);

        $response = $this->actingAs($this->admin())->post(route('admin.reports.rescore-ai', $report));

        $response->assertSessionHasErrors('ai');
        Queue::assertNotPushed(ScoreReportUrgencyJob::class);
    }

    public function test_activating_ai_settings_backfills_reports_without_an_assessment(): void
    {
        Queue::fake();
        $this->seed(RolesTableSeeder::class);
        $withAssessment = $this->report();
        ReportAiAssessment::create([
            'report_id' => $withAssessment->id,
            'ai_score' => 50,
            'ai_suggested_flag' => 'sedang',
            'ai_raw_response' => [],
        ]);
        $withoutAssessment = $this->report();

        $response = $this->actingAs($this->superuser())->put(route('admin.settings.ai.update'), [
            'provider' => 'gemini',
            'api_key' => 'test-key',
            'model' => 'gemini-flash-latest',
        ]);

        $response->assertRedirect();
        Queue::assertPushed(ScoreReportUrgencyJob::class, 1);
        Queue::assertPushed(ScoreReportUrgencyJob::class, fn ($job) => $job->report->is($withoutAssessment));
    }

    public function test_saving_ai_settings_again_while_already_configured_does_not_backfill(): void
    {
        Queue::fake();
        $this->configureAi();
        $this->report();

        $response = $this->actingAs($this->superuser())->put(route('admin.settings.ai.update'), [
            'provider' => 'gemini',
            'api_key' => 'another-key',
            'model' => 'gemini-flash-latest',
        ]);

        $response->assertRedirect();
        Queue::assertNotPushed(ScoreReportUrgencyJob::class);
    }

    public function test_ai_status_endpoint_reports_not_ready_without_an_assessment(): void
    {
        $report = $this->report();

        $response = $this->actingAs($this->admin())->getJson(route('admin.reports.ai-status', $report));

        $response->assertOk();
        $response->assertJson(['ready' => false]);
    }

    public function test_ai_status_endpoint_reports_ready_once_assessment_exists(): void
    {
        $report = $this->report();
        ReportAiAssessment::create([
            'report_id' => $report->id,
            'ai_score' => 50,
            'ai_suggested_flag' => 'sedang',
            'ai_raw_response' => [],
        ]);

        $response = $this->actingAs($this->admin())->getJson(route('admin.reports.ai-status', $report));

        $response->assertOk();
        $response->assertJson(['ready' => true]);
    }
}
