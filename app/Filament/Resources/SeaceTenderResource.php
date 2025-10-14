<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SeaceTenderResource\Pages;
use App\Models\SeaceTender;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class SeaceTenderResource extends Resource
{
    protected static ?string $model = SeaceTender::class;

    protected static ?string $label = 'Datos SEACE';

    protected static ?string $pluralLabel = 'Datos SEACE';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 2;

    public static function getNavigationIcon(): string
    {
        return request()->routeIs('filament.admin.resources.seace-tenders.index') ? 'heroicon-s-document-text' : 'heroicon-o-document-text';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(5)
                    ->schema([
                        // ========================================================================
                        // 📊 PANEL IZQUIERDO: INFORMACIÓN PRINCIPAL (60% = 3/5)
                        // ========================================================================
                        Forms\Components\Fieldset::make('Información Principal')
                            ->schema([
                                Forms\Components\Grid::make(12)
                                    ->schema([
                                        // ========================================================================
                                        // 🏷️ IDENTIFICACIÓN DEL PROCESO
                                        // ========================================================================
                                        Forms\Components\TextInput::make('identifier')
                                            ->label('Nomenclatura')
                                            ->required()
                                            ->maxLength(255)
                                            ->autofocus()
                                            ->columnSpan(7)
                                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                // Validar nomenclatura duplicada
                                                $normalized = SeaceTender::normalizeIdentifier($state);

                                                $isDuplicate = SeaceTender::query()
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
                                            ->options(\App\Models\ProcessType::pluck('description_short_type', 'description_short_type'))
                                            ->required()
                                            ->columnSpan(5),

                                        // ========================================================================
                                        // 💰 INFORMACIÓN FINANCIERA
                                        // ========================================================================
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
                                            ->columnSpan(5),

                                        // ========================================================================
                                        // 📝 DESCRIPCIÓN DEL OBJETO
                                        // ========================================================================
                                        Forms\Components\Textarea::make('object_description')
                                            ->label('Descripción del Objeto')
                                            ->required()
                                            ->rows(4)
                                            ->columnSpanFull(),
                                    ]),
                            ])->columnSpan(3), // 60% del espacio

                        // ========================================================================
                        // 📊 PANEL DERECHO: ESTADO Y DATOS SEACE (40% = 2/5)
                        // ========================================================================
                        Forms\Components\Fieldset::make('Estado y Datos SEACE')
                            ->schema([
                                Forms\Components\Grid::make(12)
                                    ->schema([
                                        // ========================================================================
                                        // 🎯 ESTADO ACTUAL DEL PROCEDIMIENTO
                                        // ========================================================================
                                        Forms\Components\Select::make('tender_status_id')
                                            ->label('Estado Actual')
                                            ->options(\App\Models\TenderStatus::validForForm()->pluck('name', 'id'))
                                            ->columnSpanFull()
                                            ->required()
                                            ->placeholder('Seleccione el estado'),

                                        // ========================================================================
                                        // 📅 DATOS ESPECÍFICOS DE SEACE
                                        // ========================================================================
                                        Forms\Components\DatePicker::make('publish_date')
                                            ->label('Fecha de Publicación en SEACE')
                                            ->columnSpanFull()
                                            ->displayFormat('d/m/Y'),

                                        Forms\Components\TextInput::make('resumed_from')
                                            ->label('Procedimiento del cual se reanuda')
                                            ->maxLength(255)
                                            ->columnSpanFull()
                                            ->placeholder('Ej: LP-001-2024'),
                                    ]),
                            ])->columnSpan(2), // 40% del espacio
                    ]),
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

                TextColumn::make('publish_date')
                    ->label('Fecha SEACE')
                    ->date('d/m/Y')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('resumed_from')
                    ->label('Reanudado de')
                    ->searchable()
                    ->sortable()
                    ->limit(20)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 20 ? $state : null;
                    }),

                TextColumn::make('tenderStatus.name')
                    ->label('Estado')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn ($record): string => match (true) {
                        ! $record->tenderStatus => 'danger',
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
                    ->formatStateUsing(fn ($record): string => ! $record->tenderStatus ? '⚠️ SIN ESTADO' : $record->tenderStatus->name)
                    ->limit(20)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 20 ? $state : null;
                    }),

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
                    ->tooltip('Editar este procedimiento SEACE')
                    ->color('primary')
                    ->size('lg')
                    ->authorize(fn ($record) => Gate::allows('update', $record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_status_update')
                        ->label('Actualizar Estado')
                        ->icon('heroicon-m-pencil-square')
                        ->color('info')
                        ->authorize(fn () => Gate::allows('update', SeaceTender::class))
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
                                ->body("Se han actualizado {$updatedCount} procedimientos SEACE al estado: {$statusName}")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar Seleccionados')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Procedimientos SEACE Seleccionados')
                        ->modalDescription('¿Está seguro de que desea eliminar los procedimientos SEACE seleccionados? Esta acción no se puede deshacer.')
                        ->modalSubmitActionLabel('Sí, eliminar')
                        ->modalCancelActionLabel('Cancelar')
                        ->authorize(fn () => Gate::allows('delete', SeaceTender::class)),
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
            'index' => Pages\ListSeaceTenders::route('/'),
            'create' => Pages\CreateSeaceTender::route('/create'),
            'edit' => Pages\EditSeaceTender::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', SeaceTender::class);
    }

    public static function canCreate(): bool
    {
        return Gate::allows('create', SeaceTender::class);
    }

    public static function canEdit($record): bool
    {
        return Gate::allows('update', $record);
    }

    public static function canDelete($record): bool
    {
        return Gate::allows('delete', $record);
    }
}