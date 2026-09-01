<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    public const TYPES = ['base', 'package', 'addon', 'material', 'additional_charge', 'adjustment'];

    protected $fillable = [
        'order_id', 'type', 'name', 'qty', 'unit_price', 'amount', 'ref_id', 'unit_label', 'meta',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
