<?php

namespace App\Domain\Clients\Actions;

use App\Models\Client;
use Illuminate\Support\Str;

class CreateClient
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Client
    {
        $data['slug'] ??= Str::slug((string) $data['name']);
        $data['status'] ??= 'onboarding';
        $data['default_locale'] ??= 'es-ES';
        $data['timezone'] ??= 'Europe/Madrid';

        return Client::create($data);
    }
}
