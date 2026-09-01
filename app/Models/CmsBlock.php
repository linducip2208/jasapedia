<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsBlock extends Model
{
    protected $fillable = ["key", "type", "data", "sort", "is_active"];

    protected function casts(): array
    {
        return ["data" => "array", "is_active" => "boolean"];
    }
}
