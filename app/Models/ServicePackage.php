<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicePackage extends Model
{
    protected $fillable = [
        'service_id', 'name', 'description', 'price', 'duration_minutes', 'inclusions', 'is_default', 'sort',
    ];

    protected function casts(): array
    {
        return ['inclusions' => 'array', 'is_default' => 'boolean'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
