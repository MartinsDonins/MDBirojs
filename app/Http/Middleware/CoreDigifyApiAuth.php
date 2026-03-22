<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CoreDigifyApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedKey = AppSetting::getRaw('coredigify_incoming_key');

        if (empty($expectedKey)) {
            return response()->json(['error' => 'API key not configured'], 503);
        }

        $providedKey = $request->bearerToken();

        if (empty($providedKey) || !hash_equals($expectedKey, $providedKey)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
