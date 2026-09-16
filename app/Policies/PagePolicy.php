<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Page $page): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Page $page): bool
    {
        return true;
    }

    public function delete(User $user, Page $page): bool
    {
        if ($page->children()->exists()) {
            return false;
        }

        return $user->isAdmin() || ! $page->template->isSystem();
    }

    public function changeStructure(User $user, Page $page): bool
    {
        return $user->isAdmin() || ! $page->template->isSystem();
    }
}
