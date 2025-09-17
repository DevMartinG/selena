<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenderResource\Pages;
use App\Models\Tender;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\IconPosition;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class TenderResource extends Resource
{
    protected static ?string $model = Tender::class;

    protected static ?string $label = 'Proc. Selección';

    protected static ?string $pluralLabel = 'Proc. Selección';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationIcon(): string
    {
        return request()->routeIs('filament.admin.resources.tenders.index') ? 'heroicon-s-rectangle-stack' : 'heroicon-o-rectangle-stack';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Tender Management')
                    ->persistTab() // recordar la última tab seleccionada
                    ->id('tender-form-tabs')
                    ->tabs([
                        Tabs\Tab::make('General Info')
                            ->label('Info. General')
                            ->icon('heroicon-m-clipboard-document')
                            ->iconPosition(IconPosition::Before)
                            ->schema([
                                Grid::make(5)
                                    ->schema([
                                        // PANEL IZQUIERDO: Información Básica (60% = 2/3)
                                        Fieldset::make('Información Principal')
                                            ->schema([
                                                Grid::make(12)
                                                    ->schema([
                                                        // Identificación del Proceso
                                                        TextInput::make('identifier')
                                                            ->label('Nomenclatura')
                                                            ->required()
                                                            ->maxLength(255)
                                                            ->autofocus()
                                                            ->columnSpan(7)
                                                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                                $normalized = Tender::normalizeIdentifier($state);

                                                                $isDuplicate = Tender::query()
                                                                    ->where('code_full', $normalized)
                                                                    ->when($get('id'), fn ($query, $id) => $query->where('id', '!=', $id))
                                                                    ->exists();

                                                                if ($isDuplicate) {
                                                                    Notification::make()
                                                                        ->title('Nomenclatura duplicada')
                                                                        ->icon('heroicon-s-exclamation-triangle')
                                                                        ->warning()
                                                                        ->duration(5000)
                                                                        ->send();
                                                                }
                                                            }),

                                                        Select::make('process_type')
                                                            ->label('Tipo de Proceso')
                                                            ->options(\App\Models\ProcessType::pluck('description_short_type', 'description_short_type'))
                                                            ->required()
                                                            ->columnSpan(5),

                                                        // Objeto y Descripción
                                                        Select::make('currency_name')
                                                            ->label('Moneda')
                                                            ->options([
                                                                'PEN' => 'Soles (PEN)',
                                                                'USD' => 'Dólares (USD)',
                                                                'EUR' => 'Euros (EUR)',
                                                            ])
                                                            ->required()
                                                            ->default('PEN')
                                                            ->columnSpan(3),

                                                        TextInput::make('estimated_referenced_value')
                                                            ->label('Valor Ref. / Valor Estimado')
                                                            ->numeric()
                                                            ->prefix(fn (Forms\Get $get) => match ($get('currency_name')) {
                                                                'PEN' => 'S/',
                                                                'USD' => '$',
                                                                'EUR' => '€',
                                                                default => 'S/',
                                                            })
                                                            ->step(0.01)
                                                            ->minValue(0)
                                                            ->required()
                                                            ->columnSpan(4),
                                                        Select::make('contract_object')
                                                            ->label('Objeto de Contratación')
                                                            ->required()
                                                            ->options([
                                                                'Bien' => 'Bien',
                                                                'Consultoría de Obra' => 'Consultoría de Obra',
                                                                'Obra' => 'Obra',
                                                                'Servicio' => 'Servicio',
                                                            ])
                                                            ->placeholder('[Seleccione]')
                                                            ->columnSpan(5),

                                                        // Descripción del Objeto
                                                        Textarea::make('object_description')
                                                            ->label('Descripción del Objeto')
                                                            ->required()
                                                            ->rows(4)
                                                            ->columnSpanFull(),
                                                    ]),

                                            ])->columnSpan(3),

                                        // PANEL DERECHO: Información Adicional (40% = 1/3)
                                        Fieldset::make('Estado, Observaciones y Comité')
                                            ->schema([
                                                Grid::make(12)
                                                    ->schema([
                                                        Select::make('tender_status_id')
                                                            ->label('Estado Actual')
                                                            ->options(\App\Models\TenderStatus::validForForm()->pluck('name', 'id'))
                                                            ->columnSpanFull()
                                                            ->required()
                                                            ->placeholder('Seleccione el estado'),

                                                        // Observaciones y Comité
                                                        Textarea::make('observation')
                                                            ->label('Observaciones')
                                                            ->rows(3)
                                                            ->columnSpanFull(),

                                                        Textarea::make('selection_comittee')
                                                            ->label('OEC/ Comité de Selección')
                                                            ->rows(3)
                                                            ->columnSpanFull(),
                                                    ]),
                                            ])->columnSpan(2),

                                    ]),
                            ]),

                        // ========================================================================
                        // 🎯 TAB S1 - ACTUACIONES PREPARATORIAS
                        // ========================================================================
                        // Este tab maneja la etapa S1 del proceso de selección.
                        // Los campos usan la sintaxis 's1Stage.campo' que es manejada automáticamente
                        // por los mutators/accessors del modelo Tender.
                        //
                        // FLUJO:
                        // 1. Usuario hace clic en "Crear Etapa 1" → TenderStageInitializer crea la etapa
                        // 2. Usuario llena campos → Mutators guardan automáticamente en tender_stage_s1_preparatory_actions
                        // 3. Usuario hace clic en "Guardar" → Accessors leen datos para mostrar en formulario
                        Tabs\Tab::make('S1 Preparatory')
                            ->label('1.Act. Preparatorias')
                            ->icon('heroicon-m-clipboard-document-list')
                            ->badge(fn ($record) => $record?->s1Stage ? 'Creada' : 'Pendiente')
                            ->badgeColor(fn ($record) => $record?->s1Stage ? 'success' : 'gray')
                            ->schema([
                                Section::make('S1 - Actuaciones Preparatorias')
                                    ->schema([
                                        Placeholder::make('s1_status')
                                            ->label('')
                                            ->content(fn ($record) => $record?->s1Stage
                                                ? '✅ La etapa 1.Act. Preparatorias está creada. Puede editar los datos a continuación.'
                                                : '⏳ La etapa 1.Act. Preparatorias no está creada. Haga clic en "Crear Etapa 1" para inicializarla.')
                                            ->columnSpanFull(),

                                        Grid::make(8)
                                            ->schema([
                                                Section::make()
                                                    ->label(false)
                                                    ->description(new HtmlString(
                                                        '<h2 class="text-center font-bold text-xs">Presentación de Requerimiento<br>de Bien</h2>'
                                                    ))
                                                    ->compact()
                                                    ->schema([
                                                        TextInput::make('s1Stage.request_presentation_doc')
                                                            ->label(false)
                                                            ->placeholder('Documento/Ref.')
                                                            ->maxLength(255)
                                                            ->visible(fn ($record) => $record?->s1Stage),

                                                        DatePicker::make('s1Stage.request_presentation_date')
                                                            ->label(false)
                                                            ->placeholder('Seleccione Fecha')
                                                            // ->native(false)
                                                            ->prefixIcon('heroicon-s-flag')
                                                            ->prefixIconColor('info')
                                                            ->visible(fn ($record) => $record?->s1Stage)
                                                            ->live(),
                                                    ])->columnSpan(2),
                                                Section::make()
                                                    ->description(new HtmlString(
                                                        '<h2 class="text-center font-bold text-xs"><br>Indagación de Mercado</h2>'
                                                    ))
                                                    ->compact()
                                                    ->schema([
                                                    TextInput::make('s1Stage.market_indagation_doc')
                                                        ->label(false)
                                                        ->placeholder('Documento/Ref.')
                                                        ->maxLength(255)
                                                        ->visible(fn ($record) => $record?->s1Stage),

                                                    DatePicker::make('s1Stage.market_indagation_date')
                                                        ->label(false)
                                                        ->placeholder('Seleccione Fecha')
                                                        // ->native(false)
                                                        ->visible(fn ($record) => $record?->s1Stage),
                                                    ])->columnSpan(2),
                                                Section::make()
                                                    ->label(false)
                                                    ->description(new HtmlString(
                                                        '<h2 class="text-center font-bold text-xs"><br>Certificación</h2>'
                                                    ))
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
                                                            ->placeholder('Seleccione Fecha')
                                                            // ->native(false)
                                                            ->visible(fn ($record) => $record?->s1Stage) // condición estática
                                                            ->hidden(fn (Forms\Get $get) => ! $get('s1Stage.with_certification')), // dinámica

                                                        TextInput::make('s1Stage.no_certification_reason')
                                                            ->label(false)
                                                            ->placeholder('Motivo de no certificación')
                                                            ->maxLength(255)
                                                            ->visible(fn ($record) => $record?->s1Stage) // condición estática
                                                            ->hidden(fn (Forms\Get $get) => $get('s1Stage.with_certification')), // dinámica
                                                    ])->columnSpan(2),
                                                Section::make()
                                                    ->description(new HtmlString(
                                                        '<h2 class="text-center font-bold text-xs">Aprobación del Expediente<br>de Contratación</h2>'
                                                    ))
                                                    ->compact()
                                                    ->schema([
                                                        Placeholder::make('approval_expedient_legal_timeframe')
                                                            ->label('Plazo segun Ley')
                                                            ->content('02 días hábiles'),

                                                        DatePicker::make('s1Stage.approval_expedient_date')
                                                            ->label(false)
                                                            ->placeholder('Seleccione Fecha')
                                                            // ->native(false)
                                                            ->visible(fn ($record) => $record?->s1Stage),
                                                    ])->columnSpan(2),

                                                Section::make()
                                                    ->description(new HtmlString(
                                                        '<h2 class="text-center font-bold text-xs">Designación del Comité<br>de Selección</h2>'
                                                    ))
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

                                                        /* Placeholder::make('selection_committee_legal_timeframe')
                                                            ->label(false)
                                                            ->content('01 día hábil, segun Ley')
                                                            ->hidden(fn (Forms\Get $get) => ! $get('s1Stage.apply_selection_committee')), */

                                                        DatePicker::make('s1Stage.selection_committee_date')
                                                            ->label(false)
                                                            ->placeholder('Seleccione Fecha')
                                                            // ->native(false)
                                                            ->visible(fn ($record) => $record?->s1Stage)
                                                            ->hidden(fn (Forms\Get $get) => ! $get('s1Stage.apply_selection_committee'))
                                                            ->helperText('01 día hábil, segun Ley'),
                                                    ])->columnSpan(2),
                                                Section::make()
                                                    ->description(new HtmlString(
                                                        '<h2 class="text-center font-bold text-xs"><br>Elaboración de Bases Administrativas</h2>'
                                                    ))
                                                    ->compact()
                                                    ->schema([
                                                        Placeholder::make('administrative_bases_legal_timeframe')
                                                            ->label('Plazo segun Ley')
                                                            ->content('02 días hábiles'),
                                                        DatePicker::make('s1Stage.administrative_bases_date')
                                                            ->label(false)
                                                            ->placeholder('Seleccione Fecha')
                                                            // ->native(false)
                                                            ->visible(fn ($record) => $record?->s1Stage),
                                                    ])->columnSpan(2),
                                                Section::make()
                                                    ->description(new HtmlString(
                                                        '<h4 class="text-center font-bold text-xs">Aprobación de Bases Administrativas<br>Formato 2 y Expediente</h4>'
                                                    ))
                                                    ->compact()
                                                    ->schema([
                                                        Placeholder::make('approval_expedient_format_2_legal_timeframe')
                                                            ->label('Plazo segun Ley')
                                                            ->content('01 día hábil'),
                                                        DatePicker::make('s1Stage.approval_expedient_format_2')
                                                            ->label(false)
                                                            ->placeholder('Seleccione Fecha')
                                                            ->prefixIcon('heroicon-s-flag')
                                                            ->prefixIconColor('success')
                                                            // ->native(false)
                                                            ->visible(fn ($record) => $record?->s1Stage)
                                                            ->live(),
                                                    ])->columnSpan(2),
                                                Section::make()
                                                    ->description(new HtmlString(
                                                        '<h2 class="text-center font-bold text-3xl">TOTAL DE DIAS</h2>'
                                                    ))
                                                    ->compact()
                                                    ->schema([
                                                        Placeholder::make('total_days')
                                                            ->label(false)
                                                            ->content(function (Forms\Get $get) {
                                                                $start = $get('s1Stage.request_presentation_date');
                                                                $end = $get('s1Stage.approval_expedient_format_2');

                                                                if (! $start || ! $end) {
                                                                    return new HtmlString("<span class='text-xs'>Las Fechas con icono de bandera deben ser seleccionadas para el cálculo.</span>");
                                                                }

                                                                try {
                                                                    $startDate = Carbon::parse($start);
                                                                    $endDate = Carbon::parse($end);

                                                                    // Diferencia en días
                                                                    $days = $startDate->diffInDays($endDate);

                                                                    return new HtmlString("<span class='font-bold text-lg'>{$days} día(s) calendario</span>");
                                                                } catch (\Exception $e) {
                                                                    return 'Fechas inválidas';
                                                                }
                                                            }),
                                                        Placeholder::make('total_business_days')
                                                            ->label(false)
                                                            ->content(function (Forms\Get $get) {
                                                                $start = $get('s1Stage.request_presentation_date');
                                                                $end = $get('s1Stage.approval_expedient_format_2');

                                                                if (! $start || ! $end) {
                                                                    return new HtmlString("<span class='text-xs'>Las Fechas con icono de bandera deben ser seleccionadas para el cálculo.</span>");
                                                                }

                                                                try {
                                                                    $startDate = \Carbon\Carbon::parse($start);
                                                                    $endDate = \Carbon\Carbon::parse($end);

                                                                    if ($endDate->lessThan($startDate)) {
                                                                        return 'Fechas inválidas';
                                                                    }

                                                                    $businessDays = 0;
                                                                    $date = $startDate->copy();

                                                                    while ($date->lte($endDate)) {
                                                                        if (! $date->isWeekend()) {
                                                                            $businessDays++;
                                                                        }
                                                                        $date->addDay();
                                                                    }

                                                                    return new HtmlString("<span class='font-bold text-lg'>{$businessDays} día(s) hábil(es)</span>");
                                                                } catch (\Exception $e) {
                                                                    return 'Fechas inválidas';
                                                                }
                                                            }),
                                                    ])->columnSpan(2),
                                            ])->visible(fn ($record) => $record?->s1Stage),
                                    ])
                                    ->columns(2),
                            ]),

                        // ========================================================================
                        // 🎯 TAB S2 - PROCEDIMIENTO DE SELECCIÓN
                        // ========================================================================
                        // Este tab maneja la etapa S2 del proceso de selección.
                        // Campos: published_at, participants_registration, absolution_obs, etc.
                        // Los datos se guardan en tender_stage_s2_selection_process
                        Tabs\Tab::make('S2 Selection')
                            ->label('2.Proc. de Selección')
                            ->icon('heroicon-m-users')
                            ->badge(fn ($record) => $record?->s2Stage ? 'Creada' : 'Pendiente')
                            ->badgeColor(fn ($record) => $record?->s2Stage ? 'success' : 'gray')
                            ->schema([
                                Forms\Components\Section::make('S2 - Procedimiento de Selección')
                                    ->schema([
                                        Forms\Components\Placeholder::make('s2_status')
                                            ->label('')
                                            ->content(fn ($record) => $record?->s2Stage
                                                ? '✅ La etapa 2.Proc. de Selección está creada. Puede editar los datos a continuación.'
                                                : '⏳ La etapa 2.Proc. de Selección no está creada. Haga clic en "Crear Etapa 2" para inicializarla.')
                                            ->columnSpanFull(),

                                        // Campos S2 solo visibles si la etapa existe
                                        Forms\Components\DatePicker::make('s2Stage.published_at')
                                            ->label('Fecha de Publicación en SEACE')
                                            ->native(false)
                                            ->required()
                                            ->visible(fn ($record) => $record?->s2Stage),

                                        Forms\Components\DatePicker::make('s2Stage.participants_registration')
                                            ->label('Registro de Participantes')
                                            ->native(false)
                                            ->visible(fn ($record) => $record?->s2Stage),

                                        Forms\Components\TextInput::make('s2Stage.restarted_from')
                                            ->label('Reiniciado desde')
                                            ->maxLength(255)
                                            ->visible(fn ($record) => $record?->s2Stage),

                                        Forms\Components\TextInput::make('s2Stage.cui_code')
                                            ->label('Código CUI')
                                            ->maxLength(255)
                                            ->visible(fn ($record) => $record?->s2Stage),

                                        Forms\Components\DatePicker::make('s2Stage.absolution_obs')
                                            ->label('Absolución de Consultas/Observaciones')
                                            ->native(false)
                                            ->visible(fn ($record) => $record?->s2Stage),

                                        Forms\Components\DatePicker::make('s2Stage.base_integration')
                                            ->label('Integración de Bases')
                                            ->native(false)
                                            ->visible(fn ($record) => $record?->s2Stage),

                                        Forms\Components\DatePicker::make('s2Stage.offer_presentation')
                                            ->label('Presentación de Ofertas')
                                            ->native(false)
                                            ->visible(fn ($record) => $record?->s2Stage),

                                        Forms\Components\DatePicker::make('s2Stage.offer_evaluation')
                                            ->label('Evaluación de Propuestas')
                                            ->native(false)
                                            ->visible(fn ($record) => $record?->s2Stage),

                                        Forms\Components\DatePicker::make('s2Stage.award_granted_at')
                                            ->label('Otorgamiento de Buena Pro')
                                            ->native(false)
                                            ->visible(fn ($record) => $record?->s2Stage),

                                        Forms\Components\DatePicker::make('s2Stage.award_consent')
                                            ->label('Consentimiento de Buena Pro')
                                            ->native(false)
                                            ->visible(fn ($record) => $record?->s2Stage),

                                        Forms\Components\DatePicker::make('s2Stage.appeal_date')
                                            ->label('Fecha de Apelación')
                                            ->native(false)
                                            ->visible(fn ($record) => $record?->s2Stage),

                                        Forms\Components\TextInput::make('s2Stage.awarded_tax_id')
                                            ->label('RUC del Adjudicado')
                                            ->maxLength(255)
                                            ->visible(fn ($record) => $record?->s2Stage),

                                        Forms\Components\Textarea::make('s2Stage.awarded_legal_name')
                                            ->label('Razón Social del Adjudicado')
                                            ->visible(fn ($record) => $record?->s2Stage),
                                    ])
                                    ->columns(2),
                            ]),

                        // ========================================================================
                        // 🎯 TAB S3 - SUSCRIPCIÓN DEL CONTRATO
                        // ========================================================================
                        // Este tab maneja la etapa S3 del proceso de selección.
                        // Campos: contract_signing, awarded_amount, adjusted_amount, etc.
                        // Los datos se guardan en tender_stage_s3_contract_signing
                        Tabs\Tab::make('S3 Contract')
                            ->label('3.Suscripción del Contrato')
                            ->icon('heroicon-m-document-text')
                            ->badge(fn ($record) => $record?->s3Stage ? 'Creada' : 'Pendiente')
                            ->badgeColor(fn ($record) => $record?->s3Stage ? 'success' : 'gray')
                            ->schema([
                                Forms\Components\Section::make('S3 - Suscripción del Contrato')
                                    ->schema([
                                        Forms\Components\Placeholder::make('s3_status')
                                            ->label('')
                                            ->content(fn ($record) => $record?->s3Stage
                                                ? '✅ La etapa 3.Suscripción del Contrato está creada. Puede editar los datos a continuación.'
                                                : '⏳ La etapa 3.Suscripción del Contrato no está creada. Haga clic en "Crear Etapa 3" para inicializarla.')
                                            ->columnSpanFull(),

                                        // Campos S3 solo visibles si la etapa existe
                                        Forms\Components\DatePicker::make('s3Stage.doc_sign_presentation_date')
                                            ->label('Fecha de Presentación de Documentos de Suscripción')
                                            ->native(false)
                                            ->visible(fn ($record) => $record?->s3Stage),

                                        Forms\Components\DatePicker::make('s3Stage.contract_signing')
                                            ->label('Suscripción del Contrato')
                                            ->native(false)
                                            ->visible(fn ($record) => $record?->s3Stage),

                                        Forms\Components\TextInput::make('s3Stage.awarded_amount')
                                            ->label('Monto Adjudicado')
                                            ->numeric()
                                            ->visible(fn ($record) => $record?->s3Stage),

                                        Forms\Components\TextInput::make('s3Stage.adjusted_amount')
                                            ->label('Monto Ajustado')
                                            ->numeric()
                                            ->visible(fn ($record) => $record?->s3Stage),
                                    ])
                                    ->columns(2),
                            ]),

                        // ========================================================================
                        // 🎯 TAB S4 - TIEMPO DE EJECUCIÓN
                        // ========================================================================
                        // Este tab maneja la etapa S4 del proceso de selección.
                        // Campos: contract_details, contract_signing, contract_vigency_date
                        // Los datos se guardan en tender_stage_s4_execution_time
                        Tabs\Tab::make('S4 Execution')
                            ->label('4.Ejecución')
                            ->icon('heroicon-m-clock')
                            ->badge(fn ($record) => $record?->s4Stage ? 'Creada' : 'Pendiente')
                            ->badgeColor(fn ($record) => $record?->s4Stage ? 'success' : 'gray')
                            ->schema([
                                Forms\Components\Section::make('S4 - Tiempo de Ejecución')
                                    ->schema([
                                        Forms\Components\Placeholder::make('s4_status')
                                            ->label('')
                                            ->content(fn ($record) => $record?->s4Stage
                                                ? '✅ La etapa 4.Ejecución está creada. Puede editar los datos a continuación.'
                                                : '⏳ La etapa 4.Ejecución no está creada. Haga clic en "Crear Etapa 4" para inicializarla.')
                                            ->columnSpanFull(),

                                        // Campos S4 solo visibles si la etapa existe
                                        Forms\Components\Textarea::make('s4Stage.contract_details')
                                            ->label('Detalles del Contrato')
                                            ->visible(fn ($record) => $record?->s4Stage),

                                        Forms\Components\DatePicker::make('s4Stage.contract_signing')
                                            ->label('Fecha de Suscripción del Contrato')
                                            ->native(false)
                                            ->visible(fn ($record) => $record?->s4Stage),

                                        Forms\Components\DatePicker::make('s4Stage.contract_vigency_date')
                                            ->label('Fecha de Vigencia del Contrato')
                                            ->native(false)
                                            ->visible(fn ($record) => $record?->s4Stage),
                                    ])
                                    ->columns(2),
                            ]),
                    ])
                    ->persistTab(false)
                    ->columnSpanFull()
                    ->activeTab(1), // Tab "Info. General" por defecto
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('identifier')
                    ->label('Nomenclatura')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold')
                    ->color('primary')
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();

                        return strlen($state) > 30 ? $state : null;
                    }),

                TextColumn::make('entity_name')
                    ->label('Entidad')
                    ->searchable()
                    ->sortable()
                    ->limit(25)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();

                        return strlen($state) > 25 ? $state : null;
                    }),

                TextColumn::make('process_type')
                    ->label('Tipo de Proceso')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Licitación Pública' => 'info',
                        'Concurso Público' => 'success',
                        'Adjudicación Directa' => 'warning',
                        'Selección Simplificada' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('contract_object')
                    ->label('Objeto')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Bien' => 'info',
                        'Obra' => 'warning',
                        'Servicio' => 'success',
                        'Consultoría de Obra' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('estimated_referenced_value')
                    ->label('Valor Referencial')
                    ->money('PEN')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('currency_name')
                    ->label('Moneda')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'PEN' => 'success',
                        'USD' => 'info',
                        'EUR' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('tenderStatus.name')
                    ->label('Estado')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn ($record): string => match (true) {
                        ! $record->tenderStatus => 'danger', // ← ROJO para estados no válidos
                        $record->tenderStatus->code === '--' => 'gray',
                        $record->tenderStatus->category === 'special' => 'danger',
                        str_contains($record->tenderStatus->code, 'CONVOCADO') => 'info',
                        str_contains($record->tenderStatus->code, 'REGISTRO') => 'warning',
                        str_contains($record->tenderStatus->code, 'CONSULTAS') => 'gray',
                        str_contains($record->tenderStatus->code, 'ABSOLUCION') => 'primary',
                        str_contains($record->tenderStatus->code, 'INTEGRACION') => 'warning',
                        str_contains($record->tenderStatus->code, 'PRESENTACION') => 'info',
                        str_contains($record->tenderStatus->code, 'EVALUACION') => 'warning',
                        str_contains($record->tenderStatus->code, 'OTORGAMIENTO') => 'success',
                        str_contains($record->tenderStatus->code, 'CONSENTIDO') => 'success',
                        str_contains($record->tenderStatus->code, 'CONTRATADO') => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($record): string => ! $record->tenderStatus ? '⚠️ SIN ESTADO' : $record->tenderStatus->name
                    )
                    ->limit(20)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();

                        return strlen($state) > 20 ? $state : null;
                    }),

                TextColumn::make('stages_count')
                    ->label('Etapas')
                    ->counts('stages')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('contract_object')
                    ->label('Objeto de Contratación')
                    ->options([
                        'Bien' => 'Bien',
                        'Consultoría de Obra' => 'Consultoría de Obra',
                        'Obra' => 'Obra',
                        'Servicio' => 'Servicio',
                    ]),
                Tables\Filters\SelectFilter::make('tender_status_id')
                    ->label('Estado')
                    ->relationship('tenderStatus', 'name')
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->icon('heroicon-s-pencil-square')
                    ->label(false)
                    ->tooltip('Editar este procedimiento de selección')
                    ->color('primary')
                    ->size('lg'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_status_update')
                        ->label('Actualizar Estado')
                        ->icon('heroicon-m-pencil-square')
                        ->color('info')
                        ->form([
                            Forms\Components\Select::make('tender_status_id')
                                ->label('Nuevo Estado')
                                ->options(\App\Models\TenderStatus::validForForm()->pluck('name', 'id'))
                                ->required(),
                        ])
                        ->action(function ($records, array $data) {
                            $updatedCount = 0;
                            $statusName = \App\Models\TenderStatus::find($data['tender_status_id'])->name;

                            foreach ($records as $record) {
                                $record->update(['tender_status_id' => $data['tender_status_id']]);
                                $updatedCount++;
                            }

                            Notification::make()
                                ->title('Estados actualizados')
                                ->body("Se han actualizado {$updatedCount} procedimientos al estado: {$statusName}")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar Seleccionados')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Procedimientos Seleccionados')
                        ->modalDescription('¿Está seguro de que desea eliminar los procedimientos seleccionados? Esta acción eliminará también todas las etapas asociadas y no se puede deshacer.')
                        ->modalSubmitActionLabel('Sí, eliminar')
                        ->modalCancelActionLabel('Cancelar'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenders::route('/'),
            'create' => Pages\CreateTender::route('/create'),
            'edit' => Pages\EditTender::route('/{record}/edit'),
        ];
    }
}
