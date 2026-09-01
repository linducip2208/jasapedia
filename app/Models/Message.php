<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    public const TYPES = [
        'text', 'image', 'video', 'audio', 'file', 'location', 'system_event',
        'service_card', 'order_card', 'quotation_card', 'payment_request',
        'milestone_card', 'reschedule_request', 'additional_charge_request',
        'dispute_event', 'warranty_event',
    ];

    protected $fillable = [
        'conversation_id', 'sender_id', 'type', 'body', 'structured',
        'reply_to_id', 'client_message_id', 'status',
    ];

    protected function casts(): array
    {
        return ['structured' => 'array'];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(MessageRead::class);
    }
}
