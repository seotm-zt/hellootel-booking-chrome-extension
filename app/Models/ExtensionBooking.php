<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtensionBooking extends Model
{
    protected $fillable = [
        'user_id',
        'processed_booking_id',
        'saved_by',
        'booking_code',
        'hotel_name',
        'subtitle',
        'stay_dates',
        'reservation_at',
        'nights',
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

    public function processedBooking(): BelongsTo
    {
        return $this->belongsTo(ProcessedBooking::class, 'processed_booking_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (self $record) {
            if ($processed = $record->processedBooking) {
                ProcessedBooking::withoutEvents(fn () => $processed->delete());
            }
        });
    }
}
