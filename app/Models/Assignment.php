<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assignment extends Model
{
    public const MODES = ['auto_direct', 'broadcast', 'sequential', 'manual', 'vendor_internal'];
    public const STATUSES = ['offered', 'accepted', 'rejected', 'expired', 'reassigned'];

    protected $fillable = [
        'order_id', 'partner_id', 'member_id', 'worker_user_id', 'mode', 'status',
        'responded_at', 'expires_at', 'attempt', 'score_breakdown',
    ];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime', 'expires_at' => 'datetime', 'score_breakdown' => 'array'];
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
