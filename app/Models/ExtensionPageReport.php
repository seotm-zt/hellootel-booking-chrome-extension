<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtensionPageReport extends Model
{
    protected $fillable = [
        'url',
        'title',
        'html',
    ];
}
