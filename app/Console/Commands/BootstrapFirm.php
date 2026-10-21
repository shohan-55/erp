<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class BootstrapFirm extends Command
{
    protected $signature = 'erp:bootstrap {slug : New firm slug} {--company= : Official firm name} {--workspace= : Initial Partner workspace name}';
    protected $description = 'Securely provision the first company, Partner workspace and Partner login interactively.';

    public function handle(): int
    {
        $slug = strtolower(trim($this->argument('slug')));
        $companyName = trim($this->option('company') ?: $this->ask('Official company name'));
        $workspaceName = trim($this->option('workspace') ?: $this->ask('First Partner workspace name', 'Partner Unit 01'));
        $email = strtolower(trim($this->ask('First Partner login email')));
        $name = trim($this->ask('First Partner full name'));

        if (! preg_match('/^[a-z0-9][a-z0-9-]{1,68}[a-z0-9]$/', $slug)
            || mb_strlen($companyName) < 3 || mb_strlen($companyName) > 255
            || mb_strlen($workspaceName) < 3 || mb_strlen($workspaceName) > 255
            || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || mb_strlen($name) < 3 || mb_strlen($name) > 255) {
            $this->error('Invalid slug, name, workspace or email.');
            return self::FAILURE;
        }

        if (DB::table('companies')->where('slug', $slug)->exists()
            || User::where('email', $email)->exists()) {
            $this->error('The company slug or initial account email is already registered.');
            return self::FAILURE;
        }

        $password = $this->secret('Initial Partner password (12+ characters, mixed case and numbers)');
        $confirm = $this->secret('Repeat initial password');

        $validator = \Validator::make(['password' => $password, 'password_confirmation' => $confirm], [
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);

        if ($validator->fails()) {
            $this->error('Password does not meet the security requirements or confirmation does not match.');
            return self::FAILURE;
        }

        if (! $this->confirm('Create the new firm and initial Partner account?', false)) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($slug, $companyName, $workspaceName, $email, $name, $password) {
            $companyId = DB::table('companies')->insertGetId([
                'slug' => $slug, 'name' => $companyName, 'timezone' => 'Asia/Dhaka',
                'office_start' => '10:00:00', 'office_end' => '18:00:00',
                'grace_minutes' => 10, 'weekly_holidays' => json_encode([5, 6]),
                'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $workspaceId = DB::table('workspaces')->insertGetId([
                'company_id' => $companyId, 'slug' => 'partner-01',
                'name' => $workspaceName, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $user = User::create(['name' => $name, 'email' => $email, 'password' => $password]);
            DB::table('memberships')->insert([
                'company_id' => $companyId, 'workspace_id' => $workspaceId,
                'user_id' => $user->id, 'role' => 'partner', 'active' => true,
                'monthly_allowance' => '0.00', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);

        unset($password, $confirm);
        $this->info('Firm provisioned. Use /c/'.$slug.'/app/login. This command does not email credentials.');
        return self::SUCCESS;
    }
}