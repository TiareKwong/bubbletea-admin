<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WalletTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'walletTransactions';

    protected static ?string $title = 'Wallet Transactions';

    protected static bool $shouldSkipAuthorization = true;

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date & Time')
                    ->dateTime('d M Y, h:i A')
                    ->timezone('Pacific/Tarawa')
                    ->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'topup'    => 'success',
                        'deduct'   => 'danger',
                        'reversal' => 'warning',
                        default    => 'gray',
                    }),

                TextColumn::make('amount')
                    ->formatStateUsing(fn ($state, $record): string =>
                        ($record->type === 'topup' ? '+' : '−') . 'A$' . number_format(abs((float) $state), 2)
                    )
                    ->color(fn ($record): string => $record->type === 'topup' ? 'success' : 'danger')
                    ->weight('bold'),

                TextColumn::make('reference')
                    ->placeholder('—')
                    ->limit(30),

                TextColumn::make('notes')
                    ->placeholder('—')
                    ->limit(40),

                TextColumn::make('actioned_by')
                    ->label('By')
                    ->placeholder('—'),
            ])
            ->paginated([10, 25])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
