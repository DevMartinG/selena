<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenderDeadlineRuleResource\Pages;
use App\Models\TenderDeadlineRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

use Illuminate\Support\HtmlString;


/**
 * 🎯 RESOURCE: TENDERDEADLINERULERESOURCE
 *
 * Este resource permite gestionar las reglas de plazos legales para tenders.
 * Solo SuperAdmin puede acceder a esta funcionalidad.
 *
 * FUNCIONALIDADES:
 * - CRUD completo de reglas de plazos
 * - Filtros por etapa y estado
 * - Acciones de activar/desactivar
 * - Validaciones de formulario
 * - Navegación organizada
 */
class TenderDeadlineRuleResource extends Resource
{
    protected static ?string $model = TenderDeadlineRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Reglas de Plazos';

    protected static ?string $modelLabel = 'Regla de Plazo';

    protected static ?string $pluralModelLabel = 'Reglas de Plazos';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de la Regla')
                    ->schema([

                        Forms\Components\Select::make('process_type_id')
                            ->label('Tipo de Proceso')
                            ->relationship('processType', 'description_short_type')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state, $set) => $set('from_field', null))
                            ->columnSpan(6),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true)
                            ->inline()
                            ->columnSpan(2),

                        Forms\Components\Select::make('from_stage')
                            ->label('Etapa Origen')
                            ->options(TenderDeadlineRule::getStageOptions())
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state, $set) => $set('from_field', null))
                            ->columnSpan(4),

                        Forms\Components\Select::make('from_field')
                            ->label('Campo Origen')
                            ->options(fn (Forms\Get $get) =>
                                $get('from_stage')
                                    ? TenderDeadlineRule::getFieldOptionsByStage($get('from_stage'))
                                    : []
                            )
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state, $set) => $set('to_field', null))
                            ->columnSpan(4),

                        Forms\Components\Select::make('to_stage')
                            ->label('Etapa Destino')
                            ->options(TenderDeadlineRule::getStageOptions())
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state, $set) => $set('to_field', null))
                            ->columnSpan(4),

                        Forms\Components\Select::make('to_field')
                            ->label('Campo Destino')
                            ->options(function (Forms\Get $get) {

                                $stage = $get('to_stage');
                                $fromStage = $get('from_stage');
                                $fromField = $get('from_field');

                                $options = $stage
                                    ? TenderDeadlineRule::getFieldOptionsByStage($stage)
                                    : [];

                                if ($fromStage === $stage && $fromField && isset($options[$fromField])) {
                                    unset($options[$fromField]);
                                }

                                return $options;
                            })
                            ->required()
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('legal_days')
                            ->label('Días Hábiles Permitidos')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(365)
                            ->required()
                            ->suffix('días')
                            ->columnSpan(2),

                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->placeholder('Descripción opcional de la regla...')
                            ->columnSpanFull(),

                    ])
                    ->columns(8),
                        
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // ========================================================================
                // 🎯 COLUMNA: ETAPA ORIGEN CON COLORES GLOBALES CONSISTENTES
                // ========================================================================
                // Aplica los mismos colores definidos en tender_colors.php para
                // mantener coherencia visual en toda la aplicación:
                // - S1 (Preparatorias): info (azul)
                // - S2 (Selección): warning (amarillo)
                // - S3 (Contrato): custom-orange (naranja)
                // - S4 (Ejecución): success (verde)



                Tables\Columns\TextColumn::make('processType.code_short_type')
                    ->label('Tipo de Proceso')
                    ->html()
                    ->searchable()
                    ->sortable()
                    ->weight('normal')
                    ->formatStateUsing(function ($state, $record) {

                        $description = e($state);

                        return new HtmlString(
                            <<<HTML
                            <div style="
                                display: -webkit-box;
                                -webkit-line-clamp: 3;
                                -webkit-box-orient: vertical;
                                overflow: hidden;
                                text-overflow: ellipsis;
                                white-space: normal;
                                line-height: 1.2;
                                font-size: 0.8rem;
                                max-width: 250px;
                            ">
                                {$description}
                            </div>
                            HTML
                        );
                    })
                    ->description(function ($record) {

                        $contractObject = e($record->processType?->description_short_type ?? 'Sin Clasificar');

                        return new HtmlString(
                            <<<HTML
                            <div style="
                                display: inline-flex;
                                align-items: center;
                                padding: 0.125rem 0.5rem;
                                background-color: #6B7280;
                                color: white;
                                border-radius: 0.375rem;
                                font-size: 0.7rem;
                                font-weight: 500;
                                width: fit-content;
                            ">
                                {$contractObject}
                            </div>
                            HTML
                        );
                    }),


                
                Tables\Columns\TextColumn::make('from_stage')
                    ->label('Etapa Origen')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'S1' => 'info',           // Azul - Preparatorias
                        'S2' => 'warning',        // Amarillo - Selección
                        'S3' => 'custom-orange',  // Naranja - Contrato
                        'S4' => 'success',        // Verde - Ejecución
                        default => 'gray',
                    })
                    ->formatStateUsing(function (string $state) {
                        $stageOptions = TenderDeadlineRule::getStageOptions();
                        return $stageOptions[$state] ?? $state;
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('from_field')
                    ->label('Campo Origen')
                    ->formatStateUsing(function (string $state, TenderDeadlineRule $record) {
                        $options = TenderDeadlineRule::getFieldOptionsByStage($record->from_stage);

                        return $options[$state] ?? $state;
                    })
                    ->searchable(),

                // ========================================================================
                // 🎯 COLUMNA: ETAPA DESTINO CON COLORES GLOBALES CONSISTENTES
                // ========================================================================
                // Aplica los mismos colores definidos en tender_colors.php para
                // mantener coherencia visual en toda la aplicación:
                // - S1 (Preparatorias): info (azul)
                // - S2 (Selección): warning (amarillo)
                // - S3 (Contrato): custom-orange (naranja)
                // - S4 (Ejecución): success (verde)
                
                Tables\Columns\TextColumn::make('to_stage')
                    ->label('Etapa Destino')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'S1' => 'info',           // Azul - Preparatorias
                        'S2' => 'warning',        // Amarillo - Selección
                        'S3' => 'custom-orange',  // Naranja - Contrato
                        'S4' => 'success',        // Verde - Ejecución
                        default => 'gray',
                    })
                    ->formatStateUsing(function (string $state) {
                        $stageOptions = TenderDeadlineRule::getStageOptions();
                        return $stageOptions[$state] ?? $state;
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('to_field')
                    ->label('Campo Destino')
                    ->formatStateUsing(function (string $state, TenderDeadlineRule $record) {
                        $options = TenderDeadlineRule::getFieldOptionsByStage($record->to_stage);

                        return $options[$state] ?? $state;
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('legal_days')
                    ->label('Días Permitidos')
                    ->suffix(' días')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),

                // Tables\Columns\IconColumn::make('is_mandatory')
                //     ->label('Obligatoria')
                //     ->boolean()
                //     ->sortable(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Creado por')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('from_stage')
                    ->label('Etapa Origen')
                    ->options(TenderDeadlineRule::getStageOptions()),

                Tables\Filters\SelectFilter::make('to_stage')
                    ->label('Etapa Destino')
                    ->options(TenderDeadlineRule::getStageOptions()),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todas las reglas')
                    ->trueLabel('Solo activas')
                    ->falseLabel('Solo inactivas'),

                Tables\Filters\TernaryFilter::make('is_mandatory')
                    ->label('Tipo')
                    ->placeholder('Todas las reglas')
                    ->trueLabel('Solo obligatorias')
                    ->falseLabel('Solo opcionales'),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn (TenderDeadlineRule $record): string => $record->is_active ? 'Desactivar' : 'Activar')
                    ->icon(fn (TenderDeadlineRule $record): string => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (TenderDeadlineRule $record): string => $record->is_active ? 'warning' : 'success')
                    ->action(function (TenderDeadlineRule $record) {
                        $record->update(['is_active' => ! $record->is_active]);
                    })
                    ->requiresConfirmation(),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('from_stage', 'asc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenderDeadlineRules::route('/'),
            'create' => Pages\CreateTenderDeadlineRule::route('/create'),
            'edit' => Pages\EditTenderDeadlineRule::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['creator']);
    }
}
