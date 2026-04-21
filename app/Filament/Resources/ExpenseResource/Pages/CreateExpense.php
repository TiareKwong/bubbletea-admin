<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use App\Services\BranchContext;
use Filament\Resources\Pages\CreateRecord;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->user()->getFilamentName();
        // If staff didn't specify who purchased it, leave it null.
        $data['purchased_by'] = $data['purchased_by'] ?: null;
        // Tag the expense with the active branch so it shows in the Sales Report.
        $data['branch_id'] = app(BranchContext::class)->getId();
        return $data;
    }
}
