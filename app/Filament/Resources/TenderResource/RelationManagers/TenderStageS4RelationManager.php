<?php

namespace App\Filament\Resources\TenderResource\RelationManagers;

use App\Models\TenderStage;
use App\Models\TenderStageS4;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TenderStageS4RelationManager extends RelationManager
{
    protected static string $relationship = 'stages';

    protected static ?string $title = 'S4 - Tiempo de Ejecución';

    protected static ?string $modelLabel = 'Etapa S4';

    protected static ?string $pluralModelLabel = 'Etapas S4';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de la Etapa')
                    ->schema([
                        Forms\Components\Select::make('stage_type')
                            ->label('Tipo de Etapa')
                            ->options([
                                'S4' => 'S4 - Tiempo de Ejecución',
                            ])
                            ->default('S4')
                            ->disabled()
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'pending' => 'Pendiente',
                                'in_progress' => 'En Progreso',
                                'completed' => 'Completado',
                                'cancelled' => 'Cancelado',
                            ])
                            ->default('pending')
                            ->required(),
                        Forms\Components\DateTimePicker::make('started_at')
                            ->label('Fecha de Inicio')
                            ->native(false),
                        Forms\Components\DateTimePicker::make('completed_at')
                            ->label('Fecha de Finalización')
                            ->native(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Tiempo de Ejecución')
                    ->schema([
                        Forms\Components\Textarea::make('s4Stage.contract_details')
                            ->label('Datos del Contrato - Tipo de Documento')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('s4Stage.contract_signing')
                            ->label('Suscripción de Contrato')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('s4Stage.contract_vigency_date')
                            ->label('Fecha de Vigencia de Contrato')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('stage_type')
            ->columns([
                Tables\Columns\TextColumn::make('stage_type')
                    ->label('Etapa')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'in_progress' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('started_at')
                    ->label('Iniciado')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Completado')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('s4Stage.contract_details')
                    ->label('Detalles del Contrato')
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),
                Tables\Columns\TextColumn::make('s4Stage.contract_vigency_date')
                    ->label('Vigencia')
                    ->limit(30)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'in_progress' => 'En Progreso',
                        'completed' => 'Completado',
                        'cancelled' => 'Cancelado',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Crear Etapa S4')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['stage_type'] = 'S4';
                        return $data;
                    })
                    ->after(function ($record) {
                        // Crear el registro específico de S4
                        TenderStageS4::create([
                            'tender_stage_id' => $record->id,
                        ]);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),
                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->where('stage_type', 'S4'));
    }
}
