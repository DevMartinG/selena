<?php

namespace App\Policies;

use App\Models\SeaceTender;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SeaceTenderPolicy
{
    use HandlesAuthorization;

    private function hasAccess(User $user, string $permission): bool
    {
        return $user->can('CRUD.seace_tenders') || $user->can($permission);
    }

    public function viewAny(User $user): bool
    {
        return $this->hasAccess($user, 'read.seace_tenders');
    }

    public function view(User $user, SeaceTender $seaceTender): bool
    {
        return $this->hasAccess($user, 'read.seace_tenders');
    }

    public function create(User $user): bool
    {
        return $this->hasAccess($user, 'create.seace_tenders');
    }

    public function update(User $user, ?SeaceTender $seaceTender = null): bool
    {
        return $this->hasAccess($user, 'update.seace_tenders');
    }

    public function delete(User $user, ?SeaceTender $seaceTender = null): bool
    {
        return $this->hasAccess($user, 'delete.seace_tenders');
    }

    public function restore(User $user, ?SeaceTender $seaceTender = null): bool
    {
        return $this->hasAccess($user, 'restore.seace_tenders');
    }

    public function forceDelete(User $user, ?SeaceTender $seaceTender = null): bool
    {
        return $this->hasAccess($user, 'forceDelete.seace_tenders');
    }
}