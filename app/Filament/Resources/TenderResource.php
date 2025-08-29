<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenderResource\Pages;
use App\Filament\Resources\TenderResource\RelationManagers;
use App\Models\Tender;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TenderResource extends Resource
{
    protected static ?string $model = Tender::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('sequence_number')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('entity_name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\DatePicker::make('published_at')
                    ->required(),
                Forms\Components\TextInput::make('identifier')
                    ->required()
                    ->maxLength(255),
                Forms\Components\DatePicker::make('restarted_from'),
                Forms\Components\TextInput::make('contract_object')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('object_description')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('cui_code')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\TextInput::make('estimated_referenced_value')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('currency_name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\DatePicker::make('absolution_obs'),
                Forms\Components\DatePicker::make('offer_presentation'),
                Forms\Components\DatePicker::make('award_granted_at'),
                Forms\Components\DatePicker::make('award_consent'),
                Forms\Components\TextInput::make('current_status')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('awarded_tax_id')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\Textarea::make('awarded_legal_name')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('awarded_amount')
                    ->numeric()
                    ->default(null),
                Forms\Components\DatePicker::make('contract_signing'),
                Forms\Components\TextInput::make('adjusted_amount')
                    ->numeric()
                    ->default(null),
                Forms\Components\Textarea::make('observation')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('selection_comittee')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('contract_execution')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('contract_details')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('sequence_number')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('entity_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('published_at')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('identifier')
                    ->searchable(),
                Tables\Columns\TextColumn::make('restarted_from')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('contract_object')
                    ->searchable(),
                Tables\Columns\TextColumn::make('cui_code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('estimated_referenced_value')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('currency_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('absolution_obs')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('offer_presentation')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('award_granted_at')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('award_consent')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('current_status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('awarded_tax_id')
                    ->searchable(),
                Tables\Columns\TextColumn::make('awarded_amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('contract_signing')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('adjusted_amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
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
