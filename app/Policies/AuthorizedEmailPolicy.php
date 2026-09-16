<?php

namespace App\Policies;

use App\Models\AuthorizedEmail;
use App\Models\User;

class AuthorizedEmailPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, AuthorizedEmail $authorizedEmail): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, AuthorizedEmail $authorizedEmail): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, AuthorizedEmail $authorizedEmail): bool
    {
        return $user->isAdmin();
    }
}
