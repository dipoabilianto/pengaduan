<?php

namespace Tests\Feature\Admin;

use App\Models\ChatMessage;
use App\Models\ChatTicket;
use App\Models\User;
use App\Services\AiSettingsService;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PolishChatDraftTest extends TestCase
{
    use RefreshDatabase;

    private function officer(): User
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

    private function fakeGeminiPolish(string $text): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode(['text' => $text])]]]],
                ],
            ]),
        ]);
    }

    public function test_officer_can_polish_a_draft(): void
    {
        $this->configureAi();
        $this->fakeGeminiPolish('Silakan datang ke kantor kami pada jam 08.00–16.00 WIB, Senin sampai Jumat.');
        $ticket = ChatTicket::findOrStartFor('081234567890');

        $response = $this->actingAs($this->officer())->postJson(route('admin.chat.polish', $ticket), [
            'draft' => 'datang aja jam kerja',
        ]);

        $response->assertOk();
        $response->assertJson(['polished' => 'Silakan datang ke kantor kami pada jam 08.00–16.00 WIB, Senin sampai Jumat.']);
        // Polishing never saves anything on its own — it's purely a suggestion.
        $this->assertSame(0, ChatMessage::count());
    }

    public function test_returns_validation_error_when_ai_is_not_configured(): void
    {
        $ticket = ChatTicket::findOrStartFor('081234567890');

        $response = $this->actingAs($this->officer())->postJson(route('admin.chat.polish', $ticket), [
            'draft' => 'datang aja jam kerja',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('draft');
    }

    public function test_returns_error_when_ai_call_fails(): void
    {
        $this->configureAi();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response('server error', 500)]);
        $ticket = ChatTicket::findOrStartFor('081234567890');

        $response = $this->actingAs($this->officer())->postJson(route('admin.chat.polish', $ticket), [
            'draft' => 'datang aja jam kerja',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('draft');
    }

    public function test_officer_without_chat_permission_cannot_polish(): void
    {
        $this->configureAi();
        $this->seed(RolesTableSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Pengawas');
        $ticket = ChatTicket::findOrStartFor('081234567890');

        $this->actingAs($user)->postJson(route('admin.chat.polish', $ticket), [
            'draft' => 'datang aja jam kerja',
        ])->assertForbidden();
    }

    public function test_sending_a_message_with_raw_draft_records_it_for_transparency(): void
    {
        $officer = $this->officer();
        $ticket = ChatTicket::findOrStartFor('081234567890');

        $this->actingAs($officer)->post(route('admin.chat.send', $ticket), [
            'message' => 'Silakan datang ke kantor kami pada jam 08.00–16.00 WIB.',
            'raw_draft' => 'datang aja jam kerja',
        ]);

        $message = ChatMessage::first();
        $this->assertSame('datang aja jam kerja', $message->raw_draft);
        $this->assertSame('Silakan datang ke kantor kami pada jam 08.00–16.00 WIB.', $message->body);
    }
}
