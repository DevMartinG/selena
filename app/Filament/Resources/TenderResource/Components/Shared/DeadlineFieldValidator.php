<?php

namespace App\Filament\Resources\TenderResource\Components\Shared;

use App\Models\TenderDeadlineRule;
use App\Services\TenderFieldExtractor;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;

/**
 * 🎯 HELPER: DEADLINE FIELD VALIDATOR
 *
 * Este helper genera automáticamente las validaciones live para campos de fecha
 * en los formularios de Tender, mostrando iconos y tooltips según el cumplimiento
 * de las reglas de plazos legales.
 *
 * FUNCIONALIDADES:
 * - Validación automática de campos "to_field" de reglas activas
 * - Iconos de estado (✅ verde, ❌ rojo) según cumplimiento
 * - Tooltips informativos con detalles de la regla
 * - Cálculo automático de días hábiles
 * - Integración simple con DatePicker de Filament
 *
 * USO:
 * - Llamar en cualquier DatePicker que tenga reglas asociadas
 * - El helper detecta automáticamente las reglas aplicables
 * - Mantiene la simplicidad del código existente
 */
class DeadlineFieldValidator
{
    /**
     * 🎯 Aplica validaciones live a un campo DatePicker
     *
     * @param  DatePicker  $field  Campo DatePicker a validar
     * @param  string  $stageType  Tipo de etapa (S1, S2, S3, S4)
     * @param  string  $fieldName  Nombre del campo
     * @return DatePicker Campo con validaciones aplicadas
     */
    public static function applyLiveValidation(DatePicker $field, string $stageType, string $fieldName): DatePicker
    {
        // Buscar reglas que usen este campo como "to_field"
        $rules = TenderDeadlineRule::active()
            ->byStage($stageType)
            ->where('to_field', $fieldName)
            ->get();

        if ($rules->isEmpty()) {
            return $field; // No hay reglas para este campo
        }

        return $field->live()
            ->afterStateUpdated(function ($state, $component, $livewire) use ($rules, $stageType) {
                if (! $state) {
                    $component->hintIcon(null);
                    $component->hintIconTooltip(null);

                    return;
                }

                $validation = self::validateFieldAgainstRules($state, $rules, $stageType, $livewire);

                // Aplicar icono según resultado
                if ($validation['is_valid']) {
                    $component->hintIcon('heroicon-o-check-circle');
                    $component->hintIconColor('success');
                } else {
                    $component->hintIcon('heroicon-o-x-circle');
                    $component->hintIconColor('danger');
                }

                // Aplicar tooltip con detalles
                $component->hintIconTooltip($validation['tooltip']);
            });
    }

    /**
     * 🎯 Valida un campo contra todas sus reglas aplicables (MÉTODO PÚBLICO)
     *
     * @param  string  $fieldValue  Valor del campo
     * @param  string  $stageType  Tipo de etapa
     * @param  string  $fieldName  Nombre del campo
     * @param  mixed  $livewire  Instancia del livewire para obtener otros valores
     * @return array Resultado de la validación
     */
    public static function validateFieldAgainstRules(string $fieldValue, string $stageType, string $fieldName, $livewire): array
    {
        // Buscar reglas que usen este campo como "to_field"
        $rules = TenderDeadlineRule::active()
            ->byStage($stageType)
            ->where('to_field', $fieldName)
            ->get();

        if ($rules->isEmpty()) {
            return [
                'is_valid' => true,
                'has_errors' => false,
                'validations' => [],
                'tooltip' => 'No hay reglas de plazo configuradas para este campo',
            ];
        }

        return self::validateFieldAgainstRulesCollection($fieldValue, $rules, $stageType, $livewire);
    }

    /**
     * 🎯 Valida un campo contra todas sus reglas aplicables (MÉTODO PRIVADO)
     *
     * @param  string  $fieldValue  Valor del campo
     * @param  \Illuminate\Database\Eloquent\Collection  $rules  Reglas aplicables
     * @param  string  $stageType  Tipo de etapa
     * @param  mixed  $livewire  Instancia del livewire para obtener otros valores
     * @return array Resultado de la validación
     */
    private static function validateFieldAgainstRulesCollection(string $fieldValue, $rules, string $stageType, $livewire): array
    {
        $validations = [];
        $allValid = true;
        $hasErrors = false;

        foreach ($rules as $rule) {
            // Obtener el valor del campo "from_field"
            $fromFieldValue = self::getFromFieldValue($rule->from_field, $stageType, $livewire);

            if (! $fromFieldValue) {
                continue; // No se puede validar sin la fecha origen
            }

            $validation = self::validateSingleRule($rule, $fromFieldValue, $fieldValue);
            $validations[] = $validation;

            if (! $validation['is_valid']) {
                $allValid = false;
                if ($rule->is_mandatory) {
                    $hasErrors = true;
                }
            }
        }

        return [
            'is_valid' => $allValid,
            'has_errors' => $hasErrors,
            'validations' => $validations,
            'tooltip' => self::generateTooltip($validations),
        ];
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
            
            // Usar TenderFieldExtractor en lugar de FieldLabelExtractor
            $fromStage = $rule->from_stage ?? $rule->stage_type;
            $toStage = $rule->to_stage ?? $rule->stage_type;
            
            $fromOptions = TenderFieldExtractor::getFieldOptionsByStage($fromStage);
            $toOptions = TenderFieldExtractor::getFieldOptionsByStage($toStage);
            
            $fromLabel = $fromOptions[$rule->from_field] ?? $rule->from_field;
            $toLabel = $toOptions[$rule->to_field] ?? $rule->to_field;

            $tooltip .= "• {$fromLabel} → {$toLabel}\n";
            $tooltip .= "  {$validation['message']}\n";
            $tooltip .= "  📝 {$rule->description}\n\n";
        }

        return trim($tooltip);
    }

    /**
     * 🎯 Obtiene todos los campos que tienen reglas de plazo
     *
     * @param  string  $stageType  Tipo de etapa
     * @return array Campos con reglas
     */
    public static function getFieldsWithRules(string $stageType): array
    {
        return TenderDeadlineRule::active()
            ->byStage($stageType)
            ->pluck('to_field')
            ->unique()
            ->toArray();
    }

    /**
     * 🎯 Verifica si un campo tiene reglas de plazo
     *
     * @param  string  $stageType  Tipo de etapa
     * @param  string  $fieldName  Nombre del campo
     * @return bool True si tiene reglas
     */
    public static function fieldHasRules(string $stageType, string $fieldName): bool
    {
        return TenderDeadlineRule::active()
            ->byStage($stageType)
            ->where('to_field', $fieldName)
            ->exists();
    }
}
