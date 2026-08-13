<?php

use App\Models\ChatTicket;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
 * Officer-side chat channels — authenticated User via the default /broadcasting/auth
 * route Reverb's installer registers. The citizen side of chat.{ticketId} is NOT
 * authorized here — guests aren't Users, see ChatBroadcastAuthController for the
 * separate token-based auth endpoint they use instead.
 */
Broadcast::channel('chat.{ticketId}', function ($user, int $ticketId) {
    return $user->can('view', ChatTicket::findOrFail($ticketId));
});

Broadcast::channel('chat-inbox', function ($user) {
    return $user->can('viewAny', ChatTicket::class);
});
