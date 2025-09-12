<?php

namespace App\Filament\Resources\TenderResource\RelationManagers;

use App\Models\TenderStage;
use App\Models\TenderStageS1;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TenderStageS1RelationManager extends RelationManager
{
    protected static string $relationship = 'stages';

    protected static ?string $title = 'S1 - Actuaciones Preparatorias';

    protected static ?string $modelLabel = 'Etapa S1';

    protected static ?string $pluralModelLabel = 'Etapas S1';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de la Etapa')
                    ->schema([
                        Forms\Components\Select::make('stage_type')
                            ->label('Tipo de Etapa')
                            ->options([
                                'S1' => 'S1 - Actuaciones Preparatorias',
                            ])
                            ->default('S1')
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

                Forms\Components\Section::make('Datos Específicos de S1')
                    ->schema([
                        Forms\Components\TextInput::make('s1Stage.request_presentation_doc')
                            ->label('Documento de Presentación de Requerimiento')
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('s1Stage.request_presentation_date')
                            ->label('Fecha de Presentación de Requerimiento')
                            ->native(false),
                        Forms\Components\TextInput::make('s1Stage.market_indagation_doc')
                            ->label('Expediente de Indagación de Mercado')
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('s1Stage.market_indagation_date')
                            ->label('Fecha de Indagación de Mercado')
                            ->native(false),
                        Forms\Components\Toggle::make('s1Stage.with_certification')
                            ->label('Tiene Certificación')
                            ->default(true),
                        Forms\Components\DatePicker::make('s1Stage.certification_date')
                            ->label('Fecha de Certificación')
                            ->native(false)
                            ->visible(fn (Forms\Get $get) => $get('s1Stage.with_certification')),
                        Forms\Components\TextInput::make('s1Stage.no_certification_reason')
                            ->label('Motivo de No Certificación')
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get) => !$get('s1Stage.with_certification')),
                        Forms\Components\DatePicker::make('s1Stage.approval_expedient_date')
                            ->label('Fecha de Aprobación del Expediente')
                            ->native(false),
                        Forms\Components\DatePicker::make('s1Stage.selection_committee_date')
                            ->label('Fecha de Designación del Comité')
                            ->native(false),
                        Forms\Components\DatePicker::make('s1Stage.administrative_bases_date')
                            ->label('Fecha de Elaboración de Bases Administrativas')
                            ->native(false),
                        Forms\Components\DatePicker::make('s1Stage.approval_expedient_format_2')
                            ->label('Fecha de Aprobación Formato 2')
                            ->native(false),
                    ])
                    ->columns(2),
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
                Tables\Columns\TextColumn::make('s1Stage.request_presentation_date')
                    ->label('Presentación Requerimiento')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('s1Stage.certification_date')
                    ->label('Certificación')
                    ->date()
                    ->sortable(),
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
                    ->label('Crear Etapa S1')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['stage_type'] = 'S1';
                        return $data;
                    })
                    ->after(function ($record) {
                        // Crear el registro específico de S1
                        TenderStageS1::create([
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
            ->modifyQueryUsing(fn (Builder $query) => $query->where('stage_type', 'S1'));
    }
}
