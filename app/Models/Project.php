<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    public const STATUSES = ['draft', 'published', 'receiving_proposals', 'shortlisting', 'negotiation', 'awarded', 'contracting', 'active', 'on_hold', 'completed', 'closed', 'cancelled', 'disputed'];
    public const BUDGET_TYPES = ['fixed', 'hourly', 'range'];

    protected $fillable = [
        'code', 'user_id', 'category_id', 'title', 'description', 'requirements', 'skills',
        'budget_type', 'budget_min', 'budget_max', 'deadline', 'attachments', 'visibility',
        'status', 'active_status_snapshot', 'awarded_partner_id',
    ];

    protected function casts(): array
    {
        return ['requirements' => 'array', 'skills' => 'array', 'attachments' => 'array', 'deadline' => 'date'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function awardedPartner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'awarded_partner_id');
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
