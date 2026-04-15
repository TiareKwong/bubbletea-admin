<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->user()->getFilamentName();
        // If staff didn't specify who purchased it, leave it null.
        $data['purchased_by'] = $data['purchased_by'] ?: null;
        return $data;
    }
}
