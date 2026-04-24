<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductTypeResource\Pages;
use App\Models\ProductType;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Illuminate\Support\HtmlString;

class ProductTypeResource extends Resource
{
    protected static ?string $model = ProductType::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Product Types';

    protected static string|\UnitEnum|null $navigationGroup = 'Menu';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(50)
                ->helperText('Use lowercase with underscores, e.g. ice_cream, merch, cake, food.')
                ->rules(['alpha_dash']),

            Placeholder::make('app_behaviour')
                ->label('App Behaviour')
                ->content(new HtmlString(
                    '<p style="font-size:0.85rem;color:#6b7280;line-height:1.6;">' .
                    '<strong>drink</strong> — shows size, ice, sugar, and toppings. Earns loyalty points.<br>' .
                    '<strong>ice_cream</strong> — shows toppings only. Earns loyalty points.<br>' .
                    '<strong>anything else</strong> — simple quantity picker, added straight to cart. Does not earn loyalty points.' .
                    '</p>'
                )),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))),

                TextColumn::make('app_flow')
                    ->label('App Flow')
                    ->badge()
                    ->getStateUsing(fn ($record): string => $record->name)
                    ->color(fn (string $state): string => match ($state) {
                        'drink'     => 'info',
                        'ice_cream' => 'warning',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'drink'     => 'Full drink options',
                        'ice_cream' => 'Toppings only',
                        default     => ucfirst(str_replace('_', ' ', $state)) . ' (simple cart)',
                    }),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn () => (bool) auth()->user()?->is_admin),
                DeleteAction::make()
                    ->visible(fn () => (bool) auth()->user()?->is_admin),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProductTypes::route('/'),
            'create' => Pages\CreateProductType::route('/create'),
            'edit'   => Pages\EditProductType::route('/{record}/edit'),
        ];
    }
}
