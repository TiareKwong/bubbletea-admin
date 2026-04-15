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

    public function getRememberTokenName()
    {
        return null;
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

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function reward(): HasOne
    {
        return $this->hasOne(Reward::class);
    }
}