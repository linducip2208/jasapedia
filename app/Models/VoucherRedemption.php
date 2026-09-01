<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherRedemption extends Model
{
    protected $fillable = ['promotion_id', 'user_id', 'order_id', 'discount_amount'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
