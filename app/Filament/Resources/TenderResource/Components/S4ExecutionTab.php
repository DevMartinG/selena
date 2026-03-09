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
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/**
 * 🎯 COMPONENTE: TAB S4 EXECUTION
 *
 * Este componente maneja la etapa S4 (Tiempo de Ejecución) del Tender
 * en el tab "4.Ejecución" del formulario principal.
 *
 * FUNCIONALIDADES:
 * - Detalles del contrato
 * - Fecha de suscripción del contrato
 * - Fecha de vigencia del contrato (calculada automáticamente)
 * - Días de vigencia del contrato
 * - Cálculo automático de días totales de TODAS las etapas (S1+S2+S3+S4)
 * - Cálculo automático de días hábiles totales de TODAS las etapas
 * - Validación de estados de etapa (creada/pendiente)
 *
 * CARACTERÍSTICAS TÉCNICAS:
 * - Usa componentes compartidos de DateCalculations y StageHelpers
 * - Campos reactivos con live() para cálculos automáticos
 * - Cálculo automático de fecha de vigencia basado en días
 * - Cálculos totales acumulativos de todas las etapas
 * - Validación de fechas con iconos de bandera
 * - Distribución en Grid de 8 columnas
 *
 * USO:
 * - Importar en TenderResource.php
 * - Usar como schema en el tab S4 Execution
 * - Mantiene toda la funcionalidad original incluyendo cálculos totales
 */
class S4ExecutionTab
{
    /**
     * 🎯 Crea el schema completo del tab S4 Execution
     *
     * @return array Array de componentes para el schema del tab
     */
    public static function getSchema(): array
    {
        return [
            // ========================================================================
            // 📋 CAMPOS DE LA ETAPA S4 - TIEMPO DE EJECUCIÓN
            // ========================================================================

            // ========================================================================
            // 📊 GRID PRINCIPAL CON TODAS LAS SECCIONES
            // ========================================================================
            Grid::make(8)
                ->schema([
                    // ========================================================================
                    // 📋 SECCIÓN 1: DETALLES DEL CONTRATO
                    // ========================================================================
                    Grid::make(8)
                        ->schema([
                            TextInput::make('s4Stage.contract_details')
                                ->label('Detalles del Contrato')
                                ->columnSpan(6)
                                ->visible(fn ($record) => $record?->s4Stage),
                        ])->columnSpanFull(),

                    // ========================================================================
                    // 📋 SECCIÓN 2: FECHA DE SUSCRIPCIÓN DEL CONTRATO
                    // ========================================================================
                    Section::make()
                        ->description(StageHelpers::createSectionTitle('Fecha de Suscripción del Contrato'))
                        ->compact()
                        ->schema([
                            DatePicker::make('s4Stage.contract_signing')
                                ->label('F. de Suscripción')
                                ->prefixIcon('heroicon-s-flag')
                                ->prefixIconColor('info')
                                ->live()
                                ->visible(fn ($record) => $record?->s4Stage)
                                ->helperText(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHelperText($get, 'S4', 's4Stage.contract_signing', $record))
                                ->hint(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHint($get, 'S4', 's4Stage.contract_signing', $record))
                                ->hintIcon(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintIcon($get, 'S4', 's4Stage.contract_signing', $record))
                                ->hintColor(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintColor($get, 'S4', 's4Stage.contract_signing', $record))
                                ->hintIconTooltip(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintIconTooltip($get, 'S4', 's4Stage.contract_signing', $record)),
                                // ->hintActions(CustomDeadlineRuleManager::createHintActions('S4', 's4Stage.contract_signing')),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📋 SECCIÓN 3: FECHA DE VIGENCIA DEL CONTRATO
                    // ========================================================================
                    Section::make()
                        ->description(StageHelpers::createSectionTitle('Fecha de Vigencia del Contrato'))
                        ->compact()
                        ->schema([
                            TextInput::make('s4Stage.contract_vigency_days')
                                ->label('Días de Vigencia')
                                ->placeholder('Defina cant. de días')
                                ->suffix('día(s)')
                                ->prefixIcon('heroicon-s-flag')
                                ->prefixIconColor('success')
                                ->live()
                                ->debounce(500)
                                ->numeric()
                                ->visible(fn ($record) => $record?->s4Stage)
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    self::calculateVigencyDate($state, $set, $get);
                                }),

                            DatePicker::make('s4Stage.contract_vigency_date')
                                ->label('Vigente Hasta:')
                                ->prefixIcon('heroicon-s-flag')
                                ->prefixIconColor('success')
                                ->readOnly()
                                ->live()
                                ->visible(fn ($record) => $record?->s4Stage)
                                ->helperText(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHelperText($get, 'S4', 's4Stage.contract_vigency_date'))
                                ->hint(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHint($get, 'S4', 's4Stage.contract_vigency_date'))
                                ->hintIcon(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintIcon($get, 'S4', 's4Stage.contract_vigency_date'))
                                ->hintColor(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintColor($get, 'S4', 's4Stage.contract_vigency_date'))
                                ->hintIconTooltip(fn (Forms\Get $get) => Shared\DeadlineHintHelper::getHintIconTooltip($get, 'S4', 's4Stage.contract_vigency_date')),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📊 SECCIÓN 4: CÁLCULO DE TOTALES DE DÍAS DE TODAS LAS ETAPAS
                    // ========================================================================
                    // Section::make()
                    //     ->description(new HtmlString(
                    //         '<h2 class="text-center font-bold text-2xl">TOTAL DE DIAS</h2>'
                    //     ))
                    //     ->compact()
                    //     ->schema([
                    //         // Usar componente personalizado para cálculos totales
                    //         self::createCompleteTotalDaysPlaceholder(),
                    //         self::createCompleteTotalBusinessDaysPlaceholder(),
                    //     ])->columnSpan(4),

                ])->columnSpanFull()->visible(fn ($record) => $record?->s4Stage),
        ];
    }

    /**
     * 🎯 Obtiene la configuración del tab S4 Execution
     *
     * @return array Configuración completa del tab
     */
    public static function getTabConfig(): array
    {
        return [
            'label' => fn ($record) => self::getTabLabel($record),
            'icon' => 'heroicon-m-clock',
            'extraAttributes' => ['style' => 'white-space: pre-line; padding-top: 0.5rem; text-align: center; line-height: 1.2;'],
            'schema' => self::getSchema(),
        ];
    }

    /**
     * 🏷️ Genera el label del tab con tooltip en el badge (TAREA 2)
     */
    private static function getTabLabel($record): HtmlString
    {
        $baseLabel = '<span class="font-bold text-lg">4.</span> <span class="text-sm font-medium">Ejecución</span>';
        
        if (!$record?->s4Stage) {
            return new HtmlString($baseLabel);
        }
        
        $progress = \App\Filament\Resources\TenderResource\Components\Shared\StageValidationHelper::getStageProgress($record, 'S4');
        $tooltip = \App\Filament\Resources\TenderResource\Components\Shared\StageHelpers::getStageBadgeTooltip($record, 'S4');
        
        $badgeWithTooltip = '<span title="' . htmlspecialchars($tooltip) . '" class="cursor-help font-semibold text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full ml-2">' . $progress . '%</span>';
        
        return new HtmlString($baseLabel . $badgeWithTooltip);
    }

    /**
     * 📅 Calcula la fecha de vigencia del contrato automáticamente
     *
     * @param  mixed  $state  Valor del campo días de vigencia
     * @param  callable  $set  Función para establecer valores
     * @param  callable  $get  Función para obtener valores
     */
    public static function calculateVigencyDate($state, callable $set, callable $get): void
    {
        $signingDate = $get('s4Stage.contract_signing');
        if ($signingDate && $state) {
            $vigencyDate = Carbon::parse($signingDate)->addDays((int) $state);
            $set('s4Stage.contract_vigency_date', $vigencyDate->format('Y-m-d'));
        }
    }

    /**
     * 📊 Crea el placeholder para días totales de todas las etapas
     */
    public static function createCompleteTotalDaysPlaceholder(): \Filament\Forms\Components\Placeholder
    {
        return \Filament\Forms\Components\Placeholder::make('complete_total_days')
            ->label(false)
            ->reactive()
            ->content(function (Forms\Get $get) {
                $totalDays = self::calculateCompleteTotalDays($get);

                if ($totalDays > 0) {
                    return new HtmlString("<span class='font-bold text-lg text-blue-600'>{$totalDays} día(s) calendario total</span>");
                } else {
                    return new HtmlString("<span class='text-xs text-gray-500'>Complete las fechas de todas las etapas para calcular el total</span>");
                }
            });
    }

    /**
     * 📊 Crea el placeholder para días hábiles totales de todas las etapas
     */
    public static function createCompleteTotalBusinessDaysPlaceholder(): \Filament\Forms\Components\Placeholder
    {
        return \Filament\Forms\Components\Placeholder::make('complete_total_business_days')
            ->label(false)
            ->reactive()
            ->content(function (Forms\Get $get) {
                $totalBusinessDays = self::calculateCompleteTotalBusinessDays($get);

                if ($totalBusinessDays > 0) {
                    return new HtmlString("<span class='font-bold text-lg text-green-600'>{$totalBusinessDays} día(s) hábil(es) total</span>");
                } else {
                    return new HtmlString("<span class='text-xs text-gray-500'>Complete las fechas de todas las etapas para calcular el total</span>");
                }
            });
    }

    /**
     * 📊 Calcula los días totales de todas las etapas
     *
     * @param  Forms\Get  $get  Función para obtener valores del formulario
     * @return int Total de días calendario
     */
    public static function calculateCompleteTotalDays(Forms\Get $get): int
    {
        $stagesData = self::getStagesData($get);
        $totalDays = 0;

        foreach ($stagesData as $stage) {
            if ($stage['start'] && $stage['end']) {
                try {
                    $startDate = Carbon::parse($stage['start']);
                    $endDate = Carbon::parse($stage['end']);
                    if ($endDate->gte($startDate)) {
                        $totalDays += $startDate->diffInDays($endDate);
                    }
                } catch (\Exception $e) {
                    // Ignorar fechas inválidas
                }
            }
        }

        return $totalDays;
    }

    /**
     * 📊 Calcula los días hábiles totales de todas las etapas
     *
     * @param  Forms\Get  $get  Función para obtener valores del formulario
     * @return int Total de días hábiles
     */
    public static function calculateCompleteTotalBusinessDays(Forms\Get $get): int
    {
        $stagesData = self::getStagesData($get);
        $totalBusinessDays = 0;

        foreach ($stagesData as $stage) {
            if ($stage['start'] && $stage['end']) {
                try {
                    $startDate = Carbon::parse($stage['start']);
                    $endDate = Carbon::parse($stage['end']);

                    if ($endDate->gte($startDate)) {
                        $businessDays = 0;
                        $date = $startDate->copy();

                        while ($date->lte($endDate)) {
                            if (! $date->isWeekend()) {
                                $businessDays++;
                            }
                            $date->addDay();
                        }

                        $totalBusinessDays += $businessDays;
                    }
                } catch (\Exception $e) {
                    // Ignorar fechas inválidas
                }
            }
        }

        return $totalBusinessDays;
    }

    /**
     * 📊 Obtiene los datos de todas las etapas para cálculos
     *
     * @param  Forms\Get  $get  Función para obtener valores del formulario
     * @return array Datos de todas las etapas
     */
    public static function getStagesData(Forms\Get $get): array
    {
        return [
            ['start' => $get('s1Stage.request_presentation_date'), 'end' => $get('s1Stage.approval_expedient_format_2'), 'name' => 'S1'],
            ['start' => $get('s2Stage.published_at'), 'end' => $get('s2Stage.appeal_date'), 'name' => 'S2'],
            ['start' => $get('s2Stage.appeal_date'), 'end' => $get('s3Stage.contract_signing'), 'name' => 'S3'], // S3 empieza donde termina S2
            ['start' => $get('s4Stage.contract_signing'), 'end' => $get('s4Stage.contract_vigency_date'), 'name' => 'S4'],
        ];
    }

    /**
     * 📅 Obtiene la configuración de campos de fecha con iconos
     *
     * @return array Configuración de campos de fecha
     */
    public static function getDateFieldConfig(): array
    {
        return [
            's4Stage.contract_signing' => [
                'prefixIcon' => 'heroicon-s-flag',
                'prefixIconColor' => 'info',
                'live' => true,
            ],
            's4Stage.contract_vigency_date' => [
                'prefixIcon' => 'heroicon-s-flag',
                'prefixIconColor' => 'success',
                'readOnly' => true,
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
            'contract_signing' => '01 día hábil',
        ];
    }

    /**
     * 🔧 Obtiene la configuración de campos de contrato
     *
     * @return array Configuración de campos de contrato
     */
    public static function getContractFieldsConfig(): array
    {
        return [
            'contract_details' => [
                'label' => 'Detalles del Contrato',
                'columnSpan' => 6,
            ],
            'contract_vigency_days' => [
                'label' => 'Días de Vigencia',
                'placeholder' => 'Defina cant. de días',
                'suffix' => 'día(s)',
                'prefixIcon' => 'heroicon-s-flag',
                'prefixIconColor' => 'success',
                'live' => true,
                'debounce' => 500,
                'numeric' => true,
            ],
        ];
    }

    /**
     * ✅ Valida si una etapa S4 está completa
     *
     * @param  array  $s4Data  Datos de la etapa S4
     * @return bool True si la etapa está completa
     */
    public static function isStageComplete(array $s4Data): bool
    {
        // Usar configuración centralizada de StageValidationHelper
        $config = \App\Filament\Resources\TenderResource\Components\Shared\StageValidationHelper::getStageFieldConfig('S4');
        
        // Obtener todos los campos críticos de la configuración
        $requiredFields = $config['critical_fields'];
        
        // Validar que todos los campos críticos estén completos
        foreach ($requiredFields as $field) {
            if (empty($s4Data[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * 📊 Calcula el progreso de la etapa S4 usando configuración centralizada
     *
     * @param  array  $s4Data  Datos de la etapa S4
     * @return int Porcentaje de progreso (0-100)
     */
    public static function calculateStageProgress(array $s4Data): int
    {
        // ✅ Usar configuración centralizada del StageValidationHelper
        $config = \App\Filament\Resources\TenderResource\Components\Shared\StageValidationHelper::getStageFieldConfig('S4');
        $allRelevantFields = array_merge(
            $config['critical_fields'],
            $config['optional_fields']
        );

        if (empty($allRelevantFields)) {
            return 0;
        }

        $completedFields = 0;
        foreach ($allRelevantFields as $field) {
            if (!empty($s4Data[$field])) {
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
            'contract_signing' => [
                'field' => 's4Stage.contract_signing',
                'label' => 'Fecha de Suscripción del Contrato',
                'icon' => 'heroicon-s-flag',
                'color' => 'info',
            ],
            'contract_vigency_date' => [
                'field' => 's4Stage.contract_vigency_date',
                'label' => 'Fecha de Vigencia del Contrato',
                'icon' => 'heroicon-s-flag',
                'color' => 'success',
                'readOnly' => true,
            ],
        ];
    }

    /**
     * 📈 Obtiene estadísticas de la etapa S4
     *
     * @param  array  $s4Data  Datos de la etapa S4
     * @param  array  $allStagesData  Datos de todas las etapas
     * @return array Estadísticas de la etapa
     */
    public static function getStageStatistics(array $s4Data, array $allStagesData = []): array
    {
        $totalDays = self::calculateCompleteTotalDaysFromData($allStagesData);
        $totalBusinessDays = self::calculateCompleteTotalBusinessDaysFromData($allStagesData);

        return [
            'total_calendar_days' => $totalDays,
            'total_business_days' => $totalBusinessDays,
            'is_complete' => self::isStageComplete($s4Data),
            'progress_percentage' => self::calculateStageProgress($s4Data),
            'has_contract_details' => ! empty($s4Data['contract_details']),
            'has_vigency_calculation' => ! empty($s4Data['contract_signing']) && ! empty($s4Data['contract_vigency_days']),
        ];
    }

    /**
     * 📊 Calcula los días totales desde datos de etapas
     *
     * @param  array  $stagesData  Datos de todas las etapas
     * @return int Total de días calendario
     */
    public static function calculateCompleteTotalDaysFromData(array $stagesData): int
    {
        $totalDays = 0;

        foreach ($stagesData as $stage) {
            if ($stage['start'] && $stage['end']) {
                try {
                    $startDate = Carbon::parse($stage['start']);
                    $endDate = Carbon::parse($stage['end']);
                    if ($endDate->gte($startDate)) {
                        $totalDays += $startDate->diffInDays($endDate);
                    }
                } catch (\Exception $e) {
                    // Ignorar fechas inválidas
                }
            }
        }

        return $totalDays;
    }

    /**
     * 📊 Calcula los días hábiles totales desde datos de etapas
     *
     * @param  array  $stagesData  Datos de todas las etapas
     * @return int Total de días hábiles
     */
    public static function calculateCompleteTotalBusinessDaysFromData(array $stagesData): int
    {
        $totalBusinessDays = 0;

        foreach ($stagesData as $stage) {
            if ($stage['start'] && $stage['end']) {
                try {
                    $startDate = Carbon::parse($stage['start']);
                    $endDate = Carbon::parse($stage['end']);

                    if ($endDate->gte($startDate)) {
                        $businessDays = 0;
                        $date = $startDate->copy();

                        while ($date->lte($endDate)) {
                            if (! $date->isWeekend()) {
                                $businessDays++;
                            }
                            $date->addDay();
                        }

                        $totalBusinessDays += $businessDays;
                    }
                } catch (\Exception $e) {
                    // Ignorar fechas inválidas
                }
            }
        }

        return $totalBusinessDays;
    }

    /**
     * 📊 Obtiene información de dependencias entre etapas
     *
     * @return array Información de dependencias
     */
    public static function getStageDependencies(): array
    {
        return [
            'depends_on' => ['S1', 'S2', 'S3'],
            'required_from_s3' => ['contract_signing'],
            'provides_to' => [],
            'is_final_stage' => true,
        ];
    }

    /**
     * 💰 Calcula la fecha de vigencia automáticamente
     *
     * @param  string  $signingDate  Fecha de suscripción
     * @param  int  $vigencyDays  Días de vigencia
     * @return string Fecha de vigencia calculada
     */
    public static function calculateVigencyDateFromValues(string $signingDate, int $vigencyDays): string
    {
        return Carbon::parse($signingDate)->addDays($vigencyDays)->format('Y-m-d');
    }
}
