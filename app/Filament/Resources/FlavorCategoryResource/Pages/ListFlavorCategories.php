<?php

namespace App\Filament\Resources\FlavorCategoryResource\Pages;

use App\Filament\Resources\FlavorCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFlavorCategories extends ListRecords
{
    protected static string $resource = FlavorCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
