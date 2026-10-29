<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's production database.
     *
     * No demo firms, fake partners, or sample student records are seeded.
     * Only the initial DhakaWala Platform Super Admin accounts are provisioned.
     */
    public function run(): void
    {
        $defaultPassword = env('PLATFORM_ADMIN_PASSWORD', 'password');

        User::updateOrCreate(
            ['email' => env('PLATFORM_ADMIN_PRIMARY_EMAIL', 'infobd.sohan@gmail.com')],
            [
                'name'              => env('PLATFORM_ADMIN_PRIMARY_NAME', 'Sohan Hossain'),
                'password'          => Hash::make($defaultPassword),
                'role'              => User::ROLE_PLATFORM_ADMIN,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => env('PLATFORM_ADMIN_SECONDARY_EMAIL', 'admin@dhakawala.com')],
            [
                'name'              => env('PLATFORM_ADMIN_SECONDARY_NAME', 'DhakaWala Platform Admin'),
                'password'          => Hash::make($defaultPassword),
                'role'              => User::ROLE_PLATFORM_ADMIN,
                'email_verified_at' => now(),
            ]
        );
    }
}
