<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerServiceArea extends Model
{
    public const COVERAGE_TYPES = ['city', 'district', 'radius', 'polygon'];

    protected $fillable = ['partner_id', 'coverage_type', 'location_id', 'radius_km', 'polygon'];

    protected function casts(): array
    {
        return ['polygon' => 'array', 'radius_km' => 'decimal:2'];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}
