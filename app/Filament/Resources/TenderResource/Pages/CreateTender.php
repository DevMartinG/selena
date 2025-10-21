<?php

namespace App\Filament\Resources\TenderResource\Pages;

use App\Filament\Resources\TenderResource;
use App\Models\Tender;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\MaxWidth;

class CreateTender extends CreateRecord
{
    protected static string $resource = TenderResource::class;
    
    protected bool $isCreatingAnother = false;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Reutilizar lógica del modelo para estandarizar el identifier
        $normalized = Tender::normalizeIdentifier($data['identifier']);

        // Verificamos duplicado
        if (Tender::where('code_full', $normalized)->exists()) {
            Notification::make()
                ->title('Nomenclatura duplicada')
                ->body("Ya existe un procedimiento con una nomenclatura equivalente a <strong>{$data['identifier']}</strong>.")
                ->icon('heroicon-s-exclamation-triangle')
                ->warning()
                ->duration(5000)
                ->send();

            $this->halt(); // Detiene el guardado
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Obtener el identifier del procedimiento creado
        $identifier = $this->record->identifier ?? 'N/A';
        
        // Usar nuestra bandera para determinar qué notificación mostrar
        if ($this->isCreatingAnother) {
            Notification::make()
                ->title('Procedimiento creado exitosamente')
                ->body("El procedimiento <strong>{$identifier}</strong> ha sido creado exitosamente. Puede continuar creando otro procedimiento o editar el procedimiento <strong>{$identifier}</strong> desde la lista de procedimientos.")
                ->icon('heroicon-s-check-circle')
                ->color('success')
                ->duration(6000)
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Procedimiento creado exitosamente')
                ->body("El procedimiento <strong>{$identifier}</strong> ha sido creado exitosamente y puede gestionar la inicialización de cada una de sus etapas.")
                ->icon('heroicon-s-check-circle')
                ->color('success')
                ->duration(8000)
                ->success()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::ScreenTwoExtraLarge;
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Crear Procedimiento')
                ->icon('heroicon-m-plus')
                ->color('success')
                ->action(function () {
                    $this->isCreatingAnother = false;
                    $this->create();
                }),
            $this->getCreateAnotherFormAction()
                ->label('Crear y crear otro')
                ->icon('heroicon-m-plus-circle')
                ->color('primary')
                ->action(function () {
                    $this->isCreatingAnother = true;
                    $this->createAnother();
                }),
            $this->getCancelFormAction()
                ->label('Ir a Proc. Selección')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }
}
