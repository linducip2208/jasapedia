<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rfq extends Model
{
    protected $table = 'rfqs';

    protected $fillable = [
        'code', 'user_id', 'category_id', 'title', 'description', 'requirements',
        'attachments', 'deadline', 'invited_partner_ids', 'visibility', 'status', 'is_demo',
    ];

    protected function casts(): array
    {
        return ['requirements' => 'array', 'attachments' => 'array', 'deadline' => 'datetime', 'invited_partner_ids' => 'array'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }
}
