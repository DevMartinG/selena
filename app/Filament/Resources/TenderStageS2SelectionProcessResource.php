<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenderStageS2SelectionProcessResource\Pages;
use App\Models\TenderStageS2;
use Carbon\Carbon;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class TenderStageS2SelectionProcessResource extends Resource
{
    protected static ?string $model = TenderStageS2::class;

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationLabel = 'Seguimiento Procesos';

    protected static ?string $label = 'Seguimiento Proc. Selec.';

    protected static ?string $pluralLabel = 'Seguimiento Proc. Selec.';

    protected static ?string $contentWidth = 'full';


    public static function getDeadlineColor($record, $field)
    {
        if (self::isCompleted($record, $field)) {
            return 'success';
        }

        $date = $record->$field;

        if (!$date) {
            return 'gray';
        }

        $today = Carbon::today();
        $date = Carbon::parse($date);

        if ($date->lt($today)) {
            return 'danger';
        }

        if ($date->isSameDay($today)) {
            return 'warning';
        }

        return 'gray';
    }

    public static function getDeadlineIcon($record, $field)
    {
        if (self::isCompleted($record, $field)) {
            return 'heroicon-o-check-circle';
        }

        $date = $record->$field;

        if (!$date) {
            return 'heroicon-o-clock';
        }

        $today = Carbon::today();
        $date = Carbon::parse($date);

        if ($date->lt($today)) {
            return 'heroicon-o-x-circle';
        }

        if ($date->isSameDay($today)) {
            return 'heroicon-o-exclamation-triangle';
        }

        return 'heroicon-o-clock';
    }

    public static function getDeadlineTooltip($record, $field): ?string
    {
        $completed = $record->completedFields
            ->where('field_name', $field)
            ->first();

        if ($completed) {
            $user = $completed->user?->name ?? 'Usuario desconocido';
            $date = Carbon::parse($completed->completed_at)->format('d/m/Y H:i');
            return "✅ Realizado por {$user} el {$date}";
        }

        $date = $record->$field;
        if (!$date) return 'Sin fecha registrada';

        $today = Carbon::today();
        $parsed = Carbon::parse($date);

        if ($parsed->lt($today)) {
            $dias = $parsed->diffInDays($today);
            return "❌ Fecha vencida hace {$dias} " . ($dias === 1 ? 'día' : 'días');
        }

        if ($parsed->isSameDay($today)) {
            return '⚠️ Último día para registrar';
        }

        $dias = $today->diffInDays($parsed);
        return "Faltan {$dias} " . ($dias === 1 ? 'día' : 'días');
    }


    public static function table(Table $table): Table
    {
        return $table

            ->modifyQueryUsing(function (Builder $query) {
                $query->with([
                    'tenderStage.tender.processType',
                    'completedFields.user', // directo en TenderStageS2
                ]);
            })

            ->columns([

                Tables\Columns\TextColumn::make('tenderStage.tender.code_full')
                    ->label('Procedimiento')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(30)
                    ->description(function ($record) {
                        // El processType vive en tender, no en TenderStageS2
                        $processType = $record->tenderStage?->tender?->processType?->description_short_type ?? 'Sin Clasificar';

                        $badgeColor = match ($processType) {
                            'Licitación Pública'            => '#3B82F6',
                            'Concurso Público'              => '#10B981',
                            'Adjudicación Directa'          => '#F59E0B',
                            'Adjudicación Simplificada'     => '#8B5CF6',
                            'Selección Simplificada'        => '#6B7280',
                            'Contratación Directa'          => '#EF4444',
                            'Adjudicación de Menor Cuantía' => '#06B6D4',
                            default                         => '#6B7280',
                        };

                        return new HtmlString(<<<HTML
                            <div style="
                                display: inline-flex;
                                align-items: center;
                                padding: 0.125rem 0.5rem;
                                background-color: {$badgeColor};
                                color: white;
                                border-radius: 0.375rem;
                                font-size: 0.75rem;
                                font-weight: 500;
                                width: fit-content;
                            ">
                                {$processType}
                            </div>
                        HTML);
                    }),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Convocatoria')
                    ->searchable()
                    ->sortable()
                    ->date('d-m-Y')
                    ->badge()
                    ->icon(fn ($record) => self::getDeadlineIcon($record, 'published_at'))
                    ->color(fn ($record) => self::getDeadlineColor($record, 'published_at'))
                    ->tooltip(fn ($record) => self::getDeadlineTooltip($record, 'published_at')),

                Tables\Columns\TextColumn::make('participants_registration')
                    ->label('Registro Part.')
                    ->searchable()
                    ->sortable()
                    ->date('d-m-Y')
                    ->badge()
                    ->icon(fn ($record) => self::getDeadlineIcon($record, 'participants_registration'))
                    ->color(fn ($record) => self::getDeadlineColor($record, 'participants_registration'))
                    ->tooltip(fn ($record) => self::getDeadlineTooltip($record, 'participants_registration')),

                Tables\Columns\TextColumn::make('formulation_obs')
                    ->label('Formulación Cons. Obs.')
                    ->searchable()
                    ->sortable()
                    ->date('d-m-Y')
                    ->badge()
                    ->icon(fn ($record) => self::getDeadlineIcon($record, 'formulation_obs'))
                    ->color(fn ($record) => self::getDeadlineColor($record, 'formulation_obs'))
                    ->tooltip(fn ($record) => self::getDeadlineTooltip($record, 'formulation_obs')),

                Tables\Columns\TextColumn::make('absolution_obs')
                    ->label('Absolución Cons. Obs.')
                    ->searchable()
                    ->sortable()
                    ->date('d-m-Y')
                    ->badge()
                    ->icon(fn ($record) => self::getDeadlineIcon($record, 'absolution_obs'))
                    ->color(fn ($record) => self::getDeadlineColor($record, 'absolution_obs'))
                    ->tooltip(fn ($record) => self::getDeadlineTooltip($record, 'absolution_obs')),

                Tables\Columns\TextColumn::make('base_integration')
                    ->label('Integración Bases')
                    ->searchable()
                    ->sortable()
                    ->date('d-m-Y')
                    ->badge()
                    ->icon(fn ($record) => self::getDeadlineIcon($record, 'base_integration'))
                    ->color(fn ($record) => self::getDeadlineColor($record, 'base_integration'))
                    ->tooltip(fn ($record) => self::getDeadlineTooltip($record, 'base_integration')),

                Tables\Columns\TextColumn::make('offer_presentation')
                    ->label('Presentación Prop.')
                    ->searchable()
                    ->sortable()
                    ->date('d-m-Y')
                    ->badge()
                    ->icon(fn ($record) => self::getDeadlineIcon($record, 'offer_presentation'))
                    ->color(fn ($record) => self::getDeadlineColor($record, 'offer_presentation'))
                    ->tooltip(fn ($record) => self::getDeadlineTooltip($record, 'offer_presentation')),

                Tables\Columns\TextColumn::make('offer_evaluation')
                    ->label('Calificación y Eva. Prop.')
                    ->searchable()
                    ->sortable()
                    ->date('d-m-Y')
                    ->badge()
                    ->icon(fn ($record) => self::getDeadlineIcon($record, 'offer_evaluation'))
                    ->color(fn ($record) => self::getDeadlineColor($record, 'offer_evaluation'))
                    ->tooltip(fn ($record) => self::getDeadlineTooltip($record, 'offer_evaluation')),

                Tables\Columns\TextColumn::make('award_granted_at')
                    ->label('Buena Pro')
                    ->searchable()
                    ->sortable()
                    ->date('d-m-Y')
                    ->badge()
                    ->icon(fn ($record) => self::getDeadlineIcon($record, 'award_granted_at'))
                    ->color(fn ($record) => self::getDeadlineColor($record, 'award_granted_at'))
                    ->tooltip(fn ($record) => self::getDeadlineTooltip($record, 'award_granted_at')),

                Tables\Columns\TextColumn::make('award_consent')
                    ->label('Consentimiento B. Pro')
                    ->searchable()
                    ->sortable()
                    ->date('d-m-Y')
                    ->badge()
                    ->icon(fn ($record) => self::getDeadlineIcon($record, 'award_consent'))
                    ->color(fn ($record) => self::getDeadlineColor($record, 'award_consent'))
                    ->tooltip(fn ($record) => self::getDeadlineTooltip($record, 'award_consent')),
            ])

            ->actions([])

            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenderStageS2SelectionProcesses::route('/'),
        ];
    }

    public static function isCompleted($record, $field): bool
    {
        return $record->completedFields
            ->where('field_name', $field)
            ->isNotEmpty();
    }

}