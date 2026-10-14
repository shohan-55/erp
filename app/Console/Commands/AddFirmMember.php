<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AddFirmMember extends Command
{
    protected $signature = 'erp:add-member {companySlug} {email} {role : partner|manager|student}
        {--workspace= : Partner workspace ID (defaults to first workspace)}
        {--manager= : Manager membership ID for student assignment}';

    protected $description = 'Safely add a firm user with Home Partner ownership (interactive initial password).';

    public function handle(): int
    {
        $company = Company::query()->where('slug', strtolower($this->argument('companySlug')))->where('is_active', true)->first();
        if (! $company) {
            $this->error('Company not found or inactive.');
            return self::FAILURE;
        }

        $role = strtolower($this->argument('role'));
        $email = strtolower(trim($this->argument('email')));

        $v = Validator::make(['role'=>$role, 'email'=>$email], [
            'role'=>['required','in:partner,manager,student'],
            'email'=>['required','email','max:255'],
        ]);
        if ($v->fails()) {
            $this->error($v->errors()->first());
            return self::FAILURE;
        }

        $workspaceQuery = Workspace::query()->where('company_id', $company->id);
        $workspace = $this->option('workspace')
            ? (clone $workspaceQuery)->whereKey($this->option('workspace'))->first()
            : $workspaceQuery->oldest('id')->first();

        if (! $workspace) {
            $this->error('No matching workspace exists under this company.');
            return self::FAILURE;
        }

        $manager = null;
        if ($this->option('manager')) {
            $manager = CompanyMembership::query()
                ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                ->where('role', 'manager')->where('is_active', true)
                ->whereKey($this->option('manager'))->first();
            if (! $manager || $role !== 'student') {
                $this->error('Manager must be an active Manager membership in the same workspace, and role must be student.');
                return self::FAILURE;
            }
        }

        if (CompanyMembership::query()->where('company_id', $company->id)
            ->whereHas('user', fn($q)=>$q->where('email', $email))->exists()) {
            $this->error('This user already has membership in the company.');
            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();
        $password = null;
        $displayName = null;
        if (! $user) {
            $displayName = $this->ask('User full name');
            $password = $this->secret('Set account password (12+ characters)');
            $confirm = $this->secret('Confirm account password');
            if (! $displayName || ! is_string($password) || strlen($password) < 12 || $password !== $confirm) {
                $this->error('A name and matching 12+ character password are required.');
                return self::FAILURE;
            }
        }

        DB::transaction(function () use ($company, $workspace, $manager, $role, $email, $user, $password, $displayName) {
            $account = $user ?: User::create([
                'name' => $displayName, 'email' => $email, 'password' => Hash::make($password),
            ]);

            CompanyMembership::create([
                'company_id' => $company->id, 'user_id' => $account->id,
                'workspace_id' => $workspace->id, 'manager_membership_id' => $manager?->id,
                'role' => $role, 'is_active' => true,
            ]);
        });

        $this->info('Member created in '.$company->slug.' / '.$workspace->name.'.');
        return self::SUCCESS;
    }
}
