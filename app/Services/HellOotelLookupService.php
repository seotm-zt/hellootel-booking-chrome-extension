<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class HellOotelLookupService
{
    private string $base;
    private string $token;

    // Per-request memoization — HellOotel reference lists rarely change
    // within a single request, but we hit /hotel/list multiple times
    // (hotels endpoint, findHotel, hotel_match enrichment).
    private ?array $hotelsFullCache = null;
    private ?array $countriesCache  = null;
    private ?array $citiesCache     = null;

    public function __construct(string $token)
    {
        $this->base  = rtrim(config('services.hellootel.base'), '/');
        $this->token = $token;
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout(15)->withBasicAuth($this->token, '');
    }

    // Returns [id => ['name', 'country_id', 'city_id']]
    public function getHotelsFull(): array
    {
        if ($this->hotelsFullCache !== null) return $this->hotelsFullCache;

        $items = $this->http()
            ->get($this->base . '/hotel/list', ['language' => 'en'])
            ->json() ?? [];

        $out = [];
        foreach ($items as $h) {
            if (!isset($h['id'])) continue;
            $out[$h['id']] = [
                'name'       => $h['name'] ?? '',
                'country_id' => $h['country_id'] ?? null,
                'city_id'    => $h['city_id'] ?? null,
            ];
        }
        return $this->hotelsFullCache = $out;
    }

    // Returns [id => name]
    public function getHotels(): array
    {
        return array_map(fn($h) => $h['name'], $this->getHotelsFull());
    }

    // Returns [id => name]
    public function getCountries(): array
    {
        if ($this->countriesCache !== null) return $this->countriesCache;

        $items = $this->http()
            ->get($this->base . '/country/list', ['language' => 'en'])
            ->json() ?? [];

        return $this->countriesCache = $this->mapIdName($items);
    }

    // Returns [id => name]
    public function getCities(): array
    {
        if ($this->citiesCache !== null) return $this->citiesCache;

        $items = $this->http()
            ->get($this->base . '/city/list', ['language' => 'en'])
            ->json() ?? [];

        return $this->citiesCache = $this->mapIdName($items);
    }

    private function mapIdName(array $items): array
    {
        if (empty($items)) return [];
        if (is_array(reset($items))) {
            return collect($items)->mapWithKeys(fn($x) => [$x['id'] => $x['name']])->all();
        }
        return $items; // already a [id => name] flat map
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

    // Returns ['id' => int, 'name' => string, 'score' => int, 'country_id' => ?int, 'city_id' => ?int] or null
    public function findHotel(string $rawName): ?array
    {
        $match = $this->bestMatch($this->getHotels(), $rawName, threshold: 75);
        if ($match) {
            $full = $this->getHotelsFull()[$match['id']] ?? null;
            $match['country_id'] = $full['country_id'] ?? null;
            $match['city_id']    = $full['city_id']    ?? null;
        }
        return $match;
    }

    // Returns ['id' => int, 'name' => string, 'score' => int] or null
    public function findRoomType(int $hotelId, string $rawName, ?string $arrivalAt = null, ?string $departureAt = null): ?array
    {
        return $this->bestMatch(
            $this->getRoomTypes($hotelId, $arrivalAt, $departureAt),
            $rawName,
            threshold: 65,
        );
    }

    /** @param array<int|string,string> $candidates */
    private function bestMatch(array $candidates, string $rawName, int $threshold): ?array
    {
        $needle = $this->normalize($rawName);
        if ($needle === '') return null;

        $best      = null;
        $bestScore = 0;
        foreach ($candidates as $id => $name) {
            $score = $this->similarity($needle, $this->normalize($name));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = ['id' => (int) $id, 'name' => $name, 'score' => $score];
            }
        }
        return ($bestScore >= $threshold) ? $best : null;
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
        $s = preg_replace('/\d\*/u', '', $s);          // strip "5*"
        $s = preg_replace('/\(.*?\)/u', '', $s);       // strip "(City)"
        $s = preg_replace('/[,.&\-\/]+/u', ' ', $s);   // punctuation → space
        $s = preg_replace('/\s+/u', ' ', $s);
        return trim($s);
    }

    /** @return list<string> */
    private function tokens(string $normalized): array
    {
        if ($normalized === '') return [];
        return array_values(array_filter(
            preg_split('/\s+/u', $normalized) ?: [],
            fn($t) => mb_strlen($t) >= 2,
        ));
    }

    /**
     * Hybrid token-based similarity:
     *   - identical → 100
     *   - one string fully contains the other → 85
     *   - Jaccard over token sets, +10 bonus if the first token matches (brand)
     *   - fallback to similar_text only for high-confidence typo matches (≥90%)
     *
     * Token approach beats raw similar_text() because hotel names share many
     * common words ("hotel", "resort", "spa") — character-by-character match
     * gave inflated scores to clearly different hotels.
     */
    private function similarity(string $a, string $b): int
    {
        if ($a === $b) return 100;
        if ($a === '' || $b === '') return 0;
        if (str_contains($a, $b) || str_contains($b, $a)) return 85;

        $ta = $this->tokens($a);
        $tb = $this->tokens($b);
        if (!$ta || !$tb) return 0;

        $setA = array_unique($ta);
        $setB = array_unique($tb);
        $intersect = count(array_intersect($setA, $setB));
        $union     = count(array_unique(array_merge($setA, $setB)));
        $score     = $union > 0 ? (int) round(100 * $intersect / $union) : 0;

        // Bonus when the first token (brand head) matches — strong signal
        if ($ta[0] === $tb[0]) {
            $score = min(100, $score + 10);
        }

        // Token-subset: one name is a shorter version of the other
        // ("Port Nature Resort" ⊂ "Port Nature Luxury Resort Hotel & Spa")
        if (count(array_diff($setA, $setB)) === 0 || count(array_diff($setB, $setA)) === 0) {
            $score = max($score, 85);
        }

        // Typo fallback: when token sets disagree but raw similarity is very
        // high, it's likely a single-word typo (e.g. "Bello"/"Belo"); take it,
        // but conservatively (capped just below the typo-detection score).
        if ($score < 75) {
            similar_text($a, $b, $pct);
            if ($pct >= 90) {
                $score = max($score, (int) round($pct) - 5);
            }
        }

        return $score;
    }
}
