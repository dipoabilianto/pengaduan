<?php

namespace App\Policies;

use App\Models\ChannelInbox;
use App\Models\User;

class ChannelInboxPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('superuser') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('laporan.kelola-inbox');
    }

    public function manage(User $user, ChannelInbox $item): bool
    {
        return $user->can('laporan.kelola-inbox');
    }
}
