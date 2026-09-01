<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Checkin extends Model
{
    protected $table = 'checkins';

    protected $fillable = ['order_id', 'user_id', 'type', 'lat', 'lng', 'otp_code', 'verified_at', 'meta'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'meta' => 'array', 'lat' => 'decimal:7', 'lng' => 'decimal:7'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
