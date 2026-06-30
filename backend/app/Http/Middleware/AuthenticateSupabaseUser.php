<?php

namespace App\Http\Middleware;

use App\Domain\Auth\SupabaseAuthClient;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateSupabaseUser
{
    public function __construct(
        private readonly SupabaseAuthClient $authClient,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            abort(Response::HTTP_UNAUTHORIZED, 'Missing Supabase bearer token.');
        }

        try {
            $user = $this->authClient->verifyBearerToken($token);
        } catch (RuntimeException $exception) {
            abort(Response::HTTP_SERVICE_UNAVAILABLE, $exception->getMessage());
        }

        if ($user === null) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid Supabase bearer token.');
        }

        $request->attributes->set('supabase_user', $user);
        $request->attributes->set('supabase_user_id', $user->id);

        return $next($request);
    }
}
