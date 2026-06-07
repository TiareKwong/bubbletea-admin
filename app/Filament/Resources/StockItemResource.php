<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockItemResource\Pages;
use App\Filament\Resources\StockItemResource\RelationManagers\StockBatchesRelationManager;
use App\Filament\Resources\StockItemResource\RelationManagers\StockLogsRelationManager;
use App\Models\Branch;
use App\Models\StockBatch;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockLog;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use App\Notifications\LowStockAlert;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Illuminate\Notifications\AnonymousNotifiable;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockItemResource extends Resource
{
    protected static ?string $model = StockItem::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationLabel = 'Stock';

    protected static string|\UnitEnum|null $navigationGroup = 'Stock';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return (bool) (auth()->user()?->is_admin || auth()->user()?->is_staff);
    }

    public static function canCreate(): bool
    {
        return (bool) (auth()->user()?->is_admin || auth()->user()?->is_staff);
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return (bool) (auth()->user()?->is_admin || auth()->user()?->is_staff);
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
                ->maxLength(255),

            Select::make('category')
                ->required()
                ->options(self::categories()),

            Select::make('unit')
                ->required()
                ->options(self::units()),

            TextInput::make('min_quantity')
                ->label('Plane Reorder Point')
                ->helperText('Order urgently by Plane when stock reaches this level.')
                ->required()
                ->numeric()
                ->minValue(0)
                ->step(0.01)
                ->default(0),

            TextInput::make('sea_reorder_quantity')
                ->label('Sea Reorder Point')
                ->helperText('Order by sea shipment when stock reaches this level.')
                ->required()
                ->numeric()
                ->minValue(0)
                ->step(0.01)
                ->default(0),

            Textarea::make('notes')
                ->rows(2)
                ->nullable(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(3)
                ->schema([
                    TextEntry::make('name')
                        ->label('Item')
                        ->weight(FontWeight::Bold)
                        ->columnSpan(2),

                    TextEntry::make('category')
                        ->label('Category')
                        ->badge(),
                ]),

            Section::make()
                ->columns(5)
                ->schema([
                    TextEntry::make('current_quantity')
                        ->label('In Storage')
                        ->formatStateUsing(fn ($state, StockItem $record) => rtrim(rtrim(number_format((float) $state, 2), '0'), '.') . ' ' . $record->unit)
                        ->color(fn (StockItem $record) => match (true) {
                            $record->isOutOfStock() => 'danger',
                            $record->isLowStock()   => 'danger',
                            $record->isOrderBySea() => 'warning',
                            default                 => 'success',
                        })
                        ->weight(FontWeight::Bold),

                    TextEntry::make('stock_status')
                        ->label('Status')
                        ->getStateUsing(fn (StockItem $record) => match (true) {
                            $record->isOutOfStock() => 'Out of Stock',
                            $record->isLowStock()   => 'Order by Plane',
                            $record->isOrderBySea() => 'Order by Sea',
                            default                 => 'In Stock',
                        })
                        ->badge()
                        ->color(fn (string $state) => match ($state) {
                            'Out of Stock'  => 'danger',
                            'Order by Plane'  => 'danger',
                            'Order by Sea'  => 'warning',
                            default         => 'success',
                        }),

                    TextEntry::make('min_quantity')
                        ->label('Plane Reorder At')
                        ->formatStateUsing(fn ($state, StockItem $record) => rtrim(rtrim(number_format((float) $state, 2), '0'), '.') . ' ' . $record->unit)
                        ->color('gray'),

                    TextEntry::make('sea_reorder_quantity')
                        ->label('Sea Reorder At')
                        ->formatStateUsing(fn ($state, StockItem $record) => rtrim(rtrim(number_format((float) $state, 2), '0'), '.') . ' ' . $record->unit)
                        ->color('gray'),

                    TextEntry::make('nearest_expiry_date')
                        ->label('Earliest Expiry')
                        ->date('d M Y')
                        ->placeholder('—')
                        ->color(fn (StockItem $record) => match (true) {
                            $record->nearest_expiry_date === null => null,
                            $record->isExpired()                 => 'danger',
                            $record->isExpiringSoon()            => 'warning',
                            default                              => null,
                        }),
                ]),

            Section::make()
                ->schema([
                    TextEntry::make('notes')
                        ->label('Notes')
                        ->placeholder('—'),
                ])
                ->hidden(fn (StockItem $record) => blank($record->notes)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('category')
                    ->badge()
                    ->sortable(),

                TextColumn::make('current_quantity')
                    ->label('In Storage')
                    ->formatStateUsing(fn ($state, StockItem $record) => rtrim(rtrim(number_format((float) $state, 2), '0'), '.') . ' ' . $record->unit)
                    ->color(fn (StockItem $record) => match (true) {
                        $record->isOutOfStock() => 'danger',
                        $record->isLowStock()   => 'danger',
                        $record->isOrderBySea() => 'warning',
                        default                 => 'success',
                    })
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('min_quantity')
                    ->label('Plane Reorder At')
                    ->formatStateUsing(fn ($state, StockItem $record) => rtrim(rtrim(number_format((float) $state, 2), '0'), '.') . ' ' . $record->unit)
                    ->color('gray'),

                TextColumn::make('stock_status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (StockItem $record) => match (true) {
                        $record->isOutOfStock() => 'Out of Stock',
                        $record->isLowStock()   => 'Order by Plane',
                        $record->isOrderBySea() => 'Order by Sea',
                        default                 => 'In Stock',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Out of Stock'  => 'danger',
                        'Order by Plane'  => 'danger',
                        'Order by Sea'  => 'warning',
                        default         => 'success',
                    }),

                TextColumn::make('nearest_expiry_date')
                    ->label('Earliest Expiry')
                    ->date('d M Y')
                    ->color(fn (StockItem $record) => match (true) {
                        $record->nearest_expiry_date === null => 'gray',
                        $record->isExpired()                 => 'danger',
                        $record->isExpiringSoon()            => 'warning',
                        default                              => 'gray',
                    })
                    ->placeholder('—'),
            ])
            ->defaultSort('name')
            ->recordUrl(fn (StockItem $record) => self::getUrl('view', ['record' => $record]))
            ->filters([
                SelectFilter::make('category')
                    ->options(self::categories()),

                SelectFilter::make('stock_status')
                    ->label('Status')
                    ->options([
                        'dhl' => 'Order by Plane',
                        'sea' => 'Order by Sea',
                        'out' => 'Out of Stock',
                    ])
                    ->query(function ($query, array $data) {
                        if ($data['value'] === 'dhl') {
                            $query->whereRaw('current_quantity > 0 AND current_quantity <= min_quantity');
                        } elseif ($data['value'] === 'sea') {
                            $query->whereRaw('sea_reorder_quantity > 0 AND current_quantity > min_quantity AND current_quantity <= sea_reorder_quantity');
                        } elseif ($data['value'] === 'out') {
                            $query->where('current_quantity', '<=', 0);
                        }
                    }),
            ])
            ->actions([
                Action::make('receive')
                    ->label('Receive')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->form([
                        TextInput::make('quantity')
                            ->label('Quantity Received')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01),

                        DatePicker::make('expiry_date')
                            ->label('Expiry Date (optional)')
                            ->helperText('Fill in if this batch has an expiry date.'),

                        Textarea::make('notes')
                            ->rows(2)
                            ->nullable(),
                    ])
                    ->action(function (StockItem $record, array $data): void {
                        StockBatch::create([
                            'stock_item_id'    => $record->id,
                            'quantity'         => $data['quantity'],
                            'initial_quantity' => $data['quantity'],
                            'expiry_date'      => $data['expiry_date'] ?? null,
                            'notes'            => $data['notes'] ?? null,
                            'received_at'      => now(),
                            'created_by'       => auth()->user()->getFilamentName(),
                        ]);

                        StockLog::create([
                            'stock_item_id' => $record->id,
                            'type'          => 'received',
                            'quantity'      => $data['quantity'],
                            'notes'         => $data['notes'] ?? null,
                            'created_by'    => auth()->user()->getFilamentName(),
                            'logged_at'     => now(),
                        ]);

                        $record->syncFromBatches();
                        Notification::make()->title('Stock received')->success()->send();
                    }),

                Action::make('dispatch')
                    ->label('Dispatch')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('info')
                    ->form([
                        TextInput::make('quantity')
                            ->label('Quantity Dispatched')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01),

                        Select::make('branch_id')
                            ->label('Branch')
                            ->options(Branch::where('is_active', true)->pluck('name', 'id'))
                            ->nullable(),

                        Textarea::make('notes')
                            ->rows(2)
                            ->nullable(),
                    ])
                    ->action(function (StockItem $record, array $data): void {
                        $before = (float) $record->current_quantity;

                        if ((float) $data['quantity'] > $before) {
                            Notification::make()
                                ->title('Not enough stock')
                                ->body('Only ' . rtrim(rtrim(number_format($before, 2), '0'), '.') . ' ' . $record->unit . ' available.')
                                ->danger()
                                ->send();
                            return;
                        }

                        self::deductFifo($record, (float) $data['quantity']);

                        StockLog::create([
                            'stock_item_id' => $record->id,
                            'type'          => 'dispatched',
                            'quantity'      => $data['quantity'],
                            'branch_id'     => $data['branch_id'] ?? null,
                            'notes'         => $data['notes'] ?? null,
                            'created_by'    => auth()->user()->getFilamentName(),
                            'logged_at'     => now(),
                        ]);

                        $record->syncFromBatches();
                        self::maybeSendLowStockAlert($record, $before);
                        Notification::make()->title('Stock dispatched to branch')->success()->send();
                    }),

                ActionGroup::make([
                    Action::make('recount')
                        ->label('Recount')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->color('gray')
                        ->form([
                            TextInput::make('quantity')
                                ->label('Actual Count')
                                ->helperText('Enter the exact quantity you physically counted.')
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->step(0.01),

                            Textarea::make('notes')
                                ->rows(2)
                                ->nullable(),
                        ])
                        ->action(function (StockItem $record, array $data): void {
                            $before = (float) $record->current_quantity;
                            $newQty = (float) $data['quantity'];
                            $diff   = $newQty - $before;

                            if ($diff < 0) {
                                self::deductFifo($record, abs($diff));
                            } elseif ($diff > 0) {
                                $latest = $record->activeBatches()->latest('received_at')->first();
                                if ($latest) {
                                    $latest->increment('quantity', $diff);
                                } else {
                                    StockBatch::create([
                                        'stock_item_id'    => $record->id,
                                        'quantity'         => $newQty,
                                        'initial_quantity' => $newQty,
                                        'expiry_date'      => null,
                                        'received_at'      => now(),
                                        'created_by'       => auth()->user()->getFilamentName(),
                                    ]);
                                }
                            }

                            StockLog::create([
                                'stock_item_id' => $record->id,
                                'type'          => 'recount',
                                'quantity'      => $data['quantity'],
                                'notes'         => $data['notes'] ?? null,
                                'created_by'    => auth()->user()->getFilamentName(),
                                'logged_at'     => now(),
                            ]);

                            $record->syncFromBatches();
                            self::maybeSendLowStockAlert($record, $before);
                            Notification::make()->title('Stock recount saved')->success()->send();
                        }),

                    Action::make('damaged')
                        ->label('Damaged')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->form(fn (StockItem $record): array => [
                            Select::make('batch_id')
                                ->label('Batch')
                                ->options(
                                    $record->activeBatches()->get()
                                        ->mapWithKeys(fn (StockBatch $batch) => [
                                            $batch->id => ($batch->expiry_date
                                                ? $batch->expiry_date->format('d M Y')
                                                : 'No expiry date') .
                                                ' — ' .
                                                rtrim(rtrim(number_format((float) $batch->quantity, 2), '0'), '.') .
                                                ' ' . $record->unit . ' remaining',
                                        ])
                                        ->toArray()
                                )
                                ->required(),

                            TextInput::make('quantity')
                                ->label('Quantity Damaged')
                                ->required()
                                ->numeric()
                                ->minValue(0.01)
                                ->step(0.01),

                            Textarea::make('notes')
                                ->rows(2)
                                ->nullable(),
                        ])
                        ->action(function (StockItem $record, array $data): void {
                            $batch = StockBatch::find($data['batch_id']);

                            if (! $batch || (float) $data['quantity'] > (float) $batch->quantity) {
                                Notification::make()
                                    ->title('Quantity exceeds batch stock')
                                    ->body('This batch only has ' . rtrim(rtrim(number_format((float) ($batch?->quantity ?? 0), 2), '0'), '.') . ' ' . $record->unit . ' remaining.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            $before = (float) $record->current_quantity;
                            $batch->decrement('quantity', $data['quantity']);

                            StockLog::create([
                                'stock_item_id' => $record->id,
                                'type'          => 'damaged',
                                'quantity'      => $data['quantity'],
                                'notes'         => $data['notes'] ?? null,
                                'created_by'    => auth()->user()->getFilamentName(),
                                'logged_at'     => now(),
                            ]);

                            $record->syncFromBatches();
                            self::maybeSendLowStockAlert($record, $before);
                            Notification::make()->title('Damaged stock recorded')->warning()->send();
                        }),

                    Action::make('expired')
                        ->label('Expired')
                        ->icon('heroicon-o-clock')
                        ->color('warning')
                        ->form(fn (StockItem $record): array => [
                            Select::make('batch_id')
                                ->label('Batch')
                                ->options(
                                    $record->activeBatches()->get()
                                        ->mapWithKeys(fn (StockBatch $batch) => [
                                            $batch->id => ($batch->expiry_date
                                                ? $batch->expiry_date->format('d M Y')
                                                : 'No expiry date') .
                                                ' — ' .
                                                rtrim(rtrim(number_format((float) $batch->quantity, 2), '0'), '.') .
                                                ' ' . $record->unit . ' remaining',
                                        ])
                                        ->toArray()
                                )
                                ->required(),

                            TextInput::make('quantity')
                                ->label('Quantity Expired')
                                ->required()
                                ->numeric()
                                ->minValue(0.01)
                                ->step(0.01),

                            Textarea::make('notes')
                                ->rows(2)
                                ->nullable(),
                        ])
                        ->action(function (StockItem $record, array $data): void {
                            $batch = StockBatch::find($data['batch_id']);

                            if (! $batch || (float) $data['quantity'] > (float) $batch->quantity) {
                                Notification::make()
                                    ->title('Quantity exceeds batch stock')
                                    ->body('This batch only has ' . rtrim(rtrim(number_format((float) ($batch?->quantity ?? 0), 2), '0'), '.') . ' ' . $record->unit . ' remaining.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            $before = (float) $record->current_quantity;
                            $batch->decrement('quantity', $data['quantity']);

                            StockLog::create([
                                'stock_item_id' => $record->id,
                                'type'          => 'expired',
                                'quantity'      => $data['quantity'],
                                'notes'         => $data['notes'] ?? null,
                                'created_by'    => auth()->user()->getFilamentName(),
                                'logged_at'     => now(),
                            ]);

                            $record->syncFromBatches();
                            self::maybeSendLowStockAlert($record, $before);
                            Notification::make()->title('Expired stock removed')->warning()->send();
                        }),

                    EditAction::make(),
                    DeleteAction::make()
                        ->visible(fn () => (bool) auth()->user()?->is_admin),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            StockLogsRelationManager::class,
            StockBatchesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListStockItems::route('/'),
            'create' => Pages\CreateStockItem::route('/create'),
            'view'   => Pages\ViewStockItem::route('/{record}'),
            'edit'   => Pages\EditStockItem::route('/{record}/edit'),
        ];
    }

    private static function deductFifo(StockItem $record, float $quantity): void
    {
        $remaining = $quantity;
        foreach ($record->activeBatches()->get() as $batch) {
            if ($remaining <= 0) break;
            $batchQty  = (float) $batch->quantity;
            $deduct    = min($batchQty, $remaining);
            $batch->decrement('quantity', $deduct);
            $remaining -= $deduct;
        }
    }

    private static function maybeSendLowStockAlert(StockItem $record, float $quantityBefore): void
    {
        $record->refresh();
        $crossedThreshold = $quantityBefore > $record->min_quantity
            && $record->current_quantity <= $record->min_quantity;

        if ($crossedThreshold) {
            (new AnonymousNotifiable)->notify(new LowStockAlert($record));
        }
    }

    private static function categories(): array
    {
        return StockCategory::orderBy('name')->pluck('name', 'name')->toArray();
    }

    private static function units(): array
    {
        return [
            'Bags'    => 'Bags',
            'Bottles' => 'Bottles',
            'Boxes'   => 'Boxes',
            'Drums'   => 'Drums',
            'Pieces'  => 'Pieces',
            'Sachets' => 'Sachets',
            'Rolls'   => 'Rolls',
            'Sleeves' => 'Sleeves',
            'Tubs'    => 'Tubs',
            'kg'      => 'kg',
            'Litres'  => 'Litres',
            'Other'   => 'Other',
        ];
    }
}
