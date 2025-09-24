<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 1;

    protected static ?string $label = 'Usuario';

    protected static ?string $pluralLabel = 'Usuarios';

    protected static ?string $navigationGroup = 'Configuración';

    public static function getNavigationIcon(): string
    {
        return request()->routeIs('filament.admin.resources.users.index') ? 'heroicon-s-user-group' : 'heroicon-o-user-group';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(4)->schema([
                    TextInput::make('name')->label('Nombre')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('last_name')->label('Apellido')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('nin')->label('DNI')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('email')->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(255),
                    TextInput::make('username')->label('Nombre Usuario')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('password')->label('Contraseña')
                        ->password()
                        ->required(fn (Page $livewire) => ($livewire instanceof CreateUser))
                        ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->maxLength(255),

                    Select::make('roles')->label('Roles')
                        ->multiple()
                        ->preload()
                        ->relationship('roles', 'name'),

                    Select::make('permissions')->label('Permisos')
                        ->multiple()
                        ->preload()
                        ->relationship('permissions', 'id')
                        ->options(fn () => \App\Models\Permission::pluck('id', 'name')
                            ->mapWithKeys(fn ($id, $name) => [$id => \App\Models\Permission::getLabel($name)])),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombres')->searchable(),
                TextColumn::make('last_name')->label('Apellidos')->searchable(),
                TextColumn::make('nin')->label('DNI')->searchable(),
                TextColumn::make('email')->label('Correo')->searchable(),
                TextColumn::make('username')->label('Usuario')->searchable(),
                TextColumn::make('roles.name')->label('Roles')
                    ->sortable()
                    ->searchable()
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-m-finger-print'),

                TextColumn::make('created_at')->label('Fecha de creación:')
                    ->timezone('America/Lima')
                    ->date()
                    ->sortable()
                    ->toggleable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->label('Fecha de actualización:')
                    ->timezone('America/Lima')
                    ->date()
                    ->sortable()
                    ->toggleable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->authorize(fn ($record) => Gate::allows('update', $record)),
                Tables\Actions\DeleteAction::make()
                    ->authorize(fn ($record) => Gate::allows('delete', $record)),
                Tables\Actions\RestoreAction::make()
                    ->authorize(fn ($record) => Gate::allows('restore', $record)),
                Tables\Actions\ForceDeleteAction::make()
                    ->authorize(fn ($record) => Gate::allows('forceDelete', $record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->authorize(fn () => Gate::allows('delete', User::class)),

                    Tables\Actions\RestoreBulkAction::make()
                        ->authorize(fn () => Gate::allows('restore', User::class)),

                    Tables\Actions\ForceDeleteBulkAction::make()
                        ->authorize(fn () => Gate::allows('forceDelete', User::class)),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('email', '!=', 'superadmin@laravel.app')
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', User::class);
    }

    public static function canCreate(): bool
    {
        return Gate::allows('create', User::class);
    }

    public static function canEdit($record): bool
    {
        return Gate::allows('update', $record);
    }

    public static function canDelete($record): bool
    {
        return Gate::allows('delete', $record);
    }

    public static function canForceDelete($record): bool
    {
        return Gate::allows('forceDelete', $record);
    }

    public static function canRestore($record): bool
    {
        return Gate::allows('restore', $record);
    }
}
