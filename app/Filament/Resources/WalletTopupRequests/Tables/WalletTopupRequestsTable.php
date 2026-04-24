<?php

namespace App\Filament\Resources\WalletTopupRequests\Tables;

use App\Models\Branch;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WalletTopupRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('user.first_name')
                    ->label('Customer')
                    ->formatStateUsing(fn ($state, $record) => trim(($record->user?->first_name ?? '') . ' ' . ($record->user?->last_name ?? '')))
                    ->searchable(query: fn ($query, $search) => $query->whereHas('user', fn ($q) => $q->where('first_name', 'like', "%$search%")->orWhere('last_name', 'like', "%$search%"))),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('AUD')
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->label('Payment Method'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Approved' => 'success',
                        'Rejected' => 'danger',
                        default    => 'warning',
                    }),

                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('actioned_by')
                    ->label('Actioned By')
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime('d M Y, h:i A')
                    ->timezone('Pacific/Tarawa')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'Pending'  => 'Pending',
                        'Approved' => 'Approved',
                        'Rejected' => 'Rejected',
                    ]),

                SelectFilter::make('branch_id')
                    ->label('Branch')
                    ->options(fn () => Branch::orderBy('name')->pluck('name', 'id')->toArray())
                    ->placeholder('All Branches'),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
