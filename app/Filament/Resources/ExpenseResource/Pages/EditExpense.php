<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use Filament\Resources\Pages\EditRecord;

class EditExpense extends EditRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['purchased_by'] = $data['purchased_by'] ?: null;
        return $data;
    }
}
