<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceTemplate extends Model
{
    public const FULFILLMENT_TYPES = [
        'instant_booking', 'appointment', 'fixed_package', 'hourly', 'daily',
        'per_unit', 'survey_required', 'request_quotation', 'rfq', 'project', 'milestone_project',
    ];

    public const DELIVERY_MODES = ['remote', 'onsite', 'hybrid', 'provider_location'];

    protected $fillable = [
        'category_id', 'name', 'slug', 'fulfillment_type', 'delivery_mode',
        'default_duration_minutes', 'description', 'config', 'is_active',
    ];

    protected function casts(): array
    {
        return ['config' => 'array', 'is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
