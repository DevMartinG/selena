<?php

namespace App\Filament\Widgets;

use App\Models\Tender;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class TenderStagesChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Distribución por Etapas';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $maxHeight = '300px';

    public function getDescription(): ?string
    {
        return 'Distribución visual de procedimientos por etapa alcanzada';
    }

    protected function getData(): array
    {
        $user = auth()->user();
        
        // Construir query base con filtros por usuario
        $query = Tender::query();
        
        // Aplicar filtro por usuario (SuperAdmin ve todo, otros solo sus tenders)
        if (!$user || !$user->roles->contains('name', 'SuperAdmin')) {
            $query->where('created_by', $user?->id);
        }

        // Obtener conteos por etapa en el orden correcto
        $stagesData = [
            'No iniciado' => $query->clone()->byLastStage('No iniciado')->count(),
            'S1' => $query->clone()->byLastStage('S1')->count(),
            'S2' => $query->clone()->byLastStage('S2')->count(),
            'S3' => $query->clone()->byLastStage('S3')->count(),
            'S4' => $query->clone()->byLastStage('S4')->count(),
        ];

        // Calcular total para porcentajes
        $total = array_sum($stagesData);

        // Preparar datos para el gráfico
        $labels = [];
        $data = [];
        $colors = [];
        $backgroundColors = [];

        foreach ($stagesData as $stage => $count) {
            if ($count > 0) {
                $labels[] = $this->getStageLabel($stage);
                $data[] = $count;
                $colors[] = $this->getStageColor($stage);
                $backgroundColors[] = $this->getStageBackgroundColor($stage);
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Procedimientos por Etapa',
                    'data' => $data,
                    'backgroundColor' => $backgroundColors,
                    'borderColor' => $colors,
                    'borderWidth' => 2,
                    'hoverOffset' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
                'x' => [
                    'ticks' => [
                        'maxRotation' => 0,
                        'minRotation' => 0,
                    ],
                ],
            ],
            'elements' => [
                'bar' => [
                    'borderRadius' => 6,
                    'borderSkipped' => false,
                ],
            ],
        ];
    }

    /**
     * 🎨 Obtiene el label descriptivo para cada etapa
     */
    private function getStageLabel(string $stage): string
    {
        return match ($stage) {
            'S1' => 'E1 - Act. Prep.',
            'S2' => 'E2 - Proc. Selección',
            'S3' => 'E3 - Susc. Contrato',
            'S4' => 'E4 - Ejecución',
            'No iniciado' => 'No Iniciado',
            default => $stage,
        };
    }

    /**
     * 🎨 Obtiene el color para cada etapa
     */
    private function getStageColor(string $stage): string
    {
        return match ($stage) {
            'S1' => '#3B82F6', // Azul - Preparatorias
            'S2' => '#F59E0B', // Amarillo - Selección
            'S3' => '#10B981', // Verde - Contrato
            'S4' => '#8B5CF6', // Púrpura - Ejecución
            'No iniciado' => '#6B7280', // Gris - No iniciado
            default => '#9CA3AF',
        };
    }

    /**
     * 🎨 Obtiene el color de fondo para cada etapa (para gráfico de barras)
     */
    private function getStageBackgroundColor(string $stage): string
    {
        return match ($stage) {
            'S1' => '#3B82F6', // Azul sólido
            'S2' => '#F59E0B', // Amarillo sólido
            'S3' => '#10B981', // Verde sólido
            'S4' => '#8B5CF6', // Púrpura sólido
            'No iniciado' => '#6B7280', // Gris sólido
            default => '#9CA3AF',
        };
    }

}
