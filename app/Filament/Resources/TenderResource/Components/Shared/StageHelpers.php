<?php

namespace App\Filament\Resources\TenderResource\Components\Shared;

use Filament\Forms\Components\Placeholder;

/**
 * 🛠️ COMPONENTE COMPARTIDO: HELPERS DE ETAPAS
 *
 * Este componente proporciona funciones auxiliares y componentes
 * reutilizables para todas las etapas del formulario de Tender.
 *
 * FUNCIONALIDADES:
 * - Placeholders de estado de etapas (creada/pendiente)
 * - Componentes de información legal (plazos según ley)
 * - Helpers para validaciones comunes
 * - Componentes de feedback visual
 *
 * USO:
 * - Importar en cualquier tab que necesite componentes comunes
 * - Usar métodos estáticos para crear componentes reutilizables
 * - Mantener consistencia visual en todas las etapas
 */
class StageHelpers
{
    /**
     * ✅ Crea un placeholder que muestra el estado "creada" de una etapa
     *
     * @param  string  $stageName  Nombre de la etapa (ej: "1.Act. Preparatorias")
     * @param  string  $placeholderName  Nombre único del placeholder
     * @param  callable  $isCreatedCallback  Callback para verificar si la etapa está creada
     * @return Placeholder Componente Placeholder configurado
     */
    public static function createStageCreatedPlaceholder(string $stageName, string $placeholderName, callable $isCreatedCallback): Placeholder
    {
        return Placeholder::make($placeholderName)
            // ->label("✅ La etapa {$stageName} está creada. Puede editar los datos a continuación.")
            ->label(false)
            ->visible($isCreatedCallback)
            ->columnSpanFull();
    }

    /**
     * ⏳ Crea un placeholder que muestra el estado "pendiente" de una etapa
     *
     * @param  string  $stageName  Nombre de la etapa (ej: "1.Act. Preparatorias")
     * @param  string  $placeholderName  Nombre único del placeholder
     * @param  callable  $isNotCreatedCallback  Callback para verificar si la etapa NO está creada
     * @return Placeholder Componente Placeholder configurado
     */
    public static function createStagePendingPlaceholder(string $stageName, string $placeholderName, callable $isNotCreatedCallback): Placeholder
    {
        return Placeholder::make($placeholderName)
            ->label("⏳ La etapa {$stageName} no está creada. Haga clic en \"Crear Etapa\" para inicializarla.")
            ->visible($isNotCreatedCallback)
            ->columnSpanFull();
    }

    /**
     * 📋 Crea un placeholder que muestra información legal (plazos según ley)
     *
     * @param  string  $legalTimeframe  Texto del plazo legal (ej: "02 días hábiles")
     * @param  string  $placeholderName  Nombre único del placeholder
     * @return Placeholder Componente Placeholder configurado
     */
    public static function createLegalTimeframePlaceholder(string $legalTimeframe, string $placeholderName): Placeholder
    {
        return Placeholder::make($placeholderName)
            ->label('Plazo segun Ley')
            ->content($legalTimeframe);
    }

    /**
     * 🎯 Crea un placeholder que muestra información de proceso legal
     *
     * @param  string  $processInfo  Información del proceso (ej: "Fecha establecida en la Etapa 2")
     * @param  string  $placeholderName  Nombre único del placeholder
     * @return Placeholder Componente Placeholder configurado
     */
    public static function createProcessInfoPlaceholder(string $processInfo, string $placeholderName): Placeholder
    {
        return Placeholder::make($placeholderName)
            ->label(false)
            ->content($processInfo);
    }

    /**
     * 📊 Crea un placeholder que muestra el total de días de una etapa específica
     *
     * @param  string  $stageName  Nombre de la etapa para el título
     * @param  string  $placeholderName  Nombre único del placeholder
     * @return Placeholder Componente Placeholder configurado
     */
    public static function createStageTotalDaysPlaceholder(string $stageName, string $placeholderName): Placeholder
    {
        return Placeholder::make($placeholderName)
            ->label(false)
            ->content(new \Illuminate\Support\HtmlString(
                "<h2 class='text-center font-bold text-2xl'>{$stageName}</h2>"
            ));
    }

    /**
     * 🔗 Obtiene el callback para verificar si una etapa está creada
     *
     * @param  string  $stageField  Campo de la etapa (ej: 's1Stage', 's2Stage')
     * @return callable Callback para usar en visible()
     */
    public static function getStageCreatedCallback(string $stageField): callable
    {
        return fn ($record) => $record?->{$stageField};
    }

    /**
     * 🔗 Obtiene el callback para verificar si una etapa NO está creada
     *
     * @param  string  $stageField  Campo de la etapa (ej: 's1Stage', 's2Stage')
     * @return callable Callback para usar en visible()
     */
    public static function getStageNotCreatedCallback(string $stageField): callable
    {
        return fn ($record) => ! $record?->{$stageField};
    }

    /**
     * 🎨 Crea un título de sección con HTML personalizado
     *
     * @param  string  $title  Título de la sección
     * @param  string  $subtitle  Subtítulo opcional
     * @return \Illuminate\Support\HtmlString HTML formateado
     */
    public static function createSectionTitle(string $title, ?string $subtitle = null): \Illuminate\Support\HtmlString
    {
        $html = "<h2 class='text-center font-bold text-xs'>{$title}";

        if ($subtitle) {
            $html .= "<br>{$subtitle}";
        }

        $html .= '</h2>';

        return new \Illuminate\Support\HtmlString($html);
    }

    /**
     * 📅 Valida que una fecha de fin sea posterior a una fecha de inicio
     *
     * @param  string|null  $startDate  Fecha de inicio
     * @param  string|null  $endDate  Fecha de fin
     * @return bool True si la fecha de fin es válida
     */
    public static function validateDateRange(?string $startDate, ?string $endDate): bool
    {
        if (! $startDate || ! $endDate) {
            return false;
        }

        try {
            $start = \Carbon\Carbon::parse($startDate);
            $end = \Carbon\Carbon::parse($endDate);

            return $end->gte($start);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 🎯 Obtiene el nombre completo de una etapa para mostrar
     *
     * @param  string  $stageCode  Código de la etapa (S1, S2, S3, S4)
     * @return string Nombre completo de la etapa
     */
    public static function getStageDisplayName(string $stageCode): string
    {
        return match ($stageCode) {
            'S1' => '1.Act. Preparatorias',
            'S2' => '2.Proc. de Selección',
            'S3' => '3.Suscripción del Contrato',
            'S4' => '4.Ejecución',
            default => $stageCode
        };
    }

    /**
     * 🎨 Obtiene el color del badge según el estado de la etapa
     *
     * @param  string  $stageCode  Código de la etapa (S1, S2, S3, S4)
     * @param  bool  $isCreated  Si la etapa está creada
     * @return string Color del badge
     */
    public static function getStageBadgeColor(string $stageCode, bool $isCreated): string
    {
        if (! $isCreated) {
            return 'gray';
        }

        return match ($stageCode) {
            'S1' => 'info',
            'S2' => 'warning',
            'S3' => 'success',
            'S4' => 'primary',
            default => 'gray'
        };
    }
}
