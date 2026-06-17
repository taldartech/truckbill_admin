<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebsiteApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = config('crm.website_api_key');

        if (empty($apiKey) || $request->header('X-Api-Key') !== $apiKey) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
