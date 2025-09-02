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
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\RawJs;
use Illuminate\Support\HtmlString;
use Filament\Tables\Columns\TextColumn;

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
                                    ->columnSpan(7),

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
                                    //->selectablePlaceholder(false)
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
                    ->size(TextColumn\TextColumnSize::ExtraSmall)
                    // ->description(fn (Tender $record) => $record->getLoanDescription())
                    ->searchable(),
                TextColumn::make('published_at')
                    ->label('Publicado')
                    ->date()
                    ->sortable(),
                TextColumn::make('restarted_from')
                    ->label('Reiniciado desde')
                    ->size(TextColumn\TextColumnSize::ExtraSmall)
                    ->sortable(),
                TextColumn::make('object_description')
                    ->label('Descripción del Objeto')
                    ->limit(50)
                    ->tooltip(fn (Tender $record): ?string => $record->object_description ? new HtmlString(nl2br(e($record->object_description))) : null)
                    ->wrap()
                    ->description(fn (Tender $record) => $record->contract_object)
                    ->searchable(),
                TextColumn::make('cui_code')
                    ->label('CUI')
                    ->searchable(),
                TextColumn::make('estimated_referenced_value')
                    ->label('Valor Ref./Est.')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('absolution_obs')
                    ->label('Absol. Consultas/Obs Integr. Bases')
                    ->date()
                    ->sortable(),
                TextColumn::make('offer_presentation')
                    ->label('Present. de Ofertas')
                    ->date()
                    ->sortable(),
                TextColumn::make('award_granted_at')
                    ->label('Otorgam. Buena Pro')
                    ->date()
                    ->sortable(),
                TextColumn::make('award_consent')
                    ->label('Consentim. Buena Pro')
                    ->date()
                    ->sortable(),
                TextColumn::make('current_status')
                    ->label('Estado Actual')
                    ->searchable(),
                TextColumn::make('awarded_tax_id')
                    ->label('RUC Adjudicado')
                    ->searchable(),
                TextColumn::make('awarded_amount')
                    ->label('Monto Adjudicado')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('contract_signing')
                    ->label('Fec. Suscripción Contrato')
                    ->date()
                    ->sortable(),
                TextColumn::make('adjusted_amount')
                    ->label('Monto Diferencial')
                    ->numeric()
                    ->sortable(),
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
