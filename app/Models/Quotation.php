<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quotation extends Model
{
    public const STATUSES = ['draft', 'sent', 'approved', 'rejected', 'expired', 'superseded'];

    protected $fillable = [
        'code', 'rfq_id', 'order_id', 'partner_id', 'customer_id', 'version',
        'line_items', 'subtotal', 'tax', 'discount', 'total', 'terms',
        'valid_until', 'attachments', 'status', 'approved_by', 'decided_at', 'is_demo',
    ];

    protected function casts(): array
    {
        return ['line_items' => 'array', 'attachments' => 'array', 'valid_until' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
