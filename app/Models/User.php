<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Http;

class User extends Authenticatable implements FilamentUser, HasName
{
    use HasFactory, Notifiable;

    const UPDATED_AT = null;

    protected $fillable = [
        'email',
        'first_name',
        'last_name',
        'password',
        'is_staff',
        'is_admin',
        'birthday',
        'phone_number',
        'is_verified',
        'verification_token',
        'locale',
        'wallet_balance',
    ];

    protected $hidden = [
        'password',
        'verification_token',
    ];

    protected function casts(): array
    {
        return [
            'password'    => 'hashed',
            'is_staff'    => 'boolean',
            'is_admin'    => 'boolean',
            'is_verified' => 'boolean',
            'birthday'    => 'date',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_staff;
    }

    public function getFilamentName(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    public function sendPasswordResetNotification($token): void
    {
        $resetUrl = url('/admin/password-reset/reset?token=' . $token . '&email=' . urlencode($this->email));
        $name     = $this->getFilamentName();

        \Log::info('sendPasswordResetNotification called', ['email' => $this->email, 'key_set' => ! empty(config('services.resend.key'))]);

        try {
            $response = Http::withToken(config('services.resend.key'))
                ->timeout(15)
                ->post('https://api.resend.com/emails', [
                    'from'    => "Vicky's Bubble-Fruit Tea <noreply@vickysbubbletea.com>",
                    'to'      => [$this->email],
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
                ]);

            \Log::info('Resend password reset response', ['status' => $response->status(), 'body' => $response->body()]);

            if (! $response->successful()) {
                \Log::error('Password reset email failed', ['status' => $response->status(), 'body' => $response->body()]);
            }
        } catch (\Exception $e) {
            \Log::error('Password reset email exception: ' . $e->getMessage());
        }
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function reward(): HasOne
    {
        return $this->hasOne(Reward::class);
    }
}