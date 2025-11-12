<?php

namespace App\Filament\Resources\TenderResource\Pages;

use App\Filament\Resources\TenderResource;
use App\Filament\Resources\TenderResource\Components\Shared\StageValidationHelper;
use App\Traits\TenderStageInitializer;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\MaxWidth;
use Filament\Notifications\Notification;

class EditTender extends EditRecord
{
    use TenderStageInitializer;

    protected static string $resource = TenderResource::class;

    /* protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    } */

    protected function getHeaderActions(): array
    {
        return [
            // ========================================================================
            // 🎯 ACCIONES PARA CREAR STAGES SECUENCIALMENTE
            // ========================================================================
            // Estas acciones permiten crear las etapas del proceso de selección
            // en orden secuencial (S1 → S2 → S3 → S4). Cada acción:
            // 1. Verifica que la etapa anterior existe (excepto S1)
            // 2. Crea la etapa usando TenderStageInitializer
            // 3. Redirige para refrescar el formulario y mostrar los campos

            Action::make('create_s1')
                ->label('Crear Etapa 1')
                ->icon('heroicon-m-plus-circle')
                ->color('success')
                ->visible(fn () => ! $this->record->s1Stage) // Solo visible si S1 no existe
                ->action(function () {
                    $this->initializeStage('S1'); // Usa TenderStageInitializer trait
                    // Redirigir para refrescar el formulario y mostrar campos S1
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),

            Action::make('create_s2')
                ->label('Crear Etapa 2')
                ->icon('heroicon-m-plus-circle')
                ->color('success')
                ->visible(fn () => 
                    ! $this->record->s2Stage && 
                    $this->record->s1Stage && 
                    StageValidationHelper::canCreateNextStage($this->record, 'S1')
                )
                ->tooltip(fn () => StageValidationHelper::getCreationTooltip($this->record, 'S2'))
                ->action(function () {
                    // Verificar nuevamente antes de crear
                    if (!StageValidationHelper::canCreateNextStage($this->record, 'S1')) {
                        Notification::make()
                            ->title('Etapa S1 incompleta')
                            ->body(StageValidationHelper::getErrorMessage($this->record, 'S2'))
                            ->warning()
                            ->send();
                        return;
                    }
                    
                    $this->initializeStage('S2');
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),

            Action::make('create_s3')
                ->label('Crear Etapa 3')
                ->icon('heroicon-m-plus-circle')
                ->color('success')
                ->visible(fn () => 
                    ! $this->record->s3Stage && 
                    $this->record->s2Stage && 
                    StageValidationHelper::canCreateNextStage($this->record, 'S2')
                )
                ->tooltip(fn () => StageValidationHelper::getCreationTooltip($this->record, 'S3'))
                ->action(function () {
                    // Verificar nuevamente antes de crear
                    if (!StageValidationHelper::canCreateNextStage($this->record, 'S2')) {
                        Notification::make()
                            ->title('Etapa S2 incompleta')
                            ->body(StageValidationHelper::getErrorMessage($this->record, 'S3'))
                            ->warning()
                            ->send();
                        return;
                    }
                    
                    $this->initializeStage('S3');
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),

            Action::make('create_s4')
                ->label('Crear Etapa 4')
                ->icon('heroicon-m-plus-circle')
                ->color('success')
                ->visible(fn () => 
                    ! $this->record->s4Stage && 
                    $this->record->s3Stage && 
                    StageValidationHelper::canCreateNextStage($this->record, 'S3')
                )
                ->tooltip(fn () => StageValidationHelper::getCreationTooltip($this->record, 'S4'))
                ->action(function () {
                    // Verificar nuevamente antes de crear
                    if (!StageValidationHelper::canCreateNextStage($this->record, 'S3')) {
                        Notification::make()
                            ->title('Etapa S3 incompleta')
                            ->body(StageValidationHelper::getErrorMessage($this->record, 'S4'))
                            ->warning()
                            ->send();
                        return;
                    }
                    
                    $this->initializeStage('S4');
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),

            Actions\DeleteAction::make()
                ->label('Eliminar Procedimiento')
                ->icon('heroicon-m-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Eliminar Procedimiento')
                ->modalDescription('¿Está seguro de que desea eliminar este procedimiento? Esta acción eliminará también todas las etapas asociadas y no se puede deshacer.')
                ->modalSubmitActionLabel('Sí, eliminar')
                ->modalCancelActionLabel('Cancelar'),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Guardar Cambios')
                ->icon('heroicon-m-check')
                ->color('success'),
            $this->getCancelFormAction()
                ->label('Ir a Proc. Selección')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        // return MaxWidth::ScreenTwoExtraLarge;
        return MaxWidth::ScreenExtraLarge;
    }

    // ========================================================================
    // 🎯 MÉTODOS PARA MANEJAR DATOS DE STAGES EN FILAMENT
    // ========================================================================
    // Estos métodos aseguran que Filament cargue correctamente los datos
    // de stages cuando se abre el formulario de edición

    /**
     * Mutate form data before filling the form
     * Este método se ejecuta cuando Filament carga los datos del modelo
     * para mostrarlos en el formulario
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Cargar datos de stages usando los accessors
        $data['s1Stage'] = $this->record->s1Stage;
        $data['s2Stage'] = $this->record->s2Stage;
        $data['s3Stage'] = $this->record->s3Stage;
        $data['s4Stage'] = $this->record->s4Stage;

        return $data;
    }

    /**
     * Mutate form data before saving
     * Este método se ejecuta antes de guardar los datos del formulario
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Los mutators del modelo Tender ya manejan el guardado de stages
        // No necesitamos hacer nada especial aquí
        return $data;
    }
}
