<?php

namespace App\Filament\Resources\TenderResource\Components\Shared;

use App\Models\TenderDeadlineRule;
use App\Services\TenderFieldExtractor;
use Carbon\Carbon;
use Filament\Forms;
use Illuminate\Support\HtmlString;

/**
 * 🎯 HELPER: DEADLINE HINT HELPER
 *
 * Este helper genera hints, icons y helperText para campos de fecha
 * basándose en las reglas de plazo configuradas.
 *
 * FUNCIONALIDADES:
 * - Genera helperText con fecha programada según reglas
 * - Genera hint con texto descriptivo
 * - Genera hintIcon (check/x) según validación
 * - Genera hintIconTooltip con información detallada
 * - Calcula días hábiles entre fechas
 *
 * USO:
 * DatePicker::make('field_name')
 *     ->helperText(fn(Get $get) => DeadlineHintHelper::getHelperText($get, 'S1', 'field_name'))
 *     ->hint(fn(Get $get) => DeadlineHintHelper::getHint($get, 'S1', 'field_name'))
 *     ->hintIcon(fn(Get $get) => DeadlineHintHelper::getHintIcon($get, 'S1', 'field_name'))
 *     ->hintColor(fn(Get $get) => DeadlineHintHelper::getHintColor($get, 'S1', 'field_name'))
 *     ->hintIconTooltip(fn(Get $get) => DeadlineHintHelper::getHintIconTooltip($get, 'S1', 'field_name'))
 */
class DeadlineHintHelper
{
    /**
     * 🎯 Genera el helperText con la fecha programada según las reglas
     *
     * @param  Forms\Get  $get  Objeto Get de Filament
     * @param  string  $stageType  Tipo de etapa (S1, S2, S3, S4)
     * @param  string  $fieldName  Nombre del campo (con prefijo stageX.)
     * @return HtmlString|null
     */
    public static function getHelperText(Forms\Get $get, string $stageType, string $fieldName): ?HtmlString
    {
        // Obtener reglas aplicables
        $rules = TenderDeadlineRule::active()
            ->where('to_stage', $stageType)
            ->where('to_field', $fieldName)
            ->get();

        if ($rules->isEmpty()) {
            return null;
        }

        // Obtener valor del campo actual
        $currentValue = $get($fieldName);
        if (! $currentValue) {
            return null;
        }

        $currentDate = Carbon::parse($currentValue);
        $scheduledDates = [];

        // Calcular fechas programadas para cada regla
        foreach ($rules as $rule) {
            $fromFieldValue = $get($rule->from_field);
            
            if (! $fromFieldValue) {
                continue;
            }

            $fromDate = Carbon::parse($fromFieldValue);
            $scheduledDate = self::addBusinessDays($fromDate, $rule->legal_days);
            
            $fieldOptions = TenderFieldExtractor::getFieldOptionsByStage($rule->from_stage);
            $fromFieldLabel = $fieldOptions[$rule->from_field] ?? $rule->from_field;

            $scheduledDates[] = [
                'from_label' => $fromFieldLabel,
                'from_date' => $fromDate->format('d/m/Y'),
                'scheduled_date' => $scheduledDate->format('d/m/Y'),
                'days' => $rule->legal_days,
            ];
        }

        if (empty($scheduledDates)) {
            return null;
        }

        // Generar HTML estilizado
        $selectedDate = $currentDate->format('d/m/Y');
        $html = '<span style="
            display: inline-block;
            background: linear-gradient(to bottom, #4b5563, #374151);
            color: #f9fafb;
            text-shadow: 0 1px 0 rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(0, 0, 0, 0.15);
            border-radius: 0.75rem;
            padding: 0.20rem 0.70rem;
            font-size: 0.95em;
            line-height: 1.6;
        ">';

        $html .= 'Fecha Seleccionada: <strong>' . $selectedDate . '</strong>';

        foreach ($scheduledDates as $info) {
            $html .= ' &nbsp; | &nbsp; ';
            $html .= 'Fecha Programada (desde ' . $info['from_label'] . '): <strong>' . $info['scheduled_date'] . '</strong> (+' . $info['days'] . ' días hábiles)';
        }

        $html .= '</span>';

        return new HtmlString($html);
    }

    /**
     * 🎯 Genera el hint del campo
     *
     * @param  Forms\Get  $get  Objeto Get de Filament
     * @param  string  $stageType  Tipo de etapa
     * @param  string  $fieldName  Nombre del campo
     * @return string|null
     */
    public static function getHint(Forms\Get $get, string $stageType, string $fieldName): ?string
    {
        $currentValue = $get($fieldName);
        if (! $currentValue) {
            return null;
        }

        return 'Fecha Ejecutada';
    }

    /**
     * 🎯 Genera el hintIcon del campo (check o x)
     *
     * @param  Forms\Get  $get  Objeto Get de Filament
     * @param  string  $stageType  Tipo de etapa
     * @param  string  $fieldName  Nombre del campo
     * @return string|null
     */
    public static function getHintIcon(Forms\Get $get, string $stageType, string $fieldName): ?string
    {
        $validation = self::validateField($get, $stageType, $fieldName);
        
        if ($validation === null) {
            return null;
        }

        return $validation['is_valid'] ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle';
    }

    /**
     * 🎯 Genera el hintColor del campo
     *
     * @param  Forms\Get  $get  Objeto Get de Filament
     * @param  string  $stageType  Tipo de etapa
     * @param  string  $fieldName  Nombre del campo
     * @return string
     */
    public static function getHintColor(Forms\Get $get, string $stageType, string $fieldName): string
    {
        $validation = self::validateField($get, $stageType, $fieldName);
        
        if ($validation === null) {
            return 'gray';
        }

        return $validation['is_valid'] ? 'success' : 'danger';
    }

    /**
     * 🎯 Genera el hintIconTooltip con información detallada
     *
     * @param  Forms\Get  $get  Objeto Get de Filament
     * @param  string  $stageType  Tipo de etapa
     * @param  string  $fieldName  Nombre del campo
     * @return string|null
     */
    public static function getHintIconTooltip(Forms\Get $get, string $stageType, string $fieldName): ?string
    {
        $validation = self::validateField($get, $stageType, $fieldName);
        
        if ($validation === null) {
            return null;
        }

        if ($validation['is_valid']) {
            $tooltip = "✅ Plazo cumplido\n\n";
            foreach ($validation['rules'] as $ruleInfo) {
                $tooltip .= "• {$ruleInfo['message']}\n";
                $tooltip .= "  📝 {$ruleInfo['description']}\n";
            }
        } else {
            $tooltip = "❌ Plazo excedido\n\n";
            foreach ($validation['rules'] as $ruleInfo) {
                $tooltip .= "• {$ruleInfo['message']}\n";
                $tooltip .= "  📝 {$ruleInfo['description']}\n";
            }
        }

        return trim($tooltip);
    }

    /**
     * 🎯 Valida el campo contra las reglas de plazo
     *
     * @param  Forms\Get  $get  Objeto Get de Filament
     * @param  string  $stageType  Tipo de etapa
     * @param  string  $fieldName  Nombre del campo
     * @return array|null
     */
    private static function validateField(Forms\Get $get, string $stageType, string $fieldName): ?array
    {
        // Obtener reglas aplicables
        $rules = TenderDeadlineRule::active()
            ->where('to_stage', $stageType)
            ->where('to_field', $fieldName)
            ->get();

        if ($rules->isEmpty()) {
            return null;
        }

        // Obtener valor del campo actual
        $currentValue = $get($fieldName);
        if (! $currentValue) {
            return null;
        }

        $currentDate = Carbon::parse($currentValue);
        $isValid = true;
        $rulesInfo = [];

        // Validar contra cada regla
        foreach ($rules as $rule) {
            $fromFieldValue = $get($rule->from_field);
            
            if (! $fromFieldValue) {
                continue;
            }

            $fromDate = Carbon::parse($fromFieldValue);
            $businessDays = self::calculateBusinessDays($fromDate, $currentDate);
            $ruleValid = $businessDays <= $rule->legal_days;
            
            if (! $ruleValid) {
                $isValid = false;
            }

            $fieldOptions = TenderFieldExtractor::getFieldOptionsByStage($rule->from_stage);
            $fromFieldLabel = $fieldOptions[$rule->from_field] ?? $rule->from_field;

            $toFieldOptions = TenderFieldExtractor::getFieldOptionsByStage($rule->to_stage);
            $toFieldLabel = $toFieldOptions[$rule->to_field] ?? $rule->to_field;

            $rulesInfo[] = [
                'valid' => $ruleValid,
                'message' => $ruleValid 
                    ? "✅ {$fromFieldLabel} → {$toFieldLabel}: {$businessDays} días (máximo: {$rule->legal_days})"
                    : "❌ {$fromFieldLabel} → {$toFieldLabel}: {$businessDays} días (máximo: {$rule->legal_days})",
                'description' => $rule->description ?? 'Sin descripción',
            ];
        }

        return [
            'is_valid' => $isValid,
            'rules' => $rulesInfo,
        ];
    }

    /**
     * 🎯 Calcula días hábiles entre dos fechas
     *
     * @param  Carbon  $fromDate  Fecha de inicio
     * @param  Carbon  $toDate  Fecha de fin
     * @return int Número de días hábiles
     */
    private static function calculateBusinessDays(Carbon $fromDate, Carbon $toDate): int
    {
        $businessDays = 0;
        $currentDate = $fromDate->copy();

        while ($currentDate->lt($toDate)) {
            $currentDate->addDay();
            
            // Solo contar días de lunes a viernes
            if ($currentDate->isWeekday()) {
                $businessDays++;
            }
        }

        return $businessDays;
    }

    /**
     * 🎯 Agrega días hábiles a una fecha
     *
     * @param  Carbon  $date  Fecha de inicio
     * @param  int  $days  Número de días hábiles a agregar
     * @return Carbon Fecha resultante
     */
    private static function addBusinessDays(Carbon $date, int $days): Carbon
    {
        $result = $date->copy();
        $addedDays = 0;

        while ($addedDays < $days) {
            $result->addDay();
            
            // Solo contar días de lunes a viernes
            if ($result->isWeekday()) {
                $addedDays++;
            }
        }

        return $result;
    }
}

