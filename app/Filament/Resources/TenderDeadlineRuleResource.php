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
                        Forms\Components\Select::make('stage_type')
                            ->label('Etapa')
                            ->options(TenderDeadlineRule::getStageOptions())
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                // Limpiar campos cuando cambia la etapa
                                $set('from_field', null);
                                $set('to_field', null);
                            }),

                        Forms\Components\Select::make('from_field')
                            ->label('Campo Origen')
                            ->options(function (Forms\Get $get) {
                                $stage = $get('stage_type');
                                return $stage ? TenderDeadlineRule::getFieldOptionsByStage($stage) : [];
                            })
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                // Limpiar campo destino cuando cambia el origen
                                $set('to_field', null);
                            }),

                        Forms\Components\Select::make('to_field')
                            ->label('Campo Destino')
                            ->options(function (Forms\Get $get) {
                                $stage = $get('stage_type');
                                $fromField = $get('from_field');
                                $options = $stage ? TenderDeadlineRule::getFieldOptionsByStage($stage) : [];
                                
                                // Excluir el campo origen de las opciones destino
                                if ($fromField && isset($options[$fromField])) {
                                    unset($options[$fromField]);
                                }
                                
                                return $options;
                            })
                            ->required(),

                        Forms\Components\TextInput::make('legal_days')
                            ->label('Días Hábiles Permitidos')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(365)
                            ->required()
                            ->suffix('días'),

                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->placeholder('Descripción opcional de la regla...'),
                    ])->columns(2),

                Forms\Components\Section::make('Configuración')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Regla Activa')
                            ->default(true)
                            ->helperText('Si está desactivada, la regla no se aplicará'),

                        Forms\Components\Toggle::make('is_mandatory')
                            ->label('Regla Obligatoria')
                            ->default(true)
                            ->helperText('Si es obligatoria, debe cumplirse estrictamente'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('stage_type')
                    ->label('Etapa')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'S1' => 'info',
                        'S2' => 'success',
                        'S3' => 'warning',
                        'S4' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('from_field')
                    ->label('Campo Origen')
                    ->formatStateUsing(function (string $state, TenderDeadlineRule $record) {
                        $options = TenderDeadlineRule::getFieldOptionsByStage($record->stage_type);
                        return $options[$state] ?? $state;
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('to_field')
                    ->label('Campo Destino')
                    ->formatStateUsing(function (string $state, TenderDeadlineRule $record) {
                        $options = TenderDeadlineRule::getFieldOptionsByStage($record->stage_type);
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

                Tables\Columns\IconColumn::make('is_mandatory')
                    ->label('Obligatoria')
                    ->boolean()
                    ->sortable(),

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
                Tables\Filters\SelectFilter::make('stage_type')
                    ->label('Etapa')
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
                        $record->update(['is_active' => !$record->is_active]);
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
            ->defaultSort('stage_type', 'asc');
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
