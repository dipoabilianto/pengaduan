<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\AiSettingsService;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class AiAutoStatusIndicatorTest extends TestCase
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

    public function test_shows_inactive_badge_when_ai_is_not_configured(): void
    {
        $response = $this->actingAs($this->admin())->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('AI Otomatis Tidak Aktif');
    }

    public function test_shows_active_badge_when_ai_is_configured_and_queue_is_healthy(): void
    {
        $this->configureAi();

        $response = $this->actingAs($this->admin())->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('AI Otomatis Aktif');
        $response->assertDontSee('AI Otomatis Tidak Aktif');
    }

    public function test_shows_problem_badge_when_configured_but_queue_is_stuck(): void
    {
        $this->configureAi();
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'available_at' => now()->subMinutes(5)->timestamp,
            'created_at' => now()->subMinutes(5)->timestamp,
        ]);

        $response = $this->actingAs($this->admin())->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('AI Otomatis Bermasalah');
        $response->assertSee('Antrean macet');
    }

    public function test_shows_problem_badge_when_ai_recently_failed(): void
    {
        $this->configureAi();
        app(AiSettingsService::class)->recordFailureFrom(
            new RuntimeException('Gemini API error: 429 {"error":"quota exceeded"}'), 'urgency'
        );

        $response = $this->actingAs($this->admin())->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('AI Otomatis Bermasalah');
        $response->assertSee('Kuota', false);
    }

    public function test_shows_active_badge_again_once_a_previous_failure_is_cleared(): void
    {
        $this->configureAi();
        $aiSettings = app(AiSettingsService::class);
        $aiSettings->recordFailureFrom(new RuntimeException('Gemini API error: 429 {}'), 'urgency');
        $aiSettings->recordSuccess('urgency');

        $response = $this->actingAs($this->admin())->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('AI Otomatis Aktif');
    }

    public function test_badge_is_not_shown_to_guests(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertDontSee('AI Otomatis');
    }

    public function test_ai_health_endpoint_reports_current_state_as_json(): void
    {
        $this->configureAi();

        $response = $this->actingAs($this->admin())->getJson(route('admin.ai-health'));

        $response->assertOk();
        $response->assertJson(['state' => 'active']);
        $response->assertJsonStructure(['state', 'reason']);
    }

    public function test_ai_health_endpoint_reflects_a_recent_failure(): void
    {
        $this->configureAi();
        app(AiSettingsService::class)->recordFailureFrom(new RuntimeException('OpenAI API error: 401 {}'), 'urgency');

        $response = $this->actingAs($this->admin())->getJson(route('admin.ai-health'));

        $response->assertOk();
        $response->assertJson(['state' => 'error']);
        $response->assertJsonFragment(['reason' => 'API key AI ditolak — periksa kembali di Pengaturan AI.']);
    }
}
