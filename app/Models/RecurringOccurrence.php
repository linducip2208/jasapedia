<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringOccurrence extends Model
{
    protected $fillable = ['schedule_id', 'scheduled_on', 'order_id', 'status'];

    protected function casts(): array
    {
        return ['scheduled_on' => 'date'];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(RecurringSchedule::class, 'schedule_id');
    }
}
