<?php

namespace App\Filament\Resources\CupTypeResource\Pages;

use App\Filament\Resources\CupTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCupTypes extends ListRecords
{
    protected static string $resource = CupTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
