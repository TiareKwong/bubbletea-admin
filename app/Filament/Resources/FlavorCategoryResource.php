<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnlyAccess;
use App\Filament\Resources\FlavorCategoryResource\Pages;
use App\Models\FlavorCategory;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FlavorCategoryResource extends Resource
{
    use AdminOnlyAccess;

    protected static ?string $model = FlavorCategory::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Categories';

    protected static ?string $pluralModelLabel = 'Categories';

    protected static string|\UnitEnum|null $navigationGroup = 'Menu';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Category';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(100),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Added')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFlavorCategories::route('/'),
            'create' => Pages\CreateFlavorCategory::route('/create'),
            'edit'   => Pages\EditFlavorCategory::route('/{record}/edit'),
        ];
    }
}
