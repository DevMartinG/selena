<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PermissionPolicy
{
    use HandlesAuthorization;

    private function hasAccess(User $user, string $permission): bool
    {
        return $user->can('CRUD.permissions') || $user->can($permission);
    }

    public function viewAny(User $user): bool
    {
        return $this->hasAccess($user, 'read.permissions');
    }

    public function view(User $user, Permission $permission): bool
    {
        return $this->hasAccess($user, 'read.permissions');
    }

    public function create(User $user): bool
    {
        return $this->hasAccess($user, 'create.permissions');
    }

    public function update(User $user, Permission $permission): bool
    {
        return $this->hasAccess($user, 'update.permissions');
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $this->hasAccess($user, 'delete.permissions');
    }

    public function restore(User $user, Permission $permission): bool
    {
        return $this->hasAccess($user, 'restore.permissions');
    }

    public function forceDelete(User $user, Permission $permission): bool
    {
        return $this->hasAccess($user, 'forceDelete.permissions');
    }
}
