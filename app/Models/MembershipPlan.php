<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipPlan extends Model
{
    protected $fillable = ['name', 'audience', 'price_monthly', 'price_yearly', 'benefits', 'is_active'];

    protected function casts(): array
    {
        return ['benefits' => 'array', 'is_active' => 'boolean'];
    }
}
