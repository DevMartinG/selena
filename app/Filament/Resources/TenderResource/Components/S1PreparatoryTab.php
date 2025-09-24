<?php

namespace App\Filament\Resources\TenderResource\Components;

use App\Filament\Resources\TenderResource\Components\Shared\DateCalculations;
use App\Filament\Resources\TenderResource\Components\Shared\StageHelpers;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\HtmlString;

/**
 * 🎯 COMPONENTE: TAB S1 PREPARATORIAS
 *
 * Este componente maneja la etapa S1 (Actuaciones Preparatorias) del Tender
 * en el tab "1.Act. Preparatorias" del formulario principal.
 *
 * FUNCIONALIDADES:
 * - Presentación de Requerimiento de Bien
 * - Indagación de Mercado
 * - Certificación (con lógica condicional)
 * - Aprobación del Expediente de Contratación
 * - Designación del Comité de Selección (con lógica condicional)
 * - Elaboración de Bases Administrativas
 * - Aprobación de Bases Administrativas Formato 2
 * - Cálculo automático de días calendario y hábiles
 * - Validación de estados de etapa (creada/pendiente)
 *
 * CARACTERÍSTICAS TÉCNICAS:
 * - Usa componentes compartidos de DateCalculations y StageHelpers
 * - Maneja lógica condicional para certificación y comité
 * - Campos reactivos con live() para cálculos automáticos
 * - Validación de fechas con iconos de bandera
 * - Distribución en Grid de 8 columnas
 *
 * USO:
 * - Importar en TenderResource.php
 * - Usar como schema en el tab S1 Preparatory
 * - Mantiene toda la funcionalidad original
 */
class S1PreparatoryTab
{
    /**
     * 🎯 Crea el schema completo del tab S1 Preparatory
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
                '1.Act. Preparatorias',
                's1_status_created',
                StageHelpers::getStageCreatedCallback('s1Stage')
            ),

            StageHelpers::createStagePendingPlaceholder(
                '1.Act. Preparatorias',
                's1_status_not_created',
                StageHelpers::getStageNotCreatedCallback('s1Stage')
            ),

            // ========================================================================
            // 📊 GRID PRINCIPAL CON TODAS LAS SECCIONES
            // ========================================================================
            Grid::make(10)
                ->schema([
                    // ========================================================================
                    // 📋 SECCIÓN 1: PRESENTACIÓN DE REQUERIMIENTO DE BIEN
                    // ========================================================================
                    Section::make()
                        ->label(false)
                        ->description(StageHelpers::createSectionTitle('Presentación de Requerimiento', 'de Bien'))
                        ->compact()
                        ->schema([
                            // Botón para buscar requerimiento
                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('search_requirement')
                                    ->label(new HtmlString('<span class="text-xs">Buscar Requerimiento</span>'))
                                    ->icon('heroicon-m-magnifying-glass')
                                    ->color('primary')
                                    ->modalHeading('Buscar Requerimiento en SILUCIA')
                                    ->modalDescription('Selecciona el año e ingresa el número del requerimiento para buscar en el sistema SILUCIA')
                                    ->modalSubmitActionLabel('Seleccionar')
                                    ->modalCancelActionLabel('Cancelar')
                                    ->modalWidth('2xl')
                                    ->form([
                                        Grid::make(10)
                                            ->schema([
                                                Forms\Components\Select::make('anio')
                                                    ->label('Año')
                                                    ->options([
                                                        '2023' => '2023',
                                                        '2024' => '2024',
                                                        '2025' => '2025',
                                                        '2026' => '2026',
                                                    ])
                                                    ->default(now()->year)
                                                    ->required()
                                                    ->placeholder('Selecciona el año')
                                                    ->inlineLabel()
                                                    ->columnSpan(4),

                                                Forms\Components\TextInput::make('numero')
                                                    ->label('N° Req.')
                                                    ->placeholder('Ej: 4618')
                                                    ->required()
                                                    ->numeric()
                                                    ->maxLength(10)
                                                    ->inlineLabel()
                                                    ->columnSpan(4),

                                                Forms\Components\Actions::make([
                                                    Forms\Components\Actions\Action::make('search_in_modal')
                                                        ->label('Buscar')
                                                        ->icon('heroicon-m-magnifying-glass')
                                                        ->color('info')
                                                        ->size('sm')
                                                        ->action(function (Forms\Get $get, Forms\Set $set) {
                                                            $numero = $get('numero');
                                                            $anio = $get('anio');

                                                            if (empty($numero) || empty($anio)) {
                                                                \Filament\Notifications\Notification::make()
                                                                    ->title('Campos requeridos')
                                                                    ->body('Por favor completa el número y año del requerimiento')
                                                                    ->warning()
                                                                    ->send();

                                                                return;
                                                            }

                                                            // Buscar en la API
                                                            $requirement = \App\Services\RequirementApiService::searchRequirement($numero, $anio);

                                                            if ($requirement) {
                                                                // Formatear datos
                                                                $formattedData = \App\Services\RequirementApiService::formatRequirementData($requirement);

                                                                // Mostrar resultado en el modal
                                                                \Filament\Notifications\Notification::make()
                                                                    ->title('✅ Requerimiento encontrado')
                                                                    ->body('Se encontró el requerimiento: '.$formattedData['sintesis'])
                                                                    ->success()
                                                                    ->send();

                                                                // Actualizar campos del formulario principal
                                                                $set('s1Stage.requirement_api_data', $formattedData);
                                                                $set('s1Stage.request_presentation_doc', 'Req. '.$formattedData['numero'].' - '.$formattedData['anio']);

                                                                // Mostrar el campo de documento
                                                                $set('s1Stage.show_request_doc', true);

                                                            } else {
                                                                // Mostrar notificación de error
                                                                \Filament\Notifications\Notification::make()
                                                                    ->title('❌ Requerimiento no encontrado')
                                                                    ->body('No se encontró ningún requerimiento con el número '.$numero.' del año '.$anio)
                                                                    ->danger()
                                                                    ->send();
                                                            }
                                                        }),
                                                ]),
                                            ]),

                                        // Mostrar información del requerimiento encontrado
                                        Forms\Components\Placeholder::make('requirement_info')
                                            ->label('Información del Requerimiento')
                                            ->content(function (Forms\Get $get) {
                                                $apiData = $get('s1Stage.requirement_api_data');
                                                if ($apiData) {
                                                    return new \Illuminate\Support\HtmlString(
                                                        '<div class="bg-green-50 border border-green-200 rounded-lg p-4">'.
                                                        '<h4 class="font-semibold text-green-800 mb-2">Requerimiento Encontrado:</h4>'.
                                                        '<p><strong>Número:</strong> '.$apiData['numero'].'</p>'.
                                                        '<p><strong>Año:</strong> '.$apiData['anio'].'</p>'.
                                                        '<p><strong>Procedimiento:</strong> '.$apiData['desprocedim'].'</p>'.
                                                        '<p><strong>T. Segmentación:</strong> '.$apiData['descripcion_segmentacion'].'</p>'.
                                                        '<p><strong>Síntesis:</strong> '.$apiData['sintesis'].'</p>'.
                                                        '</div>'
                                                    );
                                                }

                                                return 'Realiza una búsqueda para ver la información del requerimiento';
                                            })
                                            ->visible(fn (Forms\Get $get) => ! empty($get('s1Stage.requirement_api_data'))),
                                    ])
                                    ->action(function (array $data, Forms\Set $set) {
                                        // Esta acción se ejecuta cuando se hace clic en "Seleccionar"
                                        $numero = $data['numero'];
                                        $anio = $data['anio'];

                                        if (empty($numero) || empty($anio)) {
                                            \Filament\Notifications\Notification::make()
                                                ->title('Campos requeridos')
                                                ->body('Por favor completa el número y año del requerimiento')
                                                ->warning()
                                                ->send();
                                            return;
                                        }

                                        // Buscar en la API
                                        $requirement = \App\Services\RequirementApiService::searchRequirement($numero, $anio);

                                        if ($requirement) {
                                            // Formatear datos
                                            $formattedData = \App\Services\RequirementApiService::formatRequirementData($requirement);

                                            // Actualizar campos del formulario principal
                                            $set('s1Stage.requirement_api_data', $formattedData);
                                            $set('s1Stage.request_presentation_doc', 'Req. '.$formattedData['numero'].' - '.$formattedData['anio']);

                                            // Mostrar notificación de éxito
                                            \Filament\Notifications\Notification::make()
                                                ->title('Requerimiento seleccionado')
                                                ->body('Se ha seleccionado el requerimiento: '.$formattedData['sintesis'])
                                                ->success()
                                                ->send();
                                        } else {
                                            // Mostrar notificación de error
                                            \Filament\Notifications\Notification::make()
                                                ->title('Requerimiento no encontrado')
                                                ->body('No se encontró ningún requerimiento con el número '.$numero.' del año '.$anio)
                                                ->danger()
                                                ->send();
                                        }
                                    })
                                    ->visible(fn ($record) => $record?->s1Stage),
                            ]),

                            // Campo de documento (se muestra solo después de la búsqueda)
                            TextInput::make('s1Stage.request_presentation_doc')
                                ->label(false)
                                ->maxLength(255)
                                ->readOnly()
                                ->placeholder('Req. N° - Año')
                                ->live()
                                ->visible(fn ($record) => $record?->s1Stage)
                                ->hint(function (Forms\Get $get) {
                                    $apiData = $get('s1Stage.requirement_api_data');
                                    if ($apiData && !empty($apiData)) {
                                        return 'Detalle Req ->';
                                    }
                                    return null;
                                })
                                ->hintIcon(function (Forms\Get $get) {
                                    $apiData = $get('s1Stage.requirement_api_data');
                                    if ($apiData && !empty($apiData)) {
                                        return 'heroicon-m-information-circle';
                                    }
                                    return null;
                                })
                                ->hintColor(function (Forms\Get $get) {
                                    $apiData = $get('s1Stage.requirement_api_data');
                                    if ($apiData && !empty($apiData)) {
                                        return 'info';
                                    }
                                    return null;
                                })
                                /* ->hintIconTooltip(function (Forms\Get $get) {
                                    $apiData = $get('s1Stage.requirement_api_data');
                                    if ($apiData && !empty($apiData)) {
                                        return 'ID: '.$apiData['idreq'].' | Procedimiento: '.$apiData['desprocedim'].' | Síntesis: '.$apiData['sintesis'];
                                    }
                                    return null;
                                }) */
                                ->hintIconTooltip(function (Forms\Get $get) {
                                    $apiData = $get('s1Stage.requirement_api_data');
                                    if ($apiData && !empty($apiData)) {
                                        return 'Procedimiento: '.$apiData['desprocedim'].' | Síntesis: '.$apiData['sintesis'];
                                    }
                                    return null;
                                })
                                ->helperText(function (Forms\Get $get) {
                                    $apiData = $get('s1Stage.requirement_api_data');
                                
                                    if ($apiData && !empty($apiData)) {
                                        return new \Illuminate\Support\HtmlString(
                                            '<span class="text-xs font-semibold">'
                                            .'T. Segmentación: '.$apiData['descripcion_segmentacion'].
                                            '</span>'
                                        );
                                    }
                                
                                    return null;
                                }),                                

                            // Campo de fecha
                            DatePicker::make('s1Stage.request_presentation_date')
                                ->label('Fecha de Presentación')
                                ->prefixIcon('heroicon-s-flag')
                                ->prefixIconColor('info')
                                ->visible(fn ($record) => $record?->s1Stage)
                                ->live(),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📋 SECCIÓN 2: INDAGACIÓN DE MERCADO
                    // ========================================================================
                    Section::make()
                        ->description(StageHelpers::createSectionTitle('Indagación de Mercado'))
                        ->compact()
                        ->schema([
                            TextInput::make('s1Stage.market_indagation_doc')
                                ->label(false)
                                ->placeholder('Documento/Ref.')
                                ->maxLength(255)
                                ->visible(fn ($record) => $record?->s1Stage),

                            DatePicker::make('s1Stage.market_indagation_date')
                                ->label(false)
                                ->visible(fn ($record) => $record?->s1Stage),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📋 SECCIÓN 3: CERTIFICACIÓN (CON LÓGICA CONDICIONAL)
                    // ========================================================================
                    Section::make()
                        ->label(false)
                        ->description(StageHelpers::createSectionTitle('Certificación'))
                        ->compact()
                        ->schema([
                            Toggle::make('s1Stage.with_certification')
                                ->label('¿Tiene Certificación?')
                                ->onIcon('heroicon-m-check')
                                ->offIcon('heroicon-m-x-mark')
                                ->onColor('success')
                                ->offColor('danger')
                                ->default(false)
                                ->live()
                                ->visible(fn ($record) => $record?->s1Stage)
                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                    if ($state) {
                                        // Si selecciona que SÍ tiene certificación → limpiar el motivo
                                        $set('s1Stage.no_certification_reason', null);
                                    } else {
                                        // Si selecciona que NO tiene certificación → limpiar la fecha
                                        $set('s1Stage.certification_date', null);
                                    }
                                }),

                            DatePicker::make('s1Stage.certification_date')
                                ->label(false)
                                ->visible(fn ($record) => $record?->s1Stage) // condición estática
                                ->hidden(fn (Forms\Get $get) => ! $get('s1Stage.with_certification')), // dinámica

                            TextInput::make('s1Stage.no_certification_reason')
                                ->label(false)
                                ->placeholder('Motivo de no certificación')
                                ->maxLength(255)
                                ->visible(fn ($record) => $record?->s1Stage) // condición estática
                                ->hidden(fn (Forms\Get $get) => $get('s1Stage.with_certification')), // dinámica
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📋 SECCIÓN 4: PREVISIÓN (CON LÓGICA CONDICIONAL)
                    // ========================================================================
                    Section::make()
                        ->label(false)
                        ->description(StageHelpers::createSectionTitle('Previsión'))
                        ->compact()
                        ->schema([
                            Toggle::make('s1Stage.with_provision')
                                ->label('¿Tiene Previsión?')
                                ->onIcon('heroicon-m-check')
                                ->offIcon('heroicon-m-x-mark')
                                ->onColor('success')
                                ->offColor('danger')
                                ->default(false)
                                ->live()
                                ->visible(fn ($record) => $record?->s1Stage)
                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                    if (! $state) {
                                        // Si selecciona que NO tiene previsión → limpiar todos los campos
                                        $set('s1Stage.provision_amount', null);
                                        $set('s1Stage.provision_date', null);
                                        $set('s1Stage.provision_file', null);
                                    }
                                }),

                            TextInput::make('s1Stage.provision_amount')
                                ->label(false)
                                ->numeric()
                                ->prefix('S/')
                                ->placeholder('0.00')
                                ->visible(fn ($record) => $record?->s1Stage)
                                ->hidden(fn (Forms\Get $get) => ! $get('s1Stage.with_provision')),

                            DatePicker::make('s1Stage.provision_date')
                                ->label(false)
                                ->visible(fn ($record) => $record?->s1Stage)
                                ->hidden(fn (Forms\Get $get) => ! $get('s1Stage.with_provision')),

                            Forms\Components\FileUpload::make('s1Stage.provision_file')
                                ->label(false)
                                ->acceptedFileTypes(['application/pdf', 'image/*'])
                                ->maxSize(10240) // 10MB
                                ->directory('tenders/provisions')
                                ->visibility('private')
                                ->visible(fn ($record) => $record?->s1Stage)
                                ->hidden(fn (Forms\Get $get) => ! $get('s1Stage.with_provision')),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📋 SECCIÓN 5: APROBACIÓN DEL EXPEDIENTE DE CONTRATACIÓN
                    // ========================================================================
                    Section::make()
                        ->description(StageHelpers::createSectionTitle('Aprobación del Expediente', 'de Contratación'))
                        ->compact()
                        ->schema([
                            StageHelpers::createLegalTimeframePlaceholder('02 días hábiles', 'approval_expedient_legal_timeframe'),

                            DatePicker::make('s1Stage.approval_expedient_date')
                                ->label(false)
                                ->visible(fn ($record) => $record?->s1Stage),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📋 SECCIÓN 6: DESIGNACIÓN DEL COMITÉ DE SELECCIÓN (CON LÓGICA CONDICIONAL)
                    // ========================================================================
                    Section::make()
                        ->description(StageHelpers::createSectionTitle('Designación del Comité', 'de Selección'))
                        ->compact()
                        ->schema([
                            Toggle::make('s1Stage.apply_selection_committee')
                                ->label('¿Aplica designación del comité?')
                                ->onIcon('heroicon-m-check')
                                ->offIcon('heroicon-m-x-mark')
                                ->onColor('success')
                                ->offColor('danger')
                                ->default(true)
                                ->live()
                                ->visible(fn ($record) => $record?->s1Stage),

                            DatePicker::make('s1Stage.selection_committee_date')
                                ->label(false)
                                ->visible(fn ($record) => $record?->s1Stage)
                                ->hidden(fn (Forms\Get $get) => ! $get('s1Stage.apply_selection_committee'))
                                ->helperText('01 día hábil, segun Ley'),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📋 SECCIÓN 7: ELABORACIÓN DE BASES ADMINISTRATIVAS
                    // ========================================================================
                    Section::make()
                        ->description(StageHelpers::createSectionTitle('Elaboración de Bases Administrativas'))
                        ->compact()
                        ->schema([
                            StageHelpers::createLegalTimeframePlaceholder('02 días hábiles', 'administrative_bases_legal_timeframe'),

                            DatePicker::make('s1Stage.administrative_bases_date')
                                ->label(false)
                                ->visible(fn ($record) => $record?->s1Stage),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📋 SECCIÓN 8: APROBACIÓN DE BASES ADMINISTRATIVAS FORMATO 2
                    // ========================================================================
                    Section::make()
                        ->description(new HtmlString(
                            '<h4 class="text-center font-bold text-xs">Aprobación de Bases Administrativas<br>Formato 2 y Expediente</h4>'
                        ))
                        ->compact()
                        ->schema([
                            StageHelpers::createLegalTimeframePlaceholder('01 día hábil', 'approval_expedient_format_2_legal_timeframe'),

                            DatePicker::make('s1Stage.approval_expedient_format_2')
                                ->label(false)
                                ->prefixIcon('heroicon-s-flag')
                                ->prefixIconColor('success')
                                ->visible(fn ($record) => $record?->s1Stage)
                                ->live(),
                        ])->columnSpan(2),

                    // ========================================================================
                    // 📊 SECCIÓN 9: CÁLCULO DE TOTALES DE DÍAS
                    // ========================================================================
                    Section::make()
                        ->description(new HtmlString(
                            '<h2 class="text-center font-bold text-3xl">TOTAL DE DIAS</h2>'
                        ))
                        ->compact()
                        ->schema([
                            // Usar componentes compartidos para cálculos
                            DateCalculations::createCalendarDaysPlaceholder(
                                's1Stage.request_presentation_date',
                                's1Stage.approval_expedient_format_2',
                                'total_days'
                            ),

                            DateCalculations::createBusinessDaysPlaceholder(
                                's1Stage.request_presentation_date',
                                's1Stage.approval_expedient_format_2',
                                'total_business_days'
                            ),
                        ])->columnSpan(2),
                ])->visible(fn ($record) => $record?->s1Stage),
        ];
    }

    /**
     * 🎯 Obtiene la configuración del tab S1 Preparatory
     *
     * @return array Configuración completa del tab
     */
    public static function getTabConfig(): array
    {
        return [
            'label' => '1.Act. Preparatorias',
            'icon' => 'heroicon-m-clipboard-document-list',
            'badge' => fn ($record) => $record?->s1Stage ? 'Creada' : 'Pendiente',
            'badgeColor' => fn ($record) => StageHelpers::getStageBadgeColor('S1', (bool) $record?->s1Stage),
            'schema' => self::getSchema(),
        ];
    }

    /**
     * 🔧 Obtiene las opciones de configuración para toggles
     *
     * @return array Configuración de toggles
     */
    public static function getToggleConfig(): array
    {
        return [
            'certification' => [
                'onIcon' => 'heroicon-m-check',
                'offIcon' => 'heroicon-m-x-mark',
                'onColor' => 'success',
                'offColor' => 'danger',
                'default' => false,
            ],
            'provision' => [
                'onIcon' => 'heroicon-m-check',
                'offIcon' => 'heroicon-m-x-mark',
                'onColor' => 'success',
                'offColor' => 'danger',
                'default' => false,
            ],
            'selection_committee' => [
                'onIcon' => 'heroicon-m-check',
                'offIcon' => 'heroicon-m-x-mark',
                'onColor' => 'success',
                'offColor' => 'danger',
                'default' => true,
            ],
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
            'request_presentation_date' => [
                'prefixIcon' => 'heroicon-s-flag',
                'prefixIconColor' => 'info',
                'live' => true,
            ],
            'approval_expedient_format_2' => [
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
            'approval_expedient' => '02 días hábiles',
            'administrative_bases' => '02 días hábiles',
            'approval_expedient_format_2' => '01 día hábil',
            'selection_committee' => '01 día hábil, segun Ley',
        ];
    }

    /**
     * ✅ Valida si una etapa S1 está completa
     *
     * @param  array  $s1Data  Datos de la etapa S1
     * @return bool True si la etapa está completa
     */
    public static function isStageComplete(array $s1Data): bool
    {
        $requiredFields = [
            'request_presentation_date',
            'approval_expedient_format_2',
        ];

        foreach ($requiredFields as $field) {
            if (empty($s1Data[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * 📊 Calcula el progreso de la etapa S1
     *
     * @param  array  $s1Data  Datos de la etapa S1
     * @return int Porcentaje de progreso (0-100)
     */
    public static function calculateStageProgress(array $s1Data): int
    {
        $allFields = [
            'request_presentation_doc',
            'request_presentation_date',
            'market_indagation_doc',
            'market_indagation_date',
            'with_certification',
            'certification_date',
            'with_provision',
            'provision_amount',
            'provision_date',
            'provision_file',
            'approval_expedient_date',
            'apply_selection_committee',
            'selection_committee_date',
            'administrative_bases_date',
            'approval_expedient_format_2',
        ];

        $completedFields = 0;
        foreach ($allFields as $field) {
            if (! empty($s1Data[$field])) {
                $completedFields++;
            }
        }

        return (int) round(($completedFields / count($allFields)) * 100);
    }
}
