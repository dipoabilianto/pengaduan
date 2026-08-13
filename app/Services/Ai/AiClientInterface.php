<?php

namespace App\Services\Ai;

use App\Models\Report;

interface AiClientInterface
{
    /**
     * @param  string  $facts  Superuser-curated office knowledge (see ChatFactsService) —
     *                         additional context only, must never override the flag criteria.
     *
     * @throws \RuntimeException on API/network/parse failure — caller decides how to degrade.
     */
    public function assessUrgency(Report $report, string $facts = ''): AiAssessmentResult;

    /**
     * Drafts a natural, non-repetitive reply for the reporter — never saved automatically,
     * Admin always reviews/edits before it's stored (Bab 5.3 human-in-the-loop).
     *
     * @throws \RuntimeException on API/network/parse failure — caller decides how to degrade.
     */
    public function generateReply(Report $report, ?string $reasoning = null): string;

    /**
     * Rewrites an officer's rough chat draft into more polite/natural language — content
     * stays theirs, never saved automatically (same human-in-the-loop principle).
     *
     * @throws \RuntimeException on API/network/parse failure — caller decides how to degrade.
     */
    public function polishText(string $draft): string;

    /**
     * Answers a citizen chat message autonomously — ONLY for general/FAQ-style questions.
     * Returns needs_human: true (message: null) for anything specific/sensitive/uncertain,
     * which the caller must treat as "AI declined", not send anything, and escalate to staff.
     *
     * @param  array{facts: string, history: array<int, array{role: string, body: string}>, related_report: ?array{category: string, status_label: string}}  $context
     * @return array{message: ?string, needs_human: bool}
     *
     * @throws \RuntimeException on API/network/parse failure — caller decides how to degrade.
     */
    public function answerChatMessage(array $context): array;
}
