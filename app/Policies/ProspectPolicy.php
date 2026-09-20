<?php

namespace App\Policies;

use App\Models\Prospect;
use App\Models\User;

class ProspectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Prospect $prospect): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Prospect $prospect): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Prospect $prospect): bool
    {
        return $user->isAdmin();
    }
}
