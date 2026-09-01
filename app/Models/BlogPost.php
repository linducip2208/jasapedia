<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogPost extends Model
{
    protected $fillable = ["slug", "title", "excerpt", "content", "author_id", "status", "published_at", "seo"];

    protected function casts(): array
    {
        return ["published_at" => "datetime", "seo" => "array"];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, "author_id");
    }
}
