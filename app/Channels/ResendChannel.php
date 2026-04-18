<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class ResendChannel
{
    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toResend')) {
            return;
        }

        $data = $notification->toResend($notifiable);

        $response = Http::withToken(config('services.resend.key'))
            ->timeout(15)
            ->post('https://api.resend.com/emails', $data);

        if (! $response->successful()) {
            \Log::error('ResendChannel failed', ['status' => $response->status(), 'body' => $response->body()]);
        }
    }
}
