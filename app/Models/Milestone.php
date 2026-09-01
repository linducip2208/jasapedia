<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Milestone extends Model
{
    public const STATUSES = [
        'draft', 'ready', 'funded', 'in_progress', 'submitted', 'revision_requested',
        'resubmitted', 'approved', 'release_pending', 'released', 'disputed', 'cancelled',
    ];

    protected $fillable = [
        'contract_id', 'order_id', 'title', 'description', 'amount', 'deadline',
        'sort', 'status', 'active_status_snapshot', 'submitted_at', 'approved_at', 'released_at',
    ];

    protected function casts(): array
    {
        return ['deadline' => 'date', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(MilestoneDeliverable::class);
    }
}
