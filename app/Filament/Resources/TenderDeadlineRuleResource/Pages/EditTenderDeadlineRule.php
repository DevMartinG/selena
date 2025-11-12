<?php

namespace App\Filament\Resources\TenderDeadlineRuleResource\Pages;

use App\Filament\Resources\TenderDeadlineRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTenderDeadlineRule extends EditRecord
{
    protected static string $resource = TenderDeadlineRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
