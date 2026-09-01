<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    protected $fillable = ['user_id', 'avatar_path', 'gender', 'birth_date', 'city', 'bio', 'meta'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'meta' => 'array',
        ];
    }
}
