<?php

namespace Tests\Feature\Admin;

use App\Models\Report;
use App\Models\ReportAiAssessment;
use App\Models\User;
use App\Services\AiSettingsService;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenerateReplyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesTableSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Administrator');

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
            'status' => 'baru_masuk',
            'urgency_flag' => 'tidak_valid',
            'what' => 'asdf asdf tes doang',
        ], $attributes));
    }

    private function fakeGeminiReply(string $reply): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode(['reply' => $reply])]]]],
                ],
            ]),
        ]);
    }

    public function test_admin_can_generate_an_ai_reply_draft(): void
    {
        $this->configureAi();
        $this->fakeGeminiReply('Terima kasih atas laporan Anda, namun informasi yang diberikan belum cukup jelas.');
        $report = $this->report();

        $response = $this->actingAs($this->admin())->postJson(route('admin.reports.reply.generate', $report));

        $response->assertOk();
        $response->assertJson(['reply' => 'Terima kasih atas laporan Anda, namun informasi yang diberikan belum cukup jelas.']);
    }

    public function test_preserves_paragraph_break_from_ai_response_through_to_the_json_reply(): void
    {
        $this->configureAi();
        $this->fakeGeminiReply("Terima kasih atas laporannya.\n\nBerikut penjelasan singkatnya.");
        $report = $this->report();

        $response = $this->actingAs($this->admin())->postJson(route('admin.reports.reply.generate', $report));

        $response->assertOk();
        $this->assertSame("Terima kasih atas laporannya.\n\nBerikut penjelasan singkatnya.", $response->json('reply'));
    }

    public function test_generating_two_drafts_can_return_different_wording(): void
    {
        $this->configureAi();
        $report = $this->report();

        Http::fakeSequence()
            ->push(['candidates' => [['content' => ['parts' => [['text' => json_encode(['reply' => 'Balasan pertama, versi A.'])]]]]]])
            ->push(['candidates' => [['content' => ['parts' => [['text' => json_encode(['reply' => 'Balasan kedua, versi B yang berbeda.'])]]]]]]);

        $first = $this->actingAs($this->admin())->postJson(route('admin.reports.reply.generate', $report));
        $second = $this->actingAs($this->admin())->postJson(route('admin.reports.reply.generate', $report));

        $first->assertOk();
        $second->assertOk();
        $this->assertNotSame($first->json('reply'), $second->json('reply'));
    }

    public function test_returns_validation_error_when_ai_is_not_configured(): void
    {
        $report = $this->report();

        $response = $this->actingAs($this->admin())->postJson(route('admin.reports.reply.generate', $report));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('reply');
    }

    public function test_returns_error_when_ai_call_fails(): void
    {
        $this->configureAi();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response('server error', 500),
        ]);
        $report = $this->report();

        $response = $this->actingAs($this->admin())->postJson(route('admin.reports.reply.generate', $report));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('reply');
    }

    public function test_passes_ai_reasoning_as_context_when_flag_came_from_ai(): void
    {
        $this->configureAi();
        $report = $this->report(['urgency_flag' => null, 'status' => 'baru_masuk']);
        ReportAiAssessment::create([
            'report_id' => $report->id,
            'ai_score' => 0,
            'ai_suggested_flag' => 'tidak_valid',
            'ai_raw_response' => [],
            'ai_reasoning' => 'Isi laporan tidak masuk akal, cuma tes coba-coba.',
        ]);

        Http::fake(function ($request) {
            $this->assertStringContainsString('Isi laporan tidak masuk akal', $request->body());

            return Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode(['reply' => 'Balasan yang mempertimbangkan konteks.'])]]]],
                ],
            ]);
        });

        $response = $this->actingAs($this->admin())->postJson(route('admin.reports.reply.generate', $report));

        $response->assertOk();
    }
}
