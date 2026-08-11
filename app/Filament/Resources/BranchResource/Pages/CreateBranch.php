<?php

namespace App\Filament\Resources\BranchResource\Pages;

use App\Filament\Resources\BranchResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBranch extends CreateRecord
{
    protected static string $resource = BranchResource::class;

    protected ?array $pendingStaffIds = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingStaffIds = $data['staff_ids'] ?? [];
        unset($data['staff_ids']);

        return $data;
    }

    protected function afterCreate(): void
    {
        BranchResource::syncBranchStaff($this->record, $this->pendingStaffIds ?? []);
    }
}
