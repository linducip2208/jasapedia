<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    public const STATUSES = ['draft', 'sent', 'accepted', 'amended', 'terminated'];

    protected $fillable = [
        'code', 'project_id', 'order_id', 'partner_id', 'customer_id', 'proposal_id',
        'version', 'scope', 'deliverables', 'price', 'payment_terms', 'milestone_plan',
        'revision_limit', 'warranty_days', 'ip_terms', 'cancellation_terms', 'dispute_terms',
        'status', 'customer_accepted_at', 'partner_accepted_at', 'amends',
    ];

    protected function casts(): array
    {
        return [
            'scope' => 'array', 'deliverables' => 'array', 'milestone_plan' => 'array',
            'customer_accepted_at' => 'datetime', 'partner_accepted_at' => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class)->orderBy('sort');
    }
}
