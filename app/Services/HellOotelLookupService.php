<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class HellOotelLookupService
{
    private string $base;
    private string $token;

    public function __construct(string $token)
    {
        $this->base  = rtrim(config('services.hellootel.base'), '/');
        $this->token = $token;
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout(15)->withBasicAuth($this->token, '');
    }

    // Returns [id => name]
    public function getHotels(): array
    {
        $items = $this->http()
            ->get($this->base . '/hotel/list', ['language' => 'en'])
            ->json() ?? [];

        return collect($items)->mapWithKeys(fn($h) => [$h['id'] => $h['name']])->all();
    }

    // Returns [id => name]
    public function getRoomTypes(int $hotelId, ?string $arrivalAt = null, ?string $departureAt = null): array
    {
        $arrivalAt   ??= now()->subDay()->format('Y-m-d');
        $departureAt ??= now()->format('Y-m-d');

        $url  = $this->base . '/hotel/room-types';
        $body = $this->http()->get($url, [
            'hotel_id'              => $hotelId,
            'bonus_reservation_mode' => 0,
            'arrival_at'            => $arrivalAt,
            'departure_at'          => $departureAt,
            'language'              => 'en',
        ])->json() ?? [];

        // Response is a flat {id: name} map
        return is_array($body) ? $body : [];
    }

    // Returns ['id' => int, 'name' => string, 'score' => int] or null
    public function findHotel(string $rawName): ?array
    {
        $hotels    = $this->getHotels();
        $needle    = $this->normalize($rawName);
        $best      = null;
        $bestScore = 0;

        foreach ($hotels as $id => $name) {
            $score = $this->similarity($needle, $this->normalize($name));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = ['id' => (int) $id, 'name' => $name, 'score' => $score];
            }
        }

        return ($bestScore >= 60) ? $best : null;
    }

    // Returns ['id' => int, 'name' => string] or null
    public function findRoomType(int $hotelId, string $rawName, ?string $arrivalAt = null, ?string $departureAt = null): ?array
    {
        $types     = $this->getRoomTypes($hotelId, $arrivalAt, $departureAt);
        $needle    = $this->normalize($rawName);
        $best      = null;
        $bestScore = 0;

        foreach ($types as $id => $name) {
            $score = $this->similarity($needle, $this->normalize($name));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = ['id' => (int) $id, 'name' => $name, 'score' => $score];
            }
        }

        return ($bestScore >= 55) ? $best : null;
    }

    // Returns current vote (0-100) for a hotel, or null on failure.
    public function getHotelVote(int $hotelId): ?int
    {
        try {
            $response = $this->http()->get($this->base . '/hotel/vote', ['hotel_id' => $hotelId]);
            if (!$response->successful()) return null;
            $body = $response->json();
            if (is_int($body) || is_float($body)) return (int) $body;
            if (is_array($body) && isset($body['vote'])) return (int) $body['vote'];
            return null;
        } catch (\Exception) {
            return null;
        }
    }

    // Returns [id => name]
    public function getOperators(): array
    {
        $items = $this->http()
            ->get($this->base . '/operator/list', ['language' => 'en'])
            ->json() ?? [];

        if (is_array($items) && !empty($items) && is_array(reset($items))) {
            return collect($items)->mapWithKeys(fn($o) => [$o['id'] => $o['name']])->all();
        }

        return $items;
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower($s);
        $s = preg_replace('/\d\*/', '', $s);
        $s = preg_replace('/\(.*?\)/', '', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }

    private function similarity(string $a, string $b): int
    {
        if ($a === $b) return 100;
        if ($a === '' || $b === '') return 0;
        if (str_contains($a, $b) || str_contains($b, $a)) return 85;

        similar_text($a, $b, $pct);
        return (int) round($pct);
    }
}
