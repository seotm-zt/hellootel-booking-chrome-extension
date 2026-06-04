<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiTokenAuth
{
    public function handle(Request $request, Closure $next): JsonResponse
    {
        // Bearer only — no ?access_token= query fallback (it leaks into logs/referrers).
        $token    = $request->bearerToken();
        $identity = User::findByApiToken($token);

        if (!$token || !$identity) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Optional TTL: reject expired tokens.
        if ($identity->api_token_expires_at && $identity->api_token_expires_at->isPast()) {
            return response()->json(['error' => 'Token expired'], 401);
        }

        // Track last use, throttled to avoid a write on every request.
        if (!$identity->api_token_last_used_at
            || $identity->api_token_last_used_at->lt(now()->subMinutes(5))) {
            $identity->forceFill(['api_token_last_used_at' => now()])->saveQuietly();
        }

        auth()->setUser($identity);

        return $next($request);
    }
}
