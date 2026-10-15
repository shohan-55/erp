<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PlatformCompanyAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = User::factory()->create(['password' => Hash::make('PlatformStrong2026')]);
        $owner->forceFill(['is_platform_admin' => true])->save();
        return $owner;
    }

    private function firm(string $slug = 'rac'): array
    {
        $company = DB::table('companies')->insertGetId([
            'name' => 'Rahman Anis & Co.', 'slug' => $slug,
            'timezone' => 'Asia/Dhaka', 'office_start' => '10:00:00', 'office_end' => '18:00:00',
            'grace_minutes' => 10, 'weekly_holidays' => '[5,6]', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $workspace = DB::table('workspaces')->insertGetId([
            'company_id' => $company, 'slug' => 'partner-01',
            'name' => 'Partner Unit 01', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$company, $workspace];
    }

    private function member(int $company, int $workspace, string $role = 'student', bool $active = true): array
    {
        $user = User::factory()->create(['password' => Hash::make('TenantStrong2026')]);
        $id = DB::table('memberships')->insertGetId([
            'company_id' => $company, 'workspace_id' => $workspace,
            'user_id' => $user->id, 'role' => $role, 'active' => $active,
            'monthly_allowance' => '0.00', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$user, $id];
    }

    public function test_platform_management_requires_explicit_admin_for_reads_and_all_writes(): void
    {
        [$company, $workspace] = $this->firm();
        [$student, $member] = $this->member($company, $workspace);
        $other = User::factory()->create();

        $this->get("/platform/app/companies/$company")->assertRedirect('/platform/app/login');

        foreach ([$student, $other] as $nonAdmin) {
            $this->actingAs($nonAdmin)
                ->get("/platform/app/companies/$company")->assertForbidden();
            $this->patch("/platform/app/companies/$company", ['company_name' => 'Unauthorized rename'])
                ->assertForbidden();
            $this->post("/platform/app/companies/$company/lifecycle", [
                'action' => 'suspend', 'confirmation_slug' => 'rac',
            ])->assertForbidden();
            $this->post("/platform/app/companies/$company/members/$member/lifecycle", [
                'action' => 'suspend', 'confirmation_email' => $student->email,
            ])->assertForbidden();
            $this->patch("/platform/app/companies/$company/members/$member", [
                'name' => 'Unauthorized rename', 'role' => 'manager',
            ])->assertForbidden();
            $this->post("/platform/app/companies/$company/members", [
                'workspace_id' => $workspace,
                'name' => 'Unauthorized Invite',
                'email' => 'no-access@example.test', 'role' => 'manager',
            ])->assertForbidden();
        }

        $this->assertDatabaseHas('companies', ['id' => $company, 'active' => true, 'name' => 'Rahman Anis & Co.']);
        $this->assertDatabaseHas('memberships', ['id' => $member, 'active' => true, 'role' => 'student']);
        $this->assertDatabaseMissing('users', ['email' => 'no-access@example.test']);
        $this->assertDatabaseCount('platform_control_events', 0);
    }

    public function test_company_directory_links_to_scoped_management_and_does_not_leak_private_tenant_data(): void
    {
        [$company, $workspace] = $this->firm();
        [$student] = $this->member($company, $workspace);
        $this->actingAs($this->owner());

        $this->get('/platform/app')->assertOk()
            ->assertSee("Manage Company &amp; Accounts", false)
            ->assertSee("/platform/app/companies/$company");
        $this->get("/platform/app/companies/$company")->assertOk()
            ->assertSee('Company &amp; Account Management', false)
            ->assertSee($student->email)
            ->assertDontSee('Monthly Gross Payroll')
            ->assertDontSee('Student Salary Register')
            ->assertDontSee('Private attendance document');
    }

    public function test_company_name_edit_is_scoped_and_logged_without_changing_slug(): void
    {
        [$company] = $this->firm();
        $this->actingAs($this->owner())->patch("/platform/app/companies/$company", [
            'company_name' => 'Rahman Anis Chartered Accountants',
        ])->assertRedirect();

        $this->assertDatabaseHas('companies', [
            'id' => $company, 'slug' => 'rac', 'name' => 'Rahman Anis Chartered Accountants',
        ]);
        $this->assertDatabaseHas('platform_control_events', [
            'company_id' => $company, 'action' => 'company.name.updated', 'subject_id' => $company,
        ]);
    }

    public function test_company_suspend_reactivate_archive_restore_requires_exact_confirmation_and_sequence(): void
    {
        [$company, $workspace] = $this->firm();
        [$user] = $this->member($company, $workspace, 'student');
        $this->actingAs($this->owner());

        $url = "/platform/app/companies/$company/lifecycle";
        $this->post($url, ['action' => 'archive', 'confirmation_slug' => 'rac'])
            ->assertSessionHasErrors('action');
        $this->post($url, ['action' => 'suspend', 'confirmation_slug' => 'wrong'])
            ->assertSessionHasErrors('confirmation_slug');
        $this->assertDatabaseHas('companies', ['id' => $company, 'active' => 1]);

        $this->post($url, ['action' => 'suspend', 'confirmation_slug' => 'rac'])->assertRedirect();
        $this->assertDatabaseHas('companies', ['id' => $company, 'active' => 0]);
        $this->actingAs($user)->get('/c/rac/app')->assertForbidden();

        $this->actingAs($this->owner());
        $this->post($url, ['action' => 'archive', 'confirmation_slug' => 'rac'])->assertRedirect();
        $this->assertNotNull(DB::table('companies')->where('id', $company)->value('archived_at'));

        $this->post($url, ['action' => 'reactivate', 'confirmation_slug' => 'rac'])
            ->assertSessionHasErrors('action');
        $this->post($url, ['action' => 'restore', 'confirmation_slug' => 'rac'])->assertRedirect();
        $this->assertDatabaseHas('companies', ['id' => $company, 'active' => 0, 'archived_at' => null]);

        $this->post($url, ['action' => 'reactivate', 'confirmation_slug' => 'rac'])->assertRedirect();
        $this->assertDatabaseHas('companies', ['id' => $company, 'active' => 1]);

        $this->assertDatabaseCount('platform_control_events', 4);
        $this->assertDatabaseCount('memberships', 1);
        $this->actingAs($user)->get('/c/rac/app')->assertOk();
    }

    public function test_archived_company_cannot_be_edited_or_invite_new_staff(): void
    {
        Mail::fake();
        [$company, $workspace] = $this->firm();
        $this->actingAs($this->owner());
        foreach (['suspend', 'archive'] as $action) {
            $this->post("/platform/app/companies/$company/lifecycle", [
                'action' => $action, 'confirmation_slug' => 'rac',
            ])->assertRedirect();
        }

        $this->patch("/platform/app/companies/$company", ['company_name' => 'Changed Firm'])
            ->assertSessionHasErrors('company_name');
        $this->post("/platform/app/companies/$company/members", [
            'workspace_id' => $workspace, 'name' => 'New Member Person',
            'email' => 'new-member@example.test', 'role' => 'student',
        ])->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'new-member@example.test']);
    }

    public function test_platform_invites_new_staff_inactive_with_one_time_email_and_no_password_exposure(): void
    {
        Mail::fake();
        [$company, $workspace] = $this->firm();
        [$manager, $managerId] = $this->member($company, $workspace, 'manager');
        $this->actingAs($this->owner())->post("/platform/app/companies/$company/members", [
            'workspace_id' => $workspace,
            'name' => 'New Platform Student',
            'email' => 'staff-invite@example.test',
            'role' => 'student',
            'manager_membership_id' => $managerId,
        ])->assertRedirect();

        $invitee = User::where('email', 'staff-invite@example.test')->firstOrFail();
        $this->assertDatabaseHas('memberships', [
            'company_id' => $company, 'workspace_id' => $workspace,
            'user_id' => $invitee->id, 'role' => 'student',
            'active' => 0, 'manager_membership_id' => $managerId,
        ]);
        $this->assertDatabaseHas('platform_control_events', [
            'company_id' => $company, 'action' => 'account.invited',
        ]);
        $this->assertFalse(Hash::check('TenantStrong2026', $invitee->password));
        Mail::assertSentCount(1);
        $this->actingAs($invitee)->get('/c/rac/app')->assertForbidden();
    }

    public function test_invite_rejects_existing_global_identity_instead_of_resetting_shared_password(): void
    {
        Mail::fake();
        [$company, $workspace] = $this->firm();
        [$existing] = $this->member($company, $workspace, 'manager');
        $before = $existing->password;
        $this->actingAs($this->owner())->post("/platform/app/companies/$company/members", [
            'workspace_id' => $workspace, 'name' => 'Existing Shared Account',
            'email' => $existing->email, 'role' => 'student',
        ])->assertSessionHasErrors('email');

        $this->assertSame($before, $existing->fresh()->password);
        $this->assertDatabaseCount('memberships', 1);
        Mail::assertNothingSent();
    }

    public function test_invite_rejects_other_company_workspace_or_other_partner_manager(): void
    {
        Mail::fake();
        [$company, $workspace] = $this->firm();
        [$otherCompany, $otherWorkspace] = $this->firm('other');
        [, $otherManager] = $this->member($otherCompany, $otherWorkspace, 'manager');
        $this->actingAs($this->owner());

        $url = "/platform/app/companies/$company/members";
        $this->post($url, [
            'workspace_id' => $otherWorkspace, 'name' => 'Wrong Company Member',
            'email' => 'wrong-company@example.test', 'role' => 'manager',
        ])->assertNotFound();

        $this->post($url, [
            'workspace_id' => $workspace, 'name' => 'Wrong Manager Member',
            'email' => 'wrong-manager@example.test', 'role' => 'student',
            'manager_membership_id' => $otherManager,
        ])->assertSessionHasErrors('manager_membership_id');

        $this->assertDatabaseMissing('users', ['email' => 'wrong-company@example.test']);
        $this->assertDatabaseMissing('users', ['email' => 'wrong-manager@example.test']);
        Mail::assertNothingSent();
    }

    public function test_membership_suspension_blocks_existing_logged_in_session_then_restores_without_resetting_password(): void
    {
        [$company, $workspace] = $this->firm();
        [$partner, $partnerId] = $this->member($company, $workspace, 'partner');
        [$student, $studentId] = $this->member($company, $workspace, 'student');
        $originalHash = $student->password;

        $this->actingAs($student)->get('/c/rac/app')->assertOk();
        $this->actingAs($this->owner())->post(
            "/platform/app/companies/$company/members/$studentId/lifecycle",
            ['action' => 'suspend', 'confirmation_email' => $student->email]
        )->assertRedirect();

        $this->assertDatabaseHas('memberships', ['id' => $studentId, 'active' => 0]);
        $this->assertNotNull(DB::table('memberships')->where('id', $studentId)->value('platform_suspended_at'));
        $this->actingAs($student)->get('/c/rac/app')->assertForbidden();

        $this->actingAs($this->owner())->post(
            "/platform/app/companies/$company/members/$studentId/lifecycle",
            ['action' => 'reactivate', 'confirmation_email' => $student->email]
        )->assertRedirect();
        $this->assertDatabaseHas('memberships', [
            'id' => $studentId, 'active' => 1, 'platform_suspended_at' => null,
        ]);
        $this->assertSame($originalHash, $student->fresh()->password);
        $this->actingAs($student)->get('/c/rac/app')->assertOk();
        $this->assertDatabaseCount('platform_control_events', 2);
    }

    public function test_pending_invitation_cannot_be_reactivated_by_admin_or_used_to_bypass_email_acceptance(): void
    {
        [$company, $workspace] = $this->firm();
        [$pending, $member] = $this->member($company, $workspace, 'student', false);
        $this->actingAs($this->owner())->post(
            "/platform/app/companies/$company/members/$member/lifecycle",
            ['action' => 'reactivate', 'confirmation_email' => $pending->email]
        )->assertSessionHasErrors('action');

        $this->assertDatabaseHas('memberships', [
            'id' => $member, 'active' => 0, 'platform_suspended_at' => null,
        ]);
        $this->assertDatabaseCount('platform_control_events', 0);
    }

    public function test_platform_cannot_silence_last_active_partner(): void
    {
        [$company, $workspace] = $this->firm();
        [$partner, $id] = $this->member($company, $workspace, 'partner');
        $this->actingAs($this->owner())->post(
            "/platform/app/companies/$company/members/$id/lifecycle",
            ['action' => 'suspend', 'confirmation_email' => $partner->email]
        )->assertSessionHasErrors('action');
        $this->assertDatabaseHas('memberships', ['id' => $id, 'active' => 1]);
    }

    public function test_account_edit_can_reassign_student_to_own_manager_without_touching_other_firm(): void
    {
        [$company, $workspace] = $this->firm();
        [$otherCompany, $otherWorkspace] = $this->firm('outside');
        [, $managerId] = $this->member($company, $workspace, 'manager');
        [, $otherManagerId] = $this->member($otherCompany, $otherWorkspace, 'manager');
        [$student, $studentId] = $this->member($company, $workspace);

        $this->actingAs($this->owner());
        $route = "/platform/app/companies/$company/members/$studentId";

        $this->patch($route, [
            'name' => 'New Student Identity', 'role' => 'student',
            'manager_membership_id' => $otherManagerId,
        ])->assertSessionHasErrors('manager_membership_id');
        $this->assertDatabaseHas('users', ['id' => $student->id, 'name' => $student->name]);

        $this->patch($route, [
            'name' => 'New Student Identity', 'role' => 'student',
            'manager_membership_id' => $managerId,
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $student->id, 'name' => 'New Student Identity']);
        $this->assertDatabaseHas('memberships', ['id' => $studentId, 'manager_membership_id' => $managerId]);
        $this->assertDatabaseCount('platform_control_events', 2);
    }

    public function test_role_edit_blocks_partner_promotion_and_assigned_manager_demotion(): void
    {
        [$company, $workspace] = $this->firm();
        [$partner, $partnerId] = $this->member($company, $workspace, 'partner');
        [$manager, $managerId] = $this->member($company, $workspace, 'manager');
        [$student, $studentId] = $this->member($company, $workspace, 'student');
        DB::table('memberships')->where('id', $studentId)->update(['manager_membership_id' => $managerId]);
        $this->actingAs($this->owner());

        $this->patch("/platform/app/companies/$company/members/$studentId", [
            'name' => $student->name, 'role' => 'partner',
        ])->assertSessionHasErrors('role');

        $this->patch("/platform/app/companies/$company/members/$managerId", [
            'name' => $manager->name, 'role' => 'student',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseHas('memberships', ['id' => $partnerId, 'role' => 'partner']);
        $this->assertDatabaseHas('memberships', ['id' => $managerId, 'role' => 'manager']);
        $this->assertDatabaseHas('memberships', ['id' => $studentId, 'role' => 'student']);
        $this->assertDatabaseCount('platform_control_events', 0);
    }

    public function test_global_user_identity_shared_between_firms_is_not_silently_renamed(): void
    {
        [$company, $workspace] = $this->firm();
        [$otherCompany, $otherWorkspace] = $this->firm('other');
        [$student, $member] = $this->member($company, $workspace, 'student');
        DB::table('memberships')->insert([
            'company_id' => $otherCompany, 'workspace_id' => $otherWorkspace,
            'user_id' => $student->id, 'role' => 'student', 'active' => true,
            'monthly_allowance' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->owner())->patch("/platform/app/companies/$company/members/$member", [
            'name' => 'New Shared Identity', 'role' => 'student',
        ])->assertSessionHasErrors('name');
        $this->assertSame($student->name, $student->fresh()->name);
        $this->assertDatabaseCount('platform_control_events', 0);
    }

    public function test_company_and_member_ids_are_scoped_no_cross_company_update(): void
    {
        [$company, $workspace] = $this->firm();
        [$otherCompany, $otherWorkspace] = $this->firm('other');
        [$member, $memberId] = $this->member($otherCompany, $otherWorkspace);

        $this->actingAs($this->owner());
        $this->post("/platform/app/companies/$company/members/$memberId/lifecycle", [
            'action' => 'suspend', 'confirmation_email' => $member->email,
        ])->assertNotFound();
        $this->patch("/platform/app/companies/$company/members/$memberId", [
            'name' => 'Altered Person', 'role' => 'manager',
        ])->assertNotFound();

        $this->assertDatabaseHas('memberships', ['id' => $memberId, 'company_id' => $otherCompany, 'active' => 1]);
        $this->assertDatabaseCount('platform_control_events', 0);
    }

    public function test_platform_admin_role_identity_is_not_mutable_from_tenant_membership_editor(): void
    {
        [$company, $workspace] = $this->firm();
        $owner = $this->owner();
        $memberId = DB::table('memberships')->insertGetId([
            'company_id' => $company, 'workspace_id' => $workspace,
            'user_id' => $owner->id, 'role' => 'manager',
            'active' => true, 'monthly_allowance' => '0.00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($owner)->patch("/platform/app/companies/$company/members/$memberId", [
            'name' => 'Another Platform Admin', 'role' => 'manager',
        ])->assertSessionHasErrors('name');
        $this->assertSame($owner->name, $owner->fresh()->name);
    }
}
