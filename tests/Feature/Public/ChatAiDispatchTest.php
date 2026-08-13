<?php

namespace Tests\Feature\Public;

use App\Jobs\Chat\AnswerChatMessageWithAiJob;
use App\Models\ChatTicket;
use App\Models\User;
use App\Services\AiSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ChatAiDispatchTest extends TestCase
{
    use RefreshDatabase;

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
        $this->postJson(route('chat.start'), ['phone' => '081234567890']);
        $ticket = ChatTicket::first();

        $this->postJson(route('chat.send', $ticket), ['message' => 'Jam buka kantor jam berapa?']);

        Queue::assertPushed(AnswerChatMessageWithAiJob::class, fn ($job) => $job->triggeringMessage->chat_ticket_id === $ticket->id);
    }

    public function test_sending_a_message_does_not_dispatch_when_ai_is_not_configured(): void
    {
        Queue::fake();
        $this->postJson(route('chat.start'), ['phone' => '081234567890']);
        $ticket = ChatTicket::first();

        $this->postJson(route('chat.send', $ticket), ['message' => 'Jam buka kantor jam berapa?']);

        Queue::assertNotPushed(AnswerChatMessageWithAiJob::class);
    }

    public function test_sending_a_message_does_not_dispatch_when_ai_is_disabled_on_the_ticket(): void
    {
        Queue::fake();
        $this->configureAi();
        $this->postJson(route('chat.start'), ['phone' => '081234567890']);
        $ticket = ChatTicket::first();
        $ticket->update(['ai_enabled' => false]);

        $this->postJson(route('chat.send', $ticket), ['message' => 'Jam buka kantor jam berapa?']);

        Queue::assertNotPushed(AnswerChatMessageWithAiJob::class);
    }
}
