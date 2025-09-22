<?php

namespace App\Filament\Resources\TenderResource\Components;

use App\Filament\Resources\TenderResource\Components\Shared\DateCalculations;
use App\Filament\Resources\TenderResource\Components\Shared\StageHelpers;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
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
            // 📊 PLACEHOLDERS DE ESTADO DE ETAPA
            // ========================================================================
            StageHelpers::createStageCreatedPlaceholder(
                '3.Suscripción del Contrato',
                's3_status_created',
                StageHelpers::getStageCreatedCallback('s3Stage')
            ),
            
            StageHelpers::createStagePendingPlaceholder(
                '3.Suscripción del Contrato',
                's3_status_not_created',
                StageHelpers::getStageNotCreatedCallback('s3Stage')
            ),

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
                            StageHelpers::createProcessInfoPlaceholder(
                                'Fecha establecida en la Etapa 2. Proc. de Selección',
                                'appeal_date_legal_timeframe_s2'
                            ),
                            
                            DatePicker::make('s2Stage.appeal_date')
                                ->label(false)
                                ->prefixIcon('heroicon-s-flag')
                                ->prefixIconColor('info')
                                ->live()
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
                            StageHelpers::createLegalTimeframePlaceholder('08 días hábiles', 'doc_sign_presentation_date_legal_timeframe'),
                            
                            DatePicker::make('s3Stage.doc_sign_presentation_date')
                                ->label(false)
                                ->visible(fn ($record) => $record?->s3Stage),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📋 SECCIÓN 3: SUSCRIPCIÓN DEL CONTRATO
                    // ========================================================================
                    Section::make()
                        ->description(StageHelpers::createSectionTitle('Suscripción del Contrato'))
                        ->compact()
                        ->schema([
                            StageHelpers::createLegalTimeframePlaceholder('04 días hábiles', 'contract_signing_legal_timeframe'),
                            
                            DatePicker::make('s3Stage.contract_signing')
                                ->label(false)
                                ->prefixIcon('heroicon-s-flag')
                                ->prefixIconColor('success')
                                ->live()
                                ->visible(fn ($record) => $record?->s3Stage),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📊 SECCIÓN 4: CÁLCULO DE TOTALES DE DÍAS
                    // ========================================================================
                    Section::make()
                        ->description(new HtmlString(
                            '<h2 class="text-center font-bold text-2xl">TOTAL DE DIAS</h2>'
                        ))
                        ->compact()
                        ->schema([
                            // Usar componentes compartidos para cálculos
                            DateCalculations::createCalendarDaysPlaceholder(
                                's2Stage.appeal_date',
                                's3Stage.contract_signing',
                                'total_days'
                            ),
                            
                            DateCalculations::createBusinessDaysPlaceholder(
                                's2Stage.appeal_date',
                                's3Stage.contract_signing',
                                'total_business_days'
                            ),
                        ])->columnSpan(2),
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
            'label' => '3.Suscripción del Contrato',
            'icon' => 'heroicon-m-document-text',
            'badge' => fn ($record) => $record?->s3Stage ? 'Creada' : 'Pendiente',
            'badgeColor' => fn ($record) => StageHelpers::getStageBadgeColor('S3', (bool) $record?->s3Stage),
            'schema' => self::getSchema(),
        ];
    }

    /**
     * 💰 Obtiene el prefijo de moneda según la moneda seleccionada
     * 
     * @param string $currency Código de moneda
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
     * @param array $s3Data Datos de la etapa S3
     * @return bool True si la etapa está completa
     */
    public static function isStageComplete(array $s3Data): bool
    {
        $requiredFields = [
            'contract_signing',
        ];

        foreach ($requiredFields as $field) {
            if (empty($s3Data[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * 📊 Calcula el progreso de la etapa S3
     * 
     * @param array $s3Data Datos de la etapa S3
     * @return int Porcentaje de progreso (0-100)
     */
    public static function calculateStageProgress(array $s3Data): int
    {
        $allFields = [
            'awarded_amount',
            'adjusted_amount',
            'doc_sign_presentation_date',
            'contract_signing',
        ];

        $completedFields = 0;
        foreach ($allFields as $field) {
            if (!empty($s3Data[$field])) {
                $completedFields++;
            }
        }

        return (int) round(($completedFields / count($allFields)) * 100);
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
     * @param array $s3Data Datos de la etapa S3
     * @param array $s2Data Datos de la etapa S2 (para fecha de apelación)
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
            'has_financial_info' => !empty($s3Data['awarded_amount']) || !empty($s3Data['adjusted_amount']),
            'depends_on_s2' => !empty($appealDate),
        ];
    }

    /**
     * 💰 Calcula el monto diferencial automáticamente
     * 
     * @param float $estimatedValue Valor estimado
     * @param float $awardedAmount Monto adjudicado
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
