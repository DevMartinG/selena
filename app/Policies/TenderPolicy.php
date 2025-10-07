<?php

namespace App\Policies;

use App\Models\Tender;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TenderPolicy
{
    use HandlesAuthorization;

    private function hasAccess(User $user, string $permission): bool
    {
        return $user->can('CRUD.tenders') || $user->can($permission);
    }

    public function viewAny(User $user): bool
    {
        return $this->hasAccess($user, 'read.tenders');
    }

    public function view(User $user, Tender $tender): bool
    {
        return $this->hasAccess($user, 'read.tenders');
    }

    public function create(User $user): bool
    {
        return $this->hasAccess($user, 'create.tenders');
    }

    public function update(User $user, ?Tender $tender = null): bool
    {
        return $this->hasAccess($user, 'update.tenders');
    }

    public function delete(User $user, ?Tender $tender = null): bool
    {
        return $this->hasAccess($user, 'delete.tenders');
    }

    public function restore(User $user, ?Tender $tender = null): bool
    {
        return $this->hasAccess($user, 'restore.tenders');
    }

    public function forceDelete(User $user, ?Tender $tender = null): bool
    {
        return $this->hasAccess($user, 'forceDelete.tenders');
    }
}
