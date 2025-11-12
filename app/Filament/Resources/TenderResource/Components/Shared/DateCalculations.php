<?php

namespace App\Filament\Resources\TenderResource\Components\Shared;

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
}
