<?php

namespace App\Filament\Resources\ReconciliationHistoryResource\Pages;

use App\Filament\Resources\ReconciliationHistoryResource;
use App\Services\BranchContext;
use Filament\Resources\Pages\ListRecords;

class ListReconciliationHistory extends ListRecords
{
    protected static string $resource = ReconciliationHistoryResource::class;

    public function getTitle(): string
    {
        $branch = app(BranchContext::class)->getBranch();
        return $branch ? 'Reconciliation History — ' . $branch->name : 'Reconciliation History';
    }
}
