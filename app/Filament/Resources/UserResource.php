<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Staff Accounts';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 1;

    // Administration is admin-only — hidden from staff entirely.
    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->is_admin && $record->id !== auth()->id();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('first_name')
                    ->required()
                    ->maxLength(100),

                TextInput::make('last_name')
                    ->required()
                    ->maxLength(100),

                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->columnSpanFull(),

                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('Leave blank to keep the current password.')
                    ->columnSpanFull(),

                TextInput::make('phone_number')
                    ->label('Phone')
                    ->tel()
                    ->maxLength(20),

                Toggle::make('is_staff')
                    ->label('Can access staff panel')
                    ->default(true)
                    ->inline(false),

                Toggle::make('is_admin')
                    ->label('Admin (full access)')
                    ->helperText('Admins can create, edit, and delete menu items, toppings, and promotions.')
                    ->inline(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(User::where('is_staff', true)->where('email', '!=', 'guest@internal.local'))
            ->columns([
                TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable('first_name'),

                TextColumn::make('email')
                    ->searchable(),

                TextColumn::make('phone_number')
                    ->label('Phone')
                    ->placeholder('—'),

                IconColumn::make('is_admin')
                    ->label('Admin')
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-user')
                    ->trueColor('warning')
                    ->falseColor('gray'),

                IconColumn::make('is_staff')
                    ->label('Active')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->defaultSort('first_name')
            ->filters([
                SelectFilter::make('is_admin')
                    ->label('Role')
                    ->options([
                        '1' => 'Admin',
                        '0' => 'Staff',
                    ]),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
