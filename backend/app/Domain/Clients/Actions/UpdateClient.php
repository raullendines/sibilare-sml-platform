<?php

namespace App\Domain\Clients\Actions;

use App\Models\Client;

class UpdateClient
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Client $client, array $data): Client
    {
        $client->fill($data);
        $client->save();

        return $client->refresh();
    }
}
