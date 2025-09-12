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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
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

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::ScreenTwoExtraLarge;
    }
}
