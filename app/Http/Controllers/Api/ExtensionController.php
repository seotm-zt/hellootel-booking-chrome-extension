<?php

namespace App\Http\Controllers\Api;

use App\Enums\User\Role;
use App\Http\Controllers\Controller;
use App\Models\ExtensionBooking;
use App\Models\ExtensionPageReport;
use App\Models\ExtensionParser;
use App\Models\ExtensionParserRule;
use App\Models\ProcessedBooking;
use App\Models\User;
use App\Services\BookingProcessorService;
use App\Services\HellOotelLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExtensionController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        if (!$user->hasAnyRole([Role::ADMIN->value, Role::OPERATOR->value])) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        if (!$user->access_token) {
            $user->access_token = Str::random(64);
            $user->save();
        }

        return response()->json([
            'token' => $user->access_token,
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function index(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $bookings = ExtensionBooking::where('user_id', $user->id)
            ->with('processedBooking:id,confirmed_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $bookings]);
    }

    public function store(Request $request, BookingProcessorService $processor, HellOotelLookupService $lookup): JsonResponse
    {
        $data = $request->validate([
            'booking_code'   => 'nullable|string|max:255',
            'hotel_name'     => 'nullable|string|max:500',
            'subtitle'       => 'nullable|string|max:500',
            'stay_dates'     => 'nullable|string|max:255',
            'reservation_at' => 'nullable|string|max:50',
            'nights'         => 'nullable|integer|min:0',
            'guests'         => 'nullable|string|max:255',
            'adults'         => 'nullable|integer|min:0',
            'children'       => 'nullable|integer|min:0',
            'infants'        => 'nullable|integer|min:0',
            'meal_plan'      => 'nullable|string|max:255',
            'transfer'       => 'nullable|string|max:255',
            'total_price'    => 'nullable|string|max:255',
            'statuses'       => 'nullable|array',
            'tourists'       => 'nullable|array',
            'meta'           => 'nullable|array',
            'details_link'   => 'nullable|string|max:1000',
            'thumbnail'      => 'nullable|string|max:1000',
            'source_url'     => 'nullable|string|max:1000',
            'page_title'     => 'nullable|string|max:500',
            'language'       => 'nullable|string|max:10',
            'captured_at'    => 'nullable|date',
        ]);

        /** @var User $user */
        $user = Auth::user();

        $data['user_id']       = $user->id;
        $data['saved_by']      = $user->email;
        $data['source_domain'] = parse_url($data['source_url'] ?? '', PHP_URL_HOST) ?: null;

        if (!empty($data['booking_code']) && $data['source_domain']) {
            $booking = ExtensionBooking::updateOrCreate(
                [
                    'user_id'       => $user->id,
                    'source_domain' => $data['source_domain'],
                    'booking_code'  => $data['booking_code'],
                ],
                $data
            );
        } else {
            $booking = ExtensionBooking::create($data);
        }

        // Run booking processor and hotel matching
        $processed   = null;
        $hotelMatch  = null;

        try {
            $processed  = $processor->process($booking);
            $hotelMatch = $processed->hotel_id
                ? $lookup->findHotel($booking->hotel_name ?? '')
                : null;
        } catch (\Throwable $e) {
            Log::warning('BookingProcessor failed', ['booking_id' => $booking->id, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'data'        => $booking,
            'created'     => $booking->wasRecentlyCreated,
            'processed'   => $processed ? [
                'id'                    => $processed->id,
                'hotel_id'              => $processed->hotel_id,
                'hotel_name'            => $processed->hotel_name,
                'room_type_id'          => $processed->room_type_id,
                'room_type_name'        => $processed->room_type_name,
                'confirmed_at'          => $processed->confirmed_at,
                'booking_code'          => $processed->booking_code,
                'reservation_date'      => $processed->reservation_date,
                'reservation_time'      => $processed->reservation_time,
                'arrival_at'            => $processed->arrival_at?->format('Y-m-d'),
                'departure_at'          => $processed->departure_at?->format('Y-m-d'),
                'price'                 => $processed->price,
                'currency_code'         => $processed->currency_code,
                'person_count_adults'   => $processed->person_count_adults,
                'person_count_children' => $processed->person_count_children,
                'person_count_teens'    => $processed->person_count_teens,
                'tourists'              => $processed->tourists ?? [],
            ] : null,
            'hotel_match' => $hotelMatch,
        ], $booking->wasRecentlyCreated ? 201 : 200);
    }

    public function confirm(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $booking = ExtensionBooking::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if (!$booking->processed_booking_id) {
            return response()->json(['error' => 'Booking has not been processed yet'], 422);
        }

        $data = $request->validate([
            'hotel_id'         => 'nullable|integer',
            'hotel_name'       => 'nullable|string|max:500',
            'room_type_id'     => 'nullable|integer',
            'room_type_name'   => 'nullable|string|max:500',
            'booking_code'     => 'nullable|string|max:255',
            'reservation_date' => 'nullable|date_format:Y-m-d',
            'reservation_time' => 'nullable|string|max:5',
            'arrival_at'       => 'nullable|date_format:Y-m-d',
            'departure_at'     => 'nullable|date_format:Y-m-d',
            'price'            => 'nullable|numeric',
            'currency_code'    => 'nullable|string|max:3',
            'adults'           => 'nullable|integer|min:0',
            'children'         => 'nullable|integer|min:0',
            'infants'          => 'nullable|integer|min:0',
            'tourists'         => 'nullable|array',
        ]);

        $processed = ProcessedBooking::findOrFail($booking->processed_booking_id);

        // Confirmation stamp — always written
        $processed->confirmed_by_user_id = $user->id;
        $processed->confirmed_at         = now();

        // Data fields — write only if present in request
        $directFields = [
            'hotel_id', 'hotel_name', 'room_type_id', 'room_type_name',
            'booking_code', 'reservation_date', 'reservation_time',
            'arrival_at', 'departure_at', 'price', 'currency_code', 'tourists',
        ];
        foreach ($directFields as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $processed->$field = $data[$field];
            }
        }

        // Person counts use different column names in processed_bookings
        if (array_key_exists('adults', $data))   $processed->person_count_adults   = (int) ($data['adults']   ?? 0);
        if (array_key_exists('children', $data)) $processed->person_count_children = (int) ($data['children'] ?? 0);
        if (array_key_exists('infants', $data))  $processed->person_count_teens    = (int) ($data['infants']  ?? 0);

        $processed->save();

        return response()->json(['data' => $processed]);
    }

    public function hotels(Request $request, HellOotelLookupService $lookup): JsonResponse
    {
        $q       = trim($request->query('q', ''));
        $hotels  = $lookup->getHotels(); // [id => name]

        if ($q !== '') {
            $q      = mb_strtolower($q);
            $hotels = array_filter($hotels, fn($name) => str_contains(mb_strtolower($name), $q));
        }

        $result = array_map(
            fn($id, $name) => ['id' => (int) $id, 'name' => $name],
            array_keys($hotels),
            array_values($hotels)
        );

        // Sort: items that start with the query come first
        if ($q !== '') {
            usort($result, fn($a, $b) =>
                str_starts_with(mb_strtolower($a['name']), $q) <=> str_starts_with(mb_strtolower($b['name']), $q)
                    ?: strcmp($a['name'], $b['name'])
            );
            $result = array_reverse($result); // starts-with = true sorts last, flip it
        }

        return response()->json(['data' => array_values(array_slice($result, 0, 30))]);
    }

    public function hotelRoomTypes(int $id, HellOotelLookupService $lookup): JsonResponse
    {
        $types  = $lookup->getRoomTypes($id); // [id => name]

        $result = array_map(
            fn($typeId, $name) => ['id' => (int) $typeId, 'name' => $name],
            array_keys($types),
            array_values($types)
        );

        usort($result, fn($a, $b) => strcmp($a['name'], $b['name']));

        return response()->json(['data' => $result]);
    }

    public function destroy(int $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $booking = ExtensionBooking::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($booking->processed_booking_id) {
            ProcessedBooking::where('id', $booking->processed_booking_id)->delete();
        }

        $booking->delete();

        return response()->json(['success' => true]);
    }

    public function parsersList(): JsonResponse
    {
        $parsers = ExtensionParser::where('is_active', true)
            ->orderBy('name')
            ->get(['name', 'domain', 'path_match', 'config']);

        return response()->json(['data' => $parsers]);
    }

    public function parserRules(): JsonResponse
    {
        $rules = ExtensionParserRule::orderBy('domain')
            ->get(['domain', 'path_match', 'parser']);

        return response()->json(['data' => $rules]);
    }

    public function pageReport(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url'   => 'required|string|max:2000',
            'title' => 'nullable|string|max:500',
            'html'  => 'required|string',
        ]);

        ExtensionPageReport::create($data);

        return response()->json(['success' => true], 201);
    }
}
