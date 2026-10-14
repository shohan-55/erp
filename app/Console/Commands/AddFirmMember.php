<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AddFirmMember extends Command
{
    protected $signature = 'erp:member:add {firmSlug} {--role= : partner|manager|student} {--workspace= : Partner workspace slug} {--manager= : Manager email for students}';
    protected $description = 'Interactively create a company Partner, Manager or Student account. No passwords in command arguments.';

    public function handle(): int
    {
        $company = DB::table('companies')->where('slug', $this->argument('firmSlug'))
            ->where('active', true)->first();
        if (! $company) {
            $this->error('Company not found.');
            return self::FAILURE;
        }

        $role = trim($this->option('role') ?: $this->choice('Account role', ['partner','manager','student'], 2));
        if (! in_array($role, ['partner','manager','student'], true)) {
            $this->error('Unsupported role.');
            return self::FAILURE;
        }

        $slug = strtolower(trim($this->option('workspace') ?: $this->ask('Partner workspace slug', 'partner-01')));
        if (! preg_match('/^[a-z0-9][a-z0-9-]{1,78}[a-z0-9]$/', $slug)) {
            $this->error('Invalid workspace slug.');
            return self::FAILURE;
        }

        $existingWorkspace = DB::table('workspaces')
            ->where('company_id', $company->id)->where('slug', $slug)->first();

        if ($role !== 'partner' && ! $existingWorkspace) {
            $this->error('Partner workspace does not exist.');
            return self::FAILURE;
        }
        if ($role === 'partner' && $existingWorkspace
            && DB::table('memberships')->where('workspace_id', $existingWorkspace->id)
                ->where('role', 'partner')->where('active', true)->exists()) {
            $this->error('This workspace already has an active Partner.');
            return self::FAILURE;
        }

        $name = trim($this->ask('Full name'));
        $email = strtolower(trim($this->ask('Login email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($name) < 3 || mb_strlen($name) > 255) {
            $this->error('Invalid name or email.');
            return self::FAILURE;
        }

        $existingUser = User::query()->where('email', $email)->first();
        if ($existingUser && DB::table('memberships')->where('company_id', $company->id)
            ->where('user_id', $existingUser->id)->exists()) {
            $this->error('This user is already a member of this company.');
            return self::FAILURE;
        }

        // Explicit existing-account reuse is only permitted when the operator confirms.
        $password = null;
        if ($existingUser) {
            if (! $this->confirm('An existing account uses this email. Link that account to this company?', false)) {
                return self::FAILURE;
            }
        } else {
            $password = $this->secret('Initial password (12+ chars, mixed case and numbers)');
            $confirmation = $this->secret('Repeat password');
            $validator = \Validator::make(['password' => $password, 'password_confirmation' => $confirmation], [
                'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
            ]);
            if ($validator->fails()) {
                $this->error('Weak password or confirmation mismatch.');
                return self::FAILURE;
            }
        }

        $managerId = null;
        if ($role === 'student') {
            $managerEmail = $this->option('manager') ?: $this->ask('Assigned Manager email (optional)');
            if ($managerEmail) {
                $manager = DB::table('memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')
                    ->where('m.company_id', $company->id)
                    ->where('m.workspace_id', $existingWorkspace->id)
                    ->where('m.role', 'manager')->where('m.active', true)
                    ->where('u.email', strtolower(trim($managerEmail)))->select('m.id')->first();
                if (! $manager) {
                    $this->error('Manager must belong to the same company and workspace.');
                    return self::FAILURE;
                }
                $managerId = $manager->id;
            }
        }

        $allowance = '0.00';
        if ($role === 'student') {
            $allowance = trim($this->ask('Monthly allowance BDT (0–500000, no automatic penalties)', '0'));
            if (! preg_match('/^\d{1,6}(?:\.\d{1,2})?$/', $allowance) || (float) $allowance > 500000) {
                $this->error('Invalid allowance amount.');
                return self::FAILURE;
            }
        }

        if (! $this->confirm('Create this company membership?', false)) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($company, $slug, $role, $name, $email, $existingUser, $existingWorkspace, $password, $managerId, $allowance) {
            $workspaceId = $existingWorkspace?->id;
            if (! $workspaceId) {
                $workspaceId = DB::table('workspaces')->insertGetId([
                    'company_id' => $company->id, 'slug' => $slug,
                    'name' => Str::headline($slug), 'active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $user = $existingUser ?: User::create(['name' => $name, 'email' => $email, 'password' => $password]);
            DB::table('memberships')->insert([
                'company_id' => $company->id, 'workspace_id' => $workspaceId,
                'user_id' => $user->id,
                'manager_membership_id' => $managerId,
                'role' => $role, 'monthly_allowance' => $allowance, 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);

        unset($password);
        $this->info('Membership created. The account can use /c/'.$company->slug.'/app/login.');
        return self::SUCCESS;
    }
}