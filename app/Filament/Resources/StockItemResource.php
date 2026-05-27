<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockItemResource\Pages;
use App\Filament\Resources\StockItemResource\RelationManagers\StockLogsRelationManager;
use App\Models\Branch;
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
use Filament\Notifications\Notification;
use Illuminate\Notifications\AnonymousNotifiable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
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
        return (bool) auth()->user()?->is_admin;
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
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
                ->maxLength(255),

            Select::make('category')
                ->required()
                ->options(self::categories()),

            Select::make('unit')
                ->required()
                ->options(self::units()),

            TextInput::make('min_quantity')
                ->label('Reorder Point')
                ->helperText('Alert when stock falls to or below this number.')
                ->required()
                ->numeric()
                ->minValue(0)
                ->step(0.01)
                ->default(0),

            DatePicker::make('nearest_expiry_date')
                ->label('Nearest Expiry Date')
                ->helperText('Update this whenever you receive new stock with an expiry date.')
                ->nullable(),

            Textarea::make('notes')
                ->rows(2)
                ->nullable(),
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
                        $record->isLowStock()   => 'warning',
                        default                 => 'success',
                    })
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('min_quantity')
                    ->label('Reorder At')
                    ->formatStateUsing(fn ($state, StockItem $record) => rtrim(rtrim(number_format((float) $state, 2), '0'), '.') . ' ' . $record->unit)
                    ->color('gray'),

                TextColumn::make('stock_status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (StockItem $record) => match (true) {
                        $record->isOutOfStock() => 'Out of Stock',
                        $record->isLowStock()   => 'Low Stock',
                        default                 => 'In Stock',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Out of Stock' => 'danger',
                        'Low Stock'    => 'warning',
                        default        => 'success',
                    }),

                TextColumn::make('nearest_expiry_date')
                    ->label('Expires')
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
            ->filters([
                SelectFilter::make('category')
                    ->options(self::categories()),

                SelectFilter::make('stock_status')
                    ->label('Status')
                    ->options([
                        'low' => 'Low Stock',
                        'out' => 'Out of Stock',
                    ])
                    ->query(function ($query, array $data) {
                        if ($data['value'] === 'low') {
                            $query->whereRaw('current_quantity > 0 AND current_quantity <= min_quantity');
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
                        $record->increment('current_quantity', $data['quantity']);

                        if (! empty($data['expiry_date'])) {
                            $existing = $record->nearest_expiry_date;
                            $incoming = \Carbon\Carbon::parse($data['expiry_date']);
                            if ($existing === null || $incoming->lt($existing)) {
                                $record->update(['nearest_expiry_date' => $incoming]);
                            }
                        }

                        StockLog::create([
                            'stock_item_id' => $record->id,
                            'type'          => 'received',
                            'quantity'      => $data['quantity'],
                            'notes'         => $data['notes'] ?? null,
                            'created_by'    => auth()->user()->getFilamentName(),
                            'logged_at'     => now(),
                        ]);

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
                        $record->decrement('current_quantity', $data['quantity']);

                        StockLog::create([
                            'stock_item_id' => $record->id,
                            'type'          => 'dispatched',
                            'quantity'      => $data['quantity'],
                            'branch_id'     => $data['branch_id'] ?? null,
                            'notes'         => $data['notes'] ?? null,
                            'created_by'    => auth()->user()->getFilamentName(),
                            'logged_at'     => now(),
                        ]);

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
                            $record->update(['current_quantity' => $data['quantity']]);

                            StockLog::create([
                                'stock_item_id' => $record->id,
                                'type'          => 'recount',
                                'quantity'      => $data['quantity'],
                                'notes'         => $data['notes'] ?? null,
                                'created_by'    => auth()->user()->getFilamentName(),
                                'logged_at'     => now(),
                            ]);

                            Notification::make()->title('Stock recount saved')->success()->send();
                        }),

                    Action::make('damaged')
                        ->label('Damaged')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->form([
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
                            $before = (float) $record->current_quantity;
                            $record->decrement('current_quantity', $data['quantity']);

                            StockLog::create([
                                'stock_item_id' => $record->id,
                                'type'          => 'damaged',
                                'quantity'      => $data['quantity'],
                                'notes'         => $data['notes'] ?? null,
                                'created_by'    => auth()->user()->getFilamentName(),
                                'logged_at'     => now(),
                            ]);

                            self::maybeSendLowStockAlert($record, $before);
                            Notification::make()->title('Damaged stock recorded')->warning()->send();
                        }),

                    Action::make('expired')
                        ->label('Expired')
                        ->icon('heroicon-o-clock')
                        ->color('warning')
                        ->form([
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
                            $before = (float) $record->current_quantity;
                            $record->decrement('current_quantity', $data['quantity']);
                            $record->update(['nearest_expiry_date' => null]);

                            StockLog::create([
                                'stock_item_id' => $record->id,
                                'type'          => 'expired',
                                'quantity'      => $data['quantity'],
                                'notes'         => $data['notes'] ?? null,
                                'created_by'    => auth()->user()->getFilamentName(),
                                'logged_at'     => now(),
                            ]);

                            self::maybeSendLowStockAlert($record, $before);
                            Notification::make()->title('Expired stock removed')->warning()->send();
                        }),

                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }

    public static function getRelationManagers(): array
    {
        return [
            StockLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListStockItems::route('/'),
            'create' => Pages\CreateStockItem::route('/create'),
            'edit'   => Pages\EditStockItem::route('/{record}/edit'),
        ];
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
            'Boxes'   => 'Boxes',
            'Pieces'  => 'Pieces',
            'Sachets' => 'Sachets',
            'Rolls'   => 'Rolls',
            'kg'      => 'kg',
            'Litres'  => 'Litres',
            'Other'   => 'Other',
        ];
    }
}
