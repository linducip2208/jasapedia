<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Membership extends Model
{
    protected $fillable = ['plan_id', 'member_type', 'member_id', 'starts_at', 'ends_at', 'status'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'plan_id');
    }
}
