<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GuestUserSeeder extends Seeder
{
    /**
     * Creates the reserved guest user for walk-in orders placed by staff.
     * This user cannot log in and earns no rewards.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'guest@internal.local'],
            [
                'first_name'         => 'Guest',
                'last_name'          => 'Walk-in',
                'password'           => Hash::make(Str::random(32)),
                'is_staff'           => false,
                'is_verified'        => false,
                'phone_number'       => '0000000000',
                'verification_token' => null,
            ]
        );
    }
}
