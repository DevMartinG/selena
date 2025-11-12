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
        // SuperAdmin puede ver todo
        if ($user->hasRole('SuperAdmin')) {
            return true;
        }

        // Verificar permiso básico
        if (!$this->hasAccess($user, 'read.tenders')) {
            return false;
        }

        // Otros usuarios solo ven sus propios Tenders
        return $tender->created_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $this->hasAccess($user, 'create.tenders');
    }

    public function update(User $user, ?Tender $tender = null): bool
    {
        // Si no hay tender específico (acción masiva), verificar permiso básico
        if (!$tender) {
            return $this->hasAccess($user, 'update.tenders');
        }

        // SuperAdmin puede editar todo
        if ($user->hasRole('SuperAdmin')) {
            return true;
        }

        // Verificar permiso básico
        if (!$this->hasAccess($user, 'update.tenders')) {
            return false;
        }

        // Otros usuarios solo editan sus propios Tenders
        return $tender->created_by === $user->id;
    }

    public function delete(User $user, ?Tender $tender = null): bool
    {
        // Si no hay tender específico (acción masiva), verificar permiso básico
        if (!$tender) {
            return $this->hasAccess($user, 'delete.tenders');
        }

        // SuperAdmin puede eliminar todo
        if ($user->hasRole('SuperAdmin')) {
            return true;
        }

        // Verificar permiso básico
        if (!$this->hasAccess($user, 'delete.tenders')) {
            return false;
        }

        // Otros usuarios solo eliminan sus propios Tenders
        return $tender->created_by === $user->id;
    }

    public function restore(User $user, ?Tender $tender = null): bool
    {
        // Si no hay tender específico (acción masiva), verificar permiso básico
        if (!$tender) {
            return $this->hasAccess($user, 'restore.tenders');
        }

        // SuperAdmin puede restaurar todo
        if ($user->hasRole('SuperAdmin')) {
            return true;
        }

        // Verificar permiso básico
        if (!$this->hasAccess($user, 'restore.tenders')) {
            return false;
        }

        // Otros usuarios solo restauran sus propios Tenders
        return $tender->created_by === $user->id;
    }

    public function forceDelete(User $user, ?Tender $tender = null): bool
    {
        // Si no hay tender específico (acción masiva), verificar permiso básico
        if (!$tender) {
            return $this->hasAccess($user, 'forceDelete.tenders');
        }

        // SuperAdmin puede eliminar permanentemente todo
        if ($user->hasRole('SuperAdmin')) {
            return true;
        }

        // Verificar permiso básico
        if (!$this->hasAccess($user, 'forceDelete.tenders')) {
            return false;
        }

        // Otros usuarios solo eliminan permanentemente sus propios Tenders
        return $tender->created_by === $user->id;
    }
}
