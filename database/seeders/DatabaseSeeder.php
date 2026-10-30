<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the platform's initial Super Admin accounts.
     *
     * Security rules enforced here:
     *  - A password is only ever written when the account is first created.
     *    Re-running `db:seed` never resets an existing operator password.
     *  - There is no hard-coded fallback password. When PLATFORM_ADMIN_PASSWORD
     *    is not configured, a strong random password is generated and printed
     *    once so the operator can store it.
     */
    public function run(): void
    {
        $configuredPassword = env('PLATFORM_ADMIN_PASSWORD');

        $accounts = [
            [
                'email' => env('PLATFORM_ADMIN_PRIMARY_EMAIL', 'infobd.sohan@gmail.com'),
                'name'  => env('PLATFORM_ADMIN_PRIMARY_NAME', 'Sohan Hossain'),
            ],
            [
                'email' => env('PLATFORM_ADMIN_SECONDARY_EMAIL', 'admin@dhakawala.com'),
                'name'  => env('PLATFORM_ADMIN_SECONDARY_NAME', 'DhakaWala Platform Admin'),
            ],
        ];

        foreach ($accounts as $account) {
            $email = strtolower(trim((string) $account['email']));

            if ($email === '') {
                continue;
            }

            $existing = User::where('email', $email)->first();

            if ($existing) {
                $existing->role = User::ROLE_PLATFORM_ADMIN;

                if (! empty($account['name'])) {
                    $existing->name = $account['name'];
                }

                if (! $existing->email_verified_at) {
                    $existing->email_verified_at = now();
                }

                $existing->save();

                $this->command?->warn("Platform Super Admin '{$email}' already exists — existing password left unchanged.");

                continue;
            }

            $plainPassword = $configuredPassword ?: Str::password(20);

            User::create([
                'name'              => $account['name'],
                'email'             => $email,
                'password'          => Hash::make($plainPassword),
                'role'              => User::ROLE_PLATFORM_ADMIN,
                'email_verified_at' => now(),
            ]);

            if ($configuredPassword) {
                $this->command?->info("Created Platform Super Admin '{$email}' using PLATFORM_ADMIN_PASSWORD.");
            } else {
                $this->command?->warn("Created Platform Super Admin '{$email}' with a generated one-time password:");
                $this->command?->warn("    {$plainPassword}");
                $this->command?->warn('Store this password now — it is not shown again. Change it after your first sign-in.');
            }
        }
    }
}
