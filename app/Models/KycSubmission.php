<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycSubmission extends Model
{
    public const STATUSES = ['draft', 'submitted', 'under_review', 'needs_revision', 'verified', 'rejected', 'suspended'];

    protected $fillable = [
        'partner_id', 'kind', 'identity', 'company', 'documents',
        'bank_account', 'status', 'review_notes', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['identity' => 'array', 'company' => 'array', 'documents' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
