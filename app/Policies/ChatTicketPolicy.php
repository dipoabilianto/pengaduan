<?php

namespace App\Policies;

use App\Models\ChatTicket;
use App\Models\User;

class ChatTicketPolicy
{
    /**
     * Superuser has full access — same fixed bypass as ReportPolicy::before().
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('superuser') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('chat.lihat');
    }

    /**
     * No per-status visibility restriction like Report — every officer who can see
     * the inbox can see any ticket, deliberately simpler (no assignment-based
     * restriction concept was asked for here).
     */
    public function view(User $user, ChatTicket $ticket): bool
    {
        return $user->can('chat.lihat');
    }

    public function reply(User $user, ChatTicket $ticket): bool
    {
        return $user->can('chat.balas');
    }

    public function close(User $user, ChatTicket $ticket): bool
    {
        return $user->can('chat.tutup');
    }

    /**
     * Chat is ordinary customer-service triage, not anonymous whistleblowing — the
     * strict Superuser-only lock Report::viewReporterIdentity() has doesn't apply
     * here by default (see ChatPermissions::ABILITIES doc). Exception: if this
     * ticket is linked to a whistleblowing-type report, fall back to the stricter
     * rule (only Superuser, via before()) — a heuristic, not a fully engineered
     * solution (a WBS reporter chatting under an unlinked ticket isn't caught).
     */
    public function viewPhone(User $user, ChatTicket $ticket): bool
    {
        if ($ticket->relatedReport?->type === 'whistleblowing') {
            return false;
        }

        return $user->can('chat.lihat-nomor-telepon');
    }
}
