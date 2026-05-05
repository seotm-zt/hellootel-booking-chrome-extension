<?php

namespace App\Http\Controllers\Api;

use App\Enums\User\Role;
use App\Http\Controllers\Controller;
use App\Models\ExtensionBooking;
use App\Models\ExtensionPageReport;
use App\Models\ExtensionParser;
use App\Models\ExtensionParserRule;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $bookings]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'booking_code' => 'nullable|string|max:255',
            'hotel_name'   => 'nullable|string|max:500',
            'subtitle'     => 'nullable|string|max:500',
            'stay_dates'   => 'nullable|string|max:255',
            'guests'       => 'nullable|string|max:255',
            'adults'       => 'nullable|integer|min:0',
            'children'     => 'nullable|integer|min:0',
            'infants'      => 'nullable|integer|min:0',
            'meal_plan'    => 'nullable|string|max:255',
            'transfer'     => 'nullable|string|max:255',
            'total_price'  => 'nullable|string|max:255',
            'statuses'     => 'nullable|array',
            'tourists'     => 'nullable|array',
            'meta'         => 'nullable|array',
            'details_link' => 'nullable|string|max:1000',
            'thumbnail'    => 'nullable|string|max:1000',
            'source_url'   => 'nullable|string|max:1000',
            'page_title'   => 'nullable|string|max:500',
            'language'     => 'nullable|string|max:10',
            'captured_at'  => 'nullable|date',
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

        return response()->json([
            'data'    => $booking,
            'created' => $booking->wasRecentlyCreated,
        ], $booking->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(int $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $booking = ExtensionBooking::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

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
