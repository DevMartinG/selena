<?php

namespace App\Filament\Resources\TenderResource\Components\Shared;

use App\Models\Tender;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\BadgeColumn;

/**
 * 🎨 HELPER PARA MEJORAR UI/UX DE LA TABLA DE TENDERS
 *
 * Este helper proporciona métodos para crear columnas mejoradas
 * con mejor información visual y UX para la tabla de tenders.
 */
class TenderTableUIHelper
{
    /**
     * 📊 Crea columna de progreso por etapas con indicador visual
     */
    public static function createStageProgressColumn(): TextColumn
    {
        return TextColumn::make('stage_progress')
            ->label('Progreso')
            ->getStateUsing(function (Tender $record): string {
                $stages = ['S1', 'S2', 'S3', 'S4'];
                $completedStages = 0;
                $totalStages = count($stages);
                
                foreach ($stages as $stage) {
                    if ($record->{"s{$stage[1]}Stage"}) {
                        $completedStages++;
                    }
                }
                
                return "{$completedStages}/{$totalStages}";
            })
            ->badge()
            ->color(function (Tender $record): string {
                $stages = ['S1', 'S2', 'S3', 'S4'];
                $completedStages = 0;
                
                foreach ($stages as $stage) {
                    if ($record->{"s{$stage[1]}Stage"}) {
                        $completedStages++;
                    }
                }
                
                return match($completedStages) {
                    0 => 'gray',
                    1 => 'danger',
                    2 => 'warning', 
                    3 => 'info',
                    4 => 'success',
                    default => 'gray'
                };
            })
            ->tooltip(function (Tender $record): string {
                $stages = ['S1', 'S2', 'S3', 'S4'];
                $stageNames = ['Preparatorias', 'Selección', 'Contrato', 'Ejecución'];
                $completedStages = [];
                
                foreach ($stages as $index => $stage) {
                    if ($record->{"s{$stage[1]}Stage"}) {
                        $completedStages[] = $stageNames[$index];
                    }
                }
                
                if (empty($completedStages)) {
                    return 'Ninguna etapa completada';
                }
                
                return 'Etapas completadas: ' . implode(', ', $completedStages);
            });
    }

    /**
     * 📅 Crea columna de última actividad con tiempo relativo
     */
    public static function createLastActivityColumn(): TextColumn
    {
        return TextColumn::make('last_activity')
            ->label('Última Actividad')
            ->getStateUsing(function (Tender $record): string {
                // Priorizar updated_at sobre created_at
                $lastActivity = $record->updated_at ?? $record->created_at;
                return $lastActivity ? $lastActivity->diffForHumans() : 'N/A';
            })
            ->sortable(['updated_at'])
            ->tooltip(function (Tender $record): string {
                $lastUpdate = $record->updated_at;
                $created = $record->created_at;
                
                if ($lastUpdate && $created && $lastUpdate->ne($created)) {
                    return "Última actualización: {$lastUpdate->format('d/m/Y H:i')}";
                }
                
                return "Creado: {$created->format('d/m/Y H:i')}";
            })
            ->icon('heroicon-m-clock')
            ->color('gray');
    }

    /**
     * 💰 Crea columna de valor con formato mejorado
     */
    public static function createValueColumn(): TextColumn
    {
        return TextColumn::make('estimated_referenced_value')
            ->label('Valor')
            ->getStateUsing(function (Tender $record): string {
                if (!$record->estimated_referenced_value || $record->estimated_referenced_value <= 0) {
                    return 'Sin valor';
                }
                
                $currency = $record->currency_name ?? 'PEN';
                $value = $record->estimated_referenced_value;
                
                // Formatear según el valor
                if ($value >= 1000000) {
                    return number_format($value / 1000000, 1) . 'M ' . $currency;
                } elseif ($value >= 1000) {
                    return number_format($value / 1000, 1) . 'K ' . $currency;
                } else {
                    return number_format($value, 0) . ' ' . $currency;
                }
            })
            ->sortable()
            ->alignEnd()
            ->weight('medium')
            ->tooltip(function (Tender $record): string {
                if (!$record->estimated_referenced_value || $record->estimated_referenced_value <= 0) {
                    return 'Sin valor estimado';
                }
                
                $currency = $record->currency_name ?? 'PEN';
                return 'Valor completo: ' . number_format($record->estimated_referenced_value, 2) . ' ' . $currency;
            });
    }

    /**
     * 🏢 Crea columna de entidad con información adicional
     */
    public static function createEntityColumn(): TextColumn
    {
        return TextColumn::make('entity_name')
            ->label('Entidad')
            ->searchable()
            ->sortable()
            ->limit(20)
            ->weight('medium')
            ->tooltip(function (TextColumn $column): ?string {
                $state = $column->getState();
                return strlen($state) > 20 ? $state : null;
            })
            ->icon('heroicon-m-building-office-2')
            ->color('primary');
    }

    /**
     * 📋 Crea columna de nomenclatura mejorada
     */
    public static function createIdentifierColumn(): TextColumn
    {
        return TextColumn::make('identifier')
            ->label('Nomenclatura')
            ->searchable()
            ->sortable()
            ->copyable()
            ->weight('bold')
            ->color('primary')
            ->limit(25)
            ->tooltip(function (TextColumn $column): ?string {
                $state = $column->getState();
                return strlen($state) > 25 ? $state : null;
            })
            ->icon('heroicon-m-document-text')
            ->description(function (Tender $record): string {
                return $record->contract_object ?? 'Sin objeto';
            });
    }

    /**
     * 🎯 Crea columna de estado con información de progreso
     */
    public static function createStatusColumn(): TextColumn
    {
        return TextColumn::make('tenderStatus.name')
            ->label('Estado')
            ->searchable()
            ->sortable()
            ->badge()
            ->color(fn ($record): string => match (true) {
                ! $record->tenderStatus => 'danger',
                $record->tenderStatus->code === '--' => 'gray',
                $record->tenderStatus->category === 'special' => 'danger',
                str_contains($record->tenderStatus->code, 'CONVOCADO') => 'info',
                str_contains($record->tenderStatus->code, 'REGISTRO') => 'warning',
                str_contains($record->tenderStatus->code, 'CONSULTAS') => 'gray',
                str_contains($record->tenderStatus->code, 'ABSOLUCION') => 'primary',
                str_contains($record->tenderStatus->code, 'INTEGRACION') => 'warning',
                str_contains($record->tenderStatus->code, 'PRESENTACION') => 'info',
                str_contains($record->tenderStatus->code, 'EVALUACION') => 'warning',
                str_contains($record->tenderStatus->code, 'OTORGAMIENTO') => 'success',
                str_contains($record->tenderStatus->code, 'CONSENTIDO') => 'success',
                str_contains($record->tenderStatus->code, 'CONTRATADO') => 'success',
                default => 'gray',
            })
            ->formatStateUsing(fn ($record): string => ! $record->tenderStatus ? '⚠️ SIN ESTADO' : $record->tenderStatus->name)
            ->limit(20)
            ->tooltip(function (TextColumn $column): ?string {
                $state = $column->getState();
                return strlen($state) > 20 ? $state : null;
            });
    }

    /**
     * 👤 Crea columna de creador con avatar
     */
    public static function createCreatorColumn(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label('Creado por')
            ->searchable()
            ->sortable()
            ->badge()
            ->color('info')
            ->icon('heroicon-m-user')
            ->toggleable(isToggledHiddenByDefault: true)
            ->tooltip(function (Tender $record): string {
                $creator = $record->creator;
                $createdAt = $record->created_at;
                
                if (!$creator) {
                    return 'Usuario no disponible';
                }
                
                return "Creado por {$creator->name} el {$createdAt->format('d/m/Y H:i')}";
            });
    }

    /**
     * 📊 Crea columna de tipo de proceso con iconos
     */
    public static function createProcessTypeColumn(): TextColumn
    {
        return TextColumn::make('processType.description_short_type')
            ->label('Tipo')
            ->searchable()
            ->sortable()
            ->badge()
            ->color(fn (?string $state): string => match ($state) {
                'Licitación Pública' => 'info',
                'Concurso Público' => 'success',
                'Adjudicación Directa' => 'warning',
                'Selección Simplificada' => 'gray',
                default => 'gray',
            })
            ->icon(fn (?string $state): string => match ($state) {
                'Licitación Pública' => 'heroicon-m-document-text',
                'Concurso Público' => 'heroicon-m-trophy',
                'Adjudicación Directa' => 'heroicon-m-hand-raised',
                'Selección Simplificada' => 'heroicon-m-lightning-bolt',
                default => 'heroicon-m-question-mark-circle',
            })
            ->limit(15)
            ->tooltip(function (TextColumn $column): ?string {
                $state = $column->getState();
                return strlen($state) > 15 ? $state : null;
            });
    }

    /**
     * 🎯 Crea columna de objeto con iconos
     */
    public static function createContractObjectColumn(): TextColumn
    {
        return TextColumn::make('contract_object')
            ->label('Objeto')
            ->searchable()
            ->sortable()
            ->badge()
            ->color(fn (string $state): string => match ($state) {
                'Bien' => 'info',
                'Obra' => 'warning',
                'Servicio' => 'success',
                'Consultoría de Obra' => 'gray',
                default => 'gray',
            })
            ->icon(fn (string $state): string => match ($state) {
                'Bien' => 'heroicon-m-cube',
                'Obra' => 'heroicon-m-building-office',
                'Servicio' => 'heroicon-m-cog-6-tooth',
                'Consultoría de Obra' => 'heroicon-m-wrench-screwdriver',
                default => 'heroicon-m-question-mark-circle',
            })
            ->limit(12)
            ->tooltip(function (TextColumn $column): ?string {
                $state = $column->getState();
                return strlen($state) > 12 ? $state : null;
            });
    }
}
