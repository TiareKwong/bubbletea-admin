<?php

namespace App\Filament\Resources\FlavorResource\Pages;

use App\Filament\Resources\FlavorResource;
use App\Support\ImageUploader;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditFlavor extends EditRecord
{
    protected static string $resource = FlavorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('removeImage')
                ->label('Remove Image')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn () => (bool) $this->record->image_url && auth()->user()?->is_admin)
                ->requiresConfirmation()
                ->modalHeading('Remove image')
                ->modalDescription('This will permanently delete the image from the server. Are you sure?')
                ->action(function (): void {
                    ImageUploader::delete($this->record->image_url);
                    $this->record->image_url = null;
                    $this->record->save();
                    Notification::make()->title('Image removed')->success()->send();
                    $this->refreshFormData(['image_url']);
                }),

            Actions\DeleteAction::make()
                ->visible(fn () => (bool) auth()->user()?->is_admin),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // A new file was uploaded — delete the old image from DigitalOcean
        if (! empty($data['image_url']) && $data['image_url'] !== $this->record->image_url) {
            ImageUploader::delete($this->record->image_url);
        }

        // No new file uploaded — keep the existing URL
        if (empty($data['image_url'])) {
            $data['image_url'] = $this->record->image_url;
        }

        return $data;
    }
}
