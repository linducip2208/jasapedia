<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdditionalChargeRequest extends Model
{
    public const STATUSES = ['pending', 'approved', 'rejected', 'expired', 'cancelled'];

    protected $fillable = [
        'order_id', 'item', 'description', 'amount', 'evidence_path',
        'created_by', 'status', 'decided_by', 'decided_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
