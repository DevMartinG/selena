<?php

namespace App\Filament\Resources\TenderResource\Pages;

use App\Filament\Resources\TenderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTender extends CreateRecord
{
    protected static string $resource = TenderResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
