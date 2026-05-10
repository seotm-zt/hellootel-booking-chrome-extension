<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessedBooking extends Model
{
    protected $fillable = [
        'source_booking_id',
        'saved_by_user_id',
        'booking_code',
        'hotel_name',
        'tourists',
        'tourist_ids',
        'guest_info',
        'hotel_id',
        'room_type_id',
        'room_type_name',
        'operator_id',
        'operator_name',
        'reservation_date',
        'reservation_time',
        'arrival_at',
        'departure_at',
        'nights',
        'agency_id',
        'agency_name',
        'price',
        'currency_code',
        'commission',
        'status',
        'person_count_adults',
        'person_count_children',
        'person_count_teens',
        'total_bonus',
        'hm_approval',
        'payment_status_ag',
        'payment_status_rm',
        'payment_status_cm',
        'confirmed_by_user_id',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'tourists'     => 'array',
            'tourist_ids'  => 'array',
            'arrival_at'     => 'date',
            'departure_at'   => 'date',
            'price'        => 'float',
            'commission'   => 'float',
            'confirmed_at' => 'datetime',
        ];
    }

    public function sourceBooking(): BelongsTo
    {
        return $this->belongsTo(ExtensionBooking::class, 'source_booking_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (self $record) {
            if ($source = $record->sourceBooking) {
                ExtensionBooking::withoutEvents(fn () => $source->delete());
            }
        });
    }
}
