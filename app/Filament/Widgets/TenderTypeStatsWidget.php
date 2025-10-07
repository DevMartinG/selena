<?php

namespace App\Filament\Widgets;

use App\Models\Tender;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class TenderTypeStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';
    
    protected static ?int $sort = 1;

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    protected function getStats(): array
    {
        // Obtener query base filtrado por usuario
        $baseQuery = $this->getFilteredQuery();
        
        // Obtener conteos por tipo de proceso
        $typeStats = $baseQuery
            ->selectRaw('code_short_type, COUNT(*) as count')
            ->groupBy('code_short_type')
            ->orderByDesc('count')
            ->get();

        $stats = [];

        // Crear stat para cada tipo de proceso
        foreach ($typeStats as $typeStat) {
            $typeName = $typeStat->code_short_type ?: 'Sin Clasificar';
            $count = $typeStat->count;
            
            // Determinar color basado en el tipo
            $color = $this->getTypeColor($typeStat->code_short_type);
            
            // Determinar icono basado en el tipo
            $icon = $this->getTypeIcon($typeStat->code_short_type);
            
            $stats[] = Stat::make($typeName, $count)
                ->description('procedimientos')
                ->descriptionIcon('heroicon-m-document-text')
                ->color($color)
                ->icon($icon)
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-105 transition-transform',
                    'title' => "Ver todos los procedimientos de tipo: {$typeName}",
                ])
                ->url(route('filament.admin.resources.tenders.index', [
                    'tableFilters' => [
                        'code_short_type' => [
                            'value' => $typeStat->code_short_type
                        ]
                    ],
                    'activeTab' => $typeStat->code_short_type
                ]));
        }

        // Si no hay datos, mostrar mensaje informativo
        if (empty($stats)) {
            $stats[] = Stat::make('Sin Procedimientos', 0)
                ->description('Aún no has creado ningún procedimiento')
                ->descriptionIcon('heroicon-m-information-circle')
                ->color('gray')
                ->icon('heroicon-m-document-plus');
        }

        return $stats;
    }

    /**
     * Obtiene el query base filtrado según los permisos del usuario
     */
    private function getFilteredQuery()
    {
        $query = Tender::query();
        
        // SuperAdmin ve todos los Tenders
        $user = Auth::user();
        if ($user && $user->roles->contains('name', 'SuperAdmin')) {
            return $query;
        }
        
        // Otros usuarios solo ven sus propios Tenders
        return $query->where('created_by', Auth::id());
    }

    /**
     * Determina el color del stat basado en el tipo de proceso
     */
    private function getTypeColor(?string $type): string
    {
        return match ($type) {
            'COMPRE' => 'success',
            'AS' => 'primary',
            'LP' => 'warning',
            'SIN CODIGO' => 'gray',
            default => 'info',
        };
    }

    /**
     * Determina el icono del stat basado en el tipo de proceso
     */
    private function getTypeIcon(?string $type): string
    {
        return match ($type) {
            'COMPRE' => 'heroicon-m-shopping-cart',
            'AS' => 'heroicon-m-clipboard-document-list',
            'LP' => 'heroicon-m-megaphone',
            'SIN CODIGO' => 'heroicon-m-question-mark-circle',
            default => 'heroicon-m-document-text',
        };
    }

    /**
     * Obtiene el título del widget
     */
    public function getHeading(): string
    {
        return '📊 Mis Procedimientos por Tipo de Proceso';
    }

    /**
     * Obtiene la descripción del widget
     */
    public function getDescription(): ?string
    {
        return 'Distribución de tus procedimientos según el tipo de proceso';
    }

    /**
     * Obtiene el contenido adicional del widget (división visual)
     */
    protected function getFooter(): ?string
    {
        return '<div class="border-t border-gray-200 dark:border-gray-700 mt-4 pt-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 text-center">
                        💡 Haz clic en cualquier tarjeta para ver los procedimientos filtrados
                    </div>
                </div>';
    }
}
