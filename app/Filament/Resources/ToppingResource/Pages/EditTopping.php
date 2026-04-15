<?php

namespace App\Filament\Resources\ToppingResource\Pages;

use App\Filament\Resources\ToppingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTopping extends EditRecord
{
    protected static string $resource = ToppingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (empty($data['image_url'])) {
            $data['image_url'] = $this->record->image_url;
        }

        return $data;
    }
}
