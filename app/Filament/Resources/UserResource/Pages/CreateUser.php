<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['is_staff']      = true;
        $data['is_verified']   = true;
        $data['phone_number']  = $data['phone_number'] ?? '';
        // users.name column (Laravel default) — keep in sync with first/last name
        $data['name'] = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));

        return $data;
    }
}
