<?php

namespace App\Filament\Resources\TenderResource\Pages;

use App\Filament\Resources\TenderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Models\Tender;
use Filament\Resources\Components\Tab;
use Filament\Support\Enums\MaxWidth;

class ListTenders extends ListRecords
{
    protected static string $resource = TenderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

     public function getTabs(): array
    {
        // Obtener todos los valores únicos de code_short_type
        $allTypes = Tender::query()
            ->select('code_short_type')
            ->distinct()
            ->pluck('code_short_type')
            ->map(fn ($type) => trim((string) $type))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $tabs = [];

        // Tab "Todos"
        $tabs['all'] = Tab::make()
            ->label('Todos')
            ->badge(Tender::count())
            ->modifyQueryUsing(fn ($query) => $query);

        // Tabs dinámicos por code_short_type
        foreach ($allTypes as $type) {
            $tabs[$type] = Tab::make()
                ->label($type)
                ->badge(Tender::where('code_short_type', $type)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('code_short_type', $type));
        }

        return $tabs;
    }

    public function getTitle(): string
    {
        $activeTab = $this->activeTab;

        if (empty($activeTab) || $activeTab === 'all') {
            return 'Procedimientos de Selección - Todos';
        }

        return "Procedimientos de Selección - {$activeTab}";
    }


    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return 'full';
    }
}
