<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CorporateOrganization extends Model
{
    protected $fillable = ["owner_user_id", "name", "npwp", "billing_email", "settings"];

    protected function casts(): array
    {
        return ["settings" => "array"];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, "owner_user_id");
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(CorporateServiceRequest::class, "organization_id");
    }
}
