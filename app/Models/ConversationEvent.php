<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationEvent extends Model
{
    public $timestamps = false;

    protected $table = 'conversation_events';

    protected $fillable = ['conversation_id', 'event', 'actor_id', 'payload', 'created_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'created_at' => 'datetime'];
    }
}
