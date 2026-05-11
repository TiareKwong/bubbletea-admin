<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CupTypeResource\Pages;
use App\Models\Branch;
use App\Models\CupType;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class CupTypeResource extends Resource
{
    protected static ?string $model = CupType::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationLabel = 'Cup Types';

    protected static string|\UnitEnum|null $navigationGroup = 'Menu';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Cup Type';

    public static function canViewAny(): bool  { return (bool) auth()->user()?->is_admin; }
    public static function canCreate(): bool   { return (bool) auth()->user()?->is_admin; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool   { return (bool) auth()->user()?->is_admin; }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return (bool) auth()->user()?->is_admin; }
    public static function canDeleteAny(): bool { return (bool) auth()->user()?->is_admin; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(100)
                ->placeholder('e.g. 500ml, 700ml, Ice Cream'),

            TextInput::make('sort_order')
                ->label('Sort Order')
                ->numeric()
                ->default(0)
                ->helperText('Lower numbers appear first'),

            Toggle::make('is_active')
                ->label('Active')
                ->default(true),

            CheckboxList::make('branches')
                ->label('Available at branches')
                ->helperText('Leave all unchecked = available at every branch')
                ->relationship('branches', 'name')
                ->options(Branch::where('is_active', true)->orderBy('name')->pluck('name', 'id')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('Cup Type')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('Active'),

                TextColumn::make('branches.name')
                    ->label('Branches')
                    ->badge()
                    ->placeholder('All branches'),

                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->actions([EditAction::make(), DeleteAction::make()])
            ->bulkActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCupTypes::route('/'),
            'create' => Pages\CreateCupType::route('/create'),
            'edit'   => Pages\EditCupType::route('/{record}/edit'),
        ];
    }
}
