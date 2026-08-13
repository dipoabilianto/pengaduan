<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    public const SENDER_CITIZEN = 'citizen';

    public const SENDER_OFFICER = 'officer';

    public const SENDER_AI = 'ai';

    public const SENDER_SYSTEM = 'system';

    public $timestamps = false;

    protected $fillable = [
        'chat_ticket_id',
        'sender_type',
        'sender_user_id',
        'body',
        'raw_draft',
        'ai_generated',
        'escalation_flag',
        'moderation_flagged',
        'offer_escalation',
        'cta_action',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'ai_generated' => 'boolean',
            'escalation_flag' => 'boolean',
            'moderation_flagged' => 'boolean',
            'offer_escalation' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ChatTicket::class, 'chat_ticket_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}
