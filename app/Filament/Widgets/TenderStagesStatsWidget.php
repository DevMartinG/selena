<?php

namespace App\Filament\Widgets;

use App\Models\Tender;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TenderStagesStatsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public function getDescription(): ?string
    {
        return 'Estadísticas detalladas del progreso de tus procedimientos';
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        
        // Construir query base con filtros por usuario
        $query = Tender::query();
        
        // Aplicar filtro por usuario (SuperAdmin ve todo, otros solo sus tenders)
        if (!$user || !$user->roles->contains('name', 'SuperAdmin')) {
            $query->where('created_by', $user?->id);
        }

        // Obtener estadísticas más relevantes
        $total = $query->count();
        $notStarted = $query->clone()->byLastStage('No iniciado')->count();
        $inProgress = $query->clone()
            ->where(function ($q) {
                $q->byLastStage('S1')
                  ->orWhere->byLastStage('S2')
                  ->orWhere->byLastStage('S3');
            })
            ->count();
        $completed = $query->clone()->byLastStage('S4')->count();
        
        // Calcular valor total de los procedimientos
        $totalValue = $query->clone()
            ->whereNotNull('estimated_referenced_value')
            ->where('estimated_referenced_value', '>', 0)
            ->sum('estimated_referenced_value');
        
        // Contar procedimientos con valor
        $tendersWithValue = $query->clone()
            ->whereNotNull('estimated_referenced_value')
            ->where('estimated_referenced_value', '>', 0)
            ->count();
        
        // Calcular porcentajes
        $completionRate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;
        $progressRate = $total > 0 ? round(($inProgress / $total) * 100, 1) : 0;

        return [
            Stat::make('Total Procedimientos', $total)
                ->description('Todos tus procedimientos')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary')
                ->chart([7, 3, 4, 5, 6, 3, 5, 3]),

            Stat::make('Valor Total', 'S/ ' . number_format($totalValue, 2))
                ->description("{$tendersWithValue} procedimientos con valor")
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success')
                ->chart([2, 3, 4, 5, 6, 7, 8, 9]),

            Stat::make('En Progreso', $inProgress)
                ->description("{$progressRate}% del total")
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->chart([3, 4, 2, 5, 3, 4, 2, 3]),

            Stat::make('Completados', $completed)
                ->description("{$completionRate}% del total")
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->chart([2, 3, 4, 5, 6, 7, 8, 9]),
        ];
    }
}
