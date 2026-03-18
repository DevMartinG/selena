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
use Illuminate\Support\Facades\Gate;


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
                    'tenderStage.tender.creator', 
                    'tenderStage.tender.meta',     
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
                
                // Columnas adicionales a mostrar
                Tables\Columns\TextColumn::make('tenderStage.tender.creator.nin')
                    ->label('Creado por')
                    ->description(function ($record) {
                        $creator = $record->tenderStage?->tender?->creator;

                        if (!$creator) return null;

                        return new HtmlString("
                            <div style='font-size:11px; color:#6b7280;'>
                                {$creator->name} {$creator->last_name}
                            </div>
                        ");
                    })
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-m-user')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha creación')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                // Fin columnas a mostrar

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

            ->actions([

                // ========================================================================
                // 🎯 BOTÓN VER (VIEW) - SLIDEOVER READ-ONLY SIN ACCIÓN EDITAR
                // ========================================================================
                Tables\Actions\ViewAction::make()
                    ->iconButton()
                    ->icon('heroicon-s-eye')
                    ->label(false)
                    ->tooltip('Ver este procedimiento de selección')
                    ->color('info')
                    ->size('sm')
                    ->slideOver()
                    ->modalWidth('3xl')
                    ->infolist(function ($record) {
                        $fields = [
                            'published_at'              => 'Convocatoria',
                            'participants_registration' => 'Registro de Participantes',
                            'formulation_obs'           => 'Formulación Cons. Obs.',
                            'absolution_obs'            => 'Absolución Cons. Obs.',
                            'base_integration'          => 'Integración de Bases',
                            'offer_presentation'        => 'Presentación de Propuesta',
                            'offer_evaluation'          => 'Calificación y Eva. Prop.',
                            'award_granted_at'          => 'Buena Pro',
                            'award_consent'             => 'Consentimiento B. Pro',
                        ];

                        $colorMap = [
                            'success' => '#10B981',
                            'danger'  => '#EF4444',
                            'warning' => '#F59E0B',
                            'gray'    => '#6B7280',
                        ];

                        $iconMap = [
                            'heroicon-o-check-circle'          => '✅',
                            'heroicon-o-x-circle'              => '❌',
                            'heroicon-o-exclamation-triangle'  => '⚠️',
                            'heroicon-o-clock'                 => '🕐',
                        ];

                        // ✅ Variables correctas usando las relaciones del modelo
                        $codeFull           = $record->tenderStage?->tender?->code_full ?? '—';
                        $processType        = $record->tenderStage?->tender?->processType?->description_short_type ?? 'Sin Clasificar';
                        $nameUserCreator    = $record->tenderStage?->tender?->creator?->name ?? 'Desconocido';
                        $apelUserCreator    = $record->tenderStage?->tender?->creator?->last_name ?? 'Desconocido';
                        $numMeta            = $record->tenderStage?->tender?->meta?->codmeta ?? 'Sin meta';
                        $anioMeta           = $record->tenderStage?->tender?->meta?->anio ?? 'Sin anio meta';

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

                        // Construir filas de fechas
                        $rows = '';
                        $completedCount = 0;
                        $expiredCount   = 0;
                        $pendingCount   = 0;

                        foreach ($fields as $field => $label) {
                            $color   = self::getDeadlineColor($record, $field);
                            $icon    = self::getDeadlineIcon($record, $field);
                            $tooltip = self::getDeadlineTooltip($record, $field);
                            $hex     = $colorMap[$color] ?? '#6B7280';
                            $emoji   = $iconMap[$icon]   ?? '🕐';

                            if ($color === 'success') $completedCount++;
                            elseif ($color === 'danger') $expiredCount++;
                            else $pendingCount++;

                            $dateValue = $record->$field
                                ? Carbon::parse($record->$field)->format('d/m/Y')
                                : '—';

                            $bgHex = $hex . '18';
                            $borderHex = $hex . '44';

                            $rows .= <<<HTML
                                <tr style="border-bottom: 1px solid #f3f4f6; transition: background 0.15s;">
                                    <td style="padding: 0.65rem 1rem; font-size: 0.82rem; color: #374151; font-weight: 500;">
                                        {$label}
                                    </td>
                                    <td style="padding: 0.65rem 1rem; text-align: center;">
                                        <span style="
                                            display: inline-flex;
                                            align-items: center;
                                            gap: 0.3rem;
                                            padding: 0.2rem 0.65rem;
                                            background-color: {$bgHex};
                                            color: {$hex};
                                            border: 1px solid {$borderHex};
                                            border-radius: 9999px;
                                            font-size: 0.78rem;
                                            font-weight: 600;
                                            white-space: nowrap;
                                        ">
                                            {$emoji} {$dateValue}
                                        </span>
                                    </td>
                                    <td style="padding: 0.65rem 1rem; font-size: 0.78rem; color: #6B7280;">
                                        {$tooltip}
                                    </td>
                                </tr>
                            HTML;
                        }

                        $totalFields = count($fields);

                        $html = <<<HTML
                            <div style="font-family: inherit; padding: 0.25rem 0;">

                                <!-- ENCABEZADO: Código y tipo de proceso -->
                                <div style="background: linear-gradient(135deg,#eff6ff,#eef2ff); border:1px solid #c7d2fe; border-radius:0.75rem; padding:0.8rem 1.1rem; margin-bottom:0.875rem; display:flex; align-items:center; gap:0.6rem;">
                                    <span style="padding:0.2rem 0.65rem; background-color:{$badgeColor}; color:white; border-radius:0.375rem; font-size:0.72rem; font-weight:600; letter-spacing:0.02em;">{$processType}</span>
                                    <span style="font-size:1.05rem; font-weight:700; color:#1e40af;">{$codeFull}</span>
                                </div>

                                <!-- INFO: Entidad, creador, meta, objeto -->
                                <div style="
                                    display: grid;
                                    grid-template-columns: 1fr 1fr;
                                    gap: 0.75rem;
                                    margin-bottom: 0.875rem;
                                ">
                                    <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.6rem; padding: 0.75rem 1rem;">
                                        <div style="font-size: 0.7rem; color: #9ca3af; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">
                                            👤 Creado por
                                        </div>
                                        <div style="font-size: 0.85rem; color: #111827; font-weight: 500;">{$nameUserCreator} {$apelUserCreator}</div>
                                    </div>
                                    <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.6rem; padding: 0.75rem 1rem;">
                                        <div style="font-size: 0.7rem; color: #9ca3af; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">
                                            🎯 Meta
                                        </div>
                                        <div style="font-size: 0.85rem; color: #111827; font-weight: 500;">{$numMeta} - {$anioMeta}</div>
                                    </div>
                                </div>

                                <!-- RESUMEN de estados -->
                                <div style="
                                    display: flex;
                                    gap: 0.6rem;
                                    margin-bottom: 0.875rem;
                                ">
                                    <div style="flex:1; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:0.6rem; padding:0.6rem 0.75rem; text-align:center;">
                                        <div style="font-size:1.1rem; font-weight:700; color:#16a34a;">{$completedCount}</div>
                                        <div style="font-size:0.7rem; color:#16a34a; font-weight:500;">✅ Completados</div>
                                    </div>
                                    <div style="flex:1; background:#fef2f2; border:1px solid #fecaca; border-radius:0.6rem; padding:0.6rem 0.75rem; text-align:center;">
                                        <div style="font-size:1.1rem; font-weight:700; color:#dc2626;">{$expiredCount}</div>
                                        <div style="font-size:0.7rem; color:#dc2626; font-weight:500;">❌ Vencidos</div>
                                    </div>
                                    <div style="flex:1; background:#f9fafb; border:1px solid #e5e7eb; border-radius:0.6rem; padding:0.6rem 0.75rem; text-align:center;">
                                        <div style="font-size:1.1rem; font-weight:700; color:#6b7280;">{$pendingCount}</div>
                                        <div style="font-size:0.7rem; color:#6b7280; font-weight:500;">🕐 Pendientes</div>
                                    </div>
                                    <div style="flex:1; background:#eff6ff; border:1px solid #bfdbfe; border-radius:0.6rem; padding:0.6rem 0.75rem; text-align:center;">
                                        <div style="font-size:1.1rem; font-weight:700; color:#2563eb;">{$totalFields}</div>
                                        <div style="font-size:0.7rem; color:#2563eb; font-weight:500;">📋 Total</div>
                                    </div>
                                </div>

                                <!-- TABLA DE FECHAS -->
                                <div style="border: 1px solid #e5e7eb; border-radius: 0.75rem; overflow: hidden;">
                                    <table style="width: 100%; border-collapse: collapse;">
                                        <thead>
                                            <tr style="background-color: #f9fafb; border-bottom: 2px solid #e5e7eb;">
                                                <th style="padding: 0.6rem 1rem; text-align: left; font-size: 0.7rem; color: #6B7280; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;">Etapa</th>
                                                <th style="padding: 0.6rem 1rem; text-align: center; font-size: 0.7rem; color: #6B7280; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;">Fecha</th>
                                                <th style="padding: 0.6rem 1rem; text-align: left; font-size: 0.7rem; color: #6B7280; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;">Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {$rows}
                                        </tbody>
                                    </table>
                                </div>

                            </div>
                        HTML;

                        return [
                            \Filament\Infolists\Components\TextEntry::make('__html')
                                ->hiddenLabel()
                                ->html()
                                ->state($html),
                        ];
                    })

                    // ->authorize(function ($record) {
                    //     return Gate::allows('view', $record);
                    // }),

            ])

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

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (!$user) {
            return $query;
        }

        // --------- SUPERADMIN ---------
        if ($user->roles->contains('name', 'SuperAdmin')) {
            return $query;
        }

        // --------- ADMIN ---------
        if ($user->roles->contains('name', 'Admin')) {
            return $query;
        }

        // --------- PROCESOS - OEC ---------
        if ($user->roles->contains('name', 'PROCESOS - OEC')) {
            return $query->where('created_by', $user->id);
        }

        // --------- COORDINADOR - PROCESOS ---------
        if ($user->roles->contains('name', 'COORDINADOR - PROCESOS')) {
            return $query;
        }

        $metaIds = $user->metas()->pluck('metas.id');

        // --------- COORDINADOR UEI + ADMINISTRATIVO ---------
        if (
            $user->roles->contains('name', 'COORDINADOR UEI') ||
            $user->roles->contains('name', 'ADMINISTRATIVO DE COORDINADOR')
        ) {
            return $query->whereHas('tenderStage.tender', function ($q) use ($metaIds) {
                $q->whereIn('meta_id', $metaIds);
            });
        }

        // Otros usuarios - creados por el o sus metas
        
        return $query->where(function ($q) use ($user, $metaIds) {

            // Creados por él (subiendo hasta tender)
            $q->whereHas('tenderStage.tender', function ($q2) use ($user) {
                $q2->where('created_by', $user->id);
            })

            // O sus metas (subiendo hasta tender)
            ->orWhereHas('tenderStage.tender', function ($q2) use ($metaIds) {
                $q2->whereIn('meta_id', $metaIds);
            });

        });


    }

}