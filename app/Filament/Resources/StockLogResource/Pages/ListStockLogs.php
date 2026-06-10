<?php

namespace App\Filament\Resources\StockLogResource\Pages;

use App\Filament\Resources\StockLogResource;
use Filament\Resources\Pages\ListRecords;

class ListStockLogs extends ListRecords
{
    protected static string $resource = StockLogResource::class;

    public function getTitle(): string
    {
        return 'Stock History';
    }
}
