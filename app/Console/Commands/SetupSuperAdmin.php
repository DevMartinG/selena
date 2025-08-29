<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SetupSuperAdmin extends Command
{
    protected $signature = 'app:setup-super-admin';

    protected $description = 'Crear el Super Admin, los roles y permisos si no existen';

    public function handle()
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('users') || ! Schema::hasTable('permissions')) {
            $this->error('Las tablas necesarias no existen. ¿Ejecutaste las migraciones?');

            return;
        }

        DB::transaction(function () {
            $this->info('Creando permisos...');
            $permissions = ['CRUD.users', 'CRUD.roles',
                'create.users', 'read.users', 'update.users', 'delete.users', 'forceDelete.users', 'restore.users',
                'read.permissions', 'forceDelete.permissions',
                'create.roles', 'read.roles', 'update.roles', 'delete.roles', 'forceDelete.roles', 'restore.roles',
            ];

            collect($permissions)->each(fn ($permission) => Permission::findOrCreate($permission, 'web'));

            $this->info('Creando roles...');
            $roles = ['SuperAdmin', 'Admin', 'Usuario', 'Auditor'];

            collect($roles)->each(fn ($role) => Role::findOrCreate($role, 'web'));

            // Asignación de permisos a roles específicos
            Role::findByName('Admin', 'web')->syncPermissions(['CRUD.users']);
            Role::findByName('Usuario', 'web')->syncPermissions(['read.users', 'read.roles']);
            Role::findByName('Auditor', 'web')->syncPermissions([
                'read.users', 'read.roles', 'read.permissions', ]);

            // SuperAdmin obtiene todos los permisos
            $superAdminRole = Role::findByName('SuperAdmin', 'web');
            $superAdminRole->givePermissionTo(Permission::all());

            // Crear usuario SuperAdmin si no existe
            $user = User::where('email', 'superadmin@docs-repo.com')->first();

            if (! $user) {
                $this->info('Creando Super Admin...');
                $user = User::create([
                    'name' => 'Super',
                    'last_name' => 'Admin',
                    'nin' => '56781234',
                    'email' => 'superadmin@laravel.app',
                    'username' => 'SuperAdmin',
                    'password' => bcrypt('~14AH1]yd\6L'),
                ]);
            }

            // Asegurar que tiene el rol SuperAdmin
            if (! $user->hasRole('SuperAdmin')) {
                $user->assignRole('SuperAdmin');
                $this->info('Super Admin asignado al rol correctamente.');
            }
        });

        $this->info('Proceso completado.');
    }
}
