<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers\OrdersRelationManager;
use App\Models\Reward;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Customers';

    protected static string|\UnitEnum|null $navigationGroup = 'Customers';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->is_admin ?? false;
    }

    public static function canCreate(): bool  { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canEdit(Model $record): bool   { return (bool) auth()->user()?->is_staff; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('is_staff', false)
            ->where('email', '!=', 'guest@internal.local')
            ->with('reward');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Personal Details')
                ->columns(2)
                ->schema([
                    TextEntry::make('first_name')->label('First Name'),
                    TextEntry::make('last_name')->label('Last Name'),
                    TextEntry::make('email')->label('Email'),
                    TextEntry::make('phone_number')->label('Phone')->placeholder('—'),
                    TextEntry::make('birthday')->label('Birthday')->date()->placeholder('—'),
                    TextEntry::make('created_at')->label('Member Since')->dateTime(),
                    IconEntry::make('is_verified')->label('Email Verified')->boolean(),
                ]),

            Section::make('Loyalty Points')
                ->columns(2)
                ->schema([
                    TextEntry::make('reward.points')
                        ->label('Current Points')
                        ->default(0)
                        ->badge()
                        ->color('warning'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->searchOnBlur()
            ->columns([
                TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(
                        fn ($q) => $q->where('first_name', 'like', "%{$search}%")
                                     ->orWhere('last_name', 'like', "%{$search}%")
                    ))
                    ->sortable('first_name'),

                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone_number')
                    ->label('Phone')
                    ->placeholder('—'),

                TextColumn::make('reward.points')
                    ->label('Points')
                    ->default(0)
                    ->badge()
                    ->color('warning')
                    ->sortable(),

                TextColumn::make('orders_count')
                    ->label('Orders')
                    ->counts('orders')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Member Since')
                    ->date()
                    ->sortable(),

                IconColumn::make('is_verified')
                    ->label('Verified')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                \Filament\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelationManagers(): array
    {
        return [
            OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'view'  => Pages\ViewCustomer::route('/{record}'),
        ];
    }
}
