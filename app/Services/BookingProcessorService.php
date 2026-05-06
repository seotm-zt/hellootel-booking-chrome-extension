<?php

namespace App\Services;

use App\Models\ExtensionBooking;
use App\Models\ProcessedBooking;
use Carbon\Carbon;

class BookingProcessorService
{
    public function process(ExtensionBooking $booking): ProcessedBooking
    {
        if ($booking->processed_booking_id) {
            return ProcessedBooking::findOrFail($booking->processed_booking_id);
        }

        [$arrival, $departure] = $this->parseStayDates($booking->stay_dates);
        [$price, $currency] = $this->parseTotalPrice($booking->total_price);

        $tourists = $booking->tourists ?: [];
        $guestInfo = collect($tourists)
            ->map(fn($t) => trim(($t['last_name'] ?? '') . ' ' . ($t['first_name'] ?? '')))
            ->filter()
            ->implode(', ') ?: null;

        $processed = ProcessedBooking::create([
            'source_booking_id'    => $booking->id,
            'booking_code'         => $booking->booking_code,
            'tourists'             => $tourists,
            'tourist_ids'          => [],
            'guest_info'           => $guestInfo,
            'hotel_id'             => null,
            'room_type_id'         => null,
            'room_type_name'       => null,
            'operator_id'          => null,
            'operator_name'        => null,
            'reservation_at'       => $booking->captured_at?->toDateString(),
            'arrival_at'           => $arrival,
            'departure_at'         => $departure,
            'agency_id'            => null,
            'price'                => $price,
            'currency_code'        => $currency,
            'person_count_adults'  => $booking->adults ?? 0,
            'person_count_children' => $booking->children ?? 0,
            'person_count_teens'   => $booking->infants ?? 0,
            'total_bonus'          => 0,
            'hm_approval'          => null,
            'payment_status_ag'    => 0,
            'payment_status_rm'    => 0,
            'payment_status_cm'    => 0,
        ]);

        $booking->update(['processed_booking_id' => $processed->id]);

        return $processed;
    }

    private function parseStayDates(?string $raw): array
    {
        if (!$raw) {
            return [null, null];
        }

        $normalized = preg_replace('/[\x{2013}\x{2014}\/]|(?<!\d)-(?!\d)/u', '|', $raw);
        $parts = array_map('trim', explode('|', $normalized));

        if (count($parts) < 2) {
            return [null, null];
        }

        return [
            $this->tryParseDate($parts[0]),
            $this->tryParseDate($parts[1]),
        ];
    }

    private function tryParseDate(string $s): ?string
    {
        $s = trim($s);
        foreach (['d.m.Y', 'd/m/Y', 'Y-m-d', 'd.m.y', 'd-m-Y'] as $fmt) {
            try {
                $d = Carbon::createFromFormat($fmt, $s);
                if ($d) return $d->format('Y-m-d');
            } catch (\Exception) {}
        }
        return null;
    }

    private function parseTotalPrice(?string $raw): array
    {
        if (!$raw) {
            return [null, null];
        }

        $currency = null;
        if (preg_match('/\b([A-Z]{3})\b/', strtoupper($raw), $m)) {
            $currency = $m[1];
        } elseif (preg_match('/[€$£¥₽]/', $raw, $m)) {
            $currency = match($m[0]) {
                '€' => 'EUR', '$' => 'USD', '£' => 'GBP',
                '¥' => 'JPY', '₽' => 'RUB', default => null,
            };
        }

        $digits = preg_replace('/[^\d.,]/', '', $raw);
        if (str_contains($digits, ',') && str_contains($digits, '.')) {
            if (strrpos($digits, ',') > strrpos($digits, '.')) {
                $digits = str_replace(['.', ','], ['', '.'], $digits);
            } else {
                $digits = str_replace(',', '', $digits);
            }
        } elseif (str_contains($digits, ',')) {
            $digits = str_replace(['.', ','], ['', '.'], $digits);
        }

        return [is_numeric($digits) ? (float) $digits : null, $currency];
    }
}
