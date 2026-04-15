<?php

namespace App\Observers;

use App\Models\Flavor;
use App\Services\PushNotificationService;

class FlavorObserver
{
    public function created(Flavor $flavor): void
    {
        if ($flavor->status === 'Available') {
            PushNotificationService::broadcastAll(
                '🧋 New Flavor Alert!',
                "{$flavor->name} is now available. Order yours today!"
            );
        }
    }

    public function updated(Flavor $flavor): void
    {
        // Only notify when status changes TO Available (not on every save)
        if ($flavor->wasChanged('status') && $flavor->status === 'Available') {
            PushNotificationService::broadcastAll(
                '🧋 Back in Stock!',
                "{$flavor->name} is now available again. Don't miss out!"
            );
        }
    }
}
