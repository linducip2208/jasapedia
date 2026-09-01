<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAttachment extends Model
{
    protected $fillable = ['message_id', 'file_path', 'kind', 'mime', 'size', 'name'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
