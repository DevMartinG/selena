<?php

namespace App\Traits;

use App\Models\TenderStage;
use App\Models\TenderStageS1;
use App\Models\TenderStageS2;
use App\Models\TenderStageS3;
use App\Models\TenderStageS4;
use App\Filament\Resources\TenderResource\Components\Shared\StageValidationHelper;
use Filament\Notifications\Notification;

trait TenderStageInitializer
{
    /**
     * Inicializa una etapa específica con datos por defecto válidos
     *
     * @deprecated Este método está siendo reemplazado por los mutators del modelo Tender.
     *             Los mutators ahora manejan automáticamente la creación de stages.
     *             Este trait se mantiene por compatibilidad temporal.
     */
    public function initializeStage(string $stageType): bool
    {
        try {
            $tender = $this->record ?? $this->ownerRecord;

            // Verificar si la etapa ya existe
            if ($this->stageExists($tender, $stageType)) {
                Notification::make()
                    ->title('Etapa ya existe')
                    ->body("La etapa {$stageType} ya está inicializada para este procedimiento.")
                    ->warning()
                    ->send();

                return false;
            }

            // ✅ VALIDACIÓN ADICIONAL: Verificar que la etapa anterior esté completa
            if ($stageType !== 'S1') {
                $previousStage = 'S' . (intval($stageType[1]) - 1);
                
                if (!StageValidationHelper::canCreateNextStage($tender, $previousStage)) {
                    $errorMessage = StageValidationHelper::getErrorMessage($tender, $stageType);
                    
                    Notification::make()
                        ->title("No se puede crear la Etapa {$stageType}")
                        ->body($errorMessage)
                        ->warning()
                        ->send();

                    return false;
                }
            }

            // Crear la etapa principal
            $stage = TenderStage::create([
                'tender_id' => $tender->id,
                'stage_type' => $stageType,
                'status' => 'pending',
            ]);

            // Crear el registro específico de la etapa con datos por defecto
            $this->createStageData($stage, $stageType);

            Notification::make()
                ->title('Etapa inicializada')
                ->body("La etapa {$stageType} ha sido inicializada exitosamente.")
                ->success()
                ->send();

            return true;

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al inicializar etapa')
                ->body("No se pudo inicializar la etapa {$stageType}: ".$e->getMessage())
                ->danger()
                ->send();

            return false;
        }
    }

    /**
     * Verifica si una etapa ya existe para un tender
     */
    private function stageExists($tender, string $stageType): bool
    {
        return TenderStage::where('tender_id', $tender->id)
            ->where('stage_type', $stageType)
            ->exists();
    }

    /**
     * Crea los datos específicos de cada etapa con valores por defecto válidos
     */
    private function createStageData(TenderStage $stage, string $stageType): void
    {
        match ($stageType) {
            'S1' => $this->createS1Data($stage),
            'S2' => $this->createS2Data($stage),
            'S3' => $this->createS3Data($stage),
            'S4' => $this->createS4Data($stage),
        };
    }

    /**
     * Crea datos por defecto para la etapa S1
     */
    private function createS1Data(TenderStage $stage): void
    {
        TenderStageS1::create([
            'tender_stage_id' => $stage->id,
            'with_certification' => false, // ✅ Cambiar a false para progreso realista
            'apply_selection_committee' => false, // ✅ Cambiar a false para progreso realista
            'with_provision' => false, // ✅ Cambiar a false para progreso realista
            // Los demás campos son nullable, así que no necesitan valores por defecto
        ]);
    }

    /**
     * Crea datos por defecto para la etapa S2
     * Nota: published_at es requerido en la tabla, por eso se asigna un valor por defecto
     */
    private function createS2Data(TenderStage $stage): void
    {
        TenderStageS2::create([
            'tender_stage_id' => $stage->id,
            'published_at' => now()->toDateString(), // Campo requerido - fecha actual como valor por defecto
            // Los demás campos son nullable
        ]);
    }

    /**
     * Crea datos por defecto para la etapa S3
     */
    private function createS3Data(TenderStage $stage): void
    {
        TenderStageS3::create([
            'tender_stage_id' => $stage->id,
            // Todos los campos son nullable en S3
        ]);
    }

    /**
     * Crea datos por defecto para la etapa S4
     */
    private function createS4Data(TenderStage $stage): void
    {
        TenderStageS4::create([
            'tender_stage_id' => $stage->id,
            // Todos los campos son nullable en S4
        ]);
    }

    /**
     * Inicializa múltiples etapas de una vez
     */
    public function initializeMultipleStages(array $stageTypes): array
    {
        $results = [];

        foreach ($stageTypes as $stageType) {
            $results[$stageType] = $this->initializeStage($stageType);
        }

        $successCount = count(array_filter($results));
        $totalCount = count($stageTypes);

        if ($successCount > 0) {
            Notification::make()
                ->title('Etapas inicializadas')
                ->body("Se inicializaron {$successCount} de {$totalCount} etapas seleccionadas.")
                ->success()
                ->send();
        }

        return $results;
    }
}
