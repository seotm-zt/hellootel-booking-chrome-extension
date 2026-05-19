<?php

namespace App\Http\Controllers;

use App\Models\ProcessedBooking;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class HellOotelPortalController extends Controller
{
    private const AUTH_URL = 'https://demo.hellootel.com/api/v1/auth/login';

    public function login()
    {
        if (session()->has('hellootel_auth')) {
            return redirect()->route('portal.bookings');
        }

        return view('portal.login');
    }

    public function authenticate(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $response = Http::timeout(10)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(self::AUTH_URL, [
                'name'     => $request->username,
                'password' => $request->password,
            ]);

        if (!$response->successful()) {
            $error = $response->json('error') ?: $response->json('message') ?: 'Invalid credentials';
            return back()->withErrors(['username' => $error])->withInput();
        }

        $payload        = $response->json();
        $hellootelToken = $payload['access_token'] ?? null;

        if (!$hellootelToken) {
            return back()->withErrors(['username' => 'Authentication failed'])->withInput();
        }

        $username  = $payload['login'] ?? $request->username;
        $fakeEmail = $username . '@hellootel.local';
        $name      = $payload['full_name'] ?? $payload['legacy_name'] ?? $username;

        $user = User::firstOrCreate(
            ['email' => $fakeEmail],
            ['name' => $name, 'password' => Str::random(32)]
        );

        if ($user->name !== $name) {
            $user->name = $name;
        }
        $user->access_token = $hellootelToken;
        $user->save();

        session(['hellootel_auth' => [
            'user_id'  => $user->id,
            'username' => $username,
            'name'     => $user->name,
        ]]);

        return redirect()->route('portal.bookings');
    }

    public function bookings()
    {
        $auth = session('hellootel_auth');

        if (!$auth) {
            return redirect()->route('portal.login');
        }

        $bookings = ProcessedBooking::where('saved_by_user_id', $auth['user_id'])
            ->with('sourceBooking:id,source_url,source_domain')
            ->orderByDesc('created_at')
            ->get();

        return view('portal.bookings', compact('bookings', 'auth'));
    }

    public function logout(Request $request)
    {
        $request->session()->forget('hellootel_auth');

        return redirect()->route('portal.login');
    }
}
