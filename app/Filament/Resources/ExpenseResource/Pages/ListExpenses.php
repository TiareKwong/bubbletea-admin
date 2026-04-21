<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use App\Services\BranchContext;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    public function getTitle(): string
    {
        $branch = app(BranchContext::class)->getBranch();
        return $branch ? 'Expenses — ' . $branch->name : 'Expenses';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Log Expense'),
        ];
    }
}
