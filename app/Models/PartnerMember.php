<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerMember extends Model
{
    protected $fillable = ['organization_id', 'user_id', 'role', 'status', 'invited_by', 'joined_at'];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(PartnerOrganization::class, 'organization_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Map member role → RBAC role name. */
    public static function roleToRbac(string $memberRole): string
    {
        return match ($memberRole) {
            'owner' => 'VendorOwner',
            'manager' => 'VendorManager',
            'dispatcher' => 'VendorDispatcher',
            'finance' => 'VendorFinance',
            'pm' => 'VendorPM',
            'worker' => 'VendorWorker',
            default => 'Customer',
        };
    }
}
