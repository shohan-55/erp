<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;
use Throwable;

/**
 * Explicit, interactive pilot onboarding for three predetermined tenant roles.
 * This is not a background seeder or a platform-admin impersonation endpoint.
 */
class ConfigurePilotTeam extends Command
{
    protected $signature = 'erp:pilot:roles
        {firmSlug : Existing firm slug}
        {--workspace=partner-01 : Existing Partner workspace slug}
        {--partner= : Partner email}
        {--manager= : Manager email}
        {--student= : Student email}
        {--partner-name=Pilot Partner : New Partner display name}
        {--manager-name=Pilot Manager : New Manager display name}
        {--student-name=Pilot Student : New Student display name}
        {--dry-run : Inspect planned changes without writing to the database}';

    protected $description = 'Safely attach exactly three pilot accounts to an existing Partner workspace';

    public function handle(): int
    {
        $slug = strtolower(trim((string) $this->argument('firmSlug')));
        $workspaceSlug = strtolower(trim((string) $this->option('workspace')));
        $accounts = [];

        if (! preg_match('/^[a-z0-9][a-z0-9-]{1,68}[a-z0-9]$/', $slug)
            || ! preg_match('/^[a-z0-9][a-z0-9-]{1,78}[a-z0-9]$/', $workspaceSlug)) {
            $this->error('Invalid company/workspace slug.');
            return self::FAILURE;
        }

        foreach (['partner', 'manager', 'student'] as $role) {
            $email = strtolower(trim((string) $this->option($role)));
            $name = trim((string) $this->option($role.'-name'));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)
                || mb_strlen($name) < 3 || mb_strlen($name) > 255) {
                $this->error('Provide three valid distinct email addresses and display names.');
                return self::FAILURE;
            }
            $accounts[$role] = ['email' => $email, 'name' => $name, 'password' => null];
        }

        if (count(array_unique(array_column($accounts, 'email'))) !== 3) {
            $this->error('Partner, Manager and Student must use three separate email addresses.');
            return self::FAILURE;
        }

        $company = DB::table('companies')->where('slug', $slug)->where('active', true)->first();
        $workspace = $company
            ? DB::table('workspaces')->where('company_id', $company->id)
                ->where('slug', $workspaceSlug)->where('active', true)->first()
            : null;

        if (! $company || ! $workspace) {
            $this->error('The firm and Partner workspace must already exist and be active.');
            return self::FAILURE;
        }

        $existingUserIds = [];
        foreach ($accounts as $role => &$person) {
            $person['user'] = User::query()->where('email', $person['email'])->first();
            $person['membership'] = $person['user']
                ? DB::table('memberships')->where('company_id', $company->id)
                    ->where('user_id', $person['user']->id)->first()
                : null;

            if ($person['membership']
                && (! $person['membership']->active
                    || (int) $person['membership']->workspace_id !== (int) $workspace->id)) {
                $this->error('STOP: A target account has an inactive or other-workspace membership.');
                return self::FAILURE;
            }

            if ($person['user']) {
                $existingUserIds[] = $person['user']->id;
                if ($person['user']->is_platform_admin) {
                    $this->warn(strtoupper($role).' already has Platform Admin access; it will NOT be removed.');
                }
            }

            $this->line(strtoupper($role).' '.$person['email'].': '
                .($person['user'] ? 'existing account / password preserved' : 'new account required')
                .' / '.($person['membership']?->role ?? 'no membership'));
        }
        unset($person);

        $otherPartner = DB::table('memberships')
            ->where('company_id', $company->id)
            ->where('workspace_id', $workspace->id)
            ->where('role', 'partner')->where('active', true)
            ->when($existingUserIds !== [], fn ($q) => $q->whereNotIn('user_id', $existingUserIds))
            ->exists();

        if ($otherPartner) {
            $this->error('STOP: Another active Partner owns this workspace. No changes made.');
            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('DRY RUN COMPLETE: no accounts, passwords or memberships changed.');
            return self::SUCCESS;
        }

        foreach ($accounts as $role => &$person) {
            if ($person['user']) {
                continue;
            }

            $secret = (string) $this->secret('New '.ucfirst($role).' password (12+ chars, upper/lower, number)');
            $confirm = (string) $this->secret('Repeat '.ucfirst($role).' password');
            $valid = Validator::make([
                'password' => $secret, 'password_confirmation' => $confirm,
            ], ['password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()]]);
            if ($valid->fails()) {
                $this->error('STOP: Invalid password or confirmation; no database changes made.');
                return self::FAILURE;
            }
            $person['password'] = $secret;
            unset($secret, $confirm);
        }
        unset($person);

        if (! $this->confirm('Apply these pilot role/membership changes to the current ERP database?', false)) {
            $this->warn('CANCELLED: nothing changed.');
            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($company, $workspace, &$accounts): void {
                DB::table('workspaces')->where('id', $workspace->id)
                    ->where('company_id', $company->id)->lockForUpdate()->first();

                $ids = array_values(array_filter(array_map(
                    fn ($person) => $person['user']?->id, $accounts
                )));
                $otherPartner = DB::table('memberships')
                    ->where('company_id', $company->id)
                    ->where('workspace_id', $workspace->id)
                    ->where('role', 'partner')->where('active', true)
                    ->when($ids !== [], fn ($q) => $q->whereNotIn('user_id', $ids))
                    ->exists();
                if ($otherPartner) {
                    throw new RuntimeException('Unexpected workspace Partner.');
                }

                foreach (['partner', 'manager', 'student'] as $role) {
                    $person = &$accounts[$role];
                    $user = User::query()->where('email', $person['email'])->first();
                    if (! $user) {
                        $user = User::create([
                            'name' => $person['name'], 'email' => $person['email'],
                            'password' => $person['password'],
                        ]);
                    }

                    $member = DB::table('memberships')
                        ->where('company_id', $company->id)
                        ->where('user_id', $user->id)->lockForUpdate()->first();

                    if ($member && (! $member->active
                        || (int) $member->workspace_id !== (int) $workspace->id)) {
                        throw new RuntimeException('Membership scope changed during setup.');
                    }

                    $managerId = $role === 'student' ? $accounts['manager']['member_id'] : null;
                    $oldRole = $member?->role;
                    $oldManagerId = $member && $member->manager_membership_id !== null
                        ? (int) $member->manager_membership_id : null;
                    $hasChanged = ! $member || $oldRole !== $role
                        || $oldManagerId !== $managerId;

                    if ($member && $hasChanged) {
                        DB::table('memberships')->where('id', $member->id)->update([
                            'role' => $role, 'manager_membership_id' => $managerId,
                            'updated_at' => now(),
                        ]);
                    }

                    $memberId = $member?->id ?? DB::table('memberships')->insertGetId([
                        'company_id' => $company->id, 'workspace_id' => $workspace->id,
                        'user_id' => $user->id, 'role' => $role,
                        'manager_membership_id' => $managerId,
                        'monthly_allowance' => '0.00', 'active' => true,
                        'activated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $person['member_id'] = $memberId;

                    if ($hasChanged) {
                        DB::table('tenant_audit_events')->insert([
                            'company_id' => $company->id, 'workspace_id' => $workspace->id,
                            'actor_membership_id' => null,
                            'action' => 'pilot.role.provisioned',
                            'entity_type' => 'membership', 'entity_id' => $memberId,
                            'details' => json_encode(['old_role' => $oldRole, 'new_role' => $role], JSON_THROW_ON_ERROR),
                            'created_at' => now(),
                        ]);
                    }
                    unset($person['password'], $person);
                }
            }, 3);
        } catch (Throwable $error) {
            $this->error('SETUP FAILED: database transaction rolled back. No partial role changes saved.');
            return self::FAILURE;
        }

        foreach (['partner', 'manager', 'student'] as $role) {
            $this->info('OK '.strtoupper($role).': '.$accounts[$role]['email']);
        }
        $this->info('PILOT TEAM READY: /c/'.$company->slug.'/app/login');
        $this->warn('Existing passwords/MFA unchanged. If unknown, use Forgot password with verified SMTP.');
        return self::SUCCESS;
    }
}
