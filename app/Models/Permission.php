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
            // User Management Permissions
            'CRUD.users' => 'Admin Usuarios',
            'create.users' => 'Crear Usuarios',
            'read.users' => 'Ver Usuarios',
            'update.users' => 'Editar Usuarios',
            'delete.users' => 'Eliminar Usuarios',
            'restore.users' => 'Restaurar Usuarios',
            'forceDelete.users' => 'HardDelete Usuarios',

            // Role Management Permissions
            'CRUD.roles' => 'Admin Roles',
            'create.roles' => 'Crear Roles',
            'read.roles' => 'Ver Roles',
            'update.roles' => 'Editar Roles',
            'delete.roles' => 'Eliminar Roles',
            'restore.roles' => 'Restaurar Roles',
            'forceDelete.roles' => 'HardDelete Roles',

            // Permission Management Permissions
            'read.permissions' => 'Ver Permisos',
            'forceDelete.permissions' => 'HardDelete Permisos',

            // Tender Management Permissions
            'CRUD.tenders' => 'Admin Procedimientos de Selección',
            'create.tenders' => 'Crear Procedimientos de Selección',
            'read.tenders' => 'Ver Procedimientos de Selección',
            'update.tenders' => 'Editar Procedimientos de Selección',
            'delete.tenders' => 'Eliminar Procedimientos de Selección',
            'restore.tenders' => 'Restaurar Procedimientos de Selección',
            'forceDelete.tenders' => 'HardDelete Procedimientos de Selección',

            // SeaceTender Management Permissions
            'CRUD.seace_tenders' => 'Admin Procedimientos SEACE',
            'create.seace_tenders' => 'Crear Procedimientos SEACE',
            'read.seace_tenders' => 'Ver Procedimientos SEACE',
            'update.seace_tenders' => 'Editar Procedimientos SEACE',
            'delete.seace_tenders' => 'Eliminar Procedimientos SEACE',
            'restore.seace_tenders' => 'Restaurar Procedimientos SEACE',
            'forceDelete.seace_tenders' => 'HardDelete Procedimientos SEACE',

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
