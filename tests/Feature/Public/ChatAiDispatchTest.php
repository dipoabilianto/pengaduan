<?php

namespace Tests\Feature\Public;

use App\Jobs\Chat\AnswerChatMessageWithAiJob;
use App\Models\ChatTicket;
use App\Models\User;
use App\Services\AiSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ChatAiDispatchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * /chat/mulai requires a verified reCAPTCHA v2 token (see StartChatRequest) — same
     * helper as ChatTest.
     */
    private function startChat(string $phone, array $extra = []): TestResponse
    {
        Http::fake(['www.google.com/recaptcha/*' => Http::response(['success' => true])]);

        return $this->postJson(route('chat.start'), array_merge(['phone' => $phone, 'g-recaptcha-response' => 'test-token'], $extra));
    }

    private function configureAi(): void
    {
        app(AiSettingsService::class)->save(
            ['provider' => 'gemini', 'api_key' => 'test-key', 'model' => 'gemini-flash-latest'],
            User::factory()->create(),
        );
    }

    public function test_sending_a_message_dispatches_the_ai_job_when_ai_is_configured_and_enabled(): void
    {
        Queue::fake();
        $this->configureAi();
        $this->startChat('081234567890');
        $ticket = ChatTicket::first();

        $this->postJson(route('chat.send', $ticket), ['message' => 'Jam buka kantor jam berapa?']);

        Queue::assertPushed(AnswerChatMessageWithAiJob::class, fn ($job) => $job->triggeringMessage->chat_ticket_id === $ticket->id);
    }

    public function test_sending_a_message_does_not_dispatch_when_ai_is_not_configured(): void
    {
        Queue::fake();
        $this->startChat('081234567890');
        $ticket = ChatTicket::first();

        $this->postJson(route('chat.send', $ticket), ['message' => 'Jam buka kantor jam berapa?']);

        Queue::assertNotPushed(AnswerChatMessageWithAiJob::class);
    }

    public function test_sending_a_message_does_not_dispatch_when_ai_is_disabled_on_the_ticket(): void
    {
        Queue::fake();
        $this->configureAi();
        $this->startChat('081234567890');
        $ticket = ChatTicket::first();
        $ticket->update(['ai_enabled' => false]);

        $this->postJson(route('chat.send', $ticket), ['message' => 'Jam buka kantor jam berapa?']);

        Queue::assertNotPushed(AnswerChatMessageWithAiJob::class);
    }
}
