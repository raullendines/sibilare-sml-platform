<?php

namespace App\Http\Requests\Concerns;

use App\Models\ClientUser;

trait AuthorizesClientRoles
{
    /**
     * @param  list<string>  $roles
     */
    protected function clientUserHasRole(array $roles): bool
    {
        $clientUser = $this->attributes->get('client_user');

        return $clientUser instanceof ClientUser
            && in_array($clientUser->role, $roles, true);
    }
}
