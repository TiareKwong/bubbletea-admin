<?php

namespace App\Services;

use App\Models\StaffPushSubscription;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use Illuminate\Support\Facades\Log;

class StaffPushService
{
    public static function notifyNewOrder(string $orderCode, int $branchId): void
    {
        $subscriptions = StaffPushSubscription::all();
        if ($subscriptions->isEmpty()) return;

        $push = new WebPush([
            'VAPID' => [
                'subject'    => config('services.vapid.subject'),
                'publicKey'  => config('services.vapid.public_key'),
                'privateKey' => config('services.vapid.private_key'),
            ],
        ]);

        $payload = json_encode([
            'title' => '🧋 New Order!',
            'body'  => "Order #{$orderCode} is ready to prepare.",
            'url'   => '/admin',
        ]);

        foreach ($subscriptions as $sub) {
            $push->queueNotification(
                Subscription::create([
                    'endpoint'        => $sub->endpoint,
                    'keys' => [
                        'p256dh' => $sub->p256dh,
                        'auth'   => $sub->auth,
                    ],
                ]),
                $payload
            );
        }

        foreach ($push->flush() as $report) {
            if (! $report->isSuccess()) {
                // Remove expired/invalid subscriptions
                if ($report->isSubscriptionExpired()) {
                    $expiredEndpoint = $report->getRequest()->getUri()->__toString();
                    StaffPushSubscription::where('endpoint_hash', hash('sha256', $expiredEndpoint))->delete();
                }
                Log::warning('Staff push failed: ' . $report->getReason());
            }
        }
    }
}
