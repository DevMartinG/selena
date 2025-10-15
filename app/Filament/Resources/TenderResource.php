<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenderResource\Components\GeneralInfoTab;
use App\Filament\Resources\TenderResource\Components\S1PreparatoryTab;
use App\Filament\Resources\TenderResource\Components\S2SelectionTab;
use App\Filament\Resources\TenderResource\Components\S3ContractTab;
use App\Filament\Resources\TenderResource\Components\S4ExecutionTab;
use App\Filament\Resources\TenderResource\Pages;
use App\Models\Tender;
use Filament\Forms;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Spatie\Permission\Traits\HasRoles;

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
                            ->label(GeneralInfoTab::getTabConfig()['label'])
                            ->icon(GeneralInfoTab::getTabConfig()['icon'])
                            ->iconPosition(GeneralInfoTab::getTabConfig()['iconPosition'])
                            ->schema(GeneralInfoTab::getSchema()),

                        // ========================================================================
                        // 🎯 TAB S1 - ACTUACIONES PREPARATORIAS
                        // ========================================================================
                        // Este tab maneja la etapa S1 del proceso de selección.
                        // Los campos usan la sintaxis 's1Stage.campo' que es manejada automáticamente
                        // por los mutators/accessors del modelo Tender.
                        //
                        // FLUJO:
                        // 1. Usuario hace clic en "Crear Etapa 1" → TenderStageInitializer crea la etapa
                        // 2. Usuario llena campos → Mutators guardan automáticamente en tender_stage_s1_preparatory_actions
                        // 3. Usuario hace clic en "Guardar" → Accessors leen datos para mostrar en formulario
                        Tabs\Tab::make('S1 Preparatory')
                            ->label(S1PreparatoryTab::getTabConfig()['label'])
                            ->icon(S1PreparatoryTab::getTabConfig()['icon'])
                            ->badge(S1PreparatoryTab::getTabConfig()['badge'])
                            ->badgeColor(S1PreparatoryTab::getTabConfig()['badgeColor'])
                            ->schema(S1PreparatoryTab::getSchema()),

                        // ========================================================================
                        // 🎯 TAB S2 - PROCEDIMIENTO DE SELECCIÓN
                        // ========================================================================
                        // Este tab maneja la etapa S2 del proceso de selección.
                        // Campos: published_at, participants_registration, absolution_obs, etc.
                        // Los datos se guardan en tender_stage_s2_selection_process
                        Tabs\Tab::make('S2 Selection')
                            ->label(S2SelectionTab::getTabConfig()['label'])
                            ->icon(S2SelectionTab::getTabConfig()['icon'])
                            ->badge(S2SelectionTab::getTabConfig()['badge'])
                            ->badgeColor(S2SelectionTab::getTabConfig()['badgeColor'])
                            ->schema(S2SelectionTab::getSchema()),

                        // ========================================================================
                        // 🎯 TAB S3 - SUSCRIPCIÓN DEL CONTRATO
                        // ========================================================================
                        // Este tab maneja la etapa S3 del proceso de selección.
                        // Campos: contract_signing, awarded_amount, adjusted_amount, etc.
                        // Los datos se guardan en tender_stage_s3_contract_signing
                        Tabs\Tab::make('S3 Contract')
                            ->label(S3ContractTab::getTabConfig()['label'])
                            ->icon(S3ContractTab::getTabConfig()['icon'])
                            ->badge(S3ContractTab::getTabConfig()['badge'])
                            ->badgeColor(S3ContractTab::getTabConfig()['badgeColor'])
                            ->schema(S3ContractTab::getSchema()),

                        // ========================================================================
                        // 🎯 TAB S4 - TIEMPO DE EJECUCIÓN
                        // ========================================================================
                        // Este tab maneja la etapa S4 del proceso de selección.
                        // Campos: contract_details, contract_signing, contract_vigency_date
                        // Los datos se guardan en tender_stage_s4_execution_time
                        Tabs\Tab::make('S4 Execution')
                            ->label(S4ExecutionTab::getTabConfig()['label'])
                            ->icon(S4ExecutionTab::getTabConfig()['icon'])
                            ->badge(S4ExecutionTab::getTabConfig()['badge'])
                            ->badgeColor(S4ExecutionTab::getTabConfig()['badgeColor'])
                            ->schema(S4ExecutionTab::getSchema()),
                    ])
                    ->persistTab(false)
                    ->columnSpanFull()
                    ->activeTab(1), // Tab "Info. General" por defecto
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // ========================================================================
                // 🎯 COLUMNA COMPACTA: NOMENCLATURA + TIPO DE PROCESO
                // ========================================================================
                TextColumn::make('identifier')
                    ->label('Procedimiento')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(30)
                    ->description(function ($record) {
                        $processType = $record->process_type ?? 'Sin Clasificar';
                        
                        // Colores para el badge del tipo de proceso
                        $badgeColor = match ($processType) {
                            'Licitación Pública' => '#3B82F6', // blue-500
                            'Concurso Público' => '#10B981',    // emerald-500
                            'Adjudicación Directa' => '#F59E0B', // amber-500
                            'Adjudicación Simplificada' => '#8B5CF6', // violet-500
                            'Selección Simplificada' => '#6B7280', // gray-500
                            'Contratación Directa' => '#EF4444', // red-500
                            'Adjudicación de Menor Cuantía' => '#06B6D4', // cyan-500
                            default => '#6B7280', // gray-500
                        };
                        
                        return new HtmlString(
                            <<<HTML
                                <div style="
                                    display: inline-flex;
                                    align-items: center;
                                    padding: 0.125rem 0.5rem;
                                    background-color: {$badgeColor};
                                    color: white;
                                    border-radius: 0.375rem;
                                    font-size: 0.75rem;
                                    font-weight: 500;
                                    width: fit-content;
                                ">
                                    {$processType}
                                </div>
                            HTML
                        );
                    })
                    ->tooltip(function (TextColumn $column): ?string {
                        $record = $column->getRecord();
                        return "{$record->identifier}";
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

                TextColumn::make('tenderStatus.name')
                    ->label('Estado')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn ($record): string => match (true) {
                        ! $record->tenderStatus => 'danger', // ← ROJO para estados no válidos
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
                    ->formatStateUsing(fn ($record): string => ! $record->tenderStatus ? '⚠️ SIN ESTADO' : $record->tenderStatus->name
                    )
                    ->limit(20)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();

                        return strlen($state) > 20 ? $state : null;
                    }),

                TextColumn::make('stages_count')
                    ->label('Etapas')
                    ->counts('stages')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('creator.name')
                    ->label('Creado por')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-m-user')
                    ->toggleable(isToggledHiddenByDefault: true),

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
                    ->tooltip('Editar este procedimiento de selección')
                    ->color('primary')
                    ->size('lg')
                    ->slideOver() // ✅ Abrir en panel lateral
                    ->modalWidth('7xl') // ✅ Ancho amplio para el formulario complejo
                    // ->modalWidth('max-w-screen-xl')
                    ->modalHeading(fn ($record) => "Editar: {$record->identifier}")
                    ->authorize(fn ($record) => Gate::allows('update', $record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_status_update')
                        ->label('Actualizar Estado')
                        ->icon('heroicon-m-pencil-square')
                        ->color('info')
                        ->authorize(fn () => Gate::allows('update', Tender::class))
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
                                ->body("Se han actualizado {$updatedCount} procedimientos al estado: {$statusName}")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar Seleccionados')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Procedimientos Seleccionados')
                        ->modalDescription('¿Está seguro de que desea eliminar los procedimientos seleccionados? Esta acción eliminará también todas las etapas asociadas y no se puede deshacer.')
                        ->modalSubmitActionLabel('Sí, eliminar')
                        ->modalCancelActionLabel('Cancelar')
                        ->authorize(fn () => Gate::allows('delete', Tender::class)),
                ]),
            ])
            ->recordUrl(fn ($record) => null) // ✅ Deshabilitar navegación por defecto
            ->recordAction('edit') // ✅ Usar la acción edit existente
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        // SuperAdmin ve todos los Tenders
        $user = auth()->user();
        if ($user && $user->roles->contains('name', 'SuperAdmin')) {
            return $query;
        }
        
        // Otros usuarios solo ven sus propios Tenders
        return $query->where('created_by', auth()->id());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenders::route('/'),
            'create' => Pages\CreateTender::route('/create'),
            'edit' => Pages\EditTender::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', Tender::class);
    }

    public static function canCreate(): bool
    {
        return Gate::allows('create', Tender::class);
    }

    public static function canEdit($record): bool
    {
        return Gate::allows('update', $record);
    }

    public static function canDelete($record): bool
    {
        return Gate::allows('delete', $record);
    }

    public static function canForceDelete($record): bool
    {
        return Gate::allows('forceDelete', $record);
    }

    public static function canRestore($record): bool
    {
        return Gate::allows('restore', $record);
    }
}
