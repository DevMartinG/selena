<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Gate;

class TestPermissions extends Command
{
    protected $signature = 'app:test-permissions {--user=admin}';

    protected $description = 'Probar los permisos de los diferentes roles';

    public function handle()
    {
        $userType = $this->option('user');
        
        $this->info("🧪 Probando permisos para el rol: {$userType}");
        $this->newLine();

        // Obtener usuario de prueba
        $user = $this->getTestUser($userType);
        
        if (!$user) {
            $this->error("❌ No se encontró usuario con rol: {$userType}");
            return;
        }

        $this->info("👤 Usuario: {$user->name} ({$user->email})");
        $this->info("🎭 Roles: " . $user->roles->pluck('name')->join(', '));
        $this->newLine();

        // Probar permisos de Tender
        $this->testTenderPermissions($user);
        
        $this->newLine();
        
        // Probar permisos de SeaceTender
        $this->testSeaceTenderPermissions($user);
        
        $this->newLine();
        
        // Probar permisos de User
        $this->testUserPermissions($user);
    }

    private function getTestUser(string $userType): ?User
    {
        return match ($userType) {
            'superadmin' => User::where('email', 'superadmin@laravel.app')->first(),
            'admin' => User::role('Admin')->first(),
            'coordinador' => User::role('Coordinador')->first(),
            'usuario' => User::role('Usuario')->first(),
            'auditor' => User::role('Auditor')->first(),
            default => User::role('Admin')->first(),
        };
    }

    private function testTenderPermissions(User $user): void
    {
        $this->info("📋 PERMISOS DE TENDER:");
        
        $permissions = [
            'viewAny' => 'Ver lista de procedimientos',
            'create' => 'Crear procedimientos',
            'update' => 'Editar procedimientos',
            'delete' => 'Eliminar procedimientos',
            'restore' => 'Restaurar procedimientos',
            'forceDelete' => 'Eliminar permanentemente',
        ];

        foreach ($permissions as $permission => $description) {
            $can = Gate::forUser($user)->allows($permission, \App\Models\Tender::class);
            $icon = $can ? '✅' : '❌';
            $this->line("  {$icon} {$description}: " . ($can ? 'PERMITIDO' : 'DENEGADO'));
        }
    }

    private function testSeaceTenderPermissions(User $user): void
    {
        $this->info("🌐 PERMISOS DE SEACE TENDER:");
        
        $permissions = [
            'viewAny' => 'Ver lista de procedimientos SEACE',
            'create' => 'Crear procedimientos SEACE',
            'update' => 'Editar procedimientos SEACE',
            'delete' => 'Eliminar procedimientos SEACE',
            'restore' => 'Restaurar procedimientos SEACE',
            'forceDelete' => 'Eliminar permanentemente',
        ];

        foreach ($permissions as $permission => $description) {
            $can = Gate::forUser($user)->allows($permission, \App\Models\SeaceTender::class);
            $icon = $can ? '✅' : '❌';
            $this->line("  {$icon} {$description}: " . ($can ? 'PERMITIDO' : 'DENEGADO'));
        }
    }

    private function testUserPermissions(User $user): void
    {
        $this->info("👥 PERMISOS DE USER:");
        
        $permissions = [
            'viewAny' => 'Ver lista de usuarios',
            'create' => 'Crear usuarios',
            'update' => 'Editar usuarios',
            'delete' => 'Eliminar usuarios',
            'restore' => 'Restaurar usuarios',
            'forceDelete' => 'Eliminar permanentemente',
        ];

        foreach ($permissions as $permission => $description) {
            $can = Gate::forUser($user)->allows($permission, User::class);
            $icon = $can ? '✅' : '❌';
            $this->line("  {$icon} {$description}: " . ($can ? 'PERMITIDO' : 'DENEGADO'));
        }
    }
}
