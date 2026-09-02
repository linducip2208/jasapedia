<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    public const PRICE_MODELS = ['fixed', 'per_unit', 'hourly', 'daily', 'starting_from', 'package', 'quotation', 'milestone'];

    public const STATUSES = ['draft', 'pending_review', 'active', 'paused', 'rejected'];

    protected $fillable = [
        'partner_id', 'category_id', 'template_id', 'title', 'slug', 'description',
        'inclusions', 'exclusions', 'fulfillment_type', 'delivery_mode', 'price_model',
        'base_price', 'unit_label', 'min_quantity', 'max_quantity', 'duration_minutes',
        'emergency_capable', 'emergency_surcharge', 'warranty_days', 'status', 'media', 'attributes', 'is_demo',
    ];

    protected function casts(): array
    {
        return ['media' => 'array', 'attributes' => 'array', 'emergency_capable' => 'boolean'];
    }

    /** Cover path resolved from media JSON (cover key first, then first slot). */
    public function getCoverImageAttribute(): ?string
    {
        $media = $this->media ?? [];

        return $media['cover'] ?? (is_string($media[0] ?? null) ? $media[0] : null);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ServiceTemplate::class, 'template_id');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(ServicePackage::class)->orderBy('sort');
    }

    public function addons(): HasMany
    {
        return $this->hasMany(ServiceAddon::class);
    }

    public function favoritedBy()
    {
        return $this->belongsToMany(User::class, 'favorites', 'service_id', 'user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('services.status', 'active')
            ->whereHas('partner', fn ($q) => $q->where('verification_state', 'verified'));
    }

    /** Rating projection is stored on partner. */
    public function reviewDimensions(): array
    {
        return $this->category->config['review_dimensions']
            ?? ['quality', 'communication', 'value'];
    }
}
