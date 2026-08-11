<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BranchResource\Pages;
use App\Models\Branch;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;

class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Branches';

    protected static string|\UnitEnum|null $navigationGroup = 'App';

    protected static ?int $navigationSort = 10;

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
                ->label('Branch Name')
                ->required()
                ->maxLength(100),

            TextInput::make('address')
                ->label('Address')
                ->maxLength(255),

            TextInput::make('phone')
                ->label('Phone')
                ->tel()
                ->maxLength(30),

            Toggle::make('is_active')
                ->label('Active')
                ->default(true),

            Repeater::make('opening_hours')
                ->label('Opening Hours')
                ->schema([
                    TextInput::make('days')
                        ->label('Days')
                        ->placeholder('e.g. Mon – Fri')
                        ->required()
                        ->columnSpan(1),
                    TextInput::make('hours')
                        ->label('Hours')
                        ->placeholder('e.g. 9:00 AM – 8:00 PM  or  Closed')
                        ->required()
                        ->columnSpan(1),
                ])
                ->columns(2)
                ->addActionLabel('Add Row')
                ->reorderable()
                ->collapsible()
                ->nullable(),

            CheckboxList::make('staff_ids')
                ->label('Staff')
                ->helperText('Choose who currently works at this branch. Unchecking someone removes them from this branch (they become unassigned, not deleted).')
                ->options(
                    User::where('is_staff', true)
                        ->where('is_admin', false)
                        ->where('is_super_staff', false)
                        ->where('email', '!=', 'guest@internal.local')
                        ->orderBy('first_name')
                        ->get()
                        ->mapWithKeys(fn (User $u) => [$u->id => $u->full_name])
                )
                ->searchable()
                ->bulkToggleable()
                ->columnSpanFull(),
        ]);
    }

    /**
     * Syncs which staff belong to a branch from the CheckboxList above.
     * Checked staff get moved into this branch; anyone previously in this
     * branch but no longer checked is unassigned (branch_id = null).
     */
    public static function syncBranchStaff(Branch $branch, array $staffIds): void
    {
        if (! empty($staffIds)) {
            User::whereIn('id', $staffIds)->update(['branch_id' => $branch->id]);
        }

        User::where('branch_id', $branch->id)
            ->whereNotIn('id', $staffIds)
            ->update(['branch_id' => null]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Branch')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('address')
                    ->label('Address')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('phone')
                    ->label('Phone')
                    ->placeholder('—'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBranches::route('/'),
            'create' => Pages\CreateBranch::route('/create'),
            'edit'   => Pages\EditBranch::route('/{record}/edit'),
        ];
    }
}
