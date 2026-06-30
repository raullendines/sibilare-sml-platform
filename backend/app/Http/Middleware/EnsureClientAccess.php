<?php

namespace App\Http\Middleware;

use App\Models\Client;
use App\Models\ClientUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $authUserId = $request->attributes->get('supabase_user_id');
        $clientParameter = $request->route('client');
        $clientId = $clientParameter instanceof Client ? $clientParameter->id : (string) $clientParameter;

        if (! is_string($authUserId) || $authUserId === '' || $clientId === '') {
            abort(Response::HTTP_FORBIDDEN, 'Client access is not available.');
        }

        $clientUser = ClientUser::query()
            ->where('auth_user_id', $authUserId)
            ->where('client_id', $clientId)
            ->whereNull('disabled_at')
            ->first();

        if ($clientUser === null) {
            abort(Response::HTTP_FORBIDDEN, 'You do not have access to this client.');
        }

        $request->attributes->set('client_user', $clientUser);

        return $next($request);
    }
}
