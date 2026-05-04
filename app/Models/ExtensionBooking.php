<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtensionBooking extends Model
{
    protected $fillable = [
        'user_id',
        'saved_by',
        'booking_code',
        'hotel_name',
        'subtitle',
        'stay_dates',
        'guests',
        'meal_plan',
        'transfer',
        'total_price',
        'statuses',
        'meta',
        'details_link',
        'thumbnail',
        'source_url',
        'source_domain',
        'page_title',
        'language',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'statuses'    => 'array',
            'meta'        => 'array',
            'captured_at' => 'datetime',
        ];
    }
}
