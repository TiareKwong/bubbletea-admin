<?php

namespace App\Filament\Resources\WalletTopupRequests;

use App\Filament\Resources\WalletTopupRequests\Pages\ListWalletTopupRequests;
use App\Filament\Resources\WalletTopupRequests\Pages\ViewWalletTopupRequest;
use App\Filament\Resources\WalletTopupRequests\Schemas\WalletTopupRequestInfolist;
use App\Filament\Resources\WalletTopupRequests\Tables\WalletTopupRequestsTable;
use App\Models\WalletTopupRequest;
use App\Services\BranchContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WalletTopupRequestResource extends Resource
{
    protected static ?string $model = WalletTopupRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static ?string $navigationLabel = 'Wallet Top-ups';

    protected static string|\UnitEnum|null $navigationGroup = 'Rewards';

    protected static ?int $navigationSort = 3;

    public static function infolist(Schema $schema): Schema
    {
        return WalletTopupRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WalletTopupRequestsTable::configure($table);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        return app(BranchContext::class)->applyTo($query);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWalletTopupRequests::route('/'),
            'view'  => ViewWalletTopupRequest::route('/{record}'),
        ];
    }
}
