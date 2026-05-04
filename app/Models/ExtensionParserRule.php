<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtensionParserRule extends Model
{
    protected $fillable = [
        'domain',
        'path_match',
        'parser',
        'notes',
    ];
}
