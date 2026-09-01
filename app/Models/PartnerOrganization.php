<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerOrganization extends Model
{
    public const MEMBER_ROLES = ['owner', 'manager', 'dispatcher', 'finance', 'pm', 'worker'];

    protected $fillable = [
        'partner_id', 'name', 'legal_name', 'npwp', 'nib', 'address', 'pic_name', 'pic_phone', 'worker_count', 'settings',
    ];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(PartnerMember::class, 'organization_id');
    }
}
