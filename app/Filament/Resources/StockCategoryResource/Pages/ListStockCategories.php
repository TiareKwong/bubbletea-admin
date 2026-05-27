<?php

namespace App\Filament\Resources\StockCategoryResource\Pages;

use App\Filament\Resources\StockCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStockCategories extends ListRecords
{
    protected static string $resource = StockCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
