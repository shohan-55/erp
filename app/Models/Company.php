<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = ['name', 'slug', 'is_active', 'timezone'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
