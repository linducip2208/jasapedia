<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiskFlag extends Model
{
    public const LEVELS = ['low', 'medium', 'high'];

    protected $fillable = [
        'subject_type', 'subject_id', 'flag', 'risk_level', 'detail', 'status', 'resolved_by',
    ];

    public function subject()
    {
        return $this->morphTo();
    }
}
