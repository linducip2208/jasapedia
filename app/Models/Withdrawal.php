<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    public const STATUSES = ['requested', 'under_review', 'approved', 'processing', 'completed', 'failed', 'rejected', 'cancelled'];

    protected $fillable = [
        'partner_id', 'payout_destination_id', 'amount', 'fee', 'net', 'status',
        'requested_by', 'reviewed_by', 'provider_ref', 'failure_reason', 'processed_at',
    ];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(PayoutDestination::class, 'payout_destination_id');
    }
}
