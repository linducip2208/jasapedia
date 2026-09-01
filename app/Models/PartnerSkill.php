<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerSkill extends Model
{
    protected $fillable = ['partner_id', 'category_id', 'name', 'level'];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
