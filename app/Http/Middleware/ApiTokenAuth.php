<?php

namespace App\Http\Middleware;

use App\Models\ExtensionToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiTokenAuth
{
    public function handle(Request $request, Closure $next): JsonResponse
    {
        // Bearer only — no ?access_token= query fallback (it leaks into logs/referrers).
        $token  = $request->bearerToken();
        $record = ExtensionToken::findValidByPlain($token);

        if (!$record) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Slide the idle TTL forward and track last use. Throttled to avoid a
        // write on every request (refreshes at most once per 5 minutes of use).
        if (!$record->last_used_at || $record->last_used_at->lt(now()->subMinutes(5))) {
            $record->forceFill([
                'last_used_at' => now(),
                'expires_at'   => ExtensionToken::freshExpiry(),
            ])->saveQuietly();
        }

        auth()->setUser($record->user);

        return $next($request);
    }
}
