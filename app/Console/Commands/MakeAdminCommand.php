<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MakeAdminCommand extends Command
{
    protected $signature = 'erp:make-admin {email : The email of the user} {--password= : Optional password to set}';

    protected $description = 'Grant platform super administrator access to a user';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        $password = $this->option('password');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = new User();
            $user->email = $email;
            $user->name = 'Platform Super Admin';
            $user->role = User::ROLE_PLATFORM_ADMIN;
            $user->password = Hash::make($password ?: 'password');
            $user->save();

            $this->info("Created new Platform Super Admin: {$email} (Password: " . ($password ?: 'password') . ")");
            return Command::SUCCESS;
        }

        $user->role = User::ROLE_PLATFORM_ADMIN;
        if ($password) {
            $user->password = Hash::make($password);
        }
        $user->save();

        $this->info("Successfully upgraded user '{$email}' to Platform Super Admin.");
        if ($password) {
            $this->info("Password updated successfully.");
        }

        return Command::SUCCESS;
    }
}
