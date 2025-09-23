<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Permission as ModelsPermission;

class Permission extends ModelsPermission
{
    use HasFactory;

    protected $guarded = [];

    public static function getLabel(string $permission): string
    {
        $labels = [
            'CRUD.users' => 'Admin Usuarios',
            'create.users' => 'Crear Usuarios',
            'read.users' => 'Ver Usuarios',
            'update.users' => 'Editar Usuarios',
            'delete.users' => 'Eliminar Usuarios',
            'restore.users' => 'Restaurar Usuarios',
            'forceDelete.users' => 'HardDelete Usuarios',

            'read.permissions' => 'Ver Permisos',
            'forceDelete.permissions' => 'HardDelete Permisos',

            'CRUD.roles' => 'Admin Roles',
            'create.roles' => 'Crear Roles',
            'read.roles' => 'Ver Roles',
            'update.roles' => 'Editar Roles',
            'delete.roles' => 'Eliminar Roles',
            'restore.roles' => 'Restaurar Roles',
            'forceDelete.roles' => 'HardDelete Roles',

            // Deadline Management Permissions
            'CRUD.deadline_rules' => 'Admin Reglas de Plazos',
            'create.deadline_rules' => 'Crear Reglas de Plazos',
            'read.deadline_rules' => 'Ver Reglas de Plazos',
            'update.deadline_rules' => 'Editar Reglas de Plazos',
            'delete.deadline_rules' => 'Eliminar Reglas de Plazos',
            'restore.deadline_rules' => 'Restaurar Reglas de Plazos',
            'forceDelete.deadline_rules' => 'HardDelete Reglas de Plazos',
        ];

        return $labels[$permission] ?? ucfirst(str_replace('.', ' ', $permission));
    }
}
