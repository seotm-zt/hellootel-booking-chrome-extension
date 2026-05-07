<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class HellOotelLookupService
{
    private const API_BASE  = 'https://demo.hellootel.com/api/v1';
    private const API_TOKEN = 'D-JG2YaHw66wiwv3NKQXa09tnAP9TU3z';
    private const TTL       = 86400; // 24 hours

    // Returns [id => name]
    public function getHotels(): array
    {
        return Cache::remember('hellootel.hotels', self::TTL, function () {
            $items = Http::timeout(15)
                ->withBasicAuth(self::API_TOKEN, '')
                ->get(self::API_BASE . '/hotel/list', ['language' => 'en'])
                ->json() ?? [];

            return collect($items)->mapWithKeys(fn($h) => [$h['id'] => $h['name']])->all();
        });
    }

    // Returns [id => name]
    public function getRoomTypes(int $hotelId): array
    {
        return Cache::remember("hellootel.room_types.{$hotelId}", self::TTL, function () use ($hotelId) {
            $items = Http::timeout(15)
                ->withBasicAuth(self::API_TOKEN, '')
                ->get(self::API_BASE . '/hotel/room-types', [
                    'hotel_id'               => $hotelId,
                    'bonus_reservation_mode' => 0,
                    'language'               => 'en',
                ])
                ->json() ?? [];

            // API returns array of objects [{id, name, ...}] — normalise to [id => name]
            if (is_array($items) && !empty($items) && is_array(reset($items))) {
                return collect($items)->mapWithKeys(fn($t) => [$t['id'] => $t['name']])->all();
            }

            return $items; // already [id => name]
        });
    }

    // Returns ['id' => int, 'name' => string, 'score' => int] or null
    public function findHotel(string $rawName): ?array
    {
        $hotels  = $this->getHotels();
        $needle  = $this->normalize($rawName);
        $best    = null;
        $bestScore = 0;

        foreach ($hotels as $id => $name) {
            $score = $this->similarity($needle, $this->normalize($name));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = ['id' => (int) $id, 'name' => $name, 'score' => $score];
            }
        }

        // Require at least 60% similarity to avoid false positives
        return ($bestScore >= 60) ? $best : null;
    }

    // Returns ['id' => int, 'name' => string] or null
    public function findRoomType(int $hotelId, string $rawName): ?array
    {
        $types  = $this->getRoomTypes($hotelId);
        $needle = $this->normalize($rawName);
        $best   = null;
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
        return Cache::remember('hellootel.operators', self::TTL, function () {
            $items = Http::timeout(15)
                ->withBasicAuth(self::API_TOKEN, '')
                ->get(self::API_BASE . '/operator/list', ['language' => 'en'])
                ->json() ?? [];

            if (is_array($items) && !empty($items) && is_array(reset($items))) {
                return collect($items)->mapWithKeys(fn($o) => [$o['id'] => $o['name']])->all();
            }

            return $items;
        });
    }

    public function clearCache(): void
    {
        Cache::forget('hellootel.hotels');
        // Room type caches are per-hotel; clear all known hotels
        foreach (array_keys($this->getHotels()) as $hotelId) {
            Cache::forget("hellootel.room_types.{$hotelId}");
        }
    }

    // Normalize: lowercase, remove stars (5*, 4*...), brackets content, extra spaces
    private function normalize(string $s): string
    {
        $s = mb_strtolower($s);
        $s = preg_replace('/\d\*/', '', $s);       // "5*" → ""
        $s = preg_replace('/\(.*?\)/', '', $s);    // "(Botanik)" → ""
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }

    // Returns 0-100 similarity score
    private function similarity(string $a, string $b): int
    {
        if ($a === $b) return 100;
        if ($a === '' || $b === '') return 0;

        // One contains the other
        if (str_contains($a, $b) || str_contains($b, $a)) return 85;

        // similar_text percentage
        similar_text($a, $b, $pct);

        return (int) round($pct);
    }
}
