<?php

namespace App\Filament\Resources\TenderResource\Components\Shared;

use App\Models\Tender;
use App\Models\TenderCustomDeadlineRule;
use App\Models\TenderDeadlineRule;
use App\Services\TenderFieldExtractor;
use Carbon\Carbon;
use Filament\Forms;
use Illuminate\Support\HtmlString;

use App\Models\TenderStageS2Completed;

use Illuminate\Support\Facades\Log;



/**
 * 🎯 HELPER: DEADLINE HINT HELPER (OPTIMIZADO)
 *
 * Este helper genera hints, icons y helperText para campos de fecha
 * basándose en las reglas de plazo configuradas. Es el único helper
 * de validación de deadlines en el sistema.
 *
 * FUNCIONALIDADES:
 * - Genera helperText con fecha programada según reglas
 * - Genera hint con texto descriptivo
 * - Genera hintIcon (check/x) según validación
 * - Genera hintIconTooltip con información detallada
 * - Calcula días calendario entre fechas (no días hábiles)
 * - Validación automática contra reglas de plazo
 *
 * USO:
 * DatePicker::make('field_name')
 *     ->helperText(fn(Get $get) => DeadlineHintHelper::getHelperText($get, 'S1', 'field_name'))
 *     ->hint(fn(Get $get) => DeadlineHintHelper::getHint($get, 'S1', 'field_name'))
 *     ->hintIcon(fn(Get $get) => DeadlineHintHelper::getHintIcon($get, 'S1', 'field_name'))
 *     ->hintColor(fn(Get $get) => DeadlineHintHelper::getHintColor($get, 'S1', 'field_name'))
 *     ->hintIconTooltip(fn(Get $get) => DeadlineHintHelper::getHintIconTooltip($get, 'S1', 'field_name'))
 *
 * NOTA: Los días hábiles se implementarán en una fase posterior.
 */
class DeadlineHintHelper
{


    public static function getHelperText(Forms\Get $get, string $stageType, string $fieldName, $record = null): ?HtmlString
    {
        // 1. PRIMERO: Verificar si hay regla personalizada para este Tender + campo
        $customRule = null;
        if ($record && $record instanceof Tender && $record->id) {
            $customRule = TenderCustomDeadlineRule::getCustomRule($record->id, $stageType, $fieldName);
        }

        // 2. Si hay regla personalizada, usarla
        if ($customRule) {
            return self::getHelperTextForCustomRule($get, $customRule, $fieldName);
        }

        // 3. Si NO hay regla personalizada, usar reglas globales (comportamiento actual)
        return self::getHelperTextForGlobalRules($get, $stageType, $fieldName);
    }


    private static function getHelperTextForCustomRule(Forms\Get $get, TenderCustomDeadlineRule $customRule, string $fieldName): ?HtmlString
    {
        // Obtener valor del campo actual
        $currentValue = $get($fieldName);
        if (! $currentValue) {
            return null;
        }

        $currentDate = Carbon::parse($currentValue);
        $customDate = Carbon::parse($customRule->custom_date);
        
        // La fecha personalizada actúa como "fecha programada"
        // Calcular diferencia desde la fecha personalizada hasta la fecha actual
        $actualDays = self::calculateCalendarDays($customDate, $currentDate);
        
        // Verificar si la fecha ejecutada es anterior a la personalizada
        $isBeforeCustom = $currentDate->lt($customDate);
        
        // Obtener label del campo origen
        $fieldOptions = TenderFieldExtractor::getFieldOptionsByStage($customRule->from_stage);
        $fromFieldLabel = $fieldOptions[$customRule->from_field] ?? $customRule->from_field;

        // Generar HTML estilizado
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

        $html .= 'F. Programada (Personalizada): <strong>' . $customDate->format('d/m/Y') . '</strong>';
        
        if ($isBeforeCustom) {
            $html .= ' <span style="color: #f59e0b; font-weight: bold;">(⚠️ fecha anterior a personalizada)</span>';
        } elseif ($actualDays > 0) {
            $html .= ' <span style="color: #ef4444; font-weight: bold;">(+' . $actualDays . ' días excedidos)</span>';
        } else {
            $html .= ' <span style="color: #10b981; font-weight: bold;">(dentro del plazo)</span>';
        }

        $html .= '</span>';

        return new HtmlString($html);
    }


    private static function getHelperTextForGlobalRules(Forms\Get $get, string $stageType, string $fieldName): ?HtmlString
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
            $scheduledDate = self::addCalendarDays($fromDate, $rule->legal_days);
            
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

        // Calcular diferencia de días para mostrar en helperText
        $daysDifference = [];
        foreach ($rules as $rule) {
            $fromFieldValue = $get($rule->from_field);
            
            if (!$fromFieldValue) {
                continue;
            }

            $fromDate = Carbon::parse($fromFieldValue);
            $actualDays = self::calculateCalendarDays($fromDate, $currentDate);
            $maxDays = $rule->legal_days;
            $diff = $actualDays - $maxDays;
            
            // Verificar si la fecha ejecutada es anterior a la de origen
            $isBeforeOrigin = $currentDate->lt($fromDate);
            
            $daysDifference[] = [
                'actual' => $actualDays,
                'max' => $maxDays,
                'diff' => $diff,
                'is_exceeded' => $diff > 0,
                'is_before_origin' => $isBeforeOrigin,
            ];
        }

        // Generar HTML estilizado
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

        foreach ($scheduledDates as $index => $info) {
            $html .= 'F. Programada: <strong>' . $info['scheduled_date'] . '</strong>';
            
            // Agregar diferencia de días si existe
            if (isset($daysDifference[$index])) {
                $diff = $daysDifference[$index];
                if ($diff['is_before_origin']) {
                    $html .= ' <span style="color: #f59e0b; font-weight: bold;">(⚠️ fecha anterior a origen)</span>';
                } elseif ($diff['is_exceeded']) {
                    $html .= ' <span style="color: #ef4444; font-weight: bold;">(+' . abs($diff['diff']) . ' días excedidos)</span>';
                } else {
                    $html .= ' <span style="color: #10b981; font-weight: bold;">(dentro del plazo)</span>';
                }
            }
        }

        $html .= '</span>';

        return new HtmlString($html);
    }


    public static function getHint(Forms\Get $get, string $stageType, string $fieldName, $record = null)
    {
        $field = str_contains($fieldName, '.')
            ? substr($fieldName, strrpos($fieldName, '.') + 1)
            : $fieldName;

        // ✅ BD tiene prioridad — es la fuente de verdad
        $completed = false;

        if ($record && isset($record->id)) {
            $completed = TenderStageS2Completed::whereHas('tenderStage',
                fn($q) => $q->whereHas('tenderStage',
                    fn($q2) => $q2->where('tender_id', $record->id)
                )
            )
            ->where('field_name', $field)
            ->exists();
        }

        // Solo usar estado reactivo si no hay record (edge case)
        if (!$completed) {
            $completed = (bool) $get("s2StageCompleted.$field");
        }

        if ($completed) {
            return new HtmlString('<span style="font-size: 0.75rem;">Realizado</span>');
        }

        return new HtmlString('<span style="font-size: 0.75rem;">En proceso</span>');
    }

     public static function getHintIcon(Forms\Get $get, string $stageType, string $fieldName, $record = null): ?string
    {
        $field = str_contains($fieldName, '.')
            ? substr($fieldName, strrpos($fieldName, '.') + 1)
            : $fieldName;

        $completed = $get("s2StageCompleted.$field");
        if (!$completed && $record?->id) {
            $completed = TenderStageS2Completed::where('tender_stage_id', $record->id)
                ->where('field_name', $field)
                ->exists();
        }

        if ($completed) {
            return 'heroicon-m-check-badge'; // ✅ ícono especial de "realizado"
        }

        $date = $get($fieldName);
        if (!$date) return null;

        $today = \Carbon\Carbon::today();
        $limitDate = \Carbon\Carbon::parse($date);

        if ($today->lt($limitDate)) return 'heroicon-m-check-circle';
        if ($today->eq($limitDate)) return 'heroicon-m-exclamation-triangle';
        return 'heroicon-m-x-circle';
    }


    public static function getHintColor(Forms\Get $get, string $stageType, string $fieldName, $record = null): string
    {
        $field = str_contains($fieldName, '.')
            ? substr($fieldName, strrpos($fieldName, '.') + 1)
            : $fieldName;

        $completed = (bool) $get("s2StageCompleted.$field");

        if (!$completed && $record?->id) {
            $completed = \App\Models\TenderStageS2Completed::whereHas(
                'tenderStage',
                fn($q) => $q->whereHas(
                    'tenderStage',
                    fn($q2) => $q2->where('tender_id', $record->id)
                )
            )
            ->where('field_name', $field)
            ->exists();
        }

        if ($completed) {
            return 'success';
        }

        $date = $get($fieldName);
        if (!$date) return 'gray';

        $today = \Carbon\Carbon::today();
        $limitDate = \Carbon\Carbon::parse($date);

        if ($today->lt($limitDate)) return 'process';
        if ($today->eq($limitDate)) return 'warning';
        return 'danger';
    }


    public static function getHintIconTooltip(Forms\Get $get, string $stageType, string $fieldName, $record = null): ?string
    {
        $field = str_contains($fieldName, '.')
            ? substr($fieldName, strrpos($fieldName, '.') + 1)
            : $fieldName;

        $completed = (bool) $get("s2StageCompleted.$field");

        if ($completed && $record?->id) {
            $registro = \App\Models\TenderStageS2Completed::whereHas('tenderStage',
                fn($q) => $q->whereHas('tenderStage',
                    fn($q2) => $q2->where('tender_id', $record->id)
                )
            )
            ->where('field_name', $field)
            ->with('user')
            ->first();

            if ($registro) {
                $fecha = \Carbon\Carbon::parse($registro->completed_at)->format('d/m/Y H:i');
                $usuario = $registro->user?->name ?? 'Sistema';
                return "✅ Realizado el {$fecha} por {$usuario}";
            }

            return '✅ Etapa realizada';
        }
        $date = $get($fieldName);
        if (!$date) return null;

        $today = \Carbon\Carbon::today();
        $limitDate = \Carbon\Carbon::parse($date);

        if ($today->lt($limitDate)) return "✅ En plazo • Fecha límite: " . $limitDate->format('d/m/Y');
        if ($today->eq($limitDate)) return "⚠️ Último día • Fecha límite: " . $limitDate->format('d/m/Y');
        return "❌ Plazo vencido • Fecha límite: " . $limitDate->format('d/m/Y');
    }

    private static function validateField(Forms\Get $get, string $stageType, string $fieldName, $record = null): ?array
    {
        // 1. PRIMERO: Verificar si hay regla personalizada
        $customRule = null;
        if ($record && $record instanceof Tender && $record->id) {
            $customRule = TenderCustomDeadlineRule::getCustomRule($record->id, $stageType, $fieldName);
        }

        // 2. Si hay regla personalizada, validar contra ella
        if ($customRule) {
            return self::validateFieldForCustomRule($get, $customRule, $fieldName);
        }

        // 3. Si NO hay regla personalizada, validar contra reglas globales
        return self::validateFieldForGlobalRules($get, $stageType, $fieldName);
    }


    private static function validateFieldForCustomRule(Forms\Get $get, TenderCustomDeadlineRule $customRule, string $fieldName): ?array
    {
        // Obtener valor del campo actual
        $currentValue = $get($fieldName);
        if (! $currentValue) {
            return null;
        }

        $currentDate = Carbon::parse($currentValue);
        $customDate = Carbon::parse($customRule->custom_date);
        
        // Calcular días desde la fecha personalizada hasta la fecha actual
        $calendarDays = self::calculateCalendarDays($customDate, $currentDate);
        
        // La validación es: la fecha actual debe ser >= fecha personalizada (0 días o más)
        $isValid = $calendarDays >= 0;
        
        // Obtener labels
        $fieldOptions = TenderFieldExtractor::getFieldOptionsByStage($customRule->from_stage);
        $fromFieldLabel = $fieldOptions[$customRule->from_field] ?? $customRule->from_field;
        
        $toFieldOptions = TenderFieldExtractor::getFieldOptionsByStage($customRule->stage_type);
        $toFieldLabel = $toFieldOptions[$fieldName] ?? $fieldName;

        return [
            'is_valid' => $isValid,
            'rules' => [
                [
                    'valid' => $isValid,
                    'message' => "**Desde**: {$fromFieldLabel} (Personalizada: {$customDate->format('d/m/Y')}) → **Hasta**: {$toFieldLabel}: {$calendarDays} días",
                ],
            ],
        ];
    }


    private static function validateFieldForGlobalRules(Forms\Get $get, string $stageType, string $fieldName): ?array
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
            $calendarDays = self::calculateCalendarDays($fromDate, $currentDate);
            $ruleValid = $calendarDays <= $rule->legal_days;
            
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
                    ? "**Desde**: {$fromFieldLabel} → **Hasta**: {$toFieldLabel}: {$calendarDays} días (máximo: {$rule->legal_days} días según Fecha Programada)"
                    : "**Desde**: {$fromFieldLabel} → **Hasta**: {$toFieldLabel}: {$calendarDays} días (máximo: {$rule->legal_days} días según Fecha Programada)",
                // 'description' => $rule->description ?? 'Sin descripción',
            ];
        }

        return [
            'is_valid' => $isValid,
            'rules' => $rulesInfo,
        ];
    }

    private static function hasValidRule(Forms\Get $get, string $stageType, string $fieldName, $record = null): bool
    {
        // 1. PRIMERO: Verificar si hay regla personalizada
        if ($record && $record instanceof Tender && $record->id) {
            $customRule = TenderCustomDeadlineRule::getCustomRule($record->id, $stageType, $fieldName);
            if ($customRule) {
                // Si hay regla personalizada, siempre es válida (tiene fecha personalizada definida)
                return true;
            }
        }

        // 2. Si NO hay regla personalizada, verificar reglas globales
        // Obtener reglas aplicables
        $rules = TenderDeadlineRule::active()
            ->where('to_stage', $stageType)
            ->where('to_field', $fieldName)
            ->get();

        if ($rules->isEmpty()) {
            return false;
        }

        // Verificar si al menos una regla tiene el campo origen con valor
        foreach ($rules as $rule) {
            $fromFieldValue = $get($rule->from_field);
            
            if ($fromFieldValue) {
                return true;
            }
        }

        return false;
    }

    private static function calculateCalendarDays(Carbon $fromDate, Carbon $toDate): int
    {
        return $fromDate->diffInDays($toDate);
    }


    private static function addCalendarDays(Carbon $date, int $days): Carbon
    {
        return $date->copy()->addDays($days);
    }

    
}

