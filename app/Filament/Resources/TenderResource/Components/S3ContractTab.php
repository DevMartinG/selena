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

/**
 * 🎯 COMPONENTE: TAB S3 CONTRACT
 *
 * Este componente maneja la etapa S3 (Suscripción del Contrato) del Tender
 * en el tab "3.Suscripción del Contrato" del formulario principal.
 *
 * FUNCIONALIDADES:
 * - Información financiera (valor estimado, monto adjudicado, monto diferencial)
 * - Apelación (fecha heredada de la Etapa 2)
 * - Presentación de Documentos de Suscripción
 * - Suscripción del Contrato
 * - Cálculo automático de días calendario y hábiles
 * - Validación de estados de etapa (creada/pendiente)
 *
 * CARACTERÍSTICAS TÉCNICAS:
 * - Usa componentes compartidos de DateCalculations y StageHelpers
 * - Campos reactivos con live() para cálculos automáticos
 * - Validación de fechas con iconos de bandera
 * - Distribución en Grid de 8 columnas
 * - Campos de solo lectura para información heredada
 *
 * USO:
 * - Importar en TenderResource.php
 * - Usar como schema en el tab S3 Contract
 * - Mantiene toda la funcionalidad original
 */
class S3ContractTab
{
    /**
     * 🎯 Crea el schema completo del tab S3 Contract
     *
     * @return array Array de componentes para el schema del tab
     */
    public static function getSchema(): array
    {
        return [
            // ========================================================================
            // 📋 CAMPOS DE LA ETAPA S3 - SUSCRIPCIÓN DEL CONTRATO
            // ========================================================================

            // ========================================================================
            // 📊 GRID PRINCIPAL CON INFORMACIÓN FINANCIERA
            // ========================================================================
            Grid::make(8)
                ->schema([
                    // ========================================================================
                    // 💰 INFORMACIÓN FINANCIERA
                    // ========================================================================
                    TextInput::make('estimated_referenced_value')
                        ->label('Valor Ref. / Valor Estimado')
                        ->numeric()
                        ->prefix(fn (Forms\Get $get) => self::getCurrencyPrefix($get('currency_name')))
                        ->readonly()
                        ->visible(fn ($record) => $record?->s3Stage)
                        ->columnSpan(2),

                    TextInput::make('s3Stage.awarded_amount')
                        ->label('Monto Adjudicado')
                        ->numeric()
                        ->columnSpan(2)
                        ->visible(fn ($record) => $record?->s3Stage),

                    TextInput::make('s3Stage.adjusted_amount')
                        ->label('Monto Diferencial')
                        ->numeric()
                        ->columnSpan(2)
                        ->visible(fn ($record) => $record?->s3Stage),
                ])->columnSpanFull()->visible(fn ($record) => $record?->s3Stage),

            // ========================================================================
            // 📊 GRID PRINCIPAL CON TODAS LAS SECCIONES
            // ========================================================================
            Grid::make(8)
                ->schema([
                    // ========================================================================
                    // 📋 SECCIÓN 1: APELACIÓN (FECHA HEREDADA DE LA ETAPA 2)
                    // ========================================================================
                    Section::make()
                        ->description(StageHelpers::createSectionTitle('Apelación', '(Fecha de la Etapa 2)'))
                        ->compact()
                        ->schema([
                            /* StageHelpers::createProcessInfoPlaceholder(
                                'Fecha establecida en la Etapa 2. Proc. de Selección',
                                'appeal_date_legal_timeframe_s2'
                            ), */

                            DatePicker::make('s2Stage.appeal_date')
                                ->label('F. de Apelación')
                                ->prefixIcon('heroicon-s-flag')
                                ->prefixIconColor('info')
                                ->live()
                                ->helperText('Fecha establecida en la Etapa 2. Proc. de Selección')
                                ->readOnly()
                                ->visible(fn ($record) => $record?->s2Stage),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📋 SECCIÓN 2: PRESENTACIÓN DE DOCUMENTOS DE SUSCRIPCIÓN
                    // ========================================================================
                    Section::make()
                        ->description(StageHelpers::createSectionTitle('Presentación de Documentos de Suscripción'))
                        ->compact()
                        ->schema([
                            DatePicker::make('s3Stage.doc_sign_presentation_date')
                                ->label(false)
                                ->visible(fn ($record) => $record?->s3Stage)
                                ->helperText(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHelperText($get, 'S3', 's3Stage.doc_sign_presentation_date', $record))
                                ->hint(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHint($get, 'S3', 's3Stage.doc_sign_presentation_date', $record))
                                ->hintIcon(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintIcon($get, 'S3', 's3Stage.doc_sign_presentation_date', $record))
                                ->hintColor(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintColor($get, 'S3', 's3Stage.doc_sign_presentation_date', $record))
                                ->hintIconTooltip(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintIconTooltip($get, 'S3', 's3Stage.doc_sign_presentation_date', $record)),
                                // ->hintActions(CustomDeadlineRuleManager::createHintActions('S3', 's3Stage.doc_sign_presentation_date')),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📋 SECCIÓN 3: SUSCRIPCIÓN DEL CONTRATO
                    // ========================================================================
                    Section::make()
                        ->description(StageHelpers::createSectionTitle('Suscripción', 'del Contrato'))
                        ->compact()
                        ->schema([
                            DatePicker::make('s3Stage.contract_signing')
                                ->label(false)
                                ->prefixIcon('heroicon-s-flag')
                                ->prefixIconColor('success')
                                ->live()
                                ->visible(fn ($record) => $record?->s3Stage)
                                ->helperText(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHelperText($get, 'S3', 's3Stage.contract_signing', $record))
                                ->hint(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHint($get, 'S3', 's3Stage.contract_signing', $record))
                                ->hintIcon(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintIcon($get, 'S3', 's3Stage.contract_signing', $record))
                                ->hintColor(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintColor($get, 'S3', 's3Stage.contract_signing', $record))
                                ->hintIconTooltip(fn (Forms\Get $get, $record) => Shared\DeadlineHintHelper::getHintIconTooltip($get, 'S3', 's3Stage.contract_signing', $record)),
                                // ->hintActions(CustomDeadlineRuleManager::createHintActions('S3', 's3Stage.contract_signing')),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📊 SECCIÓN 4: CÁLCULO DE TOTALES DE DÍAS
                    // ========================================================================
                    // Section::make()
                    //     ->description(new HtmlString(
                    //         '<h2 class="text-center font-bold text-2xl">TOTAL DE DIAS</h2>'
                    //     ))
                    //     ->compact()
                    //     ->schema([
                    //         // Usar componentes compartidos para cálculos
                    //         DateCalculations::createCalendarDaysPlaceholder(
                    //             's2Stage.appeal_date',
                    //             's3Stage.contract_signing',
                    //             'total_days'
                    //         ),

                    //         DateCalculations::createBusinessDaysPlaceholder(
                    //             's2Stage.appeal_date',
                    //             's3Stage.contract_signing',
                    //             'total_business_days'
                    //         ),
                    //     ])->columnSpan(2),

                ])->columnSpanFull()->visible(fn ($record) => $record?->s3Stage),
        ];
    }

    /**
     * 🎯 Obtiene la configuración del tab S3 Contract
     *
     * @return array Configuración completa del tab
     */
    public static function getTabConfig(): array
    {
        return [
            'label' => fn ($record) => self::getTabLabel($record),
            'icon' => 'heroicon-m-document-text',
            'extraAttributes' => ['style' => 'white-space: pre-line; padding-top: 0.5rem; text-align: center; line-height: 1.2;'],
            'schema' => self::getSchema(),
        ];
    }

    /**
     * 🏷️ Genera el label del tab con tooltip en el badge (TAREA 2)
     */
    private static function getTabLabel($record): HtmlString
    {
        $baseLabel = '<span class="font-bold text-lg">3.</span> <span class="text-sm font-medium">Suscripción del Contrato</span>';
        
        if (!$record?->s3Stage) {
            return new HtmlString($baseLabel);
        }
        
        $progress = \App\Filament\Resources\TenderResource\Components\Shared\StageValidationHelper::getStageProgress($record, 'S3');
        $tooltip = \App\Filament\Resources\TenderResource\Components\Shared\StageHelpers::getStageBadgeTooltip($record, 'S3');
        
        $badgeWithTooltip = '<span title="' . htmlspecialchars($tooltip) . '" class="cursor-help font-semibold text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full ml-2">' . $progress . '%</span>';
        
        return new HtmlString($baseLabel . $badgeWithTooltip);
    }

    /**
     * 💰 Obtiene el prefijo de moneda según la moneda seleccionada
     *
     * @param  string  $currency  Código de moneda
     * @return string Prefijo de moneda
     */
    public static function getCurrencyPrefix(string $currency): string
    {
        return match ($currency) {
            'PEN' => 'S/',
            'USD' => '$',
            'EUR' => '€',
            default => 'S/',
        };
    }

    /**
     * 📅 Obtiene la configuración de campos de fecha con iconos
     *
     * @return array Configuración de campos de fecha
     */
    public static function getDateFieldConfig(): array
    {
        return [
            's2Stage.appeal_date' => [
                'prefixIcon' => 'heroicon-s-flag',
                'prefixIconColor' => 'info',
                'live' => true,
                'readOnly' => true,
            ],
            's3Stage.contract_signing' => [
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
            'doc_sign_presentation_date' => '08 días hábiles',
            'contract_signing' => '04 días hábiles',
        ];
    }

    /**
     * 🔧 Obtiene la configuración de campos financieros
     *
     * @return array Configuración de campos financieros
     */
    public static function getFinancialFieldsConfig(): array
    {
        return [
            'estimated_referenced_value' => [
                'label' => 'Valor Ref. / Valor Estimado',
                'readonly' => true,
                'columnSpan' => 2,
            ],
            'awarded_amount' => [
                'label' => 'Monto Adjudicado',
                'columnSpan' => 2,
            ],
            'adjusted_amount' => [
                'label' => 'Monto Diferencial',
                'columnSpan' => 2,
            ],
        ];
    }

    /**
     * ✅ Valida si una etapa S3 está completa
     *
     * @param  array  $s3Data  Datos de la etapa S3
     * @return bool True si la etapa está completa
     */
    public static function isStageComplete(array $s3Data): bool
    {
        // Usar configuración centralizada de StageValidationHelper
        $config = \App\Filament\Resources\TenderResource\Components\Shared\StageValidationHelper::getStageFieldConfig('S3');
        
        // Obtener todos los campos críticos de la configuración
        $requiredFields = $config['critical_fields'];
        
        // Validar que todos los campos críticos estén completos
        foreach ($requiredFields as $field) {
            if (empty($s3Data[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * 📊 Calcula el progreso de la etapa S3 usando configuración centralizada
     *
     * @param  array  $s3Data  Datos de la etapa S3
     * @return int Porcentaje de progreso (0-100)
     */
    public static function calculateStageProgress(array $s3Data): int
    {
        // ✅ Usar configuración centralizada del StageValidationHelper
        $config = \App\Filament\Resources\TenderResource\Components\Shared\StageValidationHelper::getStageFieldConfig('S3');
        $allRelevantFields = array_merge(
            $config['critical_fields'],
            $config['optional_fields']
        );

        if (empty($allRelevantFields)) {
            return 0;
        }

        $completedFields = 0;
        foreach ($allRelevantFields as $field) {
            if (!empty($s3Data[$field])) {
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
                'field' => 's2Stage.appeal_date',
                'label' => 'Apelación (Etapa 2)',
                'icon' => 'heroicon-s-flag',
                'color' => 'info',
                'readOnly' => true,
            ],
            'end' => [
                'field' => 's3Stage.contract_signing',
                'label' => 'Suscripción del Contrato',
                'icon' => 'heroicon-s-flag',
                'color' => 'success',
            ],
        ];
    }

    /**
     * 📈 Obtiene estadísticas de la etapa S3
     *
     * @param  array  $s3Data  Datos de la etapa S3
     * @param  array  $s2Data  Datos de la etapa S2 (para fecha de apelación)
     * @return array Estadísticas de la etapa
     */
    public static function getStageStatistics(array $s3Data, array $s2Data = []): array
    {
        $totalDays = 0;
        $businessDays = 0;

        $appealDate = $s2Data['appeal_date'] ?? null;
        $contractSigning = $s3Data['contract_signing'] ?? null;

        if ($appealDate && $contractSigning) {
            $totalDays = DateCalculations::calculateCalendarDays(
                $appealDate,
                $contractSigning
            );

            $businessDays = DateCalculations::calculateBusinessDays(
                $appealDate,
                $contractSigning
            );
        }

        return [
            'total_calendar_days' => $totalDays,
            'total_business_days' => $businessDays,
            'is_complete' => self::isStageComplete($s3Data),
            'progress_percentage' => self::calculateStageProgress($s3Data),
            'has_financial_info' => ! empty($s3Data['awarded_amount']) || ! empty($s3Data['adjusted_amount']),
            'depends_on_s2' => ! empty($appealDate),
        ];
    }

    /**
     * 💰 Calcula el monto diferencial automáticamente
     *
     * @param  float  $estimatedValue  Valor estimado
     * @param  float  $awardedAmount  Monto adjudicado
     * @return float Monto diferencial
     */
    public static function calculateAdjustedAmount(float $estimatedValue, float $awardedAmount): float
    {
        return $awardedAmount - $estimatedValue;
    }

    /**
     * 📊 Obtiene información de dependencias entre etapas
     *
     * @return array Información de dependencias
     */
    public static function getStageDependencies(): array
    {
        return [
            'depends_on' => ['S2'],
            'required_from_s2' => ['appeal_date'],
            'provides_to' => ['S4'],
            'provides_to_s4' => ['contract_signing'],
        ];
    }
}
