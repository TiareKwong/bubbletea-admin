<?php

namespace App\Filament\Resources\PromotionResource\Pages;

use App\Filament\Resources\PromotionResource;
use App\Models\Promotion;
use App\Services\PushNotificationService;
use Filament\Resources\Pages\CreateRecord;

class CreatePromotion extends CreateRecord
{
    protected static string $resource = PromotionResource::class;

    protected function afterCreate(): void
    {
        $promotion = $this->record;

        if ($promotion->status === 'Active') {
            Promotion::where('id', '!=', $promotion->id)
                ->where('status', 'Active')
                ->update(['status' => 'Inactive']);

            PushNotificationService::broadcastAll(
                '🎉 New Promotion!',
                $promotion->title . ' — ' . $promotion->description,
            );
        }
    }
}
