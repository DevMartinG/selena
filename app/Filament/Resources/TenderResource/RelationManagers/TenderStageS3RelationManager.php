<?php

namespace App\Filament\Resources\TenderResource\RelationManagers;

use App\Models\TenderStage;
use App\Models\TenderStageS3;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TenderStageS3RelationManager extends RelationManager
{
    protected static string $relationship = 'stages';

    protected static ?string $title = 'S3 - Suscripción del Contrato';

    protected static ?string $modelLabel = 'Etapa S3';

    protected static ?string $pluralModelLabel = 'Etapas S3';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de la Etapa')
                    ->schema([
                        Forms\Components\Select::make('stage_type')
                            ->label('Tipo de Etapa')
                            ->options([
                                'S3' => 'S3 - Suscripción del Contrato',
                            ])
                            ->default('S3')
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

                Forms\Components\Section::make('Suscripción del Contrato')
                    ->schema([
                        Forms\Components\DatePicker::make('s3Stage.doc_sign_presentation_date')
                            ->label('Presentación de Documentos para Firma')
                            ->native(false),
                        Forms\Components\DatePicker::make('s3Stage.contract_signing')
                            ->label('Fecha de Suscripción del Contrato')
                            ->native(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Montos')
                    ->description('Formato: 1,234.56 (coma "," para miles y punto "." para decimales)')
                    ->schema([
                        Forms\Components\TextInput::make('s3Stage.awarded_amount')
                            ->label('Monto Adjudicado')
                            ->numeric()
                            ->prefix('S/')
                            ->step(0.01)
                            ->minValue(0),
                        Forms\Components\TextInput::make('s3Stage.adjusted_amount')
                            ->label('Monto Diferencial (VE/VF vs Oferta Económica)')
                            ->numeric()
                            ->prefix('S/')
                            ->step(0.01)
                            ->minValue(0),
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
                Tables\Columns\TextColumn::make('s3Stage.contract_signing')
                    ->label('Firma del Contrato')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('s3Stage.awarded_amount')
                    ->label('Monto Adjudicado')
                    ->money('PEN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('s3Stage.adjusted_amount')
                    ->label('Monto Diferencial')
                    ->money('PEN')
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
                    ->label('Crear Etapa S3')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['stage_type'] = 'S3';
                        return $data;
                    })
                    ->after(function ($record) {
                        // Crear el registro específico de S3
                        TenderStageS3::create([
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
            ->modifyQueryUsing(fn (Builder $query) => $query->where('stage_type', 'S3'));
    }
}
