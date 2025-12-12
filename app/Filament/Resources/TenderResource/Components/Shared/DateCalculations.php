<?php

namespace App\Filament\Resources\TenderResource\Components\Shared;

use App\Models\Tender;
use App\Models\TenderCustomDeadlineRule;
use App\Models\TenderDeadlineRule;
use Filament\Forms;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/**
 * 🧮 COMPONENTE COMPARTIDO: CÁLCULOS DE FECHAS
 *
 * Este componente centraliza toda la lógica de cálculos de días
 * que se reutiliza en múltiples tabs del formulario de Tender.
 *
 * FUNCIONALIDADES:
 * - Cálculo de días calendario entre dos fechas
 * - Cálculo de días hábiles (excluyendo fines de semana)
 * - Cálculo de totales de todas las etapas
 * - Cálculo de fechas ideales (programadas) basadas en reglas
 * - Cálculo de fechas ejecutadas (reales ingresadas por usuario)
 * - Manejo robusto de errores para fechas inválidas
 *
 * USO:
 * - Importar en cualquier tab que necesite cálculos de fechas
 * - Llamar métodos estáticos para obtener cálculos específicos
 * - Usar en Placeholder components para mostrar resultados dinámicos
 */
class DateCalculations
{
    /**
     * 📅 Calcula días calendario entre dos fechas
     *
     * @param  string|null  $startDate  Fecha de inicio
     * @param  string|null  $endDate  Fecha de fin
     * @return int Número de días calendario
     */
    public static function calculateCalendarDays(?string $startDate, ?string $endDate): int
    {
        if (! $startDate || ! $endDate) {
            return 0;
        }

        try {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);

            if ($end->gte($start)) {
                return $start->diffInDays($end);
            }
        } catch (\Exception $e) {
            // Ignorar fechas inválidas
        }

        return 0;
    }

    /**
     * 🏢 Calcula días hábiles entre dos fechas (excluyendo fines de semana)
     *
     * @param  string|null  $startDate  Fecha de inicio
     * @param  string|null  $endDate  Fecha de fin
     * @return int Número de días hábiles
     */
    public static function calculateBusinessDays(?string $startDate, ?string $endDate): int
    {
        if (! $startDate || ! $endDate) {
            return 0;
        }

        try {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);

            if ($end->gte($start)) {
                $businessDays = 0;
                $date = $start->copy();

                while ($date->lte($end)) {
                    if (! $date->isWeekend()) {
                        $businessDays++;
                    }
                    $date->addDay();
                }

                return $businessDays;
            }
        } catch (\Exception $e) {
            // Ignorar fechas inválidas
        }

        return 0;
    }

    /**
     * 📊 Calcula el total de días calendario de todas las etapas
     *
     * @param  Forms\Get  $get  Función para obtener valores del formulario
     * @return HtmlString Resultado formateado para mostrar
     */
    public static function calculateTotalCalendarDays(Forms\Get $get): HtmlString
    {
        // Obtener fechas de todas las etapas
        $stagesData = self::getAllStagesDates($get);

        $totalDays = 0;
        foreach ($stagesData as $stage) {
            $totalDays += self::calculateCalendarDays($stage['start'], $stage['end']);
        }

        if ($totalDays > 0) {
            return new HtmlString("<span class='font-bold text-lg text-blue-600'>{$totalDays} día(s) calendario total</span>");
        } else {
            return new HtmlString("<span class='text-xs text-gray-500'>Complete las fechas de todas las etapas para calcular el total</span>");
        }
    }

    /**
     * 🏢 Calcula el total de días hábiles de todas las etapas
     *
     * @param  Forms\Get  $get  Función para obtener valores del formulario
     * @return HtmlString Resultado formateado para mostrar
     */
    public static function calculateTotalBusinessDays(Forms\Get $get): HtmlString
    {
        // Obtener fechas de todas las etapas
        $stagesData = self::getAllStagesDates($get);

        $totalBusinessDays = 0;
        foreach ($stagesData as $stage) {
            $totalBusinessDays += self::calculateBusinessDays($stage['start'], $stage['end']);
        }

        if ($totalBusinessDays > 0) {
            return new HtmlString("<span class='font-bold text-lg text-green-600'>{$totalBusinessDays} día(s) hábil(es) total</span>");
        } else {
            return new HtmlString("<span class='text-xs text-gray-500'>Complete las fechas de todas las etapas para calcular el total</span>");
        }
    }

    /**
     * 📋 Obtiene todas las fechas de las etapas del formulario
     *
     * @param  Forms\Get  $get  Función para obtener valores del formulario
     * @return array Array con fechas de inicio y fin de cada etapa
     */
    private static function getAllStagesDates(Forms\Get $get): array
    {
        return [
            [
                'start' => $get('s1Stage.request_presentation_date'),
                'end' => $get('s1Stage.approval_expedient_format_2'),
                'name' => 'S1',
            ],
            [
                'start' => $get('s2Stage.published_at'),
                'end' => $get('s2Stage.appeal_date'),
                'name' => 'S2',
            ],
            [
                'start' => $get('s2Stage.appeal_date'), // S3 empieza donde termina S2
                'end' => $get('s3Stage.contract_signing'),
                'name' => 'S3',
            ],
            [
                'start' => $get('s4Stage.contract_signing'),
                'end' => $get('s4Stage.contract_vigency_date'),
                'name' => 'S4',
            ],
        ];
    }

    /**
     * 🎯 Crea un Placeholder para mostrar días calendario de una etapa específica
     *
     * @param  string  $startField  Campo de fecha de inicio
     * @param  string  $endField  Campo de fecha de fin
     * @param  string  $placeholderName  Nombre del placeholder
     * @return Forms\Components\Placeholder Componente Placeholder configurado
     */
    public static function createCalendarDaysPlaceholder(string $startField, string $endField, string $placeholderName): Forms\Components\Placeholder
    {
        return Forms\Components\Placeholder::make($placeholderName)
            ->label(false)
            ->content(function (Forms\Get $get) use ($startField, $endField) {
                $start = $get($startField);
                $end = $get($endField);

                if (! $start || ! $end) {
                    return new HtmlString("<span class='text-xs'>Las Fechas con icono de bandera deben ser seleccionadas para el cálculo.</span>");
                }

                $days = self::calculateCalendarDays($start, $end);

                if ($days > 0) {
                    return new HtmlString("<span class='font-bold text-lg'>{$days} día(s) calendario</span>");
                } else {
                    return 'Fechas inválidas, la fecha de finalización debe ser mayor a la fecha de inicio';
                }
            });
    }

    /**
     * 🏢 Crea un Placeholder para mostrar días hábiles de una etapa específica
     *
     * @param  string  $startField  Campo de fecha de inicio
     * @param  string  $endField  Campo de fecha de fin
     * @param  string  $placeholderName  Nombre del placeholder
     * @return Forms\Components\Placeholder Componente Placeholder configurado
     */
    public static function createBusinessDaysPlaceholder(string $startField, string $endField, string $placeholderName): Forms\Components\Placeholder
    {
        return Forms\Components\Placeholder::make($placeholderName)
            ->label(false)
            ->content(function (Forms\Get $get) use ($startField, $endField) {
                $start = $get($startField);
                $end = $get($endField);

                if (! $start || ! $end) {
                    return new HtmlString("<span class='text-xs'>Las Fechas con icono de bandera deben ser seleccionadas para el cálculo.</span>");
                }

                $businessDays = self::calculateBusinessDays($start, $end);

                if ($businessDays > 0) {
                    return new HtmlString("<span class='font-bold text-lg'>{$businessDays} día(s) hábil(es)</span>");
                } else {
                    return 'Fechas inválidas, la fecha de finalización debe ser mayor a la fecha de inicio';
                }
            });
    }

    /**
     * 🎯 Obtiene la fecha programada (ideal) de un campo según reglas
     *
     * Prioriza reglas personalizadas sobre reglas globales.
     * Si no hay regla, retorna null.
     *
     * @param  Forms\Get  $get  Objeto Get de Filament
     * @param  string  $stageType  Tipo de etapa (S1, S2, S3, S4)
     * @param  string  $fieldName  Nombre del campo (ej: 's1Stage.certification_date')
     * @param  Tender|null  $record  Registro del Tender (opcional)
     * @return string|null Fecha programada en formato Y-m-d o null
     */
    public static function getScheduledDate(Forms\Get $get, string $stageType, string $fieldName, $record = null): ?string
    {
        // 1. PRIMERO: Verificar si hay regla personalizada
        if ($record && $record instanceof Tender && $record->id) {
            $customRule = TenderCustomDeadlineRule::getCustomRule($record->id, $stageType, $fieldName);
            if ($customRule && $customRule->custom_date) {
                return $customRule->custom_date->format('Y-m-d');
            }
        }

        // 2. Si NO hay regla personalizada, usar reglas globales
        $rules = TenderDeadlineRule::active()
            ->where('to_stage', $stageType)
            ->where('to_field', $fieldName)
            ->get();

        if ($rules->isEmpty()) {
            return null;
        }

        // Obtener la fecha programada de la primera regla válida
        foreach ($rules as $rule) {
            $fromFieldValue = self::getFieldValue($get, $rule->from_field, $record);
            
            if (! $fromFieldValue) {
                continue;
            }

            try {
                $fromDate = Carbon::parse($fromFieldValue);
                $scheduledDate = $fromDate->copy()->addDays($rule->legal_days);
                return $scheduledDate->format('Y-m-d');
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * 🎯 Obtiene la fecha ejecutada (real) de un campo
     *
     * Prioriza datos guardados del $record sobre datos del formulario.
     *
     * @param  Forms\Get  $get  Objeto Get de Filament
     * @param  string  $fieldName  Nombre del campo (ej: 's1Stage.certification_date')
     * @param  Tender|null  $record  Registro del Tender (opcional)
     * @return string|null Fecha ejecutada en formato Y-m-d o null
     */
    public static function getExecutedDate(Forms\Get $get, string $fieldName, $record = null): ?string
    {
        $value = self::getFieldValue($get, $fieldName, $record);
        
        if (! $value) {
            return null;
        }

        try {
            $date = Carbon::parse($value);
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 🎯 Obtiene el valor de un campo desde el record guardado o del formulario como fallback
     *
     * Reutiliza la lógica de DeadlineHintHelper para obtener valores.
     *
     * @param  Forms\Get  $get  Objeto Get de Filament
     * @param  string  $fieldName  Nombre del campo
     * @param  Tender|null  $record  Registro del Tender
     * @return mixed Valor del campo o null
     */
    private static function getFieldValue(Forms\Get $get, string $fieldName, $record = null)
    {
        if ($record && $record instanceof Tender && $record->id) {
            $result = self::getFieldValueFromRecord($record, $fieldName);
            if (is_array($result) && isset($result['exists']) && !$result['exists']) {
                return $get($fieldName);
            }
            return is_array($result) && isset($result['value']) ? $result['value'] : $result;
        }
        return $get($fieldName);
    }

    /**
     * 🎯 Obtiene el valor de un campo desde el record
     *
     * @param  Tender  $record  Registro del Tender
     * @param  string  $fieldName  Nombre del campo (ej: 's1Stage.market_indagation_date')
     * @return array|mixed Valor del campo o array con 'exists' => false
     */
    private static function getFieldValueFromRecord(Tender $record, string $fieldName)
    {
        if (!preg_match('/^(s[1-4]Stage)\.(.+)$/i', $fieldName, $matches)) {
            return ['exists' => false];
        }
        
        $stageRelation = strtolower($matches[1]);
        $fieldNameOnly = $matches[2];

        $stage = null;
        switch ($stageRelation) {
            case 's1stage': $stage = $record->s1Stage()->first(); break;
            case 's2stage': $stage = $record->s2Stage()->first(); break;
            case 's3stage': $stage = $record->s3Stage()->first(); break;
            case 's4stage': $stage = $record->s4Stage()->first(); break;
            default: return ['exists' => false];
        }

        if (!$stage) {
            return ['exists' => false];
        }
        
        $value = $stage->$fieldNameOnly ?? null;
        if ($value instanceof \Carbon\Carbon || $value instanceof \DateTime) {
            return $value->format('Y-m-d');
        }
        return $value;
    }

    /**
     * 🎯 Crea un Placeholder para mostrar cálculos duales (Ideales y Ejecutadas)
     *
     * Muestra días calendario y hábiles para ambos tipos de fechas.
     *
     * @param  string  $stageType  Tipo de etapa (S1, S2, S3, S4)
     * @param  string  $startField  Campo de fecha de inicio
     * @param  string  $endField  Campo de fecha de fin
     * @param  string  $placeholderName  Nombre del placeholder
     * @return Forms\Components\Placeholder Componente Placeholder configurado
     */
    public static function createDualCalculationPlaceholder(string $stageType, string $startField, string $endField, string $placeholderName): Forms\Components\Placeholder
    {
        return Forms\Components\Placeholder::make($placeholderName)
            ->label(false)
            ->content(function (Forms\Get $get, $record) use ($stageType, $startField, $endField) {
                // Obtener fechas ejecutadas (reales)
                $executedStart = self::getExecutedDate($get, $startField, $record);
                $executedEnd = self::getExecutedDate($get, $endField, $record);

                // Si no hay fechas ejecutadas, mostrar mensaje
                if (! $executedStart || ! $executedEnd) {
                    return new HtmlString("<span class='text-xs text-gray-500'>Complete las fechas de inicio y fin para calcular los totales.</span>");
                }

                // Obtener fechas programadas (ideales)
                // Si no hay fecha programada, usar la fecha ejecutada como fallback
                $scheduledStart = self::getScheduledDate($get, $stageType, $startField, $record) ?? $executedStart;
                $scheduledEnd = self::getScheduledDate($get, $stageType, $endField, $record) ?? $executedEnd;

                // Calcular días ejecutados
                $executedCalendarDays = self::calculateCalendarDays($executedStart, $executedEnd);
                $executedBusinessDays = self::calculateBusinessDays($executedStart, $executedEnd);

                // Calcular días ideales
                // Si ambas fechas programadas son diferentes de las ejecutadas, calcular entre programadas
                // Si solo una es programada, usar la ejecutada como fallback
                $idealStart = $scheduledStart !== $executedStart ? $scheduledStart : $executedStart;
                $idealEnd = $scheduledEnd !== $executedEnd ? $scheduledEnd : $executedEnd;
                
                $idealCalendarDays = self::calculateCalendarDays($idealStart, $idealEnd);
                $idealBusinessDays = self::calculateBusinessDays($idealStart, $idealEnd);
                
                // Verificar si realmente hay fechas programadas (diferentes de ejecutadas)
                $hasIdealDates = ($scheduledStart !== $executedStart || $scheduledEnd !== $executedEnd);

                // Construir HTML
                $html = '<div class="space-y-3">';
                
                // Sección: Fechas Ideales (Programadas)
                $html .= '<div class="border-b border-gray-200 pb-2">';
                $html .= '<h3 class="font-semibold text-sm text-gray-700 mb-2">FECHAS IDEALES (Programadas)</h3>';
                
                if ($hasIdealDates) {
                    $html .= '<div class="space-y-1">';
                    $html .= "<span class='text-sm'><strong class='text-blue-600'>{$idealCalendarDays}</strong> día(s) calendario</span><br>";
                    $html .= "<span class='text-sm'><strong class='text-green-600'>{$idealBusinessDays}</strong> día(s) hábil(es)</span>";
                    $html .= '</div>';
                } else {
                    $html .= "<span class='text-xs text-gray-500'>No hay reglas configuradas para calcular fechas ideales</span>";
                }
                
                $html .= '</div>';

                // Sección: Fechas Ejecutadas
                $html .= '<div class="pt-2">';
                $html .= '<h3 class="font-semibold text-sm text-gray-700 mb-2">FECHAS EJECUTADAS</h3>';
                $html .= '<div class="space-y-1">';
                $html .= "<span class='text-sm'><strong class='text-blue-600'>{$executedCalendarDays}</strong> día(s) calendario</span><br>";
                $html .= "<span class='text-sm'><strong class='text-green-600'>{$executedBusinessDays}</strong> día(s) hábil(es)</span>";
                $html .= '</div>';
                $html .= '</div>';

                // Sección: Diferencia (si hay fechas ideales)
                if ($hasIdealDates) {
                    $diffCalendar = $executedCalendarDays - $idealCalendarDays;
                    $diffBusiness = $executedBusinessDays - $idealBusinessDays;
                    
                    $html .= '<div class="border-t border-gray-200 pt-2 mt-2">';
                    $html .= '<h3 class="font-semibold text-sm text-gray-700 mb-2">DIFERENCIA</h3>';
                    $html .= '<div class="space-y-1">';
                    
                    $calendarColor = $diffCalendar > 0 ? 'text-red-600' : ($diffCalendar < 0 ? 'text-green-600' : 'text-gray-600');
                    $calendarSign = $diffCalendar > 0 ? '+' : '';
                    $html .= "<span class='text-sm'><strong class='{$calendarColor}'>{$calendarSign}{$diffCalendar}</strong> día(s) calendario</span><br>";
                    
                    $businessColor = $diffBusiness > 0 ? 'text-red-600' : ($diffBusiness < 0 ? 'text-green-600' : 'text-gray-600');
                    $businessSign = $diffBusiness > 0 ? '+' : '';
                    $html .= "<span class='text-sm'><strong class='{$businessColor}'>{$businessSign}{$diffBusiness}</strong> día(s) hábil(es)</span>";
                    
                    $html .= '</div>';
                    $html .= '</div>';
                }

                $html .= '</div>';

                return new HtmlString($html);
            });
    }

    /**
     * 🎯 Crea un Placeholder para mostrar cálculos duales de TODAS las etapas
     *
     * Calcula totales ideales y ejecutados para S1, S2, S3 y S4.
     *
     * @param  string  $placeholderName  Nombre del placeholder
     * @return Forms\Components\Placeholder Componente Placeholder configurado
     */
    public static function createCompleteDualCalculationPlaceholder(string $placeholderName): Forms\Components\Placeholder
    {
        return Forms\Components\Placeholder::make($placeholderName)
            ->label(false)
            ->content(function (Forms\Get $get, $record) {
                // Configuración de etapas
                $stages = [
                    [
                        'stageType' => 'S1',
                        'startField' => 's1Stage.request_presentation_date',
                        'endField' => 's1Stage.approval_expedient_format_2',
                    ],
                    [
                        'stageType' => 'S2',
                        'startField' => 's2Stage.published_at',
                        'endField' => 's2Stage.appeal_date',
                    ],
                    [
                        'stageType' => 'S3',
                        'startField' => 's2Stage.appeal_date',
                        'endField' => 's3Stage.contract_signing',
                    ],
                    [
                        'stageType' => 'S4',
                        'startField' => 's4Stage.contract_signing',
                        'endField' => 's4Stage.contract_vigency_date',
                    ],
                ];

                $totalIdealCalendarDays = 0;
                $totalIdealBusinessDays = 0;
                $totalExecutedCalendarDays = 0;
                $totalExecutedBusinessDays = 0;
                $hasIdealDates = false;
                $hasExecutedDates = false;

                // Calcular para cada etapa
                foreach ($stages as $stage) {
                    $executedStart = self::getExecutedDate($get, $stage['startField'], $record);
                    $executedEnd = self::getExecutedDate($get, $stage['endField'], $record);

                    // Calcular días ejecutados
                    if ($executedStart && $executedEnd) {
                        $hasExecutedDates = true;
                        $totalExecutedCalendarDays += self::calculateCalendarDays($executedStart, $executedEnd);
                        $totalExecutedBusinessDays += self::calculateBusinessDays($executedStart, $executedEnd);
                    }

                    // Obtener fechas programadas (ideales) solo si hay fechas ejecutadas
                    if ($executedStart && $executedEnd) {
                        // Si no hay fecha programada, usar la fecha ejecutada como fallback
                        $scheduledStart = self::getScheduledDate($get, $stage['stageType'], $stage['startField'], $record) ?? $executedStart;
                        $scheduledEnd = self::getScheduledDate($get, $stage['stageType'], $stage['endField'], $record) ?? $executedEnd;

                        // Calcular días ideales
                        // Si ambas fechas programadas son diferentes de las ejecutadas, calcular entre programadas
                        // Si solo una es programada, usar la ejecutada como fallback
                        $idealStart = $scheduledStart !== $executedStart ? $scheduledStart : $executedStart;
                        $idealEnd = $scheduledEnd !== $executedEnd ? $scheduledEnd : $executedEnd;
                        
                        // Verificar si realmente hay fechas programadas (diferentes de ejecutadas)
                        if ($scheduledStart !== $executedStart || $scheduledEnd !== $executedEnd) {
                            $hasIdealDates = true;
                        }
                        
                        $totalIdealCalendarDays += self::calculateCalendarDays($idealStart, $idealEnd);
                        $totalIdealBusinessDays += self::calculateBusinessDays($idealStart, $idealEnd);
                    }
                }

                // Si no hay fechas ejecutadas, mostrar mensaje
                if (! $hasExecutedDates) {
                    return new HtmlString("<span class='text-xs text-gray-500'>Complete las fechas de todas las etapas para calcular los totales.</span>");
                }

                // Construir HTML
                $html = '<div class="space-y-3">';
                
                // Sección: Fechas Ideales (Programadas)
                $html .= '<div class="border-b border-gray-200 pb-2">';
                $html .= '<h3 class="font-semibold text-sm text-gray-700 mb-2">FECHAS IDEALES (Programadas) - TOTAL</h3>';
                
                if ($hasIdealDates) {
                    $html .= '<div class="space-y-1">';
                    $html .= "<span class='text-sm'><strong class='text-blue-600'>{$totalIdealCalendarDays}</strong> día(s) calendario</span><br>";
                    $html .= "<span class='text-sm'><strong class='text-green-600'>{$totalIdealBusinessDays}</strong> día(s) hábil(es)</span>";
                    $html .= '</div>';
                } else {
                    $html .= "<span class='text-xs text-gray-500'>No hay reglas configuradas para calcular fechas ideales</span>";
                }
                
                $html .= '</div>';

                // Sección: Fechas Ejecutadas
                $html .= '<div class="pt-2">';
                $html .= '<h3 class="font-semibold text-sm text-gray-700 mb-2">FECHAS EJECUTADAS - TOTAL</h3>';
                $html .= '<div class="space-y-1">';
                $html .= "<span class='text-sm'><strong class='text-blue-600'>{$totalExecutedCalendarDays}</strong> día(s) calendario</span><br>";
                $html .= "<span class='text-sm'><strong class='text-green-600'>{$totalExecutedBusinessDays}</strong> día(s) hábil(es)</span>";
                $html .= '</div>';
                $html .= '</div>';

                // Sección: Diferencia (si hay fechas ideales)
                if ($hasIdealDates) {
                    $diffCalendar = $totalExecutedCalendarDays - $totalIdealCalendarDays;
                    $diffBusiness = $totalExecutedBusinessDays - $totalIdealBusinessDays;
                    
                    $html .= '<div class="border-t border-gray-200 pt-2 mt-2">';
                    $html .= '<h3 class="font-semibold text-sm text-gray-700 mb-2">DIFERENCIA</h3>';
                    $html .= '<div class="space-y-1">';
                    
                    $calendarColor = $diffCalendar > 0 ? 'text-red-600' : ($diffCalendar < 0 ? 'text-green-600' : 'text-gray-600');
                    $calendarSign = $diffCalendar > 0 ? '+' : '';
                    $html .= "<span class='text-sm'><strong class='{$calendarColor}'>{$calendarSign}{$diffCalendar}</strong> día(s) calendario</span><br>";
                    
                    $businessColor = $diffBusiness > 0 ? 'text-red-600' : ($diffBusiness < 0 ? 'text-green-600' : 'text-gray-600');
                    $businessSign = $diffBusiness > 0 ? '+' : '';
                    $html .= "<span class='text-sm'><strong class='{$businessColor}'>{$businessSign}{$diffBusiness}</strong> día(s) hábil(es)</span>";
                    
                    $html .= '</div>';
                    $html .= '</div>';
                }

                $html .= '</div>';

                return new HtmlString($html);
            });
    }
}
