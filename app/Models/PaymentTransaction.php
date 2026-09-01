<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    public const STATUSES = ['created', 'pending', 'authorized', 'paid', 'failed', 'expired', 'cancelled', 'refund_pending', 'partially_refunded', 'refunded'];

    protected $fillable = [
        'order_id', 'gateway', 'gateway_ref', 'amount', 'status',
        'payment_method', 'paid_at', 'expires_at', 'meta',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array', 'paid_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
