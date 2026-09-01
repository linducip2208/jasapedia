<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisputeEvidence extends Model
{
    protected $table = 'dispute_evidences';

    protected $fillable = ['dispute_id', 'uploaded_by', 'kind', 'file_path', 'ref_type', 'ref_id', 'note'];

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class);
    }
}
