<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;

class ApiTesting extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-beaker';
    protected static ?string $navigationLabel = 'Тестирование API';
    protected static ?string $title = 'Тестирование API';
    protected static ?string $navigationGroup = 'HellOotel';
    protected static ?int $navigationSort = 200;

    protected static string $view = 'filament.pages.api-testing';

    public array $responses = [];
    public array $loading = [];
    public array $apiErrors = [];

    public string $hotelId = '32';
    public string $apiToken = 'D-JG2YaHw66wiwv3NKQXa09tnAP9TU3z';

    protected static array $endpoints = [
        'hotels' => [
            'label' => 'Отели',
            'url' => 'https://demo.hellootel.com/api/v1/hotel/list?language=en',
            'params' => [],
        ],
        'room_types' => [
            'label' => 'Типы номеров в отеле',
            'url' => 'https://demo.hellootel.com/api/v1/hotel/room-types',
            'params' => ['hotel_id' => null, 'bonus_reservation_mode' => '0', 'arrival_at' => '', 'departure_at' => '', 'language' => 'en'],
        ],
        'bonus_room_types' => [
            'label' => 'Бонусные номера в отеле',
            'url' => 'https://demo.hellootel.com/api/v1/hotel/bonus-room-types',
            'params' => ['hotel_ids[]' => null, 'language' => 'en'],
        ],
        'reservations' => [
            'label' => 'Список резерва',
            'url' => 'https://demo.hellootel.com/api/v1/reservation/list',
            'params' => [],
        ],
        'operators' => [
            'label' => 'Список операторов',
            'url' => 'https://demo.hellootel.com/api/v1/operator/list?language=en',
            'params' => [],
        ],
    ];

    public function getEndpoints(): array
    {
        return static::$endpoints;
    }

    public function callApi(string $key): void
    {
        $endpoints = static::$endpoints;

        if (!isset($endpoints[$key])) {
            return;
        }

        $this->loading[$key] = true;
        $this->apiErrors[$key] = null;
        unset($this->responses[$key]);

        $endpoint = $endpoints[$key];
        $url = $endpoint['url'];
        $params = $endpoint['params'];

        if (array_key_exists('hotel_id', $params)) {
            $params['hotel_id'] = $this->hotelId;
        }
        if (array_key_exists('hotel_ids[]', $params)) {
            unset($params['hotel_ids[]']);
            $params['hotel_ids'] = [$this->hotelId];
        }

        $query = array_filter($params, fn($v) => $v !== null && $v !== '');

        try {
            $request = Http::timeout(15);
            if ($this->apiToken !== '') {
                $request = $request->withBasicAuth($this->apiToken, '');
            }
            $response = $request->get($url, $query);

            $this->responses[$key] = [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
                'ok' => $response->successful(),
            ];
        } catch (\Exception $e) {
            $this->apiErrors[$key] = $e->getMessage();
        }

        $this->loading[$key] = false;
    }
}
