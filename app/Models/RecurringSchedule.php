<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringSchedule extends Model
{
    protected $fillable = [
        'user_id', 'service_id', 'frequency', 'day_of_week', 'day_of_month',
        'preferred_time', 'address_id', 'occurrences_left', 'starts_on', 'ends_on',
        'status', 'last_generated_at',
    ];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'preferred_time' => 'datetime:H:i', 'last_generated_at' => 'datetime'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(RecurringOccurrence::class, 'schedule_id');
    }
}
