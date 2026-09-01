<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    public const STATUSES = ['requested', 'approved', 'rejected', 'processing', 'completed', 'failed'];

    protected $fillable = [
        'order_id', 'payment_transaction_id', 'amount', 'type', 'status', 'reason',
        'requested_by', 'decided_by', 'provider_ref', 'executed_at',
    ];

    protected function casts(): array
    {
        return ['executed_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
