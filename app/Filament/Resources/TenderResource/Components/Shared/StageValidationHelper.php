<?php

namespace App\Filament\Resources\TenderResource\Components\Shared;

use App\Models\Tender;
use App\Filament\Resources\TenderResource\Components\S1PreparatoryTab;
use App\Filament\Resources\TenderResource\Components\S2SelectionTab;
use App\Filament\Resources\TenderResource\Components\S3ContractTab;
use App\Filament\Resources\TenderResource\Components\S4ExecutionTab;

/**
 * 🛠️ HELPER CENTRALIZADO: VALIDACIÓN DE ETAPAS
 *
 * Este helper centraliza toda la lógica de validación para determinar
 * si una etapa está completa y permite crear la siguiente etapa.
 *
 * FUNCIONALIDADES:
 * - Validación de completitud de etapas usando métodos existentes
 * - Generación de mensajes de error específicos
 * - Identificación de campos faltantes
 * - Soporte para indicadores visuales de progreso
 *
 * USO:
 * - Importar en EditTender.php para validar botones de creación
 * - Usar en cualquier lugar que necesite validar etapas
 * - Aprovecha los métodos isStageComplete() ya implementados
 */
class StageValidationHelper
{
    /**
     * ✅ Valida si una etapa está completa para permitir crear la siguiente
     *
     * @param  Tender  $tender  Instancia del Tender
     * @param  string  $currentStage  Etapa actual (S1, S2, S3, S4)
     * @return bool True si la etapa está completa y permite crear la siguiente
     */
    public static function canCreateNextStage(Tender $tender, string $currentStage): bool
    {
        switch($currentStage) {
            case 'S1':
                return $tender->s1Stage && S1PreparatoryTab::isStageComplete($tender->s1Stage);
            case 'S2':
                return $tender->s2Stage && S2SelectionTab::isStageComplete($tender->s2Stage);
            case 'S3':
                return $tender->s3Stage && S3ContractTab::isStageComplete($tender->s3Stage);
            case 'S4':
                return $tender->s4Stage && S4ExecutionTab::isStageComplete($tender->s4Stage);
            default:
                return false;
        }
    }

    /**
     * 📋 Obtiene los campos faltantes para una etapa específica
     *
     * @param  Tender  $tender  Instancia del Tender
     * @param  string  $stage  Etapa a verificar (S1, S2, S3, S4)
     * @return array Array de campos faltantes
     */
    public static function getMissingFields(Tender $tender, string $stage): array
    {
        $stageData = $tender->{"s{$stage[1]}Stage"} ?? [];
        
        if (empty($stageData)) {
            return ['Etapa no creada'];
        }

        $requiredFields = self::getRequiredFieldsForStage($stage);
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (empty($stageData[$field])) {
                $missingFields[] = self::getFieldLabel($stage, $field);
            }
        }

        return $missingFields;
    }

    /**
     * 💬 Genera mensaje de error personalizado para crear siguiente etapa
     *
     * @param  Tender  $tender  Instancia del Tender
     * @param  string  $nextStage  Etapa que se quiere crear (S2, S3, S4)
     * @return string Mensaje de error descriptivo
     */
    public static function getErrorMessage(Tender $tender, string $nextStage): string
    {
        $currentStage = 'S' . (intval($nextStage[1]) - 1);
        $missingFields = self::getMissingFields($tender, $currentStage);

        if (empty($missingFields)) {
            return "La etapa {$currentStage} no está creada.";
        }

        $fieldsText = implode(', ', $missingFields);
        return "Complete los siguientes campos de la Etapa {$currentStage}: {$fieldsText}";
    }

    /**
     * 📊 Obtiene el progreso de una etapa específica
     *
     * @param  Tender  $tender  Instancia del Tender
     * @param  string  $stage  Etapa a verificar (S1, S2, S3, S4)
     * @return int Porcentaje de progreso (0-100)
     */
    public static function getStageProgress(Tender $tender, string $stage): int
    {
        $stageData = $tender->{"s{$stage[1]}Stage"} ?? [];
        
        if (empty($stageData)) {
            return 0;
        }

        switch($stage) {
            case 'S1':
                return S1PreparatoryTab::calculateStageProgress($stageData);
            case 'S2':
                return S2SelectionTab::calculateStageProgress($stageData);
            case 'S3':
                return S3ContractTab::calculateStageProgress($stageData);
            case 'S4':
                return S4ExecutionTab::calculateStageProgress($stageData);
            default:
                return 0;
        }
    }

    /**
     * 🎯 Obtiene los campos requeridos para cada etapa
     *
     * @param  string  $stage  Etapa (S1, S2, S3, S4)
     * @return array Array de nombres de campos requeridos
     */
    private static function getRequiredFieldsForStage(string $stage): array
    {
        return match($stage) {
            'S1' => ['request_presentation_date', 'approval_expedient_format_2'],
            'S2' => ['published_at', 'appeal_date'],
            'S3' => ['contract_signing'],
            'S4' => ['contract_signing', 'contract_vigency_date'],
            default => []
        };
    }

    /**
     * 🏷️ Obtiene la etiqueta legible de un campo
     *
     * @param  string  $stage  Etapa (S1, S2, S3, S4)
     * @param  string  $field  Nombre del campo
     * @return string Etiqueta legible del campo
     */
    private static function getFieldLabel(string $stage, string $field): string
    {
        $labels = [
            'S1' => [
                'request_presentation_date' => 'Fecha de Presentación',
                'approval_expedient_format_2' => 'Aprobación Expediente Formato 2',
            ],
            'S2' => [
                'published_at' => 'Fecha de Publicación',
                'appeal_date' => 'Fecha de Apelación',
            ],
            'S3' => [
                'contract_signing' => 'Firma del Contrato',
            ],
            'S4' => [
                'contract_signing' => 'Firma del Contrato',
                'contract_vigency_date' => 'Fecha de Vigencia',
            ],
        ];

        return $labels[$stage][$field] ?? $field;
    }

    /**
     * 🔍 Verifica si una etapa específica existe
     *
     * @param  Tender  $tender  Instancia del Tender
     * @param  string  $stage  Etapa a verificar (S1, S2, S3, S4)
     * @return bool True si la etapa existe
     */
    public static function stageExists(Tender $tender, string $stage): bool
    {
        return !empty($tender->{"s{$stage[1]}Stage"});
    }

    /**
     * 📈 Obtiene información completa del estado de una etapa
     *
     * @param  Tender  $tender  Instancia del Tender
     * @param  string  $stage  Etapa a verificar (S1, S2, S3, S4)
     * @return array Información completa del estado
     */
    public static function getStageStatus(Tender $tender, string $stage): array
    {
        $exists = self::stageExists($tender, $stage);
        $isComplete = $exists ? self::canCreateNextStage($tender, $stage) : false;
        $progress = $exists ? self::getStageProgress($tender, $stage) : 0;
        $missingFields = $exists ? self::getMissingFields($tender, $stage) : [];

        return [
            'exists' => $exists,
            'is_complete' => $isComplete,
            'progress' => $progress,
            'missing_fields' => $missingFields,
            'can_create_next' => $isComplete,
        ];
    }

    /**
     * 🎨 Genera tooltip para botones de creación de etapas
     *
     * @param  Tender  $tender  Instancia del Tender
     * @param  string  $nextStage  Etapa que se quiere crear (S2, S3, S4)
     * @return string Texto del tooltip
     */
    public static function getCreationTooltip(Tender $tender, string $nextStage): string
    {
        $currentStage = 'S' . (intval($nextStage[1]) - 1);
        
        if (!self::stageExists($tender, $currentStage)) {
            return "Primero debe crear la Etapa {$currentStage}";
        }

        if (self::canCreateNextStage($tender, $currentStage)) {
            return "Crear Etapa {$nextStage}";
        }

        $missingFields = self::getMissingFields($tender, $currentStage);
        $fieldsText = implode(', ', array_slice($missingFields, 0, 2));
        $moreText = count($missingFields) > 2 ? '...' : '';
        
        return "Complete: {$fieldsText}{$moreText}";
    }
}