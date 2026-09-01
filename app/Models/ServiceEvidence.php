<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceEvidence extends Model
{
    protected $table = 'service_evidences';

    protected $fillable = ['order_id', 'uploaded_by', 'stage', 'file_path', 'kind', 'note'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
