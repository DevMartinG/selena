<?php

namespace App\Filament\Resources\TenderResource\Components\Shared;

use App\Models\TenderDeadlineRule;
use Carbon\Carbon;

/**
 * 🎯 HELPER: SIMPLE DEADLINE VALIDATOR
 *
 * Este helper simplifica la aplicación de validaciones live a campos de fecha
 * en los formularios de Tender, usando un enfoque más directo y simple.
 *
 * FUNCIONALIDADES:
 * - Genera closures para afterStateUpdated
 * - Validación automática de campos "to_field" de reglas activas
 * - Iconos de estado (✅ verde, ❌ rojo) según cumplimiento
 * - Tooltips informativos con detalles de la regla
 * - Cálculo automático de días hábiles
 *
 * USO:
 * - Llamar en afterStateUpdated de cualquier DatePicker
 * - El helper detecta automáticamente las reglas aplicables
 * - Mantiene la simplicidad del código existente
 */
class SimpleDeadlineValidator
{
    /**
     * 🎯 Genera la lógica de validación para un campo específico
     *
     * @param  string  $stageType  Tipo de etapa (S1, S2, S3, S4)
     * @param  string  $fieldName  Nombre del campo
     * @return \Closure Closure para usar en afterStateUpdated
     */
    public static function generateValidationLogic(string $stageType, string $fieldName): \Closure
    {
        return function ($state, $component, $livewire) use ($stageType, $fieldName) {
            if (! $state) {
                $component->hintIcon(null);
                $component->hintIconTooltip(null);

                return;
            }

            // Buscar reglas que usen este campo como "to_field"
            $rules = TenderDeadlineRule::active()
                ->byStage($stageType)
                ->where('to_field', $fieldName)
                ->get();

            if ($rules->isEmpty()) {
                $component->hintIcon(null);
                $component->hintIconTooltip(null);

                return;
            }

            $validations = [];
            $allValid = true;
            $hasErrors = false;

            foreach ($rules as $rule) {
                // Obtener el valor del campo "from_field"
                $fromFieldValue = self::getFromFieldValue($rule->from_field, $stageType, $livewire);

                if (! $fromFieldValue) {
                    continue; // No se puede validar sin la fecha origen
                }

                $validation = self::validateSingleRule($rule, $fromFieldValue, $state);
                $validations[] = $validation;

                if (! $validation['is_valid']) {
                    $allValid = false;
                    if ($rule->is_mandatory) {
                        $hasErrors = true;
                    }
                }
            }

            // Aplicar icono según resultado
            if ($allValid) {
                $component->hintIcon('heroicon-o-check-circle');
                $component->hintIconColor('success');
            } else {
                $component->hintIcon('heroicon-o-x-circle');
                $component->hintIconColor('danger');
            }

            // Aplicar tooltip con detalles
            $component->hintIconTooltip(self::generateTooltip($validations));
        };
    }

    /**
     * 🎯 Obtiene el valor del campo "from_field" desde el livewire
     *
     * @param  string  $fromField  Nombre del campo origen
     * @param  string  $stageType  Tipo de etapa
     * @param  mixed  $livewire  Instancia del livewire
     * @return string|null Valor del campo origen
     */
    private static function getFromFieldValue(string $fromField, string $stageType, $livewire): ?string
    {
        $stagePrefix = strtolower($stageType).'Stage';
        $fullFieldName = "{$stagePrefix}.{$fromField}";

        return $livewire->get($fullFieldName) ?? null;
    }

    /**
     * 🎯 Valida una regla específica
     *
     * @param  TenderDeadlineRule  $rule  Regla a validar
     * @param  string  $fromDate  Fecha origen
     * @param  string  $toDate  Fecha destino
     * @return array Resultado de la validación
     */
    private static function validateSingleRule(TenderDeadlineRule $rule, string $fromDate, string $toDate): array
    {
        try {
            $from = Carbon::parse($fromDate);
            $to = Carbon::parse($toDate);

            if (! $to->gte($from)) {
                return [
                    'rule' => $rule,
                    'is_valid' => false,
                    'actual_days' => 0,
                    'message' => 'Fecha destino debe ser posterior a fecha origen',
                ];
            }

            $actualDays = self::calculateBusinessDays($from, $to);
            $isValid = $actualDays <= $rule->legal_days;

            return [
                'rule' => $rule,
                'is_valid' => $isValid,
                'actual_days' => $actualDays,
                'message' => $isValid
                    ? "✅ Cumple: {$actualDays} días hábiles (máximo: {$rule->legal_days})"
                    : "❌ Excede: {$actualDays} días hábiles (máximo: {$rule->legal_days})",
            ];
        } catch (\Exception $e) {
            return [
                'rule' => $rule,
                'is_valid' => false,
                'actual_days' => 0,
                'message' => 'Error al procesar fechas',
            ];
        }
    }

    /**
     * 🎯 Calcula días hábiles entre dos fechas
     *
     * @param  Carbon  $from  Fecha origen
     * @param  Carbon  $to  Fecha destino
     * @return int Días hábiles
     */
    private static function calculateBusinessDays(Carbon $from, Carbon $to): int
    {
        $businessDays = 0;
        $date = $from->copy();

        while ($date->lte($to)) {
            if (! $date->isWeekend()) {
                $businessDays++;
            }
            $date->addDay();
        }

        return $businessDays;
    }

    /**
     * 🎯 Genera el tooltip con información de las validaciones
     *
     * @param  array  $validations  Resultados de validaciones
     * @return string Tooltip formateado
     */
    private static function generateTooltip(array $validations): string
    {
        if (empty($validations)) {
            return 'No hay reglas de plazo configuradas para este campo';
        }

        $tooltip = "📋 REGLAS DE PLAZO:\n\n";

        foreach ($validations as $validation) {
            $rule = $validation['rule'];
            $fromLabel = FieldLabelExtractor::getFieldLabel($rule->stage_type, $rule->from_field) ?? $rule->from_field;
            $toLabel = FieldLabelExtractor::getFieldLabel($rule->stage_type, $rule->to_field) ?? $rule->to_field;

            $tooltip .= "• {$fromLabel} → {$toLabel}\n";
            $tooltip .= "  {$validation['message']}\n";
            $tooltip .= "  📝 {$rule->description}\n\n";
        }

        return trim($tooltip);
    }
}
