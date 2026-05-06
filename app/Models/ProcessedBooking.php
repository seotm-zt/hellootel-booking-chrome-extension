<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessedBooking extends Model
{
    protected $fillable = [
        'source_booking_id',
        'booking_code',
        'tourists',
        'tourist_ids',
        'guest_info',
        'hotel_id',
        'room_type_id',
        'room_type_name',
        'operator_id',
        'operator_name',
        'reservation_at',
        'arrival_at',
        'departure_at',
        'agency_id',
        'price',
        'currency_code',
        'person_count_adults',
        'person_count_children',
        'person_count_teens',
        'total_bonus',
        'hm_approval',
        'payment_status_ag',
        'payment_status_rm',
        'payment_status_cm',
    ];

    protected function casts(): array
    {
        return [
            'tourists'     => 'array',
            'tourist_ids'  => 'array',
            'reservation_at' => 'date',
            'arrival_at'   => 'date',
            'departure_at' => 'date',
            'price'        => 'float',
        ];
    }

    public function sourceBooking(): BelongsTo
    {
        return $this->belongsTo(ExtensionBooking::class, 'source_booking_id');
    }
}
