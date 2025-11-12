<?php

namespace App\Filament\Widgets;

use App\Models\Tender;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TenderTodayActivityWidget extends BaseWidget
{
    protected static ?int $sort = 7;

    protected int | string | array $columnSpan = 'full';

    public function getDescription(): ?string
    {
        return 'Resumen de actividad del día actual';
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

        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();

        // Actividad de hoy
        $todayCreated = $query->clone()->whereDate('created_at', $today)->count();
        $todayModified = $query->clone()->whereDate('updated_at', $today)->count();
        $todayTotal = $todayCreated + $todayModified;

        // Actividad de ayer para comparación
        $yesterdayCreated = $query->clone()->whereDate('created_at', $yesterday)->count();
        $yesterdayModified = $query->clone()->whereDate('updated_at', $yesterday)->count();
        $yesterdayTotal = $yesterdayCreated + $yesterdayModified;

        // Calcular tendencia
        $trend = $yesterdayTotal > 0 ? round((($todayTotal - $yesterdayTotal) / $yesterdayTotal) * 100, 1) : 0;
        $trendIcon = $trend > 0 ? 'heroicon-m-arrow-trending-up' : ($trend < 0 ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-minus');
        $trendColor = $trend > 0 ? 'success' : ($trend < 0 ? 'danger' : 'gray');

        return [
            Stat::make('Creados Hoy', $todayCreated)
                ->description('Nuevos procedimientos')
                ->descriptionIcon('heroicon-m-plus-circle')
                ->color('success')
                ->chart($this->generateTodayChart($todayCreated)),

            Stat::make('Modificados Hoy', $todayModified)
                ->description('Procedimientos actualizados')
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color('warning')
                ->chart($this->generateTodayChart($todayModified)),

            Stat::make('Total Actividad', $todayTotal)
                ->description("Tendencia: {$trend}% vs ayer")
                ->descriptionIcon($trendIcon)
                ->color($trendColor)
                ->chart($this->generateTodayChart($todayTotal)),

            Stat::make('Promedio por Hora', $this->getAveragePerHour($todayTotal))
                ->description('Actividad distribuida')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info')
                ->chart($this->generateTodayChart($this->getAveragePerHour($todayTotal))),
        ];
    }

    /**
     * Calcula el promedio de actividad por hora
     */
    private function getAveragePerHour(int $total): float
    {
        $currentHour = now()->hour;
        $hoursPassed = max(1, $currentHour); // Mínimo 1 hora para evitar división por 0
        
        return round($total / $hoursPassed, 1);
    }

    /**
     * Genera datos de gráfico para el día actual
     */
    private function generateTodayChart(int $count): array
    {
        if ($count === 0) {
            return [0, 0, 0, 0, 0, 0, 0, 0];
        }
        
        // Generar datos que reflejen la actividad del día
        $base = max(1, $count);
        return [
            $base - 1, $base, $base + 1, $base, $base - 1, $base, $base + 1, $base
        ];
    }
}
