<?php

namespace App\Filament\Resources\StockItemResource\RelationManagers;

use App\Models\StockBatch;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class StockBatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'batches';

    protected static ?string $title = 'Active Batches';

    protected static bool $shouldSkipAuthorization = true;

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('quantity', '>', 0)->orderBy('received_at'))
            ->columns([
                TextColumn::make('expiry_date')
                    ->label('Expiry Date')
                    ->date('d M Y')
                    ->placeholder('No expiry date')
                    ->color(fn (StockBatch $record) => match (true) {
                        $record->expiry_date === null => 'gray',
                        $record->isExpired()         => 'danger',
                        $record->isExpiringSoon()    => 'warning',
                        default                      => null,
                    })
                    ->sortable(),

                TextColumn::make('quantity')
                    ->label('Remaining')
                    ->formatStateUsing(fn ($state, StockBatch $record) =>
                        rtrim(rtrim(number_format((float) $state, 2), '0'), '.') . ' ' . $record->stockItem->unit
                    )
                    ->color('success')
                    ->weight('bold'),

                TextColumn::make('initial_quantity')
                    ->label('Originally Received')
                    ->formatStateUsing(fn ($state, StockBatch $record) =>
                        rtrim(rtrim(number_format((float) $state, 2), '0'), '.') . ' ' . $record->stockItem->unit
                    )
                    ->color('gray'),

                TextColumn::make('received_at')
                    ->label('Received On')
                    ->dateTime('d M Y, h:i A')
                    ->timezone('Pacific/Tarawa')
                    ->sortable(),

                TextColumn::make('created_by')
                    ->label('Received By'),

                TextColumn::make('notes')
                    ->placeholder('—')
                    ->limit(40),
            ])
            ->actions([
                Action::make('edit_batch')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->form(fn (StockBatch $record): array => [
                        \Filament\Forms\Components\TextInput::make('quantity')
                            ->label('Quantity')
                            ->helperText('Correct this if the wrong quantity was entered.')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->default($record->quantity),

                        DatePicker::make('expiry_date')
                            ->label('Expiry Date')
                            ->helperText('Leave blank if this batch has no expiry date.')
                            ->default($record->expiry_date),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2)
                            ->default($record->notes),
                    ])
                    ->action(function (StockBatch $record, array $data): void {
                        $record->update([
                            'quantity'    => $data['quantity'],
                            'expiry_date' => $data['expiry_date'] ?? null,
                            'notes'       => $data['notes'] ?? null,
                        ]);

                        $record->stockItem->syncFromBatches();

                        Notification::make()->title('Batch updated')->success()->send();
                    }),

                Action::make('delete_batch')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn () => (bool) auth()->user()?->is_admin)
                    ->requiresConfirmation()
                    ->modalHeading('Delete Batch')
                    ->modalDescription('This will permanently remove the batch and adjust the stock total. Only do this if the batch was entered by mistake.')
                    ->modalSubmitActionLabel('Yes, delete it')
                    ->action(function (StockBatch $record): void {
                        $stockItem = $record->stockItem;
                        $record->delete();
                        $stockItem->syncFromBatches();

                        Notification::make()->title('Batch deleted')->success()->send();
                    }),
            ]);
    }
}
