<?php

namespace App\Filament\Resources\BranchResource\Pages;

use App\Filament\Resources\BranchResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;

class EditBranch extends EditRecord
{
    protected static string $resource = BranchResource::class;

    protected ?array $pendingStaffIds = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['staff_ids'] = User::where('branch_id', $this->record->id)->pluck('id')->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingStaffIds = $data['staff_ids'] ?? [];
        unset($data['staff_ids']);

        return $data;
    }

    protected function afterSave(): void
    {
        BranchResource::syncBranchStaff($this->record, $this->pendingStaffIds ?? []);
    }
}
