<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoMetadata extends Model
{
    protected $fillable = [
        "page_type", "category_id", "city", "canonical_url", "meta_title",
        "meta_description", "og_image", "noindex", "h1", "intro_copy",
    ];

    protected function casts(): array
    {
        return ["noindex" => "boolean"];
    }
}
