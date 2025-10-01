<?php

namespace App\Console\Commands;

use App\Models\ProcessType;
use App\Models\TenderDeadlineRule;
use App\Models\TenderStatus;
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
            $this->info('Creando tipos de proceso...');
            $this->seedProcessTypes();

            $this->info('Creando estados de procedimientos...');
            $this->seedTenderStatuses();

            /* $this->info('Creando reglas de plazos...');
            $this->seedTenderDeadlineRules(); */

            $this->info('Creando permisos...');
            $permissions = ['CRUD.users', 'CRUD.roles',
                'create.users', 'read.users', 'update.users', 'delete.users', 'forceDelete.users', 'restore.users',
                'read.permissions', 'forceDelete.permissions',
                'create.roles', 'read.roles', 'update.roles', 'delete.roles', 'forceDelete.roles', 'restore.roles',
                // Deadline Management Permissions
                'CRUD.deadline_rules', 'create.deadline_rules', 'read.deadline_rules', 'update.deadline_rules',
                'delete.deadline_rules', 'restore.deadline_rules', 'forceDelete.deadline_rules',
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

    /**
     * Poblar la tabla process_types con los datos iniciales
     */
    private function seedProcessTypes(): void
    {
        $processTypes = [
            [
                'code_short_type' => 'AS',
                'description_short_type' => 'Adjudicación Simplificada',
                'year' => '(2024)',
            ],
            [
                'code_short_type' => 'SIE',
                'description_short_type' => 'Subasta Inversa Electrónica',
                'year' => '(2025)',
            ],
            [
                'code_short_type' => 'LP',
                'description_short_type' => 'Licitación Pública',
                'year' => '(2024)',
            ],
            [
                'code_short_type' => 'CP',
                'description_short_type' => 'Concurso Público',
                'year' => '(2024)',
            ],
            [
                'code_short_type' => 'COMPRE',
                'description_short_type' => 'Comparación de Precios',
                'year' => '(2024)',
            ],
        ];

        foreach ($processTypes as $type) {
            ProcessType::updateOrCreate(
                ['code_short_type' => $type['code_short_type']],
                $type
            );
        }

        $this->info('Tipos de proceso creados/actualizados: '.count($processTypes));
    }

    /**
     * Poblar la tabla tender_statuses con los datos iniciales
     */
    private function seedTenderStatuses(): void
    {
        $tenderStatuses = [
            // Estados normales (secuencia del proceso)
            [
                'code' => '1-CONVOCADO',
                'name' => '1. CONVOCADO',
                'category' => 'normal',
                'is_active' => true,
            ],
            [
                'code' => '2-REGISTRO DE PARTICIPANTES',
                'name' => '2. REGISTRO DE PARTICIPANTES',
                'category' => 'normal',
                'is_active' => true,
            ],
            [
                'code' => '3-CONSULTAS Y OBSERVACIONES',
                'name' => '3. CONSULTAS Y OBSERVACIONES',
                'category' => 'normal',
                'is_active' => true,
            ],
            [
                'code' => '4-ABSOLUCION DE CONSULTAS Y OBSERVACIONES',
                'name' => '4. ABSOLUCIÓN DE CONSULTAS Y OBSERVACIONES',
                'category' => 'normal',
                'is_active' => true,
            ],
            [
                'code' => '5-INTEGRACIONDE BASES',
                'name' => '5. INTEGRACIÓN DE BASES',
                'category' => 'normal',
                'is_active' => true,
            ],
            [
                'code' => '6-PRESENTANCION DE OFERTAS',
                'name' => '6. PRESENTACIÓN DE OFERTAS',
                'category' => 'normal',
                'is_active' => true,
            ],
            [
                'code' => '7-EVALUACION Y CALIFICACION',
                'name' => '7. EVALUACIÓN Y CALIFICACIÓN',
                'category' => 'normal',
                'is_active' => true,
            ],
            [
                'code' => '8-OTORGAMIENTO DE LA BUENA PRO (ADJUDICADO)',
                'name' => '8. OTORGAMIENTO DE LA BUENA PRO (ADJUDICADO)',
                'category' => 'normal',
                'is_active' => true,
            ],
            [
                'code' => '9-CONSENTIDO',
                'name' => '9. CONSENTIDO',
                'category' => 'normal',
                'is_active' => true,
            ],
            [
                'code' => '10-CONTRATADO',
                'name' => '10. CONTRATADO',
                'category' => 'normal',
                'is_active' => true,
            ],

            // Estados especiales
            [
                'code' => 'D-DESIERTO',
                'name' => 'DESIERTO',
                'category' => 'special',
                'is_active' => true,
            ],
            [
                'code' => 'N-NULO',
                'name' => 'NULO',
                'category' => 'special',
                'is_active' => true,
            ],

            // Estado por defecto (sin estado)
            [
                'code' => '--',
                'name' => 'Sin Estado',
                'category' => 'default',
                'is_active' => true,
            ],
        ];

        foreach ($tenderStatuses as $status) {
            TenderStatus::updateOrCreate(
                ['code' => $status['code']],
                $status
            );
        }

        $this->info('Estados de procedimientos creados/actualizados: '.count($tenderStatuses));
    }

    /**
     * Poblar la tabla tender_deadline_rules con las reglas predefinidas
     */
    /* private function seedTenderDeadlineRules(): void
    {
        $deadlineRules = [
            // S1 - Actuaciones Preparatorias
            [
                'stage_type' => 'S1',
                'from_field' => 's1Stage.request_presentation_date',
                'to_field' => 's1Stage.approval_expedient_date',
                'legal_days' => 2,
                'is_active' => true,
                'is_mandatory' => true,
                'description' => 'Plazo legal para aprobación del expediente de contratación',
            ],
            [
                'stage_type' => 'S1',
                'from_field' => 's1Stage.approval_expedient_date',
                'to_field' => 's1Stage.selection_committee_date',
                'legal_days' => 1,
                'is_active' => true,
                'is_mandatory' => true,
                'description' => 'Plazo legal para designación del comité de selección',
            ],
            [
                'stage_type' => 'S1',
                'from_field' => 's1Stage.selection_committee_date',
                'to_field' => 's1Stage.administrative_bases_date',
                'legal_days' => 2,
                'is_active' => true,
                'is_mandatory' => true,
                'description' => 'Plazo legal para elaboración de bases administrativas',
            ],
            [
                'stage_type' => 'S1',
                'from_field' => 's1Stage.administrative_bases_date',
                'to_field' => 's1Stage.approval_expedient_format_2',
                'legal_days' => 1,
                'is_active' => true,
                'is_mandatory' => true,
                'description' => 'Plazo legal para aprobación de bases administrativas formato 2',
            ],

            // S2 - Proceso de Selección
            [
                'stage_type' => 'S2',
                'from_field' => 's2Stage.published_at',
                'to_field' => 's2Stage.participants_registration',
                'legal_days' => 22,
                'is_active' => true,
                'is_mandatory' => true,
                'description' => 'Plazo legal para registro de participantes',
            ],
            [
                'stage_type' => 'S2',
                'from_field' => 's2Stage.participants_registration',
                'to_field' => 's2Stage.absolution_obs',
                'legal_days' => 3,
                'is_active' => true,
                'is_mandatory' => true,
                'description' => 'Plazo legal para absolución de consultas y observaciones',
            ],
            [
                'stage_type' => 'S2',
                'from_field' => 's2Stage.absolution_obs',
                'to_field' => 's2Stage.base_integration',
                'legal_days' => 3,
                'is_active' => true,
                'is_mandatory' => true,
                'description' => 'Plazo legal para integración de las bases',
            ],
            [
                'stage_type' => 'S2',
                'from_field' => 's2Stage.base_integration',
                'to_field' => 's2Stage.offer_presentation',
                'legal_days' => 3,
                'is_active' => true,
                'is_mandatory' => true,
                'description' => 'Plazo legal para presentación de propuestas',
            ],
            [
                'stage_type' => 'S2',
                'from_field' => 's2Stage.offer_presentation',
                'to_field' => 's2Stage.offer_evaluation',
                'legal_days' => 3,
                'is_active' => true,
                'is_mandatory' => true,
                'description' => 'Plazo legal para calificación y evaluación de propuestas',
            ],
            [
                'stage_type' => 'S2',
                'from_field' => 's2Stage.offer_evaluation',
                'to_field' => 's2Stage.award_granted_at',
                'legal_days' => 3,
                'is_active' => true,
                'is_mandatory' => true,
                'description' => 'Plazo legal para otorgamiento de buena pro',
            ],
            [
                'stage_type' => 'S2',
                'from_field' => 's2Stage.award_granted_at',
                'to_field' => 's2Stage.award_consent',
                'legal_days' => 3,
                'is_active' => true,
                'is_mandatory' => true,
                'description' => 'Plazo legal para consentimiento de buena pro',
            ],
            [
                'stage_type' => 'S2',
                'from_field' => 's2Stage.award_consent',
                'to_field' => 's2Stage.appeal_date',
                'legal_days' => 3,
                'is_active' => true,
                'is_mandatory' => true,
                'description' => 'Plazo legal para apelación',
            ],

            // S3 - Suscripción del Contrato
            [
                'stage_type' => 'S3',
                'from_field' => 's3Stage.doc_sign_presentation_date',
                'to_field' => 's3Stage.contract_signing',
                'legal_days' => 8,
                'is_active' => true,
                'is_mandatory' => true,
                'description' => 'Plazo legal para suscripción del contrato',
            ],

            // S4 - Tiempo de Ejecución
            [
                'stage_type' => 'S4',
                'from_field' => 's4Stage.contract_signing',
                'to_field' => 's4Stage.contract_vigency_date',
                'legal_days' => 1,
                'is_active' => true,
                'is_mandatory' => false,
                'description' => 'Plazo recomendado para establecer vigencia del contrato',
            ],
        ];

        foreach ($deadlineRules as $rule) {
            TenderDeadlineRule::updateOrCreate(
                [
                    'stage_type' => $rule['stage_type'],
                    'from_field' => $rule['from_field'],
                    'to_field' => $rule['to_field'],
                ],
                $rule
            );
        }

        $this->info('Reglas de plazos creadas/actualizadas: '.count($deadlineRules));
    } */
}
