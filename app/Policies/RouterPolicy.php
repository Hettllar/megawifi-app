<?php

namespace App\Policies;

use App\Models\Router;
use App\Models\User;

class RouterPolicy
{
    /**
     * Determine whether the user can view any routers.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the router.
     */
    public function view(User $user, Router $router): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAccessToRouter($router);
    }

    /**
     * Determine whether the user can create routers.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can update the router.
     */
    public function update(User $user, Router $router): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Allow regular admins to manage routers they have access to
        if ($user->isAdmin()) {
            return $user->hasAccessToRouter($router);
        }

        return $user->canManageRouter($router, 'can_edit');
    }

    /**
     * Determine whether the user can delete the router.
     */
    public function delete(User $user, Router $router): bool
    {
        return $user->isSuperAdmin();
    }
}
