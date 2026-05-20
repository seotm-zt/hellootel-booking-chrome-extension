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
    public function getRoomTypes(int $hotelId): array
    {
        $items = $this->http()
            ->get($this->base . '/hotel/room-types', [
                'hotel_id'               => $hotelId,
                'bonus_reservation_mode' => 0,
                'language'               => 'en',
            ])
            ->json() ?? [];

        if (is_array($items) && !empty($items) && is_array(reset($items))) {
            return collect($items)->mapWithKeys(fn($t) => [$t['id'] => $t['name']])->all();
        }

        return $items;
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
    public function findRoomType(int $hotelId, string $rawName): ?array
    {
        $types     = $this->getRoomTypes($hotelId);
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
