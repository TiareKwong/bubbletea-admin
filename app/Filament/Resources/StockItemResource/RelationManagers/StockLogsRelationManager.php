<?php

namespace App\Filament\Resources\StockItemResource\RelationManagers;

use App\Models\StockBatch;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StockLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    protected static ?string $title = 'Movement History';

    protected static bool $shouldSkipAuthorization = true;

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('logged_at', 'desc')
            ->columns([
                TextColumn::make('logged_at')
                    ->label('Date & Time')
                    ->dateTime('d M Y, h:i A')
                    ->timezone('Pacific/Tarawa')
                    ->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'received'   => 'success',
                        'dispatched' => 'info',
                        'recount'    => 'gray',
                        'damaged'    => 'danger',
                        'expired'    => 'warning',
                        default      => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                TextColumn::make('quantity')
                    ->formatStateUsing(fn ($state, $record) => match ($record->type) {
                        'received'   => '+' . number_format($state, 2),
                        'recount'    => 'Count: ' . number_format($state, 2),
                        default      => '-' . number_format($state, 2),
                    })
                    ->color(fn ($record) => match ($record->type) {
                        'received' => 'success',
                        'recount'  => 'gray',
                        default    => 'danger',
                    })
                    ->weight('bold'),

                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->placeholder('—'),

                TextColumn::make('notes')
                    ->placeholder('—')
                    ->limit(50),

                TextColumn::make('created_by')
                    ->label('By'),
            ])
            ->actions([
                Action::make('delete_log')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn () => (bool) auth()->user()?->is_admin)
                    ->requiresConfirmation()
                    ->modalHeading('Delete Movement Record')
                    ->modalDescription(fn ($record) => match ($record->type) {
                        'received' => 'This will delete the log record. The batch quantity will not change — delete the batch directly if needed.',
                        'recount'  => 'This will delete the log record only. The current stock quantity will not change.',
                        default    => 'This will delete the log and add the quantity back to stock.',
                    })
                    ->modalSubmitActionLabel('Yes, delete it')
                    ->action(function ($record): void {
                        $stockItem = $record->stockItem;
                        $qty       = (float) $record->quantity;

                        // For dispatched, damaged, expired — add the quantity back
                        if (in_array($record->type, ['dispatched', 'damaged', 'expired'])) {
                            $latestBatch = $stockItem->activeBatches()->latest('received_at')->first();
                            if ($latestBatch) {
                                $latestBatch->increment('quantity', $qty);
                            } else {
                                StockBatch::create([
                                    'stock_item_id'    => $stockItem->id,
                                    'quantity'         => $qty,
                                    'initial_quantity' => $qty,
                                    'expiry_date'      => null,
                                    'received_at'      => now(),
                                    'created_by'       => auth()->user()->getFilamentName(),
                                    'notes'            => 'Restored from deleted movement log',
                                ]);
                            }
                            $stockItem->syncFromBatches();
                        }

                        $record->delete();

                        Notification::make()->title('Movement record deleted')->success()->send();
                    }),
            ]);
    }
}
