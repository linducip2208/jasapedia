<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarrantyClaim extends Model
{
    public const STATUSES = ['submitted', 'under_assessment', 'rework_scheduled', 'resolved', 'rejected', 'expired'];

    protected $fillable = [
        'code', 'order_id', 'claimed_by', 'issue', 'evidence', 'status',
        'outcome', 'resolution_note', 'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['evidence' => 'array', 'resolved_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
