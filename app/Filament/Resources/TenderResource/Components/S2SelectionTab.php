<?php

namespace App\Filament\Resources\TenderResource\Components;

use App\Filament\Resources\TenderResource\Components\Shared\DateCalculations;
use App\Filament\Resources\TenderResource\Components\Shared\StageHelpers;
use App\Filament\Resources\TenderResource\Components\Shared\StageValidationHelper;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;

/**
 * 🎯 COMPONENTE: TAB S2 SELECTION
 *
 * Este componente maneja la etapa S2 (Procedimiento de Selección) del Tender
 * en el tab "2.Proc. de Selección" del formulario principal.
 *
 * FUNCIONALIDADES:
 * - Registro de Convocatoria en el SEACE
 * - Registro de Participantes
 * - Absolución de Consultas y Observaciones
 * - Integración de las Bases
 * - Presentación de Propuestas
 * - Calificación y Evaluación de Propuestas
 * - Otorgamiento de Buena Pro
 * - Consentimiento de Buena Pro
 * - Apelación
 * - Información del Adjudicado (RUC y Razón Social)
 * - Cálculo automático de días calendario y hábiles
 * - Validación de estados de etapa (creada/pendiente)
 *
 * CARACTERÍSTICAS TÉCNICAS:
 * - Usa componentes compartidos de DateCalculations y StageHelpers
 * - Campos reactivos con live() para cálculos automáticos
 * - Validación de fechas con iconos de bandera
 * - Distribución en Grid de 10 columnas
 * - Campos adicionales para información del adjudicado
 *
 * USO:
 * - Importar en TenderResource.php
 * - Usar como schema en el tab S2 Selection
 * - Mantiene toda la funcionalidad original
 */
class S2SelectionTab
{
    /**
     * 🎯 Crea el schema completo del tab S2 Selection
     *
     * @return array Array de componentes para el schema del tab
     */
    public static function getSchema(): array
    {
        return [
            // ========================================================================
            // 📋 CAMPOS DE LA ETAPA S2 - PROCEDIMIENTO DE SELECCIÓN
            // ========================================================================

            // ========================================================================
            // 📊 GRID PRINCIPAL CON TODAS LAS SECCIONES
            // ========================================================================
            Grid::make(10)
                ->schema([
                    // ========================================================================
                    // 📋 CAMPOS ADICIONALES DE INFORMACIÓN
                    // ========================================================================
                    Grid::make(10)
                        ->schema([
                            TextInput::make('s2Stage.restarted_from')
                                ->label('Reiniciado desde')
                                ->maxLength(255)
                                ->inlineLabel(true)
                                ->visible(fn ($record) => $record?->s2Stage)
                                ->columnSpan(4),

                            TextInput::make('s2Stage.cui_code')
                                ->label('CUI')
                                ->inlineLabel(true)
                                ->maxLength(255)
                                ->visible(fn ($record) => $record?->s2Stage)
                                ->columnSpan(2),
                        ])->columnSpan(10),

                    // ========================================================================
                    // 📋 SECCIÓN 1: REGISTRO DE CONVOCATORIA EN EL SEACE
                    // ========================================================================
                    Section::make()
                        ->description(StageHelpers::createSectionTitle('Registro de Convocatoria', 'en el SEACE'))
                        ->compact()
                        ->schema([
                            DatePicker::make('s2Stage.published_at')
                                ->label(false)
                                ->prefixIcon('heroicon-s-flag')
                                ->prefixIconColor('info')
                                ->live()
                                // ->required()
                                ->visible(fn ($record) => $record?->s2Stage)
                                ->helperText(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHelperText($get, 'S2', 's2Stage.published_at'))
                                ->hint(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHint($get, 'S2', 's2Stage.published_at'))
                                ->hintIcon(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintIcon($get, 'S2', 's2Stage.published_at'))
                                ->hintColor(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintColor($get, 'S2', 's2Stage.published_at'))
                                ->hintIconTooltip(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintIconTooltip($get, 'S2', 's2Stage.published_at')),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📋 SECCIÓN 2: REGISTRO DE PARTICIPANTES
                    // ========================================================================
                    Section::make()
                        ->description(StageHelpers::createSectionTitle('Registro de Participantes'))
                        ->compact()
                        ->schema([
                            DatePicker::make('s2Stage.participants_registration')
                                ->label(false)
                                ->visible(fn ($record) => $record?->s2Stage)
                                ->helperText(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHelperText($get, 'S2', 's2Stage.participants_registration'))
                                ->hint(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHint($get, 'S2', 's2Stage.participants_registration'))
                                ->hintIcon(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintIcon($get, 'S2', 's2Stage.participants_registration'))
                                ->hintColor(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintColor($get, 'S2', 's2Stage.participants_registration'))
                                ->hintIconTooltip(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintIconTooltip($get, 'S2', 's2Stage.participants_registration')),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📋 SECCIÓN 3: ABSOLUCIÓN DE CONSULTAS Y OBSERVACIONES
                    // ========================================================================
                    Section::make()
                        ->description(StageHelpers::createSectionTitle('Absolución de Consultas y Observaciones'))
                        ->compact()
                        ->schema([
                            DatePicker::make('s2Stage.absolution_obs')
                                ->label(false)
                                ->visible(fn ($record) => $record?->s2Stage)
                                ->helperText(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHelperText($get, 'S2', 's2Stage.absolution_obs'))
                                ->hint(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHint($get, 'S2', 's2Stage.absolution_obs'))
                                ->hintIcon(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintIcon($get, 'S2', 's2Stage.absolution_obs'))
                                ->hintColor(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintColor($get, 'S2', 's2Stage.absolution_obs'))
                                ->hintIconTooltip(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintIconTooltip($get, 'S2', 's2Stage.absolution_obs')),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📋 SECCIÓN 4: INTEGRACIÓN DE LAS BASES
                    // ========================================================================
                    Section::make()
                        ->description(StageHelpers::createSectionTitle('Integración de las Bases'))
                        ->compact()
                        ->schema([
                            DatePicker::make('s2Stage.base_integration')
                                ->label(false)
                                ->visible(fn ($record) => $record?->s2Stage)
                                ->helperText(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHelperText($get, 'S2', 's2Stage.base_integration'))
                                ->hint(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHint($get, 'S2', 's2Stage.base_integration'))
                                ->hintIcon(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintIcon($get, 'S2', 's2Stage.base_integration'))
                                ->hintColor(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintColor($get, 'S2', 's2Stage.base_integration'))
                                ->hintIconTooltip(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintIconTooltip($get, 'S2', 's2Stage.base_integration')),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📋 SECCIÓN 5: PRESENTACIÓN DE PROPUESTAS
                    // ========================================================================
                    Section::make()
                        ->description(StageHelpers::createSectionTitle('Presentación de Propuestas'))
                        ->compact()
                        ->schema([
                            DatePicker::make('s2Stage.offer_presentation')
                                ->label(false)
                                ->visible(fn ($record) => $record?->s2Stage)
                                ->helperText(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHelperText($get, 'S2', 's2Stage.offer_presentation'))
                                ->hint(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHint($get, 'S2', 's2Stage.offer_presentation'))
                                ->hintIcon(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintIcon($get, 'S2', 's2Stage.offer_presentation'))
                                ->hintColor(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintColor($get, 'S2', 's2Stage.offer_presentation'))
                                ->hintIconTooltip(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintIconTooltip($get, 'S2', 's2Stage.offer_presentation')),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📋 SECCIÓN 6: CALIFICACIÓN Y EVALUACIÓN DE PROPUESTAS
                    // ========================================================================
                    Section::make()
                        ->description(StageHelpers::createSectionTitle('Calificación y Evaluación de Propuestas'))
                        ->compact()
                        ->schema([
                            DatePicker::make('s2Stage.offer_evaluation')
                                ->label(false)
                                ->visible(fn ($record) => $record?->s2Stage)
                                ->helperText(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHelperText($get, 'S2', 's2Stage.offer_evaluation'))
                                ->hint(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHint($get, 'S2', 's2Stage.offer_evaluation'))
                                ->hintIcon(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintIcon($get, 'S2', 's2Stage.offer_evaluation'))
                                ->hintColor(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintColor($get, 'S2', 's2Stage.offer_evaluation'))
                                ->hintIconTooltip(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintIconTooltip($get, 'S2', 's2Stage.offer_evaluation')),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📋 SECCIÓN 7: OTORGAMIENTO DE BUENA PRO
                    // ========================================================================
                    Section::make()
                        ->description(StageHelpers::createSectionTitle('Otorgamiento de Buena Pro'))
                        ->compact()
                        ->schema([
                            DatePicker::make('s2Stage.award_granted_at')
                                ->label(false)
                                ->visible(fn ($record) => $record?->s2Stage)
                                ->helperText(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHelperText($get, 'S2', 's2Stage.award_granted_at'))
                                ->hint(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHint($get, 'S2', 's2Stage.award_granted_at'))
                                ->hintIcon(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintIcon($get, 'S2', 's2Stage.award_granted_at'))
                                ->hintColor(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintColor($get, 'S2', 's2Stage.award_granted_at'))
                                ->hintIconTooltip(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintIconTooltip($get, 'S2', 's2Stage.award_granted_at')),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📋 SECCIÓN 8: CONSENTIMIENTO DE BUENA PRO
                    // ========================================================================
                    Section::make()
                        ->description(StageHelpers::createSectionTitle('Consentimiento de Buena Pro'))
                        ->compact()
                        ->schema([
                            DatePicker::make('s2Stage.award_consent')
                                ->label(false)
                                ->visible(fn ($record) => $record?->s2Stage)
                                ->helperText(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHelperText($get, 'S2', 's2Stage.award_consent'))
                                ->hint(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHint($get, 'S2', 's2Stage.award_consent'))
                                ->hintIcon(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintIcon($get, 'S2', 's2Stage.award_consent'))
                                ->hintColor(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintColor($get, 'S2', 's2Stage.award_consent'))
                                ->hintIconTooltip(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintIconTooltip($get, 'S2', 's2Stage.award_consent')),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📋 SECCIÓN 9: APELACIÓN
                    // ========================================================================
                    Section::make()
                        ->description(StageHelpers::createSectionTitle('Apelación'))
                        ->compact()
                        ->schema([
                            DatePicker::make('s2Stage.appeal_date')
                                ->label(false)
                                ->prefixIcon('heroicon-s-flag')
                                ->prefixIconColor('success')
                                ->live()
                                ->visible(fn ($record) => $record?->s2Stage)
                                ->helperText(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHelperText($get, 'S2', 's2Stage.appeal_date'))
                                ->hint(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHint($get, 'S2', 's2Stage.appeal_date'))
                                ->hintIcon(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintIcon($get, 'S2', 's2Stage.appeal_date'))
                                ->hintColor(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintColor($get, 'S2', 's2Stage.appeal_date'))
                                ->hintIconTooltip(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintIconTooltip($get, 'S2', 's2Stage.appeal_date')),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📊 SECCIÓN 10: CÁLCULO DE TOTALES DE DÍAS
                    // ========================================================================
                    Section::make()
                        ->description(new HtmlString(
                            '<h2 class="text-center font-bold text-2xl">TOTAL DE DIAS</h2>'
                        ))
                        ->compact()
                        ->schema([
                            // Usar componentes compartidos para cálculos
                            DateCalculations::createCalendarDaysPlaceholder(
                                's2Stage.published_at',
                                's2Stage.appeal_date',
                                'total_days'
                            ),

                            DateCalculations::createBusinessDaysPlaceholder(
                                's2Stage.published_at',
                                's2Stage.appeal_date',
                                'total_business_days'
                            ),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📋 SECCIÓN 11: INFORMACIÓN DEL ADJUDICADO
                    // ========================================================================
                    Section::make()
                        ->compact()
                        ->schema([
                            Grid::make(10)
                                ->schema([
                                    TextInput::make('s2Stage.awarded_tax_id')
                                        ->label('RUC del Adjudicado')
                                        ->columnSpan(5)
                                        ->visible(fn ($record) => $record?->s2Stage),

                                    TextInput::make('s2Stage.awarded_legal_name')
                                        ->label('Razón Social del Adjudicado')
                                        ->columnSpan(5)
                                        ->visible(fn ($record) => $record?->s2Stage),
                                ]),
                        ])->columnSpanFull(),
                ])->visible(fn ($record) => $record?->s2Stage),
        ];
    }

    /**
     * 🎯 Obtiene la configuración del tab S2 Selection
     *
     * @return array Configuración completa del tab
     */
    public static function getTabConfig(): array
    {
        return [
            'label' => fn ($record) => self::getTabLabel($record),
            'icon' => 'heroicon-m-users',
            'extraAttributes' => ['style' => 'white-space: pre-line; padding-top: 0.5rem; text-align: center; line-height: 1.2;'],
            'schema' => self::getSchema(),
        ];
    }

    /**
     * 🏷️ Genera el label del tab con tooltip en el badge (TAREA 2)
     */
    private static function getTabLabel($record): HtmlString
    {
        $baseLabel = '<span class="font-bold text-lg">2.</span> <span class="text-sm font-medium">Proc. de Selección</span>';
        
        if (!$record?->s2Stage) {
            return new HtmlString($baseLabel);
        }
        
        $progress = \App\Filament\Resources\TenderResource\Components\Shared\StageValidationHelper::getStageProgress($record, 'S2');
        $tooltip = \App\Filament\Resources\TenderResource\Components\Shared\StageHelpers::getStageBadgeTooltip($record, 'S2');
        
        $badgeWithTooltip = '<span title="' . htmlspecialchars($tooltip) . '" class="cursor-help font-semibold text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full ml-2">' . $progress . '%</span>';
        
        return new HtmlString($baseLabel . $badgeWithTooltip);
    }

    /**
     * 📅 Obtiene la configuración de campos de fecha con iconos
     *
     * @return array Configuración de campos de fecha
     */
    public static function getDateFieldConfig(): array
    {
        return [
            'published_at' => [
                'prefixIcon' => 'heroicon-s-flag',
                'prefixIconColor' => 'info',
                'live' => true,
                'required' => true,
            ],
            'appeal_date' => [
                'prefixIcon' => 'heroicon-s-flag',
                'prefixIconColor' => 'success',
                'live' => true,
            ],
        ];
    }

    /**
     * 📋 Obtiene los plazos legales para cada sección
     *
     * @return array Plazos legales por sección
     */
    public static function getLegalTimeframes(): array
    {
        return [
            'published_at' => '01 día hábil',
            'participants_registration' => '22 días hábiles',
            'absolution_obs' => '03 días hábiles',
            'base_integration' => '03 días hábiles',
            'offer_presentation' => '03 días hábiles',
            'offer_evaluation' => '03 días hábiles',
            'award_granted_at' => '03 días hábiles',
            'award_consent' => '03 días hábiles',
            'appeal_date' => '03 días hábiles',
        ];
    }

    /**
     * 🔧 Obtiene la configuración de campos adicionales
     *
     * @return array Configuración de campos adicionales
     */
    public static function getAdditionalFieldsConfig(): array
    {
        return [
            'restarted_from' => [
                'label' => 'Reiniciado desde',
                'maxLength' => 255,
                'inlineLabel' => true,
                'columnSpan' => 4,
            ],
            'cui_code' => [
                'label' => 'CUI',
                'maxLength' => 255,
                'inlineLabel' => true,
                'columnSpan' => 2,
            ],
            'awarded_tax_id' => [
                'label' => 'RUC del Adjudicado',
                'columnSpan' => 5,
            ],
            'awarded_legal_name' => [
                'label' => 'Razón Social del Adjudicado',
                'columnSpan' => 5,
            ],
        ];
    }

    /**
     * ✅ Valida si una etapa S2 está completa
     *
     * @param  array  $s2Data  Datos de la etapa S2
     * @return bool True si la etapa está completa
     */
    public static function isStageComplete(array $s2Data): bool
    {
        $requiredFields = [
            'published_at',
            'appeal_date',
        ];

        foreach ($requiredFields as $field) {
            if (empty($s2Data[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * 📊 Calcula el progreso de la etapa S2 usando configuración centralizada
     *
     * @param  array  $s2Data  Datos de la etapa S2
     * @return int Porcentaje de progreso (0-100)
     */
    public static function calculateStageProgress(array $s2Data): int
    {
        // ✅ Usar configuración centralizada del StageValidationHelper
        $config = \App\Filament\Resources\TenderResource\Components\Shared\StageValidationHelper::getStageFieldConfig('S2');
        $allRelevantFields = array_merge(
            $config['critical_fields'],
            $config['optional_fields']
        );

        if (empty($allRelevantFields)) {
            return 0;
        }

        $completedFields = 0;
        foreach ($allRelevantFields as $field) {
            if (!empty($s2Data[$field])) {
                $completedFields++;
            }
        }

        return (int) round(($completedFields / count($allRelevantFields)) * 100);
    }

    /**
     * 🎯 Obtiene las fechas clave para cálculos
     *
     * @return array Fechas clave con sus configuraciones
     */
    public static function getKeyDates(): array
    {
        return [
            'start' => [
                'field' => 's2Stage.published_at',
                'label' => 'Registro de Convocatoria',
                'icon' => 'heroicon-s-flag',
                'color' => 'info',
            ],
            'end' => [
                'field' => 's2Stage.appeal_date',
                'label' => 'Apelación',
                'icon' => 'heroicon-s-flag',
                'color' => 'success',
            ],
        ];
    }

    /**
     * 📈 Obtiene estadísticas de la etapa S2
     *
     * @param  array  $s2Data  Datos de la etapa S2
     * @return array Estadísticas de la etapa
     */
    public static function getStageStatistics(array $s2Data): array
    {
        $totalDays = 0;
        $businessDays = 0;

        if (! empty($s2Data['published_at']) && ! empty($s2Data['appeal_date'])) {
            $totalDays = DateCalculations::calculateCalendarDays(
                $s2Data['published_at'],
                $s2Data['appeal_date']
            );

            $businessDays = DateCalculations::calculateBusinessDays(
                $s2Data['published_at'],
                $s2Data['appeal_date']
            );
        }

        return [
            'total_calendar_days' => $totalDays,
            'total_business_days' => $businessDays,
            'is_complete' => self::isStageComplete($s2Data),
            'progress_percentage' => self::calculateStageProgress($s2Data),
            'has_adjudicated_info' => ! empty($s2Data['awarded_tax_id']) && ! empty($s2Data['awarded_legal_name']),
        ];
    }
}
