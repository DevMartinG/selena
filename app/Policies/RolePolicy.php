<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    private function hasAccess(User $user, string $permission): bool
    {
        return $user->can('CRUD.roles') || $user->can($permission);
    }

    public function viewAny(User $user): bool
    {
        return $this->hasAccess($user, 'read.roles');
    }

    public function view(User $user, Role $role): bool
    {
        return $this->hasAccess($user, 'read.roles');
    }

    public function create(User $user): bool
    {
        return $this->hasAccess($user, 'create.roles');
    }

    public function update(User $user, Role $role): bool
    {
        return $this->hasAccess($user, 'update.roles');
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->hasAccess($user, 'delete.roles');
    }

    public function restore(User $user, Role $role): bool
    {
        return $this->hasAccess($user, 'restore.roles');
    }

    public function forceDelete(User $user, Role $role): bool
    {
        return $this->hasAccess($user, 'forceDelete.roles');
    }
}
