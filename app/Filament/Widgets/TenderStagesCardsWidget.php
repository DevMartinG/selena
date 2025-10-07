<?php

namespace App\Filament\Widgets;

use App\Models\Tender;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TenderStagesCardsWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    public function getDescription(): ?string
    {
        return 'Vista de tarjetas por etapa - Información rápida y visual';
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

        // Obtener conteos por etapa en el orden correcto
        $notStartedCount = $query->clone()->byLastStage('No iniciado')->count();
        $s1Count = $query->clone()->byLastStage('S1')->count();
        $s2Count = $query->clone()->byLastStage('S2')->count();
        $s3Count = $query->clone()->byLastStage('S3')->count();
        $s4Count = $query->clone()->byLastStage('S4')->count();

        return [
            Stat::make('No Iniciado', $notStartedCount)
                ->description('Pendientes de comenzar')
                ->descriptionIcon('heroicon-m-pause-circle')
                ->color('gray')
                ->chart($this->generateChartData($notStartedCount)),

            Stat::make('E1 - Preparatorias', $s1Count)
                ->description('Actuaciones Preparatorias')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('info')
                ->chart($this->generateChartData($s1Count)),

            Stat::make('E2 - Selección', $s2Count)
                ->description('Procedimiento de Selección')
                ->descriptionIcon('heroicon-m-users')
                ->color('warning')
                ->chart($this->generateChartData($s2Count)),

            Stat::make('E3 - Contrato', $s3Count)
                ->description('Suscripción del Contrato')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('success')
                ->chart($this->generateChartData($s3Count)),

            Stat::make('E4 - Ejecución', $s4Count)
                ->description('Tiempo de Ejecución')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('primary')
                ->chart($this->generateChartData($s4Count)),
        ];
    }

    /**
     * Genera datos de gráfico basados en el conteo
     */
    private function generateChartData(int $count): array
    {
        if ($count === 0) {
            return [0, 0, 0, 0, 0, 0, 0, 0];
        }
        
        // Generar datos que reflejen el conteo actual
        $base = max(1, $count);
        return [
            $base - 1, $base, $base + 1, $base, $base - 1, $base, $base + 1, $base
        ];
    }
}
