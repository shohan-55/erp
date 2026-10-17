<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class BootstrapPlatformOwner extends Command
{
    protected $signature = 'erp:platform:bootstrap';
    protected $description = 'Create the first platform operator with a hidden interactive password.';

    public function handle(): int
    {
        if (DB::table('users')->where('is_platform_admin', true)->exists()) {
            $this->error('A platform administrator already exists. Refusing to create another one automatically.');
            return self::FAILURE;
        }

        $name = trim($this->ask('Platform operator full name'));
        $email = strtolower(trim($this->ask('Platform operator email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($name) < 3 || mb_strlen($name) > 255) {
            $this->error('Invalid administrator identity.');
            return self::FAILURE;
        }
        if (User::where('email', $email)->exists()) {
            $this->error('The account email already exists. Use a new verified administrative email for the initial operator.');
            return self::FAILURE;
        }

        $password = $this->secret('Strong password (12+ characters, uppercase/lowercase and numbers)');
        $confirm = $this->secret('Repeat platform password');

        $validator = \Validator::make([
            'password' => $password, 'password_confirmation' => $confirm,
        ], ['password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()]]);

        if ($validator->fails() || ! $this->confirm('Create the first platform owner?', false)) {
            $this->error('Cancelled or password validation failed.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($name, $email, $password) {
            $user = User::create(['name'=>$name, 'email'=>$email, 'password'=>$password]);
            $user->forceFill(['is_platform_admin'=>true])->save();
        }, 3);

        unset($password, $confirm);
        $this->info('Platform owner provisioned. Sign in at /platform/app/login.');
        $this->warn('Configure MFA, HTTPS, audited backups and mail before any production deployment.');
        return self::SUCCESS;
    }
}