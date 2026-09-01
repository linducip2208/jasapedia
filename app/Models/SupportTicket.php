<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    public const CATEGORIES = ["general", "order", "payment", "project", "withdrawal", "kyc", "dispute", "warranty", "technical"];
    public const STATUSES = ["open", "pending_customer", "pending_internal", "resolved", "closed"];

    protected $fillable = [
        "code", "user_id", "category", "ref_id", "ref_type", "subject",
        "priority", "status", "assigned_to", "first_response_at", "resolved_at",
    ];

    protected function casts(): array
    {
        return ["first_response_at" => "datetime", "resolved_at" => "datetime"];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'ticket_id');
    }
}
