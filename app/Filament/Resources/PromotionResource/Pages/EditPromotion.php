<?php

namespace App\Filament\Resources\PromotionResource\Pages;

use App\Filament\Resources\PromotionResource;
use App\Services\PushNotificationService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPromotion extends EditRecord
{
    protected static string $resource = PromotionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Don't pass the existing URL into the FileUpload — it's shown via Placeholder.
        // Clearing it here means FileUpload starts empty; mutateFormDataBeforeSave restores it.
        $data['image_url'] = null;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (empty($data['image_url'])) {
            $data['image_url'] = $this->record->image_url;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $wasInactive = $this->record->getOriginal('status') === 'Inactive';
        $isNowActive = $this->record->status === 'Active';

        if ($wasInactive && $isNowActive) {
            \App\Models\Promotion::where('id', '!=', $this->record->id)
                ->where('status', 'Active')
                ->update(['status' => 'Inactive']);

            PushNotificationService::broadcastAll(
                '🎉 New Promotion!',
                $this->record->title . ' — ' . $this->record->description,
            );
        }
    }
}
