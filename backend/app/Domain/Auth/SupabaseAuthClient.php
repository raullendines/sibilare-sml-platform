<?php

namespace App\Domain\Auth;

use App\Domain\Auth\Data\SupabaseUser;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SupabaseAuthClient
{
    public function verifyBearerToken(string $token): ?SupabaseUser
    {
        $baseUrl = rtrim((string) config('services.supabase.url'), '/');
        $publishableKey = config('services.supabase.publishable_key');

        if ($baseUrl === '' || ! is_string($publishableKey) || $publishableKey === '') {
            throw new RuntimeException('Supabase Auth is not configured.');
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'apikey' => $publishableKey,
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                ])
                ->get("{$baseUrl}/auth/v1/user");
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $payload = $response->json();

        if (! is_array($payload) || ! isset($payload['id']) || ! is_string($payload['id'])) {
            return null;
        }

        return SupabaseUser::fromPayload($payload);
    }
}
