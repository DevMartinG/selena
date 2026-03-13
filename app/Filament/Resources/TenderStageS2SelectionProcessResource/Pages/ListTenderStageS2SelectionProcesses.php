<?php

namespace App\Filament\Resources\TenderStageS2SelectionProcessResource\Pages;

use App\Filament\Resources\TenderStageS2SelectionProcessResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;


class ListTenderStageS2SelectionProcesses extends ListRecords
{
    protected static string $resource = TenderStageS2SelectionProcessResource::class;

    // protected function getHeaderActions(): array
    // {
    //     return [
    //         Actions\CreateAction::make(),
    //     ];
    // }

    public function getTitle(): string
    {
        $activeTab = $this->activeTab;

        if (empty($activeTab) || $activeTab === 'all') {
            return 'Seguimiento de Procedimientos de Selección - Todos';
        }

        return "Seguimiento de Procedimientos de Selección - {$activeTab}";
    }


    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return 'full';
    }

}
