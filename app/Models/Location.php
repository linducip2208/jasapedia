<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    public const TYPES = ['country', 'province', 'city', 'district', 'subdistrict'];

    protected $fillable = ['parent_id', 'type', 'name', 'slug', 'postal_code', 'lat', 'lng'];

    protected function casts(): array
    {
        return ['lat' => 'decimal:7', 'lng' => 'decimal:7'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopePath($query, string $slug)
    {
        return $query->where('slug', $slug);
    }
}
