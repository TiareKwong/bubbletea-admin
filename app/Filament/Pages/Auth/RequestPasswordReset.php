<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;

class RequestPasswordReset extends BaseRequestPasswordReset
{
    /**
     * Add is_staff = true so the password broker only matches staff accounts.
     * Customers or unknown emails will get the same "email not found" response.
     */
    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'email'    => $data['email'],
            'is_staff' => true,
        ];
    }
}
