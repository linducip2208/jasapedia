<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Category extends Model
{
    protected $fillable = ['parent_id', 'name', 'slug', 'icon', 'sort', 'is_active', 'config'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'config' => 'array'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort');
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(CategoryAttribute::class)->orderBy('sort');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(ServiceTemplate::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
