<?php

namespace App\Filament\Resources\WalletTopupRequests\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WalletTopupRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Wallet Top-up Request')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.first_name')
                            ->label('Customer')
                            ->formatStateUsing(fn ($state, $record) => trim(($record->user?->first_name ?? '') . ' ' . ($record->user?->last_name ?? ''))),

                        TextEntry::make('payment_method')
                            ->label('Payment Method'),

                        TextEntry::make('amount')
                            ->label('Amount')
                            ->money('AUD'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'Approved' => 'success',
                                'Rejected' => 'danger',
                                default    => 'warning',
                            }),

                        TextEntry::make('actioned_by')
                            ->label('Actioned By')
                            ->placeholder('—'),

                        TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('created_at')
                            ->label('Requested At')
                            ->dateTime('d M Y, h:i A')
                            ->timezone('Pacific/Tarawa'),

                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime('d M Y, h:i A')
                            ->timezone('Pacific/Tarawa'),
                    ]),
            ]);
    }
}
