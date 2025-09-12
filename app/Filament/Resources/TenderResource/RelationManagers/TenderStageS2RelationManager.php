<?php

namespace App\Filament\Resources\TenderResource\RelationManagers;

use App\Models\TenderStage;
use App\Models\TenderStageS2;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TenderStageS2RelationManager extends RelationManager
{
    protected static string $relationship = 'stages';

    protected static ?string $title = 'S2 - Procedimiento de Selección';

    protected static ?string $modelLabel = 'Etapa S2';

    protected static ?string $pluralModelLabel = 'Etapas S2';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de la Etapa')
                    ->schema([
                        Forms\Components\Select::make('stage_type')
                            ->label('Tipo de Etapa')
                            ->options([
                                'S2' => 'S2 - Procedimiento de Selección',
                            ])
                            ->default('S2')
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

                Forms\Components\Section::make('Publicación y Registro')
                    ->schema([
                        Forms\Components\DatePicker::make('s2Stage.published_at')
                            ->label('Fecha de Publicación en SEACE')
                            ->native(false)
                            ->required(),
                        Forms\Components\DatePicker::make('s2Stage.participants_registration')
                            ->label('Registro de Participantes')
                            ->native(false),
                        Forms\Components\TextInput::make('s2Stage.restarted_from')
                            ->label('Reiniciado desde')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('s2Stage.cui_code')
                            ->label('Código CUI')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Proceso de Selección')
                    ->schema([
                        Forms\Components\DatePicker::make('s2Stage.absolution_obs')
                            ->label('Absolución de Consultas/Observaciones')
                            ->native(false),
                        Forms\Components\DatePicker::make('s2Stage.base_integration')
                            ->label('Integración de Bases')
                            ->native(false),
                        Forms\Components\DatePicker::make('s2Stage.offer_presentation')
                            ->label('Presentación de Ofertas')
                            ->native(false),
                        Forms\Components\DatePicker::make('s2Stage.offer_evaluation')
                            ->label('Evaluación de Propuestas')
                            ->native(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Adjudicación')
                    ->schema([
                        Forms\Components\DatePicker::make('s2Stage.award_granted_at')
                            ->label('Otorgamiento de Buena Pro')
                            ->native(false),
                        Forms\Components\DatePicker::make('s2Stage.award_consent')
                            ->label('Consentimiento de Buena Pro')
                            ->native(false),
                        Forms\Components\DatePicker::make('s2Stage.appeal_date')
                            ->label('Fecha de Apelación')
                            ->native(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Datos del Adjudicado')
                    ->schema([
                        Forms\Components\TextInput::make('s2Stage.awarded_tax_id')
                            ->label('RUC del Adjudicado')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('s2Stage.awarded_legal_name')
                            ->label('Razón Social del Adjudicado')
                            ->rows(3),
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
                Tables\Columns\TextColumn::make('s2Stage.published_at')
                    ->label('Publicado')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('s2Stage.award_granted_at')
                    ->label('Adjudicado')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('s2Stage.awarded_legal_name')
                    ->label('Adjudicado')
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
                    ->label('Crear Etapa S2')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['stage_type'] = 'S2';
                        return $data;
                    })
                    ->after(function ($record) {
                        // Crear el registro específico de S2
                        TenderStageS2::create([
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
            ->modifyQueryUsing(fn (Builder $query) => $query->where('stage_type', 'S2'));
    }
}
