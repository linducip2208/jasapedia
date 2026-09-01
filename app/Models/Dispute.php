<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dispute extends Model
{
    public const STATUSES = ['opened', 'evidence_collection', 'counter_response', 'mediation', 'decision', 'resolved', 'closed'];
    public const RESOLUTIONS = ['release_payment', 'partial_refund', 'full_refund', 'rework', 'service_credit', 'claim_rejected'];

    protected $fillable = [
        'code', 'order_id', 'opened_by', 'reason', 'description', 'status',
        'resolution', 'resolution_amount', 'resolution_note', 'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function evidences()
    {
        return $this->hasMany(DisputeEvidence::class);
    }
}
