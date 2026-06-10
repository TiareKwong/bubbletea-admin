<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockLogResource\Pages;
use App\Models\Branch;
use App\Models\StockItem;
use App\Models\StockLog;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockLogResource extends Resource
{
    protected static ?string $model = StockLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Stock History';

    protected static string|\UnitEnum|null $navigationGroup = 'Stock';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Movement';

    public static function canViewAny(): bool
    {
        return (bool) (auth()->user()?->is_admin || auth()->user()?->is_staff);
    }

    public static function canCreate(): bool   { return false; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool   { return false; }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('logged_at', 'desc')
            ->columns([
                TextColumn::make('logged_at')
                    ->label('Date & Time')
                    ->dateTime('d M Y, h:i A')
                    ->timezone('Pacific/Tarawa')
                    ->sortable(),

                TextColumn::make('stockItem.name')
                    ->label('Item')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'received'   => 'success',
                        'dispatched' => 'info',
                        'recount'    => 'gray',
                        'damaged'    => 'danger',
                        'expired'    => 'warning',
                        default      => 'gray',
                    }),

                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->formatStateUsing(function ($state, StockLog $record): string {
                        $qty = rtrim(rtrim(number_format((float) $state, 2), '0'), '.');
                        $unit = $record->stockItem?->unit ?? '';
                        return match ($record->type) {
                            'received' => '+' . $qty . ' ' . $unit,
                            'recount'  => $qty . ' ' . $unit,
                            default    => '−' . $qty . ' ' . $unit,
                        };
                    })
                    ->color(fn (StockLog $record): string => match ($record->type) {
                        'received' => 'success',
                        'recount'  => 'gray',
                        default    => 'danger',
                    })
                    ->weight('bold'),

                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('notes')
                    ->label('Notes')
                    ->placeholder('—')
                    ->limit(50),

                TextColumn::make('created_by')
                    ->label('By'),
            ])
            ->filters([
                Filter::make('logged_at')
                    ->label('Date Range')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'],  fn ($q, $v) => $q->whereDate('logged_at', '>=', $v))
                            ->when($data['until'], fn ($q, $v) => $q->whereDate('logged_at', '<=', $v));
                    })
                    ->columns(2),

                SelectFilter::make('stock_item_id')
                    ->label('Item')
                    ->placeholder('All Items')
                    ->native(false)
                    ->options(StockItem::orderBy('name')->pluck('name', 'id')),

                SelectFilter::make('type')
                    ->label('Type')
                    ->placeholder('All Types')
                    ->native(false)
                    ->options([
                        'received'   => 'Received',
                        'dispatched' => 'Dispatched',
                        'recount'    => 'Recount',
                        'damaged'    => 'Damaged',
                        'expired'    => 'Expired',
                    ]),

                SelectFilter::make('branch_id')
                    ->label('Branch')
                    ->placeholder('All Branches')
                    ->native(false)
                    ->options(Branch::where('is_active', true)->pluck('name', 'id')),

            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->deferFilters(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockLogs::route('/'),
        ];
    }
}
