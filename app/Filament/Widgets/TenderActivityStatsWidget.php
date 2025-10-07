<?php

namespace App\Filament\Widgets;

use App\Models\Tender;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TenderActivityStatsWidget extends BaseWidget
{
    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = 'full';

    public function getDescription(): ?string
    {
        return 'Métricas de actividad y productividad';
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

        // Obtener estadísticas de actividad
        $today = now()->startOfDay();
        $thisWeek = now()->startOfWeek();
        $thisMonth = now()->startOfMonth();

        $todayActivity = $query->clone()
            ->where(function ($q) use ($today) {
                $q->whereDate('created_at', $today)
                  ->orWhereDate('updated_at', $today);
            })
            ->count();

        $thisWeekActivity = $query->clone()
            ->where(function ($q) use ($thisWeek) {
                $q->where('created_at', '>=', $thisWeek)
                  ->orWhere('updated_at', '>=', $thisWeek);
            })
            ->count();

        $thisMonthActivity = $query->clone()
            ->where(function ($q) use ($thisMonth) {
                $q->where('created_at', '>=', $thisMonth)
                  ->orWhere('updated_at', '>=', $thisMonth);
            })
            ->count();

        // Obtener usuario más activo del mes
        $mostActiveUser = $this->getMostActiveUser($thisMonth);

        return [
            Stat::make('Actividad Hoy', $todayActivity)
                ->description('Procedimientos creados/modificados hoy')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('success')
                ->chart($this->generateActivityChart($todayActivity)),

            Stat::make('Esta Semana', $thisWeekActivity)
                ->description('Actividad de los últimos 7 días')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('info')
                ->chart($this->generateActivityChart($thisWeekActivity)),

            Stat::make('Este Mes', $thisMonthActivity)
                ->description('Actividad del mes actual')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('warning')
                ->chart($this->generateActivityChart($thisMonthActivity)),

            Stat::make('Usuario Más Activo', $mostActiveUser['name'])
                ->description("{$mostActiveUser['count']} procedimientos este mes")
                ->descriptionIcon('heroicon-m-user-circle')
                ->color('primary')
                ->chart($this->generateActivityChart($mostActiveUser['count'])),
        ];
    }

    /**
     * Obtiene el usuario más activo del período
     */
    private function getMostActiveUser($since): array
    {
        $user = auth()->user();
        
        $query = Tender::query();
        if (!$user || !$user->roles->contains('name', 'SuperAdmin')) {
            $query->where('created_by', $user?->id);
        }

        $mostActive = $query->clone()
            ->where('created_at', '>=', $since)
            ->selectRaw('created_by, COUNT(*) as count')
            ->groupBy('created_by')
            ->orderBy('count', 'desc')
            ->with('creator')
            ->first();

        if ($mostActive && $mostActive->creator) {
            return [
                'name' => $mostActive->creator->name,
                'count' => $mostActive->count,
            ];
        }

        return [
            'name' => 'Sin actividad',
            'count' => 0,
        ];
    }

    /**
     * Genera datos de gráfico basados en la actividad
     */
    private function generateActivityChart(int $count): array
    {
        if ($count === 0) {
            return [0, 0, 0, 0, 0, 0, 0, 0];
        }
        
        // Generar datos que reflejen la actividad actual
        $base = max(1, $count);
        return [
            $base - 1, $base, $base + 1, $base, $base - 1, $base, $base + 1, $base
        ];
    }
}
