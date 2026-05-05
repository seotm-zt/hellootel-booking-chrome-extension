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
        'adults',
        'children',
        'infants',
        'meal_plan',
        'transfer',
        'total_price',
        'statuses',
        'tourists',
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
            'tourists'    => 'array',
            'meta'        => 'array',
            'captured_at' => 'datetime',
        ];
    }
}
