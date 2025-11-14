<?php

namespace App\Filament\Resources\TenderResource\Components\Shared;

use App\Models\Tender;
use App\Models\TenderCustomDeadlineRule;
use App\Models\TenderDeadlineRule;
use App\Services\TenderFieldExtractor;
use Carbon\Carbon;
use Filament\Forms;
use Illuminate\Support\HtmlString;

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
 * - Usa datos guardados del record, no datos en edición (evita actualizaciones en tiempo real)
 *
 * USO:
 * DatePicker::make('field_name')
 *     ->helperText(fn(Get $get, $record) => DeadlineHintHelper::getHelperText($get, 'S1', 'field_name', $record))
 *     ->hint(fn(Get $get, $record) => DeadlineHintHelper::getHint($get, 'S1', 'field_name', $record))
 *     ->hintIcon(fn(Get $get, $record) => DeadlineHintHelper::getHintIcon($get, 'S1', 'field_name', $record))
 *     ->hintColor(fn(Get $get, $record) => DeadlineHintHelper::getHintColor($get, 'S1', 'field_name', $record))
 *     ->hintIconTooltip(fn(Get $get, $record) => DeadlineHintHelper::getHintIconTooltip($get, 'S1', 'field_name', $record))
 *
 * NOTA: Los días hábiles se implementarán en una fase posterior.
 * NOTA: Este helper prioriza datos guardados del $record sobre datos del formulario ($get) para evitar
 *       actualizaciones en tiempo real mientras se edita. Solo se actualiza después de guardar.
 */
class DeadlineHintHelper
{
    /**
     * 🎯 Obtiene el valor de un campo desde el record guardado o del formulario como fallback
     *
     * Prioriza datos guardados del $record para evitar actualizaciones en tiempo real.
     * Solo usa $get() si no hay $record o el campo no está guardado.
     *
     * @param  Forms\Get  $get  Objeto Get de Filament (datos del formulario)
     * @param  string  $fieldName  Nombre del campo (ej: 's1Stage.market_indagation_date')
     * @param  Tender|null  $record  Registro del Tender guardado
     * @return mixed Valor del campo o null
     */
    private static function getFieldValue(Forms\Get $get, string $fieldName, $record = null)
    {
        // 1. PRIMERO: Intentar obtener del record guardado
        if ($record && $record instanceof Tender && $record->id) {
            $result = self::getFieldValueFromRecord($record, $fieldName);
            // Si retorna un array con 'exists' => false, el stage no existe → usar fallback
            if (is_array($result) && isset($result['exists']) && !$result['exists']) {
                return $get($fieldName);
            }
            // Si retorna un valor (incluso null), usar ese valor guardado
            return is_array($result) && isset($result['value']) ? $result['value'] : $result;
        }

        // 2. FALLBACK: Usar datos del formulario (solo si no hay record o campo no guardado)
        return $get($fieldName);
    }

    /**
     * 🎯 Obtiene el valor de un campo desde el record guardado
     *
     * @param  Tender  $record  Registro del Tender
     * @param  string  $fieldName  Nombre del campo (ej: 's1Stage.market_indagation_date')
     * @return mixed|array Valor del campo, null si está vacío, o ['exists' => false] si el stage no existe
     */
    private static function getFieldValueFromRecord(Tender $record, string $fieldName)
    {
        // Extraer stage y nombre del campo (ej: 's1Stage.market_indagation_date' -> 'S1', 'market_indagation_date')
        if (!preg_match('/^(s[1-4]Stage)\.(.+)$/i', $fieldName, $matches)) {
            return ['exists' => false];
        }

        $stageRelation = strtolower($matches[1]); // 's1stage'
        $fieldNameOnly = $matches[2]; // 'market_indagation_date'

        // Obtener el stage correspondiente usando first() en la relación
        $stage = null;
        switch ($stageRelation) {
            case 's1stage':
                $stage = $record->s1Stage()->first();
                break;
            case 's2stage':
                $stage = $record->s2Stage()->first();
                break;
            case 's3stage':
                $stage = $record->s3Stage()->first();
                break;
            case 's4stage':
                $stage = $record->s4Stage()->first();
                break;
            default:
                return ['exists' => false];
        }

        // Si no existe el stage, retornar indicador de que no existe
        if (!$stage) {
            return ['exists' => false];
        }

        // Obtener el valor del campo (puede ser null si está vacío)
        $value = $stage->$fieldNameOnly ?? null;

        // Si es una fecha, convertirla a string en formato Y-m-d
        if ($value instanceof \Carbon\Carbon || $value instanceof \DateTime) {
            return $value->format('Y-m-d');
        }

        // Retornar el valor (puede ser null si el campo está vacío, pero el stage existe)
        return $value;
    }
    /**
     * 🎯 Genera el helperText con la fecha programada según las reglas
     *
     * @param  Forms\Get  $get  Objeto Get de Filament
     * @param  string  $stageType  Tipo de etapa (S1, S2, S3, S4)
     * @param  string  $fieldName  Nombre del campo (con prefijo stageX.)
     * @param  Tender|null  $record  Registro del Tender (opcional, para reglas personalizadas)
     * @return HtmlString|null
     */
    public static function getHelperText(Forms\Get $get, string $stageType, string $fieldName, $record = null): ?HtmlString
    {
        // 1. PRIMERO: Verificar si hay regla personalizada para este Tender + campo
        $customRule = null;
        if ($record && $record instanceof Tender && $record->id) {
            $customRule = TenderCustomDeadlineRule::getCustomRule($record->id, $stageType, $fieldName);
        }

        // 2. Si hay regla personalizada, usarla
        if ($customRule) {
            return self::getHelperTextForCustomRule($get, $customRule, $fieldName, $record);
        }

        // 3. Si NO hay regla personalizada, usar reglas globales (comportamiento actual)
        return self::getHelperTextForGlobalRules($get, $stageType, $fieldName, $record);
    }

    /**
     * 🎯 Genera helperText para regla personalizada
     */
    private static function getHelperTextForCustomRule(Forms\Get $get, TenderCustomDeadlineRule $customRule, string $fieldName, $record = null): ?HtmlString
    {
        // Obtener valor del campo actual (prioriza datos guardados)
        $currentValue = self::getFieldValue($get, $fieldName, $record);
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

    /**
     * 🎯 Genera helperText para reglas globales (comportamiento original)
     */
    private static function getHelperTextForGlobalRules(Forms\Get $get, string $stageType, string $fieldName, $record = null): ?HtmlString
    {
        // Obtener reglas aplicables
        $rules = TenderDeadlineRule::active()
            ->where('to_stage', $stageType)
            ->where('to_field', $fieldName)
            ->get();

        if ($rules->isEmpty()) {
            return null;
        }

        // Obtener valor del campo actual (prioriza datos guardados)
        $currentValue = self::getFieldValue($get, $fieldName, $record);
        if (! $currentValue) {
            return null;
        }

        $currentDate = Carbon::parse($currentValue);
        $scheduledDates = [];

        // Calcular fechas programadas para cada regla
        foreach ($rules as $rule) {
            $fromFieldValue = self::getFieldValue($get, $rule->from_field, $record);
            
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
            $fromFieldValue = self::getFieldValue($get, $rule->from_field, $record);
            
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

    /**
     * 🎯 Genera el hint del campo
     *
     * Solo muestra "Fecha Ejecutada" si existe una regla válida con el campo origen completo.
     * Esto evita mostrar hints en campos opcionales sin valor.
     *
     * @param  Forms\Get  $get  Objeto Get de Filament
     * @param  string  $stageType  Tipo de etapa
     * @param  string  $fieldName  Nombre del campo
     * @param  Tender|null  $record  Registro del Tender (opcional, para reglas personalizadas)
     * @return string|null
     */
    public static function getHint(Forms\Get $get, string $stageType, string $fieldName, $record = null)
    {
        $currentValue = self::getFieldValue($get, $fieldName, $record);
        if (! $currentValue) {
            return null;
        }

        // Verificar si hay reglas válidas (con campo origen presente o regla personalizada)
        $hasValidRule = self::hasValidRule($get, $stageType, $fieldName, $record);
        if (! $hasValidRule) {
            return null;
        }

        return new HtmlString('<span style="font-size: 0.75rem;">F. Ejecutada</span>');
    }

    /**
     * 🎯 Genera el hintIcon del campo (check o x)
     * 
     * Solo muestra el icono si existe una regla válida (con campo origen presente).
     *
     * @param  Forms\Get  $get  Objeto Get de Filament
     * @param  string  $stageType  Tipo de etapa
     * @param  string  $fieldName  Nombre del campo
     * @param  Tender|null  $record  Registro del Tender (opcional, para reglas personalizadas)
     * @return string|null
     */
    public static function getHintIcon(Forms\Get $get, string $stageType, string $fieldName, $record = null): ?string
    {
        // Verificar si hay reglas válidas primero
        $hasValidRule = self::hasValidRule($get, $stageType, $fieldName, $record);
        if (! $hasValidRule) {
            return null;
        }

        $validation = self::validateField($get, $stageType, $fieldName, $record);
        
        if ($validation === null) {
            return null;
        }

        return $validation['is_valid'] ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle';
    }

    /**
     * 🎯 Genera el hintColor del campo
     * 
     * Solo muestra el color si existe una regla válida (con campo origen presente).
     *
     * @param  Forms\Get  $get  Objeto Get de Filament
     * @param  string  $stageType  Tipo de etapa
     * @param  string  $fieldName  Nombre del campo
     * @param  Tender|null  $record  Registro del Tender (opcional, para reglas personalizadas)
     * @return string
     */
    public static function getHintColor(Forms\Get $get, string $stageType, string $fieldName, $record = null): string
    {
        // Verificar si hay reglas válidas primero
        $hasValidRule = self::hasValidRule($get, $stageType, $fieldName, $record);
        if (! $hasValidRule) {
            return 'gray';
        }

        $validation = self::validateField($get, $stageType, $fieldName, $record);
        
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
     * @param  Tender|null  $record  Registro del Tender (opcional, para reglas personalizadas)
     * @return string|null
     */
    public static function getHintIconTooltip(Forms\Get $get, string $stageType, string $fieldName, $record = null): ?string
    {
        $validation = self::validateField($get, $stageType, $fieldName, $record);
        
        if ($validation === null) {
            return null;
        }

        // Construir mensaje adornado: estado + desde/hasta
        $rulesText = [];
        foreach ($validation['rules'] as $ruleInfo) {
            // Extraer desde y hasta del mensaje
            preg_match('/\*\*Desde\*\*: (.*?) → \*\*Hasta\*\*: (.*?): \d+ días/', $ruleInfo['message'], $matches);
            if (isset($matches[1]) && isset($matches[2])) {
                $fromLabel = $matches[1];
                $toLabel = $matches[2];
                $rulesText[] = "Desde: {$fromLabel} → Hasta: {$toLabel}";
            }
        }

        $rulesStr = implode(' | ', $rulesText);
        
        // Verificar si alguna fecha es anterior a origen
        $hasBeforeOrigin = false;
        foreach ($validation['rules'] as $ruleInfo) {
            // Buscar en los datos originales si hay fecha anterior
            preg_match('/: (\d+) días/', $ruleInfo['message'], $daysMatch);
            if (isset($daysMatch[1]) && $daysMatch[1] < 0) {
                $hasBeforeOrigin = true;
                break;
            }
        }
        
        if ($hasBeforeOrigin) {
            return "⚠️ Error de lógica: Fecha ejecutada es anterior a la de origen • {$rulesStr}";
        } elseif ($validation['is_valid']) {
            return "✅ Plazo cumplido • {$rulesStr}";
        } else {
            return "❌ Plazo excedido • {$rulesStr}";
        }
    }

    /**
     * 🎯 Valida el campo contra las reglas de plazo
     *
     * @param  Forms\Get  $get  Objeto Get de Filament
     * @param  string  $stageType  Tipo de etapa
     * @param  string  $fieldName  Nombre del campo
     * @param  Tender|null  $record  Registro del Tender (opcional, para reglas personalizadas)
     * @return array|null
     */
    private static function validateField(Forms\Get $get, string $stageType, string $fieldName, $record = null): ?array
    {
        // 1. PRIMERO: Verificar si hay regla personalizada
        $customRule = null;
        if ($record && $record instanceof Tender && $record->id) {
            $customRule = TenderCustomDeadlineRule::getCustomRule($record->id, $stageType, $fieldName);
        }

        // 2. Si hay regla personalizada, validar contra ella
        if ($customRule) {
            return self::validateFieldForCustomRule($get, $customRule, $fieldName, $record);
        }

        // 3. Si NO hay regla personalizada, validar contra reglas globales
        return self::validateFieldForGlobalRules($get, $stageType, $fieldName, $record);
    }

    /**
     * 🎯 Valida el campo contra regla personalizada
     */
    private static function validateFieldForCustomRule(Forms\Get $get, TenderCustomDeadlineRule $customRule, string $fieldName, $record = null): ?array
    {
        // Obtener valor del campo actual (prioriza datos guardados)
        $currentValue = self::getFieldValue($get, $fieldName, $record);
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

    /**
     * 🎯 Valida el campo contra reglas globales (comportamiento original)
     */
    private static function validateFieldForGlobalRules(Forms\Get $get, string $stageType, string $fieldName, $record = null): ?array
    {
        // Obtener reglas aplicables
        $rules = TenderDeadlineRule::active()
            ->where('to_stage', $stageType)
            ->where('to_field', $fieldName)
            ->get();

        if ($rules->isEmpty()) {
            return null;
        }

        // Obtener valor del campo actual (prioriza datos guardados)
        $currentValue = self::getFieldValue($get, $fieldName, $record);
        if (! $currentValue) {
            return null;
        }

        $currentDate = Carbon::parse($currentValue);
        $isValid = true;
        $rulesInfo = [];

        // Validar contra cada regla
        foreach ($rules as $rule) {
            $fromFieldValue = self::getFieldValue($get, $rule->from_field, $record);
            
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

    /**
     * 🎯 Verifica si existe una regla válida (con campo origen presente o regla personalizada)
     *
     * Este método verifica si hay al menos una regla que tenga el campo origen
     * con valor, o si existe una regla personalizada. Si no hay reglas o ningún campo origen tiene valor, retorna false.
     *
     * @param  Forms\Get  $get  Objeto Get de Filament
     * @param  string  $stageType  Tipo de etapa
     * @param  string  $fieldName  Nombre del campo
     * @param  Tender|null  $record  Registro del Tender (opcional, para reglas personalizadas)
     * @return bool True si hay al menos una regla válida
     */
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
            $fromFieldValue = self::getFieldValue($get, $rule->from_field, $record);
            
            if ($fromFieldValue) {
                return true;
            }
        }

        return false;
    }

    /**
     * 🎯 Calcula días calendario entre dos fechas
     *
     * NOTA: Este método calcula días calendario (incluyendo fines de semana)
     * en lugar de días hábiles. Los días hábiles se implementarán en una
     * fase posterior del sistema.
     *
     * @param  Carbon  $fromDate  Fecha de inicio
     * @param  Carbon  $toDate  Fecha de fin
     * @return int Número de días calendario (incluye fines de semana)
     */
    private static function calculateCalendarDays(Carbon $fromDate, Carbon $toDate): int
    {
        return $fromDate->diffInDays($toDate);
    }

    /**
     * 🎯 Agrega días calendario a una fecha
     *
     * NOTA: Este método agrega días calendario (incluyendo fines de semana)
     * en lugar de días hábiles. Los días hábiles se implementarán en una
     * fase posterior del sistema.
     *
     * @param  Carbon  $date  Fecha de inicio
     * @param  int  $days  Número de días calendario a agregar (incluye fines de semana)
     * @return Carbon Fecha resultante
     */
    private static function addCalendarDays(Carbon $date, int $days): Carbon
    {
        return $date->copy()->addDays($days);
    }
}

