<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    public const TYPES = ['discount', 'cashback'];
    public const FUNDING = ['platform', 'vendor', 'shared'];

    protected $fillable = [
        'name', 'code', 'type', 'value', 'value_unit', 'max_discount', 'min_spend',
        'funding', 'vendor_share_percent', 'category_id', 'city', 'customer_segments',
        'usage_limit', 'per_user_limit', 'first_order_only', 'stackable',
        'starts_at', 'ends_at', 'status',
    ];

    protected function casts(): array
    {
        return ['customer_segments' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(VoucherRedemption::class);
    }
}
