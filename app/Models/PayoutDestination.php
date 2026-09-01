<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutDestination extends Model
{
    protected $fillable = [
        'partner_id', 'type', 'bank_code', 'account_number', 'account_name', 'is_default', 'verified_at',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'verified_at' => 'datetime'];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
