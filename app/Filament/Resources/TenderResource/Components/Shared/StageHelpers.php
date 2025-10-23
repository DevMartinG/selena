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

    /**
     * 📊 Obtiene el badge con porcentaje de progreso (TAREA 2)
     *
     * @param  mixed  $record  Instancia del Tender
     * @param  string  $stageCode  Código de la etapa (S1, S2, S3, S4)
     * @return string Badge con porcentaje
     */
    public static function getStageBadgeWithProgress($record, string $stageCode): string
    {
        if (!$record?->{"s{$stageCode[1]}Stage"}) {
            return '0%';
        }

        $progress = \App\Filament\Resources\TenderResource\Components\Shared\StageValidationHelper::getStageProgress($record, $stageCode);
        return $progress . '%';
    }

    /**
     * 🎯 Obtiene el icono del badge con tooltip (TAREA 2)
     *
     * @param  mixed  $record  Instancia del Tender
     * @param  string  $stageCode  Código de la etapa (S1, S2, S3, S4)
     * @return string Icono con información de progreso
     */
    public static function getStageBadgeIcon($record, string $stageCode): string
    {
        if (!$record?->{"s{$stageCode[1]}Stage"}) {
            return 'heroicon-o-exclamation-triangle';
        }

        $progress = \App\Filament\Resources\TenderResource\Components\Shared\StageValidationHelper::getStageProgress($record, $stageCode);
        
        return match (true) {
            $progress >= 100 => 'heroicon-s-check-circle',
            $progress >= 75 => 'heroicon-s-exclamation-triangle',
            $progress >= 50 => 'heroicon-s-information-circle',
            $progress > 0 => 'heroicon-s-x-circle',
            default => 'heroicon-o-exclamation-triangle'
        };
    }

    /**
     * 🎨 Obtiene el color del badge basado en el porcentaje de progreso (TAREA 2)
     *
     * @param  mixed  $record  Instancia del Tender
     * @param  string  $stageCode  Código de la etapa (S1, S2, S3, S4)
     * @return string Color del badge según progreso
     */
    public static function getStageBadgeColorByProgress($record, string $stageCode): string
    {
        if (!$record?->{"s{$stageCode[1]}Stage"}) {
            return 'gray';
        }

        $progress = \App\Filament\Resources\TenderResource\Components\Shared\StageValidationHelper::getStageProgress($record, $stageCode);
        
        return match (true) {
            $progress >= 100 => 'success',  // Verde - Completo
            $progress >= 75 => 'warning',   // Amarillo - Casi completo
            $progress >= 50 => 'info',      // Azul - Progreso medio
            $progress > 0 => 'danger',      // Rojo - Inicio
            default => 'gray'                // Gris - Sin progreso
        };
    }

    /**
     * 💬 Genera el tooltip detallado para el badge (TAREA 2)
     *
     * @param  mixed  $record  Instancia del Tender
     * @param  string  $stageCode  Código de la etapa (S1, S2, S3, S4)
     * @return string Tooltip con formato específico
     */
    public static function getStageBadgeTooltip($record, string $stageCode): string
    {
        if (!$record?->{"s{$stageCode[1]}Stage"}) {
            return 'Etapa no creada';
        }

        $progress = \App\Filament\Resources\TenderResource\Components\Shared\StageValidationHelper::getStageProgress($record, $stageCode);
        $config = \App\Filament\Resources\TenderResource\Components\Shared\StageValidationHelper::getStageFieldConfig($stageCode);
        $totalFields = count($config['critical_fields']);
        $completedFields = $totalFields - count(\App\Filament\Resources\TenderResource\Components\Shared\StageValidationHelper::getMissingFields($record, $stageCode));
        
        $missingFields = \App\Filament\Resources\TenderResource\Components\Shared\StageValidationHelper::getMissingFields($record, $stageCode);
        
        $tooltip = "::: Campos completos {$completedFields} de {$totalFields} :::\n";
        
        if (!empty($missingFields)) {
            $tooltip .= "Faltan por completar :\n";
            foreach ($missingFields as $fieldLabel) {
                $tooltip .= "|| {$fieldLabel} ||\n";
            }
        }
        
        return trim($tooltip);
    }
}
