<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class SoportePage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-m-lifebuoy';
    
    protected static string $view = 'filament.pages.soporte';
    
    protected static ?string $title = 'Soporte Técnico';
    
    protected static ?string $navigationLabel = 'Soporte';
    
    protected static ?int $navigationSort = 100;

}
