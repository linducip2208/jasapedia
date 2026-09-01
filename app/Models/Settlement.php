<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Settlement extends Model
{
    public const STATUSES = ['pending', 'eligible', 'processing', 'completed', 'failed', 'held'];

    protected $fillable = [
        'order_id', 'partner_id', 'gross', 'commission', 'additional_amount', 'vendor_net',
        'status', 'eligible_at', 'processed_at', 'meta',
    ];

    protected function casts(): array
    {
        return ['eligible_at' => 'datetime', 'processed_at' => 'datetime', 'meta' => 'array'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
