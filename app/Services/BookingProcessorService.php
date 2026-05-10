<?php

namespace App\Services;

use App\Models\ExtensionBooking;
use App\Models\ExtensionParser;
use App\Models\ExtensionParserRule;
use App\Models\ProcessedBooking;
use Carbon\Carbon;

class BookingProcessorService
{
    public function __construct(private HellOotelLookupService $lookup) {}

    public function process(ExtensionBooking $booking): ProcessedBooking
    {
        if ($booking->processed_booking_id) {
            return ProcessedBooking::findOrFail($booking->processed_booking_id);
        }

        [$arrival, $departure] = $this->parseStayDates($booking->stay_dates);
        [$price, $currency, $commission] = $this->parseTotalPrice($booking->total_price);

        $tourists = $booking->tourists ?: [];
        $guestInfo = collect($tourists)
            ->map(fn($t) => trim(($t['last_name'] ?? '') . ' ' . ($t['first_name'] ?? '')))
            ->filter()
            ->implode(', ') ?: null;

        $fieldMap = $this->getFieldMap($booking);

        [$hotelId, $roomTypeId, $roomTypeName] = $this->matchHotelAndRoom($booking, $fieldMap);

        [$adults, $children, $infants] = $this->parseGuestCounts($booking);

        $processed = ProcessedBooking::create([
            'source_booking_id'     => $booking->id,
            'saved_by_user_id'      => $booking->user_id,
            'booking_code'          => $booking->booking_code,
            'hotel_name'            => $booking->hotel_name,
            'tourists'              => $tourists,
            'tourist_ids'           => [],
            'guest_info'            => $guestInfo,
            'hotel_id'              => $hotelId,
            'room_type_id'          => $roomTypeId,
            'room_type_name'        => $roomTypeName ?? $this->resolveField($booking, $fieldMap, 'room_type_name', $booking->subtitle),
            'operator_id'           => null,
            'operator_name'         => $this->resolveField($booking, $fieldMap, 'operator_name', null),
            'reservation_date'       => $this->tryParseDate($this->extractDatePart($booking->reservation_at ?? '')),
            'reservation_time'       => $this->extractTimePart($booking->reservation_at ?? ''),
            'arrival_at'            => $arrival,
            'departure_at'          => $departure,
            'nights'                => $this->resolveField($booking, $fieldMap, 'nights', $booking->nights ?? $this->calcNights($arrival, $departure)),
            'agency_id'             => null,
            'agency_name'           => $this->resolveField($booking, $fieldMap, 'agency_name', $booking->meta['agency'] ?? null),
            'price'                 => $price,
            'currency_code'         => $currency,
            'commission'            => $commission,
            'status'                => $this->resolveField($booking, $fieldMap, 'status', $this->firstStatus($booking->statuses)),
            'person_count_adults'   => $adults,
            'person_count_children' => $children,
            'person_count_teens'    => $infants,
            'total_bonus'           => 0,
            'hm_approval'           => null,
            'payment_status_ag'     => 0,
            'payment_status_rm'     => 0,
            'payment_status_cm'     => 0,
        ]);

        $booking->update(['processed_booking_id' => $processed->id]);

        return $processed;
    }

    // Try to match hotel and room type from raw booking data; returns [hotel_id, room_type_id, room_type_name]
    public function matchHotelAndRoom(ExtensionBooking $booking, ?array $fieldMap = null): array
    {
        $fieldMap ??= $this->getFieldMap($booking);

        $hotelId      = null;
        $roomTypeId   = null;
        $roomTypeName = null;

        if ($booking->hotel_name) {
            $hotel = $this->lookup->findHotel($booking->hotel_name);
            if ($hotel) {
                $hotelId = $hotel['id'];

                $roomCandidate = $this->resolveField($booking, $fieldMap, 'room_type_name', $booking->subtitle ?? $booking->meal_plan ?? null);
                if ($roomCandidate) {
                    $room = $this->lookup->findRoomType($hotelId, $roomCandidate);
                    if ($room) {
                        $roomTypeId   = $room['id'];
                        $roomTypeName = $room['name'];
                    }
                }
            }
        }

        return [$hotelId, $roomTypeId, $roomTypeName];
    }

    // Load field_map from the parser config for this booking's domain/path
    private function getFieldMap(ExtensionBooking $booking): array
    {
        if (!$booking->source_domain) return [];

        $parser = $this->resolveParser($booking->source_domain, $booking->source_url ?? '');
        return $parser['field_map'] ?? [];
    }

    // Resolve parser config array for a given domain + URL (checks rules first, then direct domain match)
    private function resolveParser(string $domain, string $url): array
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        // Check parser rules (best-match: longest path_match prefix wins)
        $rule = ExtensionParserRule::where('domain', $domain)
            ->get()
            ->filter(fn($r) => $r->path_match === '' || str_starts_with($path, $r->path_match))
            ->sortByDesc(fn($r) => strlen($r->path_match))
            ->first();

        if ($rule) {
            $parser = ExtensionParser::where('name', $rule->parser)->where('is_active', true)->first();
            if ($parser) return $parser->config ?? [];
        }

        // Direct domain match
        $parser = ExtensionParser::where('domain', $domain)
            ->where('is_active', true)
            ->get()
            ->filter(fn($p) => $p->path_match === null || $p->path_match === '' || str_starts_with($path, $p->path_match))
            ->sortByDesc(fn($p) => strlen($p->path_match ?? ''))
            ->first();

        return $parser ? ($parser->config ?? []) : [];
    }

    // Resolve a value from ExtensionBooking using field_map path, or return $default
    // Supported paths: "field_name", "meta.key", "statuses.N" (N = integer index)
    private function resolveField(ExtensionBooking $booking, array $fieldMap, string $key, mixed $default = null): mixed
    {
        if (!isset($fieldMap[$key])) return $default;

        $path  = $fieldMap[$key];
        $parts = explode('.', $path, 2);

        if (count($parts) === 1) {
            return $booking->{$parts[0]} ?? $default;
        }

        [$root, $subkey] = $parts;

        if ($root === 'meta') {
            return ($booking->meta[$subkey] ?? null) ?? $default;
        }

        if ($root === 'statuses' && is_numeric($subkey)) {
            return ($booking->statuses[(int) $subkey] ?? null) ?? $default;
        }

        return $default;
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
        $s = trim($s, " \t\n\r\0\x0B()");
        foreach (['d.m.Y H:i', 'd.m.Y', 'd/m/Y H:i', 'd/m/Y', 'Y-m-d', 'd.m.y', 'd-m-Y'] as $fmt) {
            try {
                $d = Carbon::createFromFormat($fmt, $s);
                if ($d) return $d->format('Y-m-d');
            } catch (\Exception) {}
        }
        return null;
    }

    // Returns just the date part from "30.04.2026 17:03" → "30.04.2026"
    private function extractDatePart(string $s): string
    {
        return trim(explode(' ', trim($s))[0]);
    }

    // Returns just HH:MM from "30.04.2026 17:03" → "17:03", or null
    private function extractTimePart(string $s): ?string
    {
        $s = trim($s);
        if ($s === '') return null;
        $parts = explode(' ', $s);
        if (count($parts) < 2) return null;
        $time = trim($parts[1]);
        return preg_match('/^\d{1,2}:\d{2}/', $time) ? substr($time, 0, 5) : null;
    }

    private function parseTotalPrice(?string $raw): array
    {
        if (!$raw) {
            return [null, null, null];
        }

        $commission = null;

        // "1 904€ / 190,4€" — split price and commission
        if (str_contains($raw, ' / ')) {
            [$pricePart, $commissionPart] = array_map('trim', explode(' / ', $raw, 2));
            $commission = $this->extractNumeric($commissionPart);
            $raw = $pricePart;
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

        return [$this->extractNumeric($raw), $currency, $commission];
    }

    private function extractNumeric(string $raw): ?float
    {
        $digits = preg_replace('/[^\d.,]/', '', $raw);
        if ($digits === '') return null;
        if (str_contains($digits, ',') && str_contains($digits, '.')) {
            if (strrpos($digits, ',') > strrpos($digits, '.')) {
                $digits = str_replace(['.', ','], ['', '.'], $digits);
            } else {
                $digits = str_replace(',', '', $digits);
            }
        } elseif (str_contains($digits, ',')) {
            $digits = str_replace(['.', ','], ['', '.'], $digits);
        }
        return is_numeric($digits) ? (float) $digits : null;
    }

    private function calcNights(?string $arrival, ?string $departure): ?int
    {
        if (!$arrival || !$departure) return null;
        try {
            return Carbon::parse($arrival)->diffInDays(Carbon::parse($departure));
        } catch (\Exception) {
            return null;
        }
    }

    private function firstStatus(?array $statuses): ?string
    {
        if (empty($statuses)) return null;
        return array_values(array_filter($statuses))[0] ?? null;
    }

    // Returns [adults, children, infants] — uses explicit booking fields first,
    // then falls back to parsing "1 ADL , 2 CHD"-style strings from guests.
    private function parseGuestCounts(ExtensionBooking $booking): array
    {
        $adults   = $booking->adults;
        $children = $booking->children;
        $infants  = $booking->infants;

        if ($adults === null && $children === null && $booking->guests) {
            $g = $booking->guests;
            if (preg_match('/(\d+)\s*ADL/i', $g, $m)) $adults   = (int) $m[1];
            if (preg_match('/(\d+)\s*CHD/i', $g, $m)) $children = (int) $m[1];
            if (preg_match('/(\d+)\s*INF/i', $g, $m)) $infants  = (int) $m[1];
        }

        // Also derive from tourists array if still missing
        if ($adults === null && !empty($booking->tourists)) {
            $adults = count($booking->tourists);
        }

        return [$adults ?? 0, $children ?? 0, $infants ?? 0];
    }
}
