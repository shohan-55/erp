<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

class ProvisionFirm extends Command
{
    protected $signature = 'erp:provision-firm {slug} {name} {email}';
    protected $description = 'Create a CA firm, initial Partner workspace and Partner account (interactive; no secrets in shell history)';

    public function handle(): int
    {
        $slug = strtolower(trim($this->argument('slug')));
        $email = strtolower(trim($this->argument('email')));
        $name = trim($this->argument('name'));

        $validator = Validator::make(
            compact('slug', 'name', 'email'),
            ['slug' => ['required', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:80', 'unique:companies,slug'],
             'name' => ['required', 'string', 'max:255'],
             'email' => ['required', 'email', 'max:255', 'unique:users,email']]
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first());
            return self::FAILURE;
        }

        $password = $this->secret('Initial Partner password (min 12 characters)');
        $confirm = $this->secret('Confirm Partner password');

        if (! is_string($password) || $password !== $confirm || mb_strlen($password) < 12) {
            $this->error('Passwords must match and have at least 12 characters.');
            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($slug, $name, $email, $password) {
                $company = Company::create(['slug' => $slug, 'name' => $name, 'timezone' => 'Asia/Dhaka', 'is_active' => true]);
                $workspace = Workspace::create(['company_id' => $company->id, 'name' => 'Partner Unit 01', 'type' => 'partner']);
                $user = User::create(['name' => 'Initial Partner', 'email' => $email, 'password' => Hash::make($password)]);
                CompanyMembership::create(['company_id' => $company->id, 'workspace_id' => $workspace->id,
                    'user_id' => $user->id, 'role' => 'partner', 'is_active' => true]);
            });
        } catch (\Throwable $e) {
            $this->error('Provisioning failed. No partial firm should have been saved.');
            return self::FAILURE;
        }

        $this->info('Firm and Partner account created successfully.');
        $this->line('Login: '.route('operational.login', ['companySlug' => $slug]));
        return self::SUCCESS;
    }
}
