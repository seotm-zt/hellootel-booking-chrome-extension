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
        $token    = $request->bearerToken() ?? $request->query('access_token');
        $identity = User::where('access_token', $token)->first();

        if (!$token || !$identity) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        auth()->setUser($identity);

        return $next($request);
    }
}
