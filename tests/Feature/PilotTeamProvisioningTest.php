<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PilotTeamProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private function firm(): array
    {
        $company = DB::table('companies')->insertGetId([
            'slug' => 'rac', 'name' => 'RA&C Test Firm', 'timezone' => 'Asia/Dhaka',
            'office_start' => '10:00:00', 'office_end' => '18:00:00',
            'grace_minutes' => 10, 'weekly_holidays' => '[5,6]',
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $workspace = DB::table('workspaces')->insertGetId([
            'company_id' => $company, 'slug' => 'partner-01',
            'name' => 'Partner Unit 01', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$company, $workspace];
    }

    private function options(): array
    {
        return [
            'firmSlug' => 'rac',
            '--partner' => 'partner@example.test',
            '--manager' => 'manager@example.test',
            '--student' => 'student@example.test',
            '--partner-name' => 'Pilot Partner Name',
            '--student-name' => 'Pilot Student Name',
        ];
    }

    private function membership(int $company, int $workspace, User $user, string $role, bool $active = true): int
    {
        return DB::table('memberships')->insertGetId([
            'company_id' => $company, 'workspace_id' => $workspace,
            'user_id' => $user->id, 'role' => $role,
            'active' => $active, 'monthly_allowance' => '0.00',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_existing_accounts_are_linked_with_correct_roles_without_changing_password_or_mfa(): void
    {
        [$company, $workspace] = $this->firm();
        $partner = User::factory()->create(['email' => 'partner@example.test', 'password' => 'OriginalPartner2026']);
        $manager = User::factory()->create(['email' => 'manager@example.test', 'password' => 'OriginalManager2026']);
        $student = User::factory()->create(['email' => 'student@example.test', 'password' => 'OriginalStudent2026']);

        $encryptedMfa = Crypt::encryptString('FAKE_ENCRYPTED_TEST_SECRET');
        $manager->forceFill([
            'is_platform_admin' => true,
            'mfa_secret' => $encryptedMfa,
            'mfa_confirmed_at' => now(),
        ])->save();
        $originalPasswordHash = $manager->password;
        $originalMfa = $manager->mfa_secret;

        $oldManagerMember = $this->membership($company, $workspace, $manager, 'partner');

        $this->artisan('erp:pilot:roles', $this->options())
            ->expectsConfirmation('Apply these pilot role/membership changes to the current ERP database?', 'yes')
            ->expectsOutputToContain('PILOT TEAM READY:')
            ->assertExitCode(0);

        $partnerMember = DB::table('memberships')->where('user_id', $partner->id)->first();
        $managerMember = DB::table('memberships')->where('user_id', $manager->id)->first();
        $studentMember = DB::table('memberships')->where('user_id', $student->id)->first();

        $this->assertSame('partner', $partnerMember->role);
        $this->assertSame('manager', $managerMember->role);
        $this->assertSame($oldManagerMember, $managerMember->id);
        $this->assertSame('student', $studentMember->role);
        $this->assertSame($managerMember->id, $studentMember->manager_membership_id);
        $this->assertTrue(Hash::check('OriginalManager2026', $manager->fresh()->password));
        $this->assertSame($originalPasswordHash, $manager->fresh()->password);
        $this->assertSame($originalMfa, $manager->fresh()->mfa_secret);
        $this->assertTrue($manager->fresh()->is_platform_admin);
        $this->assertDatabaseCount('tenant_audit_events', 3);

        // An identical rerun must neither change existing membership IDs
        // nor add duplicate permissions/audit rows.
        $this->artisan('erp:pilot:roles', $this->options())
            ->expectsConfirmation('Apply these pilot role/membership changes to the current ERP database?', 'yes')
            ->assertExitCode(0);
        $this->assertDatabaseCount('memberships', 3);
        $this->assertDatabaseCount('tenant_audit_events', 3);
    }

    public function test_missing_users_receive_hidden_password_prompts_and_are_created_with_hashed_passwords(): void
    {
        [$company, $workspace] = $this->firm();
        $manager = User::factory()->create(['email' => 'manager@example.test', 'password' => 'OriginalManager2026']);
        $this->membership($company, $workspace, $manager, 'partner');

        $this->artisan('erp:pilot:roles', $this->options())
            ->expectsQuestion('New Partner password (12+ chars, upper/lower, number)', 'StrongPartner2026')
            ->expectsQuestion('Repeat Partner password', 'StrongPartner2026')
            ->expectsQuestion('New Student password (12+ chars, upper/lower, number)', 'StrongStudent2026')
            ->expectsQuestion('Repeat Student password', 'StrongStudent2026')
            ->expectsConfirmation('Apply these pilot role/membership changes to the current ERP database?', 'yes')
            ->assertExitCode(0);

        $partner = User::where('email', 'partner@example.test')->firstOrFail();
        $student = User::where('email', 'student@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('StrongPartner2026', $partner->password));
        $this->assertTrue(Hash::check('StrongStudent2026', $student->password));
        $this->assertSame('Pilot Partner Name', $partner->name);
        $this->assertSame('Pilot Student Name', $student->name);
        $this->assertSame('manager', DB::table('memberships')->where('user_id', $manager->id)->value('role'));
        $this->assertSame('student', DB::table('memberships')->where('user_id', $student->id)->value('role'));
    }

    public function test_unknown_firm_rejected_before_any_account_creation(): void
    {
        $this->artisan('erp:pilot:roles', $this->options())
            ->expectsOutputToContain('firm and Partner workspace must already exist')
            ->assertExitCode(1);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_another_existing_partner_blocks_every_role_change(): void
    {
        [$company, $workspace] = $this->firm();
        $other = User::factory()->create();
        $this->membership($company, $workspace, $other, 'partner');

        $this->artisan('erp:pilot:roles', $this->options())
            ->expectsOutputToContain('Another active Partner owns this workspace')
            ->assertExitCode(1);
        $this->assertDatabaseCount('memberships', 1);
        $this->assertDatabaseCount('tenant_audit_events', 0);
    }

    public function test_cross_workspace_and_inactive_memberships_are_not_reassigned(): void
    {
        [$company, $workspace] = $this->firm();
        $otherWorkspace = DB::table('workspaces')->insertGetId([
            'company_id' => $company, 'slug' => 'partner-02',
            'name' => 'Other Partner', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $manager = User::factory()->create(['email' => 'manager@example.test']);
        $member = $this->membership($company, $otherWorkspace, $manager, 'manager');

        $this->artisan('erp:pilot:roles', $this->options())
            ->expectsOutputToContain('inactive or other-workspace membership')
            ->assertExitCode(1);
        $this->assertDatabaseHas('memberships', ['id' => $member, 'workspace_id' => $otherWorkspace]);
        $this->assertDatabaseCount('tenant_audit_events', 0);

        DB::table('memberships')->where('id', $member)->update(['workspace_id' => $workspace, 'active' => false]);
        $this->artisan('erp:pilot:roles', $this->options())
            ->expectsOutputToContain('inactive or other-workspace membership')
            ->assertExitCode(1);
        $this->assertDatabaseHas('memberships', ['id' => $member, 'active' => false]);
    }

    public function test_dry_run_is_read_only_even_when_users_are_missing(): void
    {
        $this->firm();

        $this->artisan('erp:pilot:roles', [
            ...$this->options(), '--dry-run' => true,
        ])->expectsOutputToContain('DRY RUN COMPLETE')->assertExitCode(0);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('memberships', 0);
    }
}
