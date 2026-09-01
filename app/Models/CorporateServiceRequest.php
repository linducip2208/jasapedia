<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorporateServiceRequest extends Model
{
    public const STATUSES = ["pending_manager", "pending_finance", "approved", "rejected", "converted", "cancelled"];

    protected $fillable = [
        "code", "organization_id", "requested_by", "branch_id", "department_id",
        "cost_center_id", "category_id", "title", "description", "estimated_amount",
        "status", "manager_approved_by", "manager_approved_at",
        "finance_approved_by", "finance_approved_at", "order_id", "po_reference",
    ];

    protected function casts(): array
    {
        return ["manager_approved_at" => "datetime", "finance_approved_at" => "datetime"];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(CorporateOrganization::class, "organization_id");
    }
}
