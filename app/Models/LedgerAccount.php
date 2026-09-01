<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LedgerAccount extends Model
{
    public const TYPES = ['asset', 'liability', 'revenue', 'expense', 'equity'];

    protected $fillable = ['code', 'name', 'type', 'owner_type', 'owner_id'];

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
