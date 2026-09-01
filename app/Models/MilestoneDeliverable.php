<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MilestoneDeliverable extends Model
{
    protected $fillable = ['milestone_id', 'uploaded_by', 'file_path', 'kind', 'note', 'revision'];

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }
}
