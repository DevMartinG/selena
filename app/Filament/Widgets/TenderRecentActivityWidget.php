<?php

namespace App\Filament\Widgets;

use App\Models\Tender;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class TenderRecentActivityWidget extends BaseWidget
{
    protected static ?string $heading = 'Actividad Reciente';

    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    public function getDescription(): ?string
    {
        return 'Últimos procedimientos creados y modificados';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('identifier')
                    ->label('Procedimiento')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    ->limit(30)
                    ->tooltip(function (Tender $record): string {
                        return $record->identifier;
                    }),

                Tables\Columns\TextColumn::make('last_activity')
                    ->label('Última Actividad')
                    ->getStateUsing(function (Tender $record): string {
                        $updatedAt = $record->updated_at;
                        $createdAt = $record->created_at;
                        
                        // Si fue modificado después de ser creado, mostrar como "Modificado"
                        if ($updatedAt && $updatedAt->gt($createdAt)) {
                            return 'Modificado';
                        }
                        
                        return 'Creado';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Creado' => 'success',
                        'Modificado' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('last_activity_time')
                    ->label('Hace')
                    ->getStateUsing(function (Tender $record): string {
                        $updatedAt = $record->updated_at;
                        $createdAt = $record->created_at;
                        
                        // Usar la fecha más reciente
                        $lastActivity = $updatedAt && $updatedAt->gt($createdAt) ? $updatedAt : $createdAt;
                        
                        return $lastActivity->diffForHumans();
                    })
                    ->sortable()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('last_activity_user')
                    ->label('Usuario')
                    ->getStateUsing(function (Tender $record): string {
                        $updatedAt = $record->updated_at;
                        $createdAt = $record->created_at;
                        
                        // Si fue modificado después de ser creado, mostrar quien modificó
                        if ($updatedAt && $updatedAt->gt($createdAt) && $record->lastUpdater) {
                            return $record->lastUpdater->name;
                        }
                        
                        // Si no, mostrar quien creó
                        return $record->creator?->name ?? 'Sistema';
                    })
                    ->searchable()
                    ->color('info')
                    ->icon('heroicon-m-user'),

                Tables\Columns\TextColumn::make('current_stage')
                    ->label('Etapa Actual')
                    ->getStateUsing(function (Tender $record): string {
                        return $record->getLastStageName();
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Actuaciones Preparatorias' => 'info',
                        'Procedimiento de Selección' => 'warning',
                        'Suscripción del Contrato' => 'success',
                        'Tiempo de Ejecución' => 'primary',
                        'No iniciado' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('estimated_referenced_value')
                    ->label('Valor')
                    ->getStateUsing(function (Tender $record): string {
                        if (!$record->estimated_referenced_value || $record->estimated_referenced_value <= 0) {
                            return 'Sin valor';
                        }
                        
                        return 'S/ ' . number_format($record->estimated_referenced_value, 2);
                    })
                    ->color('success')
                    ->icon('heroicon-m-currency-dollar'),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label('Editar')
                    ->icon('heroicon-m-pencil')
                    ->color('warning')
                    ->url(fn (Tender $record): string => \App\Filament\Resources\TenderResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(false),
            ])
            ->defaultSort('updated_at', 'desc')
            ->paginated(false)
            ->poll('30s'); // Actualizar cada 30 segundos
    }

    protected function getTableQuery(): Builder
    {
        $user = auth()->user();
        
        // Construir query base con filtros por usuario
        $query = Tender::query();
        
        // Aplicar filtro por usuario (SuperAdmin ve todo, otros solo sus tenders)
        if (!$user || !$user->roles->contains('name', 'SuperAdmin')) {
            $query->where('created_by', $user?->id);
        }

        // Ordenar por actividad más reciente (updated_at o created_at)
        return $query
            ->with(['creator', 'lastUpdater'])
            ->orderByRaw('GREATEST(updated_at, created_at) DESC')
            ->limit(10);
    }

    /**
     * 📊 Obtiene estadísticas de actividad para mostrar en el widget
     */
    public function getActivityStats(): array
    {
        $user = auth()->user();
        
        $query = Tender::query();
        if (!$user || !$user->roles->contains('name', 'SuperAdmin')) {
            $query->where('created_by', $user?->id);
        }

        $today = now()->startOfDay();
        $thisWeek = now()->startOfWeek();

        return [
            'today' => $query->clone()
                ->where(function ($q) use ($today) {
                    $q->whereDate('created_at', $today)
                      ->orWhereDate('updated_at', $today);
                })
                ->count(),
            
            'this_week' => $query->clone()
                ->where(function ($q) use ($thisWeek) {
                    $q->where('created_at', '>=', $thisWeek)
                      ->orWhere('updated_at', '>=', $thisWeek);
                })
                ->count(),
            
            'total' => $query->clone()->count(),
        ];
    }
}
