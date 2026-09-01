<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerAddress extends Model
{
    protected $fillable = [
        'user_id', 'label', 'recipient_name', 'phone', 'subdistrict_id',
        'address_line', 'notes', 'lat', 'lng', 'is_default',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'lat' => 'decimal:7', 'lng' => 'decimal:7'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subdistrict(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'subdistrict_id');
    }
}
