<?php

namespace App\Filament\Resources\TenderStageS2SelectionProcessResource\Pages;

use App\Filament\Resources\TenderStageS2SelectionProcessResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTenderStageS2SelectionProcess extends EditRecord
{
    protected static string $resource = TenderStageS2SelectionProcessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
