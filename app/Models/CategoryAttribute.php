<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryAttribute extends Model
{
    public const TYPES = ['text', 'number', 'select', 'multi', 'boolean', 'file'];

    protected $fillable = ['category_id', 'key', 'label', 'type', 'options', 'required', 'sort'];

    protected function casts(): array
    {
        return ['options' => 'array', 'required' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
