<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtensionParser extends Model
{
    protected $fillable = [
        'name',
        'domain',
        'path_match',
        'config',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'config'    => 'array',
        'is_active' => 'boolean',
    ];
}
