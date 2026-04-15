<?php

namespace App\Filament\Resources\AnnouncementResource\Pages;

use App\Filament\Resources\AnnouncementResource;
use App\Models\Announcement;
use Filament\Resources\Pages\CreateRecord;

class CreateAnnouncement extends CreateRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // If creating as active, deactivate all others first
        if (! empty($data['is_active'])) {
            Announcement::query()->update(['is_active' => false]);
        }
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
