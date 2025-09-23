<?php

namespace App\Filament\Resources\TenderDeadlineRuleResource\Pages;

use App\Filament\Resources\TenderDeadlineRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTenderDeadlineRules extends ListRecords
{
    protected static string $resource = TenderDeadlineRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
