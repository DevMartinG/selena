<?php

namespace App\Policies;

use App\Models\TenderDeadlineRule;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * 🎯 POLICY: TENDERDEADLINERULEPOLICY
 *
 * Esta policy maneja los permisos para las reglas de plazos de tenders.
 * Solo SuperAdmin puede gestionar las reglas de plazos.
 *
 * PERMISOS:
 * - CRUD.deadline_rules: Acceso completo a reglas
 * - create.deadline_rules: Crear reglas
 * - read.deadline_rules: Ver reglas
 * - update.deadline_rules: Editar reglas
 * - delete.deadline_rules: Eliminar reglas
 */
class TenderDeadlineRulePolicy
{
    use HandlesAuthorization;

    /**
     * 🎯 Verificar si el usuario tiene acceso a reglas de plazos
     */
    private function hasAccess(User $user, string $permission): bool
    {
        return $user->can('CRUD.deadline_rules') || $user->can($permission);
    }

    /**
     * 🎯 Determinar si el usuario puede ver cualquier regla
     */
    public function viewAny(User $user): bool
    {
        return $this->hasAccess($user, 'read.deadline_rules');
    }

    /**
     * 🎯 Determinar si el usuario puede ver la regla específica
     */
    public function view(User $user, TenderDeadlineRule $tenderDeadlineRule): bool
    {
        return $this->hasAccess($user, 'read.deadline_rules');
    }

    /**
     * 🎯 Determinar si el usuario puede crear reglas
     */
    public function create(User $user): bool
    {
        return $this->hasAccess($user, 'create.deadline_rules');
    }

    /**
     * 🎯 Determinar si el usuario puede actualizar la regla
     */
    public function update(User $user, TenderDeadlineRule $tenderDeadlineRule): bool
    {
        return $this->hasAccess($user, 'update.deadline_rules');
    }

    /**
     * 🎯 Determinar si el usuario puede eliminar la regla
     */
    public function delete(User $user, TenderDeadlineRule $tenderDeadlineRule): bool
    {
        return $this->hasAccess($user, 'delete.deadline_rules');
    }

    /**
     * 🎯 Determinar si el usuario puede restaurar la regla
     */
    public function restore(User $user, TenderDeadlineRule $tenderDeadlineRule): bool
    {
        return $this->hasAccess($user, 'restore.deadline_rules');
    }

    /**
     * 🎯 Determinar si el usuario puede eliminar permanentemente la regla
     */
    public function forceDelete(User $user, TenderDeadlineRule $tenderDeadlineRule): bool
    {
        return $this->hasAccess($user, 'forceDelete.deadline_rules');
    }
}
