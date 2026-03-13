<?php

namespace App\Filament\Resources\TenderResource\Components;

use App\Filament\Resources\TenderResource\Components\Shared\CustomDeadlineRuleManager;
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

use App\Models\TenderDeadlineRule;

use Illuminate\Support\Facades\Log;


use App\Models\TenderStageS2Completed;
use Filament\Forms\Components\Actions\Action;
use Illuminate\Support\Facades\Auth;


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

        $s2Fields = [
            'published_at',
            'participants_registration',
            'formulation_obs',
            'absolution_obs',
            'base_integration',
            'offer_presentation',
            'offer_evaluation',
            'award_granted_at',
            'award_consent',
            'appeal_date',
        ];

        // Funciones locales para label y helperText
        $getLabel = fn($f) => ucwords(str_replace('_', ' ', $f));
        $getHelperText = fn($f) => match ($f) {
            'published_at' => 'Convocatoria.',
            'participants_registration' => 'Registro de participantes.',
            'formulation_obs' => 'Formulación de consultas y observaciones.',
            'absolution_obs' => 'Absolución de consultas y observaciones.',
            'base_integration' => 'Integración de las bases.',
            'offer_presentation' => 'Presentación de propuestas.',
            'offer_evaluation' => 'Calificación y Eval. de las propuestas.',
            'award_granted_at' => 'Otorgamiento de la Buena Pro.',
            'award_consent' => 'Consentimiento de la Buena Pro.',
            'appeal_date' => 'Fecha de apelaciones.',
            default => '',
        };

        // $datePickers = collect($s2Fields)->map(function ($field) use ($getLabel, $getHelperText) {

        //     $fullField = "s2Stage.$field";

        //     return DatePicker::make($fullField)
        //         ->label(false)
        //         ->columnSpan(2)
        //         ->live()
        //         ->visible(fn ($record) => $record?->s2Stage)
        //         ->afterStateUpdated(function ($state, $set, $get, $record) use ($fullField) {

        //             if (!$state || !$record) return;

        //             $applyRules = function ($currentField, $currentDate) use (&$applyRules, $set, $record) {
        //                 $rules = TenderDeadlineRule::active()
        //                     ->where('from_field', $currentField)
        //                     ->where('process_type_id', $record->process_type_id)
        //                     ->get();

        //                 foreach ($rules as $rule) {
        //                     $targetDate = self::addBusinessDays(\Carbon\Carbon::parse($currentDate), $rule->legal_days);
        //                     $set($rule->to_field, $targetDate->format('Y-m-d'));
        //                     $applyRules($rule->to_field, $targetDate);
        //                 }
        //             };

        //             $applyRules($fullField, $state);
                    
        //         })
        //         ->helperText(fn () => $getHelperText($field))
        //         ->hint(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHint($get, 'S2', $fullField, $record))
        //         ->hintIcon(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintIcon($get, 'S2', $fullField, $record))
        //         ->hintColor(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintColor($get, 'S2', $fullField, $record))
        //         ->hintIconTooltip(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintIconTooltip($get, 'S2', $fullField, $record))
        //         // ->hintActions(CustomDeadlineRuleManager::createHintActions('S2', $fullField));
        //         ->hintActions(CustomDeadlineRuleManager::createHintActionsCompleteForField('s2Stage', $fullField));

        // });

        $datePickers = collect($s2Fields)->map(function ($field) use ($getLabel, $getHelperText) {
            $fullField = "s2Stage.$field";

            return [

                DatePicker::make($fullField)
                    ->label(false)
                    ->columnSpan(2)
                    ->live()
                    ->visible(fn ($record) => $record?->s2Stage)
                    ->afterStateUpdated(function ($state, $set, $get, $record) use ($fullField) {
                        if (!$state || !$record) return;
                        $applyRules = function ($currentField, $currentDate) use (&$applyRules, $set, $record) {
                            $rules = TenderDeadlineRule::active()
                                ->where('from_field', $currentField)
                                ->where('process_type_id', $record->process_type_id)
                                ->get();
                            foreach ($rules as $rule) {
                                $targetDate = self::addBusinessDays(\Carbon\Carbon::parse($currentDate), $rule->legal_days);
                                $set($rule->to_field, $targetDate->format('Y-m-d'));
                                $applyRules($rule->to_field, $targetDate);
                            }
                        };
                        $applyRules($fullField, $state);
                    })
                    ->helperText(fn () => $getHelperText($field))
                    ->hint(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHint($get, 'S2', $fullField, $record))
                    ->hintIcon(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintIcon($get, 'S2', $fullField, $record))
                    ->hintColor(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintColor($get, 'S2', $fullField, $record))
                    ->hintIconTooltip(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintIconTooltip($get, 'S2', $fullField, $record))
                    // ->hintActions(CustomDeadlineRuleManager::createHintActionsCompleteForField('s2Stage', $fullField))
                    ->hintActions(array_map( // solo mostrar cuando se esta en modo edicion, no en modo vista (seguimiento)
                        fn($action) => $action->hidden(fn($livewire) =>
                            $livewire instanceof \Filament\Resources\Pages\ViewRecord
                            || in_array('view', $livewire->mountedTableActions ?? [])
                        ),
                        CustomDeadlineRuleManager::createHintActionsCompleteForField('s2Stage', $fullField)
                    ))
            ];

        })->flatten()->toArray(); // ✅ flatten porque ahora cada item es un array de 2 elementos

        return [
            Grid::make(10)
                ->schema([
                    // // Tipo de proceso
                    // TextInput::make('tipo_proceso')
                    //     ->label('Tipo de Proceso:')
                    //     ->disabled()
                    //     ->dehydrated(false)
                    //     ->afterStateHydrated(fn ($component, $record) => 
                    //         $component->state(optional($record->processType)->code_short_type)
                    //     ),

                    // Campos adicionales
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

                    // ✅ Aquí van todos los DatePickers dinámicos
                    ...$datePickers,

                    // Sección de cálculo de totales
                    // Section::make()
                    //     ->description(new HtmlString('<h2 class="text-center font-bold text-2xl">TOTAL DE DIAS</h2>'))
                    //     ->compact()
                    //     ->schema([
                    //         DateCalculations::createCalendarDaysPlaceholder(
                    //             's2Stage.published_at',
                    //             's2Stage.appeal_date',
                    //             'total_days'
                    //         ),
                    //         DateCalculations::createBusinessDaysPlaceholder(
                    //             's2Stage.published_at',
                    //             's2Stage.appeal_date',
                    //             'total_business_days'
                    //         ),
                    //     ])->columnSpan(2),

                    // Sección de adjudicado
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
                ])
                ->visible(fn ($record) => $record?->s2Stage),
        ];
    }


    /**
     * Suma días hábiles (lunes a viernes) a una fecha
     */
    public static function addBusinessDays(\Carbon\Carbon $date, int $days): \Carbon\Carbon
    {
        $result = $date->copy();

        while ($days > 0) {
            $result->addDay();
            if (!in_array($result->dayOfWeek, [\Carbon\Carbon::SATURDAY, \Carbon\Carbon::SUNDAY])) {
                $days--;
            }
        }

        return $result;
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
            'formulation_obs' => '03 días hábiles',
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
        // Usar configuración centralizada de StageValidationHelper
        $config = \App\Filament\Resources\TenderResource\Components\Shared\StageValidationHelper::getStageFieldConfig('S2');
        
        // Obtener todos los campos críticos de la configuración
        $requiredFields = $config['critical_fields'];
        
        // Validar que todos los campos críticos estén completos
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
