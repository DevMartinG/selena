<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenderResource\Pages;
use App\Filament\Resources\TenderResource\RelationManagers\TenderStageS1RelationManager;
use App\Filament\Resources\TenderResource\RelationManagers\TenderStageS2RelationManager;
use App\Filament\Resources\TenderResource\RelationManagers\TenderStageS3RelationManager;
use App\Filament\Resources\TenderResource\RelationManagers\TenderStageS4RelationManager;
use App\Models\Tender;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\IconPosition;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

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
                                Forms\Components\Section::make('Información Básica del Procedimiento')
                                    ->schema([
                                        Forms\Components\TextInput::make('entity_name')
                                            ->label('Nombre o Siglas de la Entidad')
                                            ->default('GOBIERNO REGIONAL DE PUNO SEDE CENTRAL')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(6),
                                        Forms\Components\TextInput::make('identifier')
                                            ->label('Nomenclatura')
                                            ->required()
                                            ->maxLength(255)
                                            ->autofocus()
                                            ->columnSpan(6)
                                            ->live(onBlur: true)
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
                                        Forms\Components\Select::make('process_type')
                                            ->label('Tipo de Proceso')
                                            ->options([
                                                'Licitación Pública' => 'Licitación Pública',
                                                'Concurso Público' => 'Concurso Público',
                                                'Adjudicación Directa' => 'Adjudicación Directa',
                                                'Selección Simplificada' => 'Selección Simplificada',
                                            ])
                                            ->required()
                                            ->columnSpan(3),
                                        Forms\Components\Select::make('contract_object')
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
                                        Forms\Components\Textarea::make('object_description')
                                            ->label('Descripción del Objeto')
                                            ->required()
                                            ->rows(3)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(12),

                                Forms\Components\Section::make('Información Económica')
                                    ->schema([
                                        Forms\Components\Select::make('currency_name')
                                            ->label('Moneda')
                                            ->options([
                                                'PEN' => 'Soles (PEN)',
                                                'USD' => 'Dólares (USD)',
                                                'EUR' => 'Euros (EUR)',
                                            ])
                                            ->required()
                                            ->default('PEN')
                                            ->columnSpan(3),
                                        Forms\Components\TextInput::make('estimated_referenced_value')
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
                                            ->columnSpan(3),
                                        Forms\Components\Select::make('current_status')
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
                                    ])
                                    ->columns(12),

                                Forms\Components\Section::make('Información Adicional')
                                    ->schema([
                                        Forms\Components\Textarea::make('observation')
                                            ->label('Observaciones')
                                            ->rows(3)
                                            ->columnSpan(6),
                                        Forms\Components\Textarea::make('selection_comittee')
                                            ->label('OEC/ Comité de Selección')
                                            ->rows(3)
                                            ->columnSpan(6),
                                    ])
                                    ->columns(12),
                            ]),

                        Tabs\Tab::make('S1 Preparatory')
                            ->label('Act. Preparatorias')
                            ->icon('heroicon-m-clipboard-document-list')
                            ->badge(fn ($record) => $record?->s1Stage ? 'Completado' : 'Pendiente')
                            ->badgeColor(fn ($record) => $record?->s1Stage ? 'success' : 'gray')
                            ->schema([
                                Forms\Components\Placeholder::make('s1_info')
                                    ->label('')
                                    ->content('Los datos de la etapa S1 se gestionan a través del Relation Manager correspondiente. Use las acciones de la tabla para crear, editar o eliminar registros de esta etapa.')
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make('S2 Selection')
                            ->label('Proced. Selección')
                            ->icon('heroicon-m-users')
                            ->badge(fn ($record) => $record?->s2Stage ? 'Completado' : 'Pendiente')
                            ->badgeColor(fn ($record) => $record?->s2Stage ? 'success' : 'gray')
                            ->schema([
                                Forms\Components\Placeholder::make('s2_info')
                                    ->label('')
                                    ->content('Los datos de la etapa S2 se gestionan a través del Relation Manager correspondiente. Use las acciones de la tabla para crear, editar o eliminar registros de esta etapa.')
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make('S3 Contract')
                            ->label('Suscripción del Contrato')
                            ->icon('heroicon-m-document-text')
                            ->badge(fn ($record) => $record?->s3Stage ? 'Completado' : 'Pendiente')
                            ->badgeColor(fn ($record) => $record?->s3Stage ? 'success' : 'gray')
                            ->schema([
                                Forms\Components\Placeholder::make('s3_info')
                                    ->label('')
                                    ->content('Los datos de la etapa S3 se gestionan a través del Relation Manager correspondiente. Use las acciones de la tabla para crear, editar o eliminar registros de esta etapa.')
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make('S4 Execution')
                            ->label('Tiempo de Ejecución')
                            ->icon('heroicon-m-clock')
                            ->badge(fn ($record) => $record?->s4Stage ? 'Completado' : 'Pendiente')
                            ->badgeColor(fn ($record) => $record?->s4Stage ? 'success' : 'gray')
                            ->schema([
                                Forms\Components\Placeholder::make('s4_info')
                                    ->label('')
                                    ->content('Los datos de la etapa S4 se gestionan a través del Relation Manager correspondiente. Use las acciones de la tabla para crear, editar o eliminar registros de esta etapa.')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull()
                    ->activeTab(1), // Tab "S1 Preparatory" por defecto
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
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            TenderStageS1RelationManager::class,
            TenderStageS2RelationManager::class,
            TenderStageS3RelationManager::class,
            TenderStageS4RelationManager::class,
        ];
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