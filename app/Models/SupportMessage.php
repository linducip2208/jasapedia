<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    protected $table = 'support_messages';

    protected $fillable = ["ticket_id", "user_id", "author_type", "body", "attachments"];

    protected function casts(): array
    {
        return ["attachments" => "array"];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class);
    }
}
