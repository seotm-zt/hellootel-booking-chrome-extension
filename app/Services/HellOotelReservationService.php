<?php

namespace App\Services;

use App\Models\ExtensionPageReport;
use App\Models\ExtensionParser;
use App\Models\ProcessedBooking;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HellOotelReservationService
{
    private string $base;
    private string $token;

    public function __construct(string $token)
    {
        $this->base  = rtrim(config('services.hellootel.base'), '/');
        $this->token = $token;
    }

    /**
     * Send a confirmed booking to HellOotel reservation API.
     * Returns ['id' => ?int, 'error' => ?string].
     * 'error' is null on success or skip; non-null string on API failure.
     */
    public function send(ProcessedBooking $processed): array
    {
        if (!$processed->hotel_id) {
            return ['id' => null, 'error' => null];
        }

        if ($processed->hellootel_reservation_id) {
            return ['id' => $processed->hellootel_reservation_id, 'error' => null];
        }

        $this->syncOperatorFromParser($processed);

        $payload = $this->buildPayload($processed);

        $url = $this->base . '/booking-saver/create-reservation?hotel_id=' . $processed->hotel_id;

        Log::info('HellOotel reservation send', [
            'processed_id' => $processed->id,
            'url'          => $url,
            'payload'      => $payload,
            'db_state'     => [
                'hotel_id'              => $processed->hotel_id,
                'hotel_name'            => $processed->hotel_name,
                'room_type_id'          => $processed->room_type_id,
                'room_type_name'        => $processed->room_type_name,
                'arrival_at'            => $processed->arrival_at?->format('Y-m-d'),
                'departure_at'          => $processed->departure_at?->format('Y-m-d'),
                'person_count_adults'   => $processed->person_count_adults,
                'person_count_children' => $processed->person_count_children,
                'person_count_teens'    => $processed->person_count_teens,
                'tourists'              => $processed->tourists,
                'booking_code'          => $processed->booking_code,
                'price'                 => $processed->price,
                'currency_code'         => $processed->currency_code,
                'hotel_vote'            => $processed->hotel_vote,
                'operator_id'           => $processed->operator_id,
                'guest_info'            => $processed->guest_info,
            ],
        ]);

        $response = Http::timeout(15)
            ->withBasicAuth($this->token, '')
            ->post($url, $payload);

        $body = $response->json() ?? $response->body();

        Log::info('HellOotel reservation response', [
            'processed_id' => $processed->id,
            'status'       => $response->status(),
            'body'         => $body,
        ]);

        $responseJson = is_array($body)
            ? json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            : (string) $body;

        if (!$response->successful()) {
            $errorMsg = is_array($body)
                ? ($body['error'] ?? $body['message'] ?? json_encode($body))
                : (string) $body;

            $processed->hellootel_response = $responseJson;
            $processed->saveQuietly();

            return ['id' => null, 'error' => $errorMsg ?: "HTTP {$response->status()}"];
        }

        $data          = is_array($body) ? $body : [];
        $reservationId = $data['id'] ?? $data['reservation_id'] ?? null;

        $processed->hellootel_reservation_id = $reservationId;
        $processed->hellootel_sent_at        = now();
        $processed->hellootel_response       = $responseJson;
        $processed->saveQuietly();

        return ['id' => $reservationId ? (int) $reservationId : null, 'error' => null];
    }

    private function syncOperatorFromParser(ProcessedBooking $processed): void
    {
        if ($processed->operator_id) {
            return;
        }

        $source = $processed->sourceBooking;
        if (!$source) {
            return;
        }

        $domain = $this->resolveRealDomain($source);
        if (!$domain) {
            return;
        }

        $parser = ExtensionParser::where('domain', $domain)
            ->where('is_active', true)
            ->whereNotNull('operator_id')
            ->first();

        if ($parser) {
            $processed->operator_id   = $parser->operator_id;
            $processed->operator_name = $parser->operator_name;
            $processed->saveQuietly();
        }
    }

    // When booking was saved from a preview page (booking.localhost/admin/.../html),
    // the real domain lives in the corresponding ExtensionPageReport::url.
    private function resolveRealDomain(\App\Models\ExtensionBooking $source): ?string
    {
        $domain = $source->source_domain;

        if ($domain && $domain !== 'booking.localhost') {
            return $domain;
        }

        // Extract page report ID from preview URL, e.g. /admin/extension/page-reports/21/html
        if (preg_match('#/page-reports/(\d+)/#', $source->source_url ?? '', $m)) {
            $report = ExtensionPageReport::find((int) $m[1]);
            if ($report?->url) {
                return parse_url($report->url, PHP_URL_HOST) ?: null;
            }
        }

        return null;
    }

    // Root URL of the original site the booking came from, e.g.
    // https://velikolepniy-vek.com/. Only parser bookings have a source page;
    // manual entries (source_booking_id = null) return null and the field is
    // dropped from the payload.
    private function originWebsite(ProcessedBooking $processed): ?string
    {
        $source = $processed->sourceBooking;
        if (!$source) {
            return null;
        }

        $host = $this->resolveRealDomain($source);

        return $host ? 'https://' . $host . '/' : null;
    }

    private function buildGuestName(ProcessedBooking $processed): ?string
    {
        $tourists = $processed->tourists ?? [];
        if (!empty($tourists)) {
            return collect($tourists)
                ->map(fn($t) => trim(($t['last_name'] ?? '') . ' ' . ($t['first_name'] ?? '')))
                ->filter()
                ->implode(', ') ?: null;
        }

        return $processed->guest_info ?: null;
    }

    private function buildPayload(ProcessedBooking $processed): array
    {
        $operatorReservationAt = null;
        if ($processed->reservation_date) {
            $operatorReservationAt = $processed->reservation_date;
        }

        $payload = [
            'arrival_at'                 => $processed->arrival_at?->format('Y-m-d'),
            'departure_at'               => $processed->departure_at?->format('Y-m-d'),
            'room_type'                  => $processed->room_type_id,
            'person_count_adults'        => $processed->person_count_adults,
            'person_count_teens'         => $processed->person_count_children,
            'person_count_children'      => $processed->person_count_teens,
            'guest_name'                 => $this->buildGuestName($processed),
            'operator_id'                => $processed->operator_id,
            'operator_reservation_at'    => $operatorReservationAt,
            'service_number'             => $processed->booking_code,
            'tour_price_native'          => $processed->price,
            'tour_price_native_currency' => $processed->currency_code,
            'vote'                       => $processed->hotel_vote,
            // 1 = parsed (automatic) save, 2 = manual entry (no source booking)
            'chrome_extension_booking_type'   => $processed->source_booking_id ? 1 : 2,
            'chrome_extension_origin_website' => $this->originWebsite($processed),
        ];

        // Remove null/empty values — only send fields that are actually set
        return array_filter($payload, fn($v) => $v !== null && $v !== '');
    }
}
