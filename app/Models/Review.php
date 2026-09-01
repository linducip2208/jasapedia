<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'order_id', 'author_id', 'partner_id', 'overall', 'dimension_ratings',
        'comment', 'images', 'partner_response', 'responded_at', 'status',
    ];

    protected function casts(): array
    {
        return ['dimension_ratings' => 'array', 'images' => 'array', 'responded_at' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
