<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerSchedule extends Model
{
    protected $fillable = ['partner_id', 'day_of_week', 'start_time', 'end_time'];

    protected function casts(): array
    {
        return ['start_time' => 'datetime:H:i:s', 'end_time' => 'datetime:H:i:s'];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
