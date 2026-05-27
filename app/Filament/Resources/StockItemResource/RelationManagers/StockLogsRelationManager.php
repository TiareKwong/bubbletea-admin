<?php

namespace App\Filament\Resources\StockItemResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StockLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    protected static ?string $title = 'Movement History';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('logged_at', 'desc')
            ->columns([
                TextColumn::make('logged_at')
                    ->label('Date & Time')
                    ->dateTime('d M Y, h:i A')
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
            ]);
    }
}
