<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PolishChatDraftRequest;
use App\Http\Requests\Admin\SendOfficerChatMessageRequest;
use App\Http\Requests\Admin\UpdateChatStatusRequest;
use App\Models\ChatMessage;
use App\Models\ChatRating;
use App\Models\ChatTicket;
use App\Services\Ai\AiClientFactory;
use App\Services\AiSettingsService;
use App\Services\ChatAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class ChatTicketController extends Controller
{
    public function __construct(
        private readonly ChatAdminService $chatAdmin,
        private readonly AiSettingsService $aiSettings,
        private readonly AiClientFactory $aiClientFactory,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', ChatTicket::class);

        return view('admin.chat.index', [
            'tickets' => ChatTicket::with('assignedOfficer')
                ->withCount(['messages as pending_escalation_count' => fn ($query) => $query->where('escalation_flag', true)])
                ->orderByDesc('last_message_at')
                ->paginate(20),
            'ratingSummary' => $this->ratingSummary(),
            'recentEscalations' => $this->recentEscalations(),
        ]);
    }

    /**
     * Raw list, not clustered/summarized — that judgment call (spotting a recurring
     * topic the AI keeps failing on) is for a human to make by skimming the actual
     * wording, not something worth building NLP grouping for at this scale. Gives
     * Admin the signal for "what Facts should I add?" (see ChatFactsService) that
     * previously only existed scattered across individual ticket transcripts.
     *
     * @return Collection<int, ChatMessage>
     */
    private function recentEscalations(): Collection
    {
        return ChatMessage::query()
            ->where('sender_type', ChatMessage::SENDER_CITIZEN)
            ->where('escalation_flag', true)
            ->with('ticket:id')
            ->latest('created_at')
            ->limit(10)
            ->get();
    }

    /**
     * Deliberately a lightweight reference summary, not a full IKM report/dashboard —
     * out of scope here, this just makes the captured ratings immediately visible where
     * officers already look.
     *
     * @return array{average: ?float, total: int, breakdown: array<int, int>}
     */
    private function ratingSummary(): array
    {
        $total = ChatRating::count();

        return [
            'average' => $total > 0 ? round(ChatRating::avg('scale'), 1) : null,
            'total' => $total,
            'breakdown' => ChatRating::selectRaw('scale, count(*) as total')
                ->groupBy('scale')
                ->pluck('total', 'scale')
                ->all(),
        ];
    }

    public function show(ChatTicket $chatTicket): View
    {
        $this->authorize('view', $chatTicket);

        return view('admin.chat.show', [
            'ticket' => $chatTicket->load('messages.sender', 'assignedOfficer', 'relatedReport', 'ratings'),
            'canReply' => auth()->user()->can('reply', $chatTicket),
            'canClose' => auth()->user()->can('close', $chatTicket),
            'canViewPhone' => auth()->user()->can('viewPhone', $chatTicket),
            'isAiConfigured' => $this->aiSettings->isConfigured(),
        ]);
    }

    public function claim(ChatTicket $chatTicket): RedirectResponse
    {
        $this->authorize('reply', $chatTicket);

        $this->chatAdmin->claim($chatTicket, auth()->user());

        return back()->with('status', 'Tiket diambil alih.');
    }

    public function sendMessage(SendOfficerChatMessageRequest $request, ChatTicket $chatTicket): RedirectResponse
    {
        try {
            $this->chatAdmin->sendMessage(
                $chatTicket,
                $request->user(),
                $request->validated('message'),
                $request->validated('raw_draft'),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['message' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Balasan terkirim.');
    }

    public function updateStatus(UpdateChatStatusRequest $request, ChatTicket $chatTicket): RedirectResponse
    {
        $this->chatAdmin->updateStatus($chatTicket, $request->validated('status'));

        return back()->with('status', 'Status tiket diperbarui.');
    }

    public function toggleAi(ChatTicket $chatTicket): RedirectResponse
    {
        $this->authorize('reply', $chatTicket);

        $this->chatAdmin->toggleAi($chatTicket, ! $chatTicket->ai_enabled);

        return back()->with('status', $chatTicket->fresh()->ai_enabled ? 'AI diaktifkan kembali.' : 'AI dinonaktifkan untuk tiket ini.');
    }

    public function revealPhone(Request $request, ChatTicket $chatTicket): RedirectResponse
    {
        $this->authorize('viewPhone', $chatTicket);

        $identity = $this->chatAdmin->revealPhone($chatTicket, $request->user(), $request->ip());

        return back()->with('chatIdentity', $identity);
    }

    /**
     * Sync, mirrors AdminReportController::generateReply() exactly — officer is actively
     * waiting on a button click, never auto-saved, officer still reviews/edits before
     * "Kirim" (see ChatAdminService::sendMessage()'s raw_draft parameter).
     */
    public function polishDraft(PolishChatDraftRequest $request, ChatTicket $chatTicket): JsonResponse
    {
        try {
            $polished = $this->aiClientFactory->make()?->polishText($request->validated('draft'));

            if ($polished) {
                $this->aiSettings->recordSuccess('polish');
            }
        } catch (Throwable $e) {
            Log::warning('AI chat draft polish failed.', [
                'chat_ticket_id' => $chatTicket->id,
                'error' => $e->getMessage(),
            ]);

            $this->aiSettings->recordFailureFrom($e, 'polish');
            $polished = null;
        }

        if (! $polished) {
            return response()->json([
                'message' => 'Gagal merapikan balasan dengan AI. Coba lagi.',
                'errors' => ['draft' => ['Gagal merapikan balasan dengan AI. Coba lagi.']],
            ], 422);
        }

        return response()->json(['polished' => $polished]);
    }
}
