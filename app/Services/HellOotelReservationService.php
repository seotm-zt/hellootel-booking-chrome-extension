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
            Log::info('HellOotel reservation skipped: no hotel_id', ['id' => $processed->id]);
            return ['id' => null, 'error' => null];
        }

        if ($processed->hellootel_reservation_id) {
            return ['id' => $processed->hellootel_reservation_id, 'error' => null];
        }

        $this->syncOperatorFromParser($processed);

        $payload = $this->buildPayload($processed);

        $url = $this->base . '/reservation/create?hotel_id=' . $processed->hotel_id;

        Log::info('HellOotel reservation request', [
            'processed_id' => $processed->id,
            'url'          => $url,
            'token_prefix' => substr($this->token, 0, 8) . '...',
            'payload'      => $payload,
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

    public function sendVote(ProcessedBooking $processed): array
    {
        if (!$processed->hotel_id) {
            return ['error' => 'No hotel_id set'];
        }

        if ($processed->hotel_vote === null) {
            return ['error' => 'No rating set'];
        }

        $url = $this->base . '/hotel/vote?hotel_id=' . $processed->hotel_id;

        Log::info('HellOotel send vote', [
            'processed_id' => $processed->id,
            'hotel_id'     => $processed->hotel_id,
            'vote'         => $processed->hotel_vote,
        ]);

        try {
            $response = Http::timeout(15)
                ->withBasicAuth($this->token, '')
                ->post($url, ['vote' => (int) ceil($processed->hotel_vote / 2)]);

            $body = $response->json() ?? $response->body();

            Log::info('HellOotel vote response', [
                'processed_id' => $processed->id,
                'status'       => $response->status(),
                'body'         => $body,
            ]);

            if (!$response->successful()) {
                $errorMsg = is_array($body) ? ($body['message'] ?? json_encode($body)) : (string) $body;
                return ['error' => $errorMsg ?: "HTTP {$response->status()}"];
            }

            return ['error' => null];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
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

    private function buildGuestName(ProcessedBooking $processed): ?string
    {
        if ($processed->guest_info) {
            return $processed->guest_info;
        }

        $tourists = $processed->tourists ?? [];
        if (empty($tourists)) {
            return null;
        }

        return collect($tourists)
            ->map(fn($t) => trim(($t['last_name'] ?? '') . ' ' . ($t['first_name'] ?? '')))
            ->filter()
            ->implode(', ') ?: null;
    }

    private function buildPayload(ProcessedBooking $processed): array
    {
        $operatorReservationAt = null;
        if ($processed->reservation_date) {
            $operatorReservationAt = $processed->reservation_date;
            if ($processed->reservation_time) {
                $operatorReservationAt .= ' ' . $processed->reservation_time;
            }
        }

        $payload = [
            'arrival_at'                 => $processed->arrival_at?->format('Y-m-d'),
            'departure_at'               => $processed->departure_at?->format('Y-m-d'),
            'room_type'                  => $processed->room_type_id,
            'person_count_adults'        => $processed->person_count_adults,
            'person_count_teens'         => $processed->person_count_teens,
            'person_count_children'      => $processed->person_count_children,
            'guest_name'                 => $this->buildGuestName($processed),
            'operator_id'                => $processed->operator_id,
            'operator_reservation_at'    => $operatorReservationAt,
            'service_number'             => $processed->booking_code,
            'tour_price_native'          => $processed->price,
            'tour_price_native_currency' => $processed->currency_code,
        ];

        // Remove null/empty values — only send fields that are actually set
        return array_filter($payload, fn($v) => $v !== null && $v !== '');
    }
}
