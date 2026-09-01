<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorporateApprovalPolicy extends Model
{
    protected $fillable = [
        "organization_id", "threshold", "finance_threshold",
        "require_category_approval", "allowed_categories",
    ];

    protected function casts(): array
    {
        return ["allowed_categories" => "array"];
    }
}
