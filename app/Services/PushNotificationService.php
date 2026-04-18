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
            'ki' => ['title' => 'Ko rabwa n am bobwai ✅', 'body' => 'Iaon kawaina n karaoaki am ota # #{code}.'],
        ],
        'payment_confirmed' => [
            'en' => ['title' => 'Payment Confirmed ✅', 'body' => 'Payment received! Order #{code} is confirmed and being prepared.'],
            'ki' => ['title' => 'Ko rabwa n am bobwai ✅', 'body' => 'Iaon kawaina n karaoaki am ota # #{code}.'],
        ],
        'order_preparing' => [
            'en' => ['title' => 'Order Being Prepared 🧋', 'body' => 'Order #{code} is now being prepared. Ready soon!'],
            'ki' => ['title' => 'E a karaoaki am ota 🧋', 'body' => 'Iaon kawaina n tauraoi am ota # #{code}.'],
        ],
        'order_ready' => [
            'en' => ['title' => 'Order Ready for Pickup 🎉', 'body' => 'Order #{code} is ready! Come collect your drinks.'],
            'ki' => ['title' => 'E a tauraoi am ota 🎉', 'body' => 'E a tia am ota # #{code} ao n kona n anaki'],
        ],
        'order_collected' => [
            'en' => ['title' => 'Order Collected 🛍️', 'body' => 'Order #{code} has been marked as collected. Enjoy!'],
            'ki' => ['title' => 'E a tia n anaki am ota 🛍️', 'body' => 'Ko rarabwa n am ota ae # #{code}. Tekeraoi!'],
        ],
        'order_cancelled' => [
            'en' => ['title' => 'Order Cancelled', 'body' => 'Your order #{code} has been cancelled.'],
            'ki' => ['title' => 'E a kamaunaki am ota', 'body' => 'E a tia n kamaunaki am ota # #{code}.'],
        ],
        'topup_approved' => [
            'en' => ['title' => 'Points Top-up Approved ✅', 'body' => '#{code} points have been added to your account!'],
            'ki' => ['title' => 'E a rin am bwii ✅', 'body' => '#{code} am bwii ae e a rin ngkai nakon am account!'],
        ],
        'topup_rejected' => [
            'en' => ['title' => 'Points Top-up Rejected', 'body' => 'Your request to add #{code} points was not approved.'],
            'ki' => ['title' => 'E aki rin am bwii', 'body' => 'Iai te kanganga nakon am bwii ae #{code}. E aki moa rin ngkai'],
        ],
        'change_to_points' => [
            'en' => ['title' => 'Change Converted to Points ✅', 'body' => '#{code} points have been added from your change!'],
            'ki' => ['title' => 'Bitaki am nikira nakon te bwii ✅', 'body' => 'E a tia n bitaki am nikira nakon am bwii #{code}!'],
        ],
        'change_points_updated' => [
            'en' => ['title' => 'Points Updated', 'body' => 'Your change conversion has been updated to #{code} points.'],
            'ki' => ['title' => 'Te bitaki nakon am bwii', 'body' => 'E a tia n bitaki am bwii nakon #{code}.'],
        ],
        'change_points_removed' => [
            'en' => ['title' => 'Points Removed', 'body' => 'Your change conversion of #{code} points has been reversed.'],
            'ki' => ['title' => 'E a Kamaunaki am Bwii', 'body' => 'E a kamaunaki am bwii ae #{code}.'],
        ],
        'wallet_topup_approved' => [
            'en' => ['title' => 'Wallet Top-up Approved ✅', 'body' => '$#{code} has been added to your wallet!'],
            'ki' => ['title' => 'E a Rin n am Bwauti ✅', 'body' => 'E a rin am mane ae $#{code} nakon am bwauti!'],
        ],
        'wallet_topup_rejected' => [
            'en' => ['title' => 'Wallet Top-up Rejected', 'body' => 'Your request to add $#{code} to your wallet was not approved.'],
            'ki' => ['title' => 'E aki Rin n am Bwauti', 'body' => 'E aki rin am mane ae $#{code} nakon am bwauti.'],
        ],
        'change_to_wallet' => [
            'en' => ['title' => 'Change Added to Wallet ✅', 'body' => '$#{code} change has been added to your wallet!'],
            'ki' => ['title' => 'E a rin am nikiran nakon am bwauti ✅', 'body' => 'E a rin te $#{code} man am nikira nakon am bwauti!'],
        ],
        'wallet_payment' => [
            'en' => ['title' => 'Wallet Payment', 'body' => '$#{code} has been deducted from your wallet for your order.'],
            'ki' => ['title' => 'Am Bwauti e Kabonganaki', 'body' => 'E kabonganaki $#{code} man am bwauti ibukin am ota.'],
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
