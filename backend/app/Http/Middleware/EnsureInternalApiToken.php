<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternalApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = config('services.internal_api.token');

        if (! is_string($configuredToken) || $configuredToken === '') {
            abort(Response::HTTP_SERVICE_UNAVAILABLE, 'Internal API token is not configured.');
        }

        $providedToken = $request->bearerToken() ?? $request->header('X-Internal-Api-Token');

        if (! is_string($providedToken) || ! hash_equals($configuredToken, $providedToken)) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid internal API token.');
        }

        return $next($request);
    }
}
