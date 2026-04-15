<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    private static array $translations = [
        'payment_verified' => [
            'en' => ['title' => 'Payment Confirmed ✅', 'body' => 'Your bank transfer has been verified. Order #{code} is confirmed!'],
            'ki' => ['title' => 'Ko rabwa n am bobwai ✅', 'body' => 'E a tia n tuoaki am IB n am ota # #{code}.'],
        ],
        'payment_confirmed' => [
            'en' => ['title' => 'Payment Confirmed ✅', 'body' => 'Payment received! Order #{code} is confirmed and being prepared.'],
            'ki' => ['title' => 'Ko rabwa n am bobwai ✅', 'body' => 'E nang katauraoaki am ota # #{code}.'],
        ],
        'order_preparing' => [
            'en' => ['title' => 'Order Being Prepared 🧋', 'body' => 'Order #{code} is now being prepared. Ready soon!'],
            'ki' => ['title' => 'E a katauraoaki 🧋', 'body' => 'Iaon kawaina n tauraoi am ota # #{code}.'],
        ],
        'order_ready' => [
            'en' => ['title' => 'Order Ready for Pickup 🎉', 'body' => 'Order #{code} is ready! Come collect your drinks.'],
            'ki' => ['title' => 'E a tauraoi am ota 🎉', 'body' => 'Ko a kona n anaa am ota # #{code}.'],
        ],
        'order_collected' => [
            'en' => ['title' => 'Order Collected 🛍️', 'body' => 'Order #{code} has been marked as collected. Enjoy!'],
            'ki' => ['title' => 'E a tia n anaki am ota 🛍️', 'body' => 'Ko rarabwa n am ota ae # #{code}. Tekeraoi!'],
        ],
        'order_cancelled' => [
            'en' => ['title' => 'Order Cancelled', 'body' => 'Your order #{code} has been cancelled.'],
            'ki' => ['title' => 'E a kamaunaki am ota', 'body' => 'E a tia n kamaunaki am ota # #{code}.'],
        ],
    ];

    /**
     * Send a localised notification using a translation key.
     */
    public static function sendLocalized(int $userId, string $key, string $orderCode): void
    {
        $locale = User::find($userId)?->locale ?? 'en';
        $t      = static::$translations[$key][$locale] ?? static::$translations[$key]['en'];
        $title  = $t['title'];
        $body   = str_replace('#{code}', $orderCode, $t['body']);

        static::send($userId, $title, $body);
    }

    /**
     * Send a push notification to a customer via the Node.js backend.
     * Fires-and-forgets: any failure is logged but never bubbles up to the caller.
     */
    public static function send(int $userId, string $title, string $body): void
    {
        $url    = rtrim(config('services.backend.internal_url', 'http://localhost:5001'), '/') . '/internal/notify';
        $secret = config('services.backend.notify_secret', '');

        try {
            Http::timeout(3)->post($url, [
                'user_id' => $userId,
                'title'   => $title,
                'body'    => $body,
                'secret'  => $secret,
            ]);
        } catch (\Exception $e) {
            Log::warning('PushNotification failed: ' . $e->getMessage());
        }
    }

    /**
     * Broadcast a push notification to all registered devices.
     */
    public static function broadcastAll(string $title, string $body): void
    {
        $url    = rtrim(config('services.backend.internal_url', 'http://localhost:5001'), '/') . '/internal/notify-all';
        $secret = config('services.backend.notify_secret', '');

        try {
            Http::timeout(5)->post($url, [
                'title'  => $title,
                'body'   => $body,
                'secret' => $secret,
            ]);
        } catch (\Exception $e) {
            Log::warning('PushNotification broadcastAll failed: ' . $e->getMessage());
        }
    }
}
