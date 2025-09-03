<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenderResource\Pages;
use App\Models\Tender;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\IconPosition;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class TenderResource extends Resource
{
    protected static ?string $model = Tender::class;

    protected static ?string $label = 'Proc. Selección';

    protected static ?string $pluralLabel = 'Proc. Selección';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationIcon(): string
    {
        return request()->routeIs('filament.admin.resources.tenders.index') ? 'heroicon-s-rectangle-stack' : 'heroicon-o-rectangle-stack';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Tender Form')
                    ->persistTab() // recordar la última tab seleccionada
                    ->id('tender-form-tabs')
                    ->tabs([
                        Tabs\Tab::make('General Info')
                            ->label('Información General')
                            ->icon('heroicon-m-clipboard-document')
                            ->iconPosition(IconPosition::Before)
                            ->schema([
                                /* Forms\Components\TextInput::make('sequence_number')
                                    ->label('Nº')
                                    ->required()
                                    ->numeric()
                                    ->columnSpan(1), */
                                Forms\Components\TextInput::make('entity_name')
                                    ->label('Nombre o Siglas de la Entidad')
                                    ->default('GOBIERNO REGIONAL DE PUNO SEDE CENTRAL')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(5),
                                Forms\Components\TextInput::make('identifier')
                                    ->label('Nomenclatura')
                                    ->required()
                                    ->maxLength(255)
                                    ->autofocus()
                                    ->columnSpan(7)
                                    ->live(onBlur: true) // activa evento después que el usuario sale del campo
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $normalized = Tender::normalizeIdentifier($state);

                                        $isDuplicate = Tender::query()
                                            ->where('code_full', $normalized)
                                            ->when($get('id'), fn ($query, $id) => $query->where('id', '!=', $id)) // Ignorar si está editando
                                            ->exists();

                                        if ($isDuplicate) {
                                            Notification::make()
                                                ->title('Nomenclatura duplicada')
                                                //->body('Este procedimiento ya fue registrado con una nomenclatura equivalente.')
                                                ->icon('heroicon-s-exclamation-triangle')
                                                ->warning()
                                                ->duration(5000)
                                                ->send();
                                        }
                                    }),
                                Forms\Components\TextInput::make('restarted_from')
                                    ->label('Reiniciado desde')
                                    ->maxLength(255)
                                    ->columnSpan(4),
                                Forms\Components\Select::make('contract_object')
                                    ->label('Objeto de Contratación')
                                    ->required()
                                    ->options([
                                        'Bien' => 'Bien',
                                        'Consultoría de Obra' => 'Consultoría de Obra',
                                        'Obra' => 'Obra',
                                        'Servicio' => 'Servicio',
                                    ])
                                    ->placeholder('[Seleccione]')
                                    // ->selectablePlaceholder(false)
                                    ->columnSpan(2),
                                Forms\Components\Textarea::make('object_description')
                                    ->label('Descripción del Objeto')
                                    ->required()
                                    ->columnSpan(6),

                                Forms\Components\TextInput::make('cui_code')
                                    ->label('Código CUI')
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('awarded_tax_id')
                                    ->label('RUC del Adjudicado')
                                    ->maxLength(255)
                                    ->columnSpan(4),
                                Forms\Components\Textarea::make('awarded_legal_name')
                                    ->label('Razón Social del Postor Adjudicado')
                                    ->columnSpanFull()
                                    ->columnSpan(6),

                                Forms\Components\Textarea::make('observation')
                                    ->label('Observaciones')
                                    ->columnSpan(6),
                                Forms\Components\Textarea::make('selection_comittee')
                                    ->label('OEC/ Comité de Selección')
                                    ->columnSpan(6),

                                Forms\Components\Textarea::make('contract_execution')
                                    ->label('Ejecución Contractual')
                                    ->columnSpan(6),
                                Forms\Components\Textarea::make('contract_details')
                                    ->label('Datos del Contrato')
                                    ->columnSpan(6),
                                Forms\Components\TextInput::make('current_status')
                                    ->label('Estado Actual')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(4),
                            ])
                            ->columns(12),

                        Tabs\Tab::make('Dates')
                            ->label('Fechas')
                            ->icon('heroicon-m-calendar-days')
                            ->iconPosition(IconPosition::Before)
                            ->schema([
                                Forms\Components\DatePicker::make('published_at')
                                    ->label('Fecha de Publicación')
                                    ->required()
                                    ->columnSpan(4),
                                Forms\Components\DatePicker::make('absolution_obs')
                                    ->label('Absol. de Consultas/Obs Integración de Bases')
                                    ->columnSpan(4),
                                Forms\Components\DatePicker::make('offer_presentation')
                                    ->label('Presentación de Ofertas')
                                    ->columnSpan(4),
                                Forms\Components\DatePicker::make('award_granted_at')
                                    ->label('Otorgamiento de la Buena Pro')
                                    ->columnSpan(4),
                                Forms\Components\DatePicker::make('award_consent')
                                    ->label('Consentimiento de la Buena Pro')
                                    ->columnSpan(4),
                                Forms\Components\DatePicker::make('contract_signing')
                                    ->label('Fecha de Suscripción del Contrato')
                                    ->columnSpan(4),
                            ])
                            ->columns(12),

                        Tabs\Tab::make('Amounts')
                            ->label('Montos')
                            ->icon('heroicon-m-currency-dollar')
                            ->iconPosition(IconPosition::Before)
                            ->schema([
                                Section::make('Moneda y Montos')
                                    ->description('Formato: 1,234.56 (coma "," para miles y punto "." para decimales)')
                                    ->schema([
                                        Forms\Components\Select::make('currency_name')
                                            ->label('Moneda')
                                            ->options([
                                                'PEN' => 'Soles (PEN)',
                                                'USD' => 'Dólares (USD)',
                                                'EUR' => 'Euros (EUR)',
                                            ])
                                            ->required()
                                            ->default('PEN')
                                            ->reactive()
                                            ->columnSpan(2),
                                        Forms\Components\TextInput::make('estimated_referenced_value')
                                            ->label('Valor Referencial / Estimado')
                                            ->required()
                                            ->numeric()
                                            ->prefix(fn ($get) => match ($get('currency_name')) {
                                                'USD' => '$',
                                                'EUR' => '€',
                                                default => 'S/',
                                            })
                                            ->suffix(fn ($get) => match ($get('currency_name')) {
                                                'USD' => ' USD',
                                                'EUR' => ' EUR',
                                                default => ' SOLES',
                                            })
                                            ->mask(RawJs::make('$money($input)'))
                                            ->stripCharacters([','])
                                            ->extraAttributes(['class' => 'font-bold text-lg'])
                                            ->reactive()
                                            ->columnSpan(3),
                                        Forms\Components\TextInput::make('awarded_amount')
                                            ->label('Monto Adjudicado')
                                            ->numeric()
                                            ->prefix(fn ($get) => match ($get('currency_name')) {
                                                'USD' => '$',
                                                'EUR' => '€',
                                                default => 'S/',
                                            })
                                            ->suffix(fn ($get) => match ($get('currency_name')) {
                                                'USD' => ' USD',
                                                'EUR' => ' EUR',
                                                default => ' SOLES',
                                            })
                                            ->mask(RawJs::make('$money($input)'))
                                            ->stripCharacters([','])
                                            ->reactive()
                                            ->columnSpan(3),
                                        Forms\Components\TextInput::make('adjusted_amount')
                                            ->label('Monto Diferencial')
                                            ->helperText('VE/VF vs Oferta Económica')
                                            ->numeric()
                                            ->prefix(fn ($get) => match ($get('currency_name')) {
                                                'USD' => '$',
                                                'EUR' => '€',
                                                default => 'S/',
                                            })
                                            ->suffix(fn ($get) => match ($get('currency_name')) {
                                                'USD' => ' USD',
                                                'EUR' => ' EUR',
                                                default => ' SOLES',
                                            })
                                            ->mask(RawJs::make('$money($input)'))
                                            ->stripCharacters([','])
                                            ->reactive()
                                            ->columnSpan(3),
                                    ])
                                    ->columns(11),

                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entity_name')
                    ->label('Entidad')
                    ->html()
                    ->formatStateUsing(fn ($state) => new HtmlString(
                        '<div style="
                            display: block;
                            overflow-wrap: break-word;
                            white-space: normal;
                            max-width: 60ch;   /* Ajusta para controlar cuántas palabras entran por línea */
                            line-height: 1.1rem;
                            font-style: italic;
                        ">'.e($state).'</div>'
                    ))
                    ->size(TextColumn\TextColumnSize::ExtraSmall)
                    ->width(120)
                    ->searchable(),

                TextColumn::make('info_summary')
                    ->label('Nomenclatura')
                    ->html()
                    ->getStateUsing(function (Tender $record) {
                        $identifierFull = $record->identifier ?? '';
                        $identifier = e(Str::limit($identifierFull, 40));

                        $published = $record->published_at
                            ? '📅 Publicado: '.\Carbon\Carbon::parse($record->published_at)->format('d/m/Y')
                            : '📅 Sin fecha';

                        $restartedFull = $record->restarted_from ?? '';
                        $restartedText = Str::limit($restartedFull, 50);

                        $restarted = $restartedFull
                            ? <<<HTML
                                <div class="text-sm text-green-600 dark:text-green-400 mt-1" title="{$restartedFull}">
                                    🔁 Reiniciado desde: <span class="italic">{$restartedText}</span>
                                </div>
                            HTML
                            : '';

                        return <<<HTML
                            <div style="line-height: 1.3;" title="{$identifierFull}">
                                <div class="font-semibold text-sm leading-snug break-words max-w-[220px]">
                                    {$identifier}
                                </div>
                                <div class="text-sm text-green-600 dark:text-green-400">
                                    {$published}
                                </div>
                                {$restarted}
                            </div>
                        HTML;
                    })
                    ->wrap()
                    ->extraAttributes(['class' => 'min-w-[180px] max-w-[240px] whitespace-normal break-words'])
                    ->width(280)
                    ->sortable('published_at')
                    ->searchable(),

                TextColumn::make('object_summary')
                    ->label('Objeto')
                    ->html()
                    ->getStateUsing(function (Tender $record) {
                        $description = e(Str::limit($record->object_description, 120));
                        $tooltip = e($record->object_description);

                        $badge = $record->contract_object
                            ? '<span style="display: inline-block; background-color: #5a7ec7ff; color: #ffffffff; font-size: 12px; padding: 2px 6px; border-radius: 9999px; margin-top: 4px;">'.e($record->contract_object).'</span>'
                            : '';

                        $cuiValue = $record->cui_code
                            ? '<strong>'.e($record->cui_code).'</strong>'
                            : 'No asignado aún';

                        $cui = <<<HTML
                            <div style="font-size: 12px; color: var(--filament-color-gray-600); margin-top: 3px;">
                                CUI: {$cuiValue}
                            </div>
                        HTML;

                        return <<<HTML
                            <div title="{$tooltip}" style="line-height: 1.3;">
                                <div style="font-size: 13px; font-weight: 500; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">{$description}</div>
                                {$badge}
                                {$cui}
                            </div>
                        HTML;
                    })
                    ->wrap()
                    ->searchable()
                    ->extraAttributes(['style' => 'min-width: 220px;']),

                TextColumn::make('amount_summary')
                    ->label('Montos')
                    ->html()
                    ->getStateUsing(function ($record) {
                        $format = fn ($amount) => $amount !== null
                            ? 'S/ '.number_format((float) $amount, 2, '.', ',')
                            : '<span class="text-gray-400 dark:text-gray-500">—</span>';

                        $estimated = $format($record->estimated_referenced_value);
                        $adjusted = $format($record->adjusted_amount);
                        $awarded = $format($record->awarded_amount);

                        // ✅ Estilo badge moderno con soporte para modo oscuro
                        $status = $record->current_status
                            ? '<div class="mt-2">
                                    <span class="inline-block rounded-full bg-gray-200 px-3 py-1 text-xs font-semibold text-gray-900 underline underline-offset-2 decoration-gray-400 dark:bg-gray-700 dark:text-white dark:decoration-gray-500">'
                                    .e($record->current_status).
                                    '</span>
                            </div>'
                            : '';

                        return <<<HTML
                            <div class="text-sm leading-snug space-y-1">
                                <div><span class="text-gray-500 dark:text-gray-400 text-xs">Ref./Est.:</span> <strong>{$estimated}</strong></div>
                                <div><span class="text-gray-500 dark:text-gray-400 text-xs">Diferencial:</span> <strong>{$adjusted}</strong></div>
                                <div><span class="text-gray-500 dark:text-gray-400 text-xs">Adjudicado:</span> <strong>{$awarded}</strong></div>
                                {$status}
                            </div>
                        HTML;
                    })
                    ->alignRight()
                    ->extraAttributes(['class' => 'min-w-[180px]']),

                TextColumn::make('phase_summary')
                    ->label('Fechas')
                    ->html()
                    ->getStateUsing(function ($record) {
                        $date = fn ($value) => $value
                            ? Carbon::parse($value)->format('d/m/Y')
                            : '<span style="color:var(--filament-color-gray-400)">—</span>';

                        $rows = [
                            [
                                'icon' => '📌',
                                'label' => 'Consultas:',
                                'value' => $record->absolution_obs,
                                'tooltip' => 'Etapa en la que se absuelven consultas y observaciones a las bases.',
                            ],
                            [
                                'icon' => '📤',
                                'label' => 'Oferta:',
                                'value' => $record->offer_presentation,
                                'tooltip' => 'Fecha límite para presentar propuestas técnicas y económicas.',
                            ],
                            [
                                'icon' => '🟦',
                                'label' => 'Buena Pro:',
                                'value' => $record->award_granted_at,
                                'tooltip' => 'Fecha de otorgamiento de la buena pro al postor ganador.',
                            ],
                            [
                                'icon' => '✅',
                                'label' => 'Consent.:',
                                'value' => $record->award_consent,
                                'tooltip' => 'Consentimiento de la buena pro si no hay impugnaciones.',
                            ],
                            [
                                'icon' => '📝',
                                'label' => 'Contrato:',
                                'value' => $record->contract_signing,
                                'tooltip' => 'Fecha prevista para la suscripción del contrato.',
                            ],
                        ];

                        $html = '<div style="line-height: 1.5; font-size: 12.5px; color: var(--filament-color-gray-700);">';
                        foreach ($rows as $row) {
                            $formattedDate = $row['value']
                                ? Carbon::parse($row['value'])->format('d/m/Y')
                                : '<span style="color:var(--filament-color-gray-400)">—</span>';

                            $html .= <<<HTML
                                <div title="{$row['tooltip']}">
                                    {$row['icon']} <strong>{$row['label']}</strong> {$formattedDate}
                                </div>
                            HTML;
                        }
                        $html .= '</div>';

                        return new HtmlString($html);
                    })
                    ->wrap()
                    ->extraAttributes(['style' => 'min-width: 200px;'])
                    ->searchable(),

                TextColumn::make('awarded_tax_id')
                    ->label('RUC Adjudicado')
                    ->searchable(),
                TextColumn::make('awarded_legal_name')
                    ->label('Razón Social del Postor Adjudicado')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->awarded_legal_name)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenders::route('/'),
            'create' => Pages\CreateTender::route('/create'),
            'edit' => Pages\EditTender::route('/{record}/edit'),
        ];
    }
}
