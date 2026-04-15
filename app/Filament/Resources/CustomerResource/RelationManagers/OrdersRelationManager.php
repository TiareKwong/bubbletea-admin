<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Order History';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order_code')
                    ->label('Code')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('total_price')
                    ->label('Total')
                    ->money('AUD'),

                TextColumn::make('payment_method')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Cash'          => 'success',
                        'Bank Transfer' => 'info',
                        'Points'        => 'warning',
                        'EFTPOS'        => 'primary',
                        default         => 'gray',
                    }),

                TextColumn::make('order_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Pending Payment'      => 'warning',
                        'Payment Verification' => 'info',
                        'Points Verification'  => 'info',
                        'Paid'                 => 'success',
                        'Preparing'            => 'primary',
                        'Ready'                => 'success',
                        'Cancelled'            => 'danger',
                        default                => 'gray',
                    }),

                TextColumn::make('points_earned')
                    ->label('Points Earned'),

                IconColumn::make('collected')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('gray'),

                TextColumn::make('created_at')
                    ->label('Placed')
                    ->dateTime()
                    ->sortable(),
            ])
            ->paginated([10, 25])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
