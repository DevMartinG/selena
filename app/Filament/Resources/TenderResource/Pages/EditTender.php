<?php

namespace App\Filament\Resources\TenderResource\Pages;

use App\Filament\Resources\TenderResource;
use App\Traits\TenderStageInitializer;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\MaxWidth;

class EditTender extends EditRecord
{
    use TenderStageInitializer;

    protected static string $resource = TenderResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_s1')
                ->label('Crear Etapa 1')
                ->icon('heroicon-m-plus-circle')
                ->color('success')
                ->visible(fn () => ! $this->record->s1Stage)
                ->action(function () {
                    $this->initializeStage('S1');
                    // Redirigir para refrescar el formulario
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),

            Action::make('create_s2')
                ->label('Crear Etapa 2')
                ->icon('heroicon-m-plus-circle')
                ->color('success')
                ->visible(fn () => ! $this->record->s2Stage && $this->record->s1Stage)
                ->action(function () {
                    $this->initializeStage('S2');
                    // Redirigir para refrescar el formulario
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),

            Action::make('create_s3')
                ->label('Crear Etapa 3')
                ->icon('heroicon-m-plus-circle')
                ->color('success')
                ->visible(fn () => ! $this->record->s3Stage && $this->record->s2Stage)
                ->action(function () {
                    $this->initializeStage('S3');
                    // Redirigir para refrescar el formulario
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),

            Action::make('create_s4')
                ->label('Crear Etapa 4')
                ->icon('heroicon-m-plus-circle')
                ->color('success')
                ->visible(fn () => ! $this->record->s4Stage && $this->record->s3Stage)
                ->action(function () {
                    $this->initializeStage('S4');
                    // Redirigir para refrescar el formulario
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
                ->label('Cancelar')
                ->color('gray'),
        ];
    }



    public function getMaxContentWidth(): MaxWidth|string|null
    {
        // return MaxWidth::ScreenTwoExtraLarge;
        return MaxWidth::ScreenExtraLarge;
    }
}
