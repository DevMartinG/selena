<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenderResource\Pages;
use App\Models\Tender;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\IconPosition;
use Filament\Tables;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                                Grid::make(15)
                                    ->schema([
                                        TextInput::make('entity_name')
                                            ->label('Nombre o Siglas de la Entidad')
                                            ->default('GOBIERNO REGIONAL DE PUNO SEDE CENTRAL')
                                            ->readOnly()
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(5),
                                        TextInput::make('identifier')
                                            ->label('Nomenclatura')
                                            ->required()
                                            ->maxLength(255)
                                            ->autofocus()
                                            ->columnSpan(4)
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
                                            ->options([
                                                'Licitación Pública' => 'Licitación Pública',
                                                'Concurso Público' => 'Concurso Público',
                                                'Adjudicación Directa' => 'Adjudicación Directa',
                                                'Selección Simplificada' => 'Selección Simplificada',
                                            ])
                                            ->required()
                                            ->columnSpan(3),
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
                                            ->columnSpan(3),

                                        Textarea::make('object_description')
                                            ->label('Descripción del Objeto')
                                            ->required()
                                            ->rows(3)
                                            ->columnSpanFull(),

                                        Select::make('currency_name')
                                            ->label('Moneda')
                                            ->options([
                                                'PEN' => 'Soles (PEN)',
                                                'USD' => 'Dólares (USD)',
                                                'EUR' => 'Euros (EUR)',
                                            ])
                                            ->required()
                                            ->default('PEN')
                                            ->columnSpan(2),
                                        TextInput::make('estimated_referenced_value')
                                            ->label('Valor Referencial / Valor Estimado')
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
                                        Select::make('current_status')
                                            ->label('Estado Actual')
                                            ->required()
                                            ->options([
                                                '1-CONVOCADO' => '1. CONVOCADO',
                                                '2-REGISTRO DE PARTICIPANTES' => '2. REGISTRO DE PARTICIPANTES',
                                                '3-CONSULTAS Y OBSERVACIONES' => '3. CONSULTAS Y OBSERVACIONES',
                                                '4-ABSOLUCION DE CONSULTAS Y OBSERVACIONES' => '4. ABSOLUCIÓN DE CONSULTAS Y OBSERVACIONES',
                                                '5-INTEGRACIONDE BASES' => '5. INTEGRACIÓN DE BASES',
                                                '6-PRESENTANCION DE OFERTAS' => '6. PRESENTACIÓN DE OFERTAS',
                                                '7-EVALUACION Y CALIFICACION' => '7. EVALUACIÓN Y CALIFICACIÓN',
                                                '8-OTORGAMIENTO DE LA BUENA PRO (ADJUDICADO)' => '8. OTORGAMIENTO DE LA BUENA PRO (ADJUDICADO)',
                                                '9-CONSENTIDO' => '9. CONSENTIDO',
                                                '10-CONTRATADO' => '10. CONTRATADO',
                                                '11-CONTRATO SUSCRITO' => '11. CONTRATO SUSCRITO',
                                                '12-CONTRATO EN EJECUCION' => '12. CONTRATO EN EJECUCIÓN',
                                                '13-CONTRATO CULMINADO' => '13. CONTRATO CULMINADO',
                                            ])
                                            ->placeholder('[Seleccione Estado]')
                                            ->searchable()
                                            ->columnSpan(6),

                                        Textarea::make('observation')
                                            ->label('Observaciones')
                                            ->rows(3)
                                            ->columnSpan(6),
                                        Textarea::make('selection_comittee')
                                            ->label('OEC/ Comité de Selección')
                                            ->rows(3)
                                            ->columnSpan(6),
                                    ]),
                            ]),

                        Tabs\Tab::make('S1 Preparatory')
                            ->label('1.Act. Preparatorias')
                            ->icon('heroicon-m-clipboard-document-list')
                            ->badge(fn ($record) => $record?->s1Stage ? 'Creada' : 'Pendiente')
                            ->badgeColor(fn ($record) => $record?->s1Stage ? 'success' : 'gray')
                            ->schema([
                                Forms\Components\Section::make('S1 - Actuaciones Preparatorias')
                                    ->schema([
                                        Forms\Components\Placeholder::make('s1_status')
                                            ->label('')
                                            ->content(fn ($record) => $record?->s1Stage
                                                ? '✅ La etapa 1.Act. Preparatorias está creada. Puede editar los datos a continuación.'
                                                : '⏳ La etapa 1.Act. Preparatorias no está creada. Haga clic en "Crear Etapa 1" para inicializarla.')
                                            ->columnSpanFull(),

                                        // Campos S1 solo visibles si la etapa existe
                                        Forms\Components\TextInput::make('s1Stage.request_presentation_doc')
                                            ->label('Documento de Presentación del Requerimiento')
                                            ->maxLength(255)
                                            ->visible(fn ($record) => $record?->s1Stage),

                                        Forms\Components\DatePicker::make('s1Stage.request_presentation_date')
                                            ->label('Fecha de Presentación del Requerimiento')
                                            ->native(false)
                                            ->visible(fn ($record) => $record?->s1Stage),

                                        Forms\Components\TextInput::make('s1Stage.market_indagation_doc')
                                            ->label('Documento de Indagación de Mercado')
                                            ->maxLength(255)
                                            ->visible(fn ($record) => $record?->s1Stage),

                                        Forms\Components\DatePicker::make('s1Stage.market_indagation_date')
                                            ->label('Fecha de Indagación de Mercado')
                                            ->native(false)
                                            ->visible(fn ($record) => $record?->s1Stage),

                                        Forms\Components\Toggle::make('s1Stage.with_certification')
                                            ->label('Con Certificación')
                                            ->default(true)
                                            ->visible(fn ($record) => $record?->s1Stage),

                                        Forms\Components\DatePicker::make('s1Stage.certification_date')
                                            ->label('Fecha de Certificación')
                                            ->native(false)
                                            ->visible(fn ($record, Forms\Get $get) => $record?->s1Stage && $get('s1Stage.with_certification')),

                                        Forms\Components\TextInput::make('s1Stage.no_certification_reason')
                                            ->label('Motivo de No Certificación')
                                            ->maxLength(255)
                                            ->visible(fn ($record, Forms\Get $get) => $record?->s1Stage && ! $get('s1Stage.with_certification')),

                                        Forms\Components\DatePicker::make('s1Stage.approval_expedient_date')
                                            ->label('Fecha de Aprobación del Expediente')
                                            ->native(false)
                                            ->visible(fn ($record) => $record?->s1Stage),

                                        Forms\Components\DatePicker::make('s1Stage.selection_committee_date')
                                            ->label('Fecha de Designación del Comité')
                                            ->native(false)
                                            ->visible(fn ($record) => $record?->s1Stage),

                                        Forms\Components\DatePicker::make('s1Stage.administrative_bases_date')
                                            ->label('Fecha de Elaboración de Bases Administrativas')
                                            ->native(false)
                                            ->visible(fn ($record) => $record?->s1Stage),

                                        Forms\Components\DatePicker::make('s1Stage.approval_expedient_format_2')
                                            ->label('Fecha de Aprobación Formato 2')
                                            ->native(false)
                                            ->visible(fn ($record) => $record?->s1Stage),
                                    ])
                                    ->columns(2),
                            ]),

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

                                        Forms\Components\TextInput::make('s3Stage.contract_amount')
                                            ->label('Monto del Contrato')
                                            ->numeric()
                                            ->visible(fn ($record) => $record?->s3Stage),

                                        Forms\Components\TextInput::make('s3Stage.currency_name')
                                            ->label('Moneda')
                                            ->maxLength(255)
                                            ->visible(fn ($record) => $record?->s3Stage),

                                        Forms\Components\DatePicker::make('s3Stage.contract_start_date')
                                            ->label('Fecha de Inicio del Contrato')
                                            ->native(false)
                                            ->visible(fn ($record) => $record?->s3Stage),

                                        Forms\Components\DatePicker::make('s3Stage.contract_end_date')
                                            ->label('Fecha de Fin del Contrato')
                                            ->native(false)
                                            ->visible(fn ($record) => $record?->s3Stage),

                                        Forms\Components\TextInput::make('s3Stage.contract_duration')
                                            ->label('Duración del Contrato (días)')
                                            ->numeric()
                                            ->visible(fn ($record) => $record?->s3Stage),

                                        Forms\Components\Textarea::make('s3Stage.contract_terms')
                                            ->label('Términos del Contrato')
                                            ->visible(fn ($record) => $record?->s3Stage),
                                    ])
                                    ->columns(2),
                            ]),

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
                                            ->label('Suscripción del Contrato')
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
                    ->columnSpanFull()
                    ->activeTab(0), // Tab "Info. General" por defecto
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code_full')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold')
                    ->color('primary'),

                TextColumn::make('entity_name')
                    ->label('Entidad')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();

                        return strlen($state) > 30 ? $state : null;
                    }),

                TextColumn::make('identifier')
                    ->label('Nomenclatura')
                    ->searchable()
                    ->sortable()
                    ->limit(25)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();

                        return strlen($state) > 25 ? $state : null;
                    }),

                TextColumn::make('contract_object')
                    ->label('Objeto')
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

                TextColumn::make('current_status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, 'CONVOCADO') => 'info',
                        str_contains($state, 'REGISTRO') => 'warning',
                        str_contains($state, 'CONSULTAS') => 'gray',
                        str_contains($state, 'ABSOLUCION') => 'primary',
                        str_contains($state, 'INTEGRACION') => 'warning',
                        str_contains($state, 'PRESENTACION') => 'info',
                        str_contains($state, 'EVALUACION') => 'warning',
                        str_contains($state, 'OTORGAMIENTO') => 'success',
                        str_contains($state, 'CONSENTIDO') => 'success',
                        str_contains($state, 'CONTRATADO') => 'success',
                        str_contains($state, 'SUSCRITO') => 'success',
                        str_contains($state, 'EJECUCION') => 'info',
                        str_contains($state, 'CULMINADO') => 'success',
                        default => 'gray',
                    })
                    ->limit(25)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();

                        return strlen($state) > 25 ? $state : null;
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
                Tables\Filters\SelectFilter::make('current_status')
                    ->label('Estado')
                    ->options([
                        '1-CONVOCADO' => '1. CONVOCADO',
                        '2-REGISTRO DE PARTICIPANTES' => '2. REGISTRO DE PARTICIPANTES',
                        '3-CONSULTAS Y OBSERVACIONES' => '3. CONSULTAS Y OBSERVACIONES',
                        '4-ABSOLUCION DE CONSULTAS Y OBSERVACIONES' => '4. ABSOLUCIÓN DE CONSULTAS Y OBSERVACIONES',
                        '5-INTEGRACIONDE BASES' => '5. INTEGRACIÓN DE BASES',
                        '6-PRESENTANCION DE OFERTAS' => '6. PRESENTACIÓN DE OFERTAS',
                        '7-EVALUACION Y CALIFICACION' => '7. EVALUACIÓN Y CALIFICACIÓN',
                        '8-OTORGAMIENTO DE LA BUENA PRO (ADJUDICADO)' => '8. OTORGAMIENTO DE LA BUENA PRO (ADJUDICADO)',
                        '9-CONSENTIDO' => '9. CONSENTIDO',
                        '10-CONTRATADO' => '10. CONTRATADO',
                        '11-CONTRATO SUSCRITO' => '11. CONTRATO SUSCRITO',
                        '12-CONTRATO EN EJECUCION' => '12. CONTRATO EN EJECUCIÓN',
                        '13-CONTRATO CULMINADO' => '13. CONTRATO CULMINADO',
                    ]),
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
                            Forms\Components\Select::make('new_status')
                                ->label('Nuevo Estado')
                                ->options([
                                    '1-CONVOCADO' => '1. CONVOCADO',
                                    '2-REGISTRO DE PARTICIPANTES' => '2. REGISTRO DE PARTICIPANTES',
                                    '3-CONSULTAS Y OBSERVACIONES' => '3. CONSULTAS Y OBSERVACIONES',
                                    '4-ABSOLUCION DE CONSULTAS Y OBSERVACIONES' => '4. ABSOLUCIÓN DE CONSULTAS Y OBSERVACIONES',
                                    '5-INTEGRACIONDE BASES' => '5. INTEGRACIÓN DE BASES',
                                    '6-PRESENTANCION DE OFERTAS' => '6. PRESENTACIÓN DE OFERTAS',
                                    '7-EVALUACION Y CALIFICACION' => '7. EVALUACIÓN Y CALIFICACIÓN',
                                    '8-OTORGAMIENTO DE LA BUENA PRO (ADJUDICADO)' => '8. OTORGAMIENTO DE LA BUENA PRO (ADJUDICADO)',
                                    '9-CONSENTIDO' => '9. CONSENTIDO',
                                    '10-CONTRATADO' => '10. CONTRATADO',
                                    '11-CONTRATO SUSCRITO' => '11. CONTRATO SUSCRITO',
                                    '12-CONTRATO EN EJECUCION' => '12. CONTRATO EN EJECUCIÓN',
                                    '13-CONTRATO CULMINADO' => '13. CONTRATO CULMINADO',
                                ])
                                ->required(),
                        ])
                        ->action(function ($records, array $data) {
                            $updatedCount = 0;
                            foreach ($records as $record) {
                                $record->update(['current_status' => $data['new_status']]);
                                $updatedCount++;
                            }

                            Notification::make()
                                ->title('Estados actualizados')
                                ->body("Se han actualizado {$updatedCount} procedimientos al estado: {$data['new_status']}")
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
