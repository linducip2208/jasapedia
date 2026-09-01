<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageReport extends Model
{
    protected $fillable = ['message_id', 'reported_by', 'reason', 'note', 'status'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
