<?php

namespace App\Notifications;

use App\Channels\ResendChannel;
use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;

/**
 * Custom password reset notification that sends via Resend HTTP API
 * instead of SMTP (which is blocked on this server).
 *
 * Filament v4 resolves Filament\Auth\Notifications\ResetPassword via the IoC
 * container, so we bind this class in AppServiceProvider to replace it.
 */
class ResetPassword extends BaseResetPassword
{
    /** Set by Filament after construction */
    public string $url = '';

    public function via(mixed $notifiable): array
    {
        return [ResendChannel::class];
    }

    public function toResend(mixed $notifiable): array
    {
        $name     = method_exists($notifiable, 'getFilamentName')
            ? $notifiable->getFilamentName()
            : ($notifiable->name ?? $notifiable->email);

        $resetUrl = $this->url ?: $this->resetUrl($notifiable);

        return [
            'from'    => "Vicky's Bubble-Fruit Tea <noreply@vickysbubbletea.com>",
            'to'      => [$notifiable->email],
            'subject' => "Reset Your Password — Vicky's Bubble-Fruit Tea Admin",
            'html'    => "
                <div style='font-family:sans-serif;max-width:520px;margin:auto;'>
                    <div style='background:#7E57C2;padding:20px 24px;border-radius:12px 12px 0 0;'>
                        <h2 style='color:#fff;margin:0;'>Password Reset</h2>
                    </div>
                    <div style='background:#fff;padding:24px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 12px 12px;'>
                        <p>Hi {$name},</p>
                        <p>Click the button below to reset your admin panel password. This link expires in 60 minutes.</p>
                        <a href='{$resetUrl}'
                           style='display:inline-block;margin:16px 0;padding:12px 24px;background:#7E57C2;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;'>
                            Reset Password
                        </a>
                        <p style='color:#6b7280;font-size:0.85rem;'>If you did not request a password reset, you can ignore this email.</p>
                    </div>
                </div>
            ",
        ];
    }
}
