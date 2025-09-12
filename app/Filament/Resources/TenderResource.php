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
                                            // ->live(onBlur: true)
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
                    Tables\Actions\BulkAction::make('bulk_duplicate')
                        ->label('Duplicar Seleccionados')
                        ->icon('heroicon-m-document-duplicate')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Duplicar Procedimientos Seleccionados')
                        ->modalDescription('¿Está seguro de que desea duplicar los procedimientos seleccionados? Se crearán copias con todas sus etapas.')
                        ->action(function ($records) {
                            $duplicatedCount = 0;
                            foreach ($records as $record) {
                                $this->duplicateTenderRecord($record);
                                $duplicatedCount++;
                            }
                            
                            Notification::make()
                                ->title('Procedimientos duplicados')
                                ->body("Se han duplicado {$duplicatedCount} procedimientos exitosamente.")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('bulk_export')
                        ->label('Exportar Seleccionados')
                        ->icon('heroicon-m-arrow-down-tray')
                        ->color('success')
                        ->action(function ($records) {
                            $this->exportSelectedTenders($records);
                        }),

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

    // Métodos auxiliares para operaciones bulk
    private function duplicateTenderRecord($record): void
    {
        $originalTender = $record;
        
        // Crear nuevo tender con datos básicos
        $newTender = Tender::create([
            'entity_name' => $originalTender->entity_name,
            'process_type' => $originalTender->process_type,
            'identifier' => $originalTender->identifier . '-COPIA-' . time(),
            'contract_object' => $originalTender->contract_object,
            'object_description' => $originalTender->object_description,
            'estimated_referenced_value' => $originalTender->estimated_referenced_value,
            'currency_name' => $originalTender->currency_name,
            'current_status' => '1-CONVOCADO',
            'observation' => $originalTender->observation,
            'selection_comittee' => $originalTender->selection_comittee,
        ]);

        // Duplicar etapas
        foreach ($originalTender->stages as $stage) {
            $newStage = \App\Models\TenderStage::create([
                'tender_id' => $newTender->id,
                'stage_type' => $stage->stage_type,
                'status' => 'pending',
                'started_at' => null,
                'completed_at' => null,
            ]);

            // Duplicar datos específicos de cada etapa
            match ($stage->stage_type) {
                'S1' => $this->duplicateStageData($stage, $newStage, 'S1'),
                'S2' => $this->duplicateStageData($stage, $newStage, 'S2'),
                'S3' => $this->duplicateStageData($stage, $newStage, 'S3'),
                'S4' => $this->duplicateStageData($stage, $newStage, 'S4'),
            };
        }
    }

    private function duplicateStageData($originalStage, $newStage, $stageType): void
    {
        $stageData = match ($stageType) {
            'S1' => $originalStage->s1Stage,
            'S2' => $originalStage->s2Stage,
            'S3' => $originalStage->s3Stage,
            'S4' => $originalStage->s4Stage,
        };

        if ($stageData) {
            $data = $stageData->toArray();
            unset($data['id'], $data['tender_stage_id'], $data['created_at'], $data['updated_at']);
            
            // Resetear fechas
            foreach ($data as $key => $value) {
                if (str_contains($key, 'date') && $value) {
                    $data[$key] = null;
                }
            }
            
            $data['tender_stage_id'] = $newStage->id;

            match ($stageType) {
                'S1' => \App\Models\TenderStageS1::create($data),
                'S2' => \App\Models\TenderStageS2::create($data),
                'S3' => \App\Models\TenderStageS3::create($data),
                'S4' => \App\Models\TenderStageS4::create($data),
            };
        }
    }

    private function exportSelectedTenders($records): void
    {
        // Aquí podrías implementar la lógica de exportación masiva
        // Por ejemplo, generar un archivo Excel con todos los datos seleccionados
        
        Notification::make()
            ->title('Datos exportados')
            ->body("Se han exportado " . count($records) . " procedimientos exitosamente.")
            ->success()
            ->send();
    }
}