<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Proposal extends Model
{
    public const STATUSES = ['draft', 'submitted', 'shortlisted', 'rejected', 'withdrawn', 'accepted'];

    protected $fillable = [
        'project_id', 'rfq_id', 'partner_id', 'cover_letter', 'technical_approach',
        'price', 'timeline_days', 'deliverables', 'milestone_plan', 'warranty_days',
        'valid_until', 'attachments', 'status',
    ];

    protected function casts(): array
    {
        return ['deliverables' => 'array', 'milestone_plan' => 'array', 'attachments' => 'array', 'valid_until' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
