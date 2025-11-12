<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    private function hasAccess(User $user, string $permission): bool
    {
        return $user->can('CRUD.users') || $user->can($permission);
    }

    public function viewAny(User $user): bool
    {
        return $this->hasAccess($user, 'read.users');
    }

    public function view(User $user, User $model): bool
    {
        return $this->hasAccess($user, 'read.users');
    }

    public function create(User $user): bool
    {
        return $this->hasAccess($user, 'create.users');
    }

    public function update(User $user, ?User $model = null): bool
    {
        return $this->hasAccess($user, 'update.users');
    }

    public function delete(User $user, ?User $model = null): bool
    {
        return $this->hasAccess($user, 'delete.users');
    }

    public function restore(User $user, ?User $model = null): bool
    {
        return $this->hasAccess($user, 'restore.users');
    }

    public function forceDelete(User $user, ?User $model = null): bool
    {
        return $this->hasAccess($user, 'forceDelete.users');
    }
}
