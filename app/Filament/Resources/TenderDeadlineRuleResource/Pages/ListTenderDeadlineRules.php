<?php

namespace App\Filament\Resources\TenderDeadlineRuleResource\Pages;

use App\Filament\Resources\TenderDeadlineRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;

class ListTenderDeadlineRules extends ListRecords
{
    protected static string $resource = TenderDeadlineRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        // return MaxWidth::ScreenTwoExtraLarge;
        return MaxWidth::Full;
    }
}
