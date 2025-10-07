<?php

namespace App\Filament\Resources\SeaceTenderResource\Pages;

use App\Filament\Resources\SeaceTenderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSeaceTender extends EditRecord
{
    protected static string $resource = SeaceTenderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
