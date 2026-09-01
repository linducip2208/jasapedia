<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Partner extends Model
{
    public const VERIFICATION_STATES = ['unverified', 'submitted', 'under_review', 'needs_revision', 'verified', 'rejected', 'suspended'];
    public const TYPES = ['freelancer', 'individual', 'vendor_company'];
    public const ONLINE_STATUSES = ['offline', 'online', 'busy'];

    protected $fillable = [
        'user_id', 'type', 'display_name', 'slug', 'about', 'avatar_path',
        'verification_state', 'online_status', 'city', 'lat', 'lng', 'meta',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array', 'rating_avg' => 'decimal:2', 'lat' => 'decimal:7', 'lng' => 'decimal:7'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): HasOne
    {
        return $this->hasOne(PartnerOrganization::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(PartnerSkill::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PartnerDocument::class);
    }

    public function serviceAreas(): HasMany
    {
        return $this->hasMany(PartnerServiceArea::class);
    }

    public function payoutDestinations(): HasMany
    {
        return $this->hasMany(PayoutDestination::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function favoritedBy()
    {
        return $this->belongsToMany(User::class, 'favorites', 'partner_id', 'user_id');
    }

    public function isVendorCompany(): bool
    {
        return $this->type === 'vendor_company';
    }

    public function isVerified(): bool
    {
        return $this->verification_state === 'verified';
    }

    public function isOnline(): bool
    {
        return $this->online_status === 'online';
    }
}
