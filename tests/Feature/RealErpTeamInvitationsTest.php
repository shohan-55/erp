<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class RealErpTeamInvitationsTest extends TestCase
{
    use RefreshDatabase;

    private function firm(): array
    {
        $companyId = DB::table('companies')->insertGetId([
            'slug'=>'rac', 'name'=>'Rahman Anis & Co.', 'timezone'=>'Asia/Dhaka',
            'office_start'=>'10:00:00', 'office_end'=>'18:00:00', 'grace_minutes'=>10,
            'weekly_holidays'=>'[5,6]', 'active'=>true,
            'created_at'=>now(), 'updated_at'=>now(),
        ]);
        $workspaceId = DB::table('workspaces')->insertGetId([
            'company_id'=>$companyId,'slug'=>'partner-1','name'=>'Partner Unit 01',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        return [$companyId, $workspaceId];
    }

    private function person(int $company, int $workspace, string $role, bool $active = true): array
    {
        $user = User::factory()->create(['password'=>Hash::make('AnotherSecret42Pass')]);
        $memberId = DB::table('memberships')->insertGetId([
            'company_id'=>$company,'workspace_id'=>$workspace,'role'=>$role,
            'user_id'=>$user->id,'active'=>$active,
            'monthly_allowance'=>'0.00','created_at'=>now(),'updated_at'=>now(),
        ]);
        return [$user, $memberId];
    }

    public function test_only_partner_can_invite_and_invitation_starts_inactive(): void
    {
        Mail::fake();
        [$company, $workspace] = $this->firm();
        [$partner] = $this->person($company, $workspace, 'partner');
        [$manager] = $this->person($company, $workspace, 'manager');

        $payload = [
            'name'=>'Student Applicant','email'=>'student-applicant@example.test',
            'role'=>'student','monthly_allowance'=>'8000.00',
        ];
        $this->actingAs($manager)->post('/c/rac/app/team/invite', $payload)->assertForbidden();
        $this->assertDatabaseMissing('users', ['email'=>$payload['email']]);

        $this->actingAs($partner)->post('/c/rac/app/team/invite', $payload)->assertRedirect();
        $person = User::where('email', $payload['email'])->firstOrFail();
        $this->assertDatabaseHas('memberships', [
            'user_id'=>$person->id, 'company_id'=>$company, 'workspace_id'=>$workspace,
            'active'=>0, 'role'=>'student',
        ]);
        $this->get('/c/rac/app/team')->assertOk()->assertSee('Invitation Pending');
        $this->actingAs($person)->get('/c/rac/app')->assertForbidden();
    }

    public function test_invitation_activation_requires_valid_token_and_sets_account_password(): void
    {
        [$company, $workspace] = $this->firm();
        [$user, $memberId] = $this->person($company, $workspace, 'student', false);
        $token = Password::broker()->createToken($user);

        $this->post('/c/rac/app/activate', [
            'token'=>'invalid-token', 'email'=>$user->email,
            'password'=>'NewStrongSecret42', 'password_confirmation'=>'NewStrongSecret42',
        ])->assertSessionHasErrors('email');

        $this->post('/c/rac/app/activate', [
            'token'=>$token,'email'=>$user->email,
            'password'=>'NewStrongSecret42','password_confirmation'=>'NewStrongSecret42',
        ])->assertRedirect('/c/rac/app/login');

        $this->assertDatabaseHas('memberships', ['id'=>$memberId, 'active'=>1]);
        $this->assertTrue(Hash::check('NewStrongSecret42', $user->fresh()->password));
        $this->assertDatabaseHas('tenant_audit_events', ['entity_id'=>$memberId, 'action'=>'team.activate']);
    }

    public function test_invitation_cannot_assign_manager_from_another_partner(): void
    {
        Mail::fake();
        [$company, $workspace] = $this->firm();
        $otherWorkspace = DB::table('workspaces')->insertGetId([
            'company_id'=>$company,'slug'=>'partner-2','name'=>'Partner Unit 02','active'=>true,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        [$partner] = $this->person($company, $workspace, 'partner');
        [, $foreignManagerId] = $this->person($company, $otherWorkspace, 'manager');

        $this->actingAs($partner)->post('/c/rac/app/team/invite', [
            'name'=>'Wrong Partner Student', 'email'=>'otherworkspace@example.test',
            'role'=>'student', 'monthly_allowance'=>'6500.00',
            'manager_membership_id'=>$foreignManagerId,
        ])->assertSessionHasErrors('manager_membership_id');

        $this->assertDatabaseMissing('users', ['email'=>'otherworkspace@example.test']);
    }

    public function test_partner_can_update_only_own_students_allowance_and_manager(): void
    {
        [$company, $workspace] = $this->firm();
        $otherWorkspace = DB::table('workspaces')->insertGetId([
            'company_id'=>$company,'slug'=>'partner-2','name'=>'Partner Unit 02','active'=>true,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        [$partner] = $this->person($company, $workspace, 'partner');
        [, $managerId] = $this->person($company, $workspace, 'manager');
        [, $studentId] = $this->person($company, $workspace, 'student');
        [, $otherStudentId] = $this->person($company, $otherWorkspace, 'student');

        $this->actingAs($partner)->post("/c/rac/app/team/$otherStudentId/settings", [
            'monthly_allowance'=>'9000','manager_membership_id'=>$managerId,
        ])->assertNotFound();

        $this->post("/c/rac/app/team/$studentId/settings", [
            'monthly_allowance'=>'9000','manager_membership_id'=>$managerId,
        ])->assertRedirect();

        $this->assertDatabaseHas('memberships', [
            'id'=>$studentId,'monthly_allowance'=>9000,'manager_membership_id'=>$managerId,
        ]);
        $this->assertDatabaseHas('tenant_audit_events', [
            'entity_id'=>$studentId,'action'=>'team.update_student',
        ]);
    }

    public function test_claim_requires_partner_approval_after_manager_review(): void
    {
        [$company,$workspace] = $this->firm();
        [$partner] = $this->person($company, $workspace, 'partner');
        [$manager,$managerId] = $this->person($company,$workspace,'manager');
        [$student,$studentId] = $this->person($company,$workspace,'student');
        DB::table('memberships')->where('id',$studentId)->update(['manager_membership_id'=>$managerId]);
        $claimId = DB::table('conveyance_claims')->insertGetId([
            'company_id'=>$company,'workspace_id'=>$workspace,'membership_id'=>$studentId,
            'journey_date'=>'2026-10-11','journey_route'=>'Office to Client',
            'purpose'=>'Example statutory audit client visit','amount'=>'200.00',
            'status'=>'pending','created_at'=>now(),'updated_at'=>now(),
        ]);

        $this->actingAs($manager)->post("/c/rac/app/claims/$claimId/decision", [
            'decision'=>'approve',
        ])->assertRedirect();
        $this->assertDatabaseHas('conveyance_claims', ['id'=>$claimId,'status'=>'manager_reviewed']);

        $this->actingAs($partner)->post("/c/rac/app/claims/$claimId/decision", [
            'decision'=>'approve',
        ])->assertRedirect();
        $this->assertDatabaseHas('conveyance_claims', ['id'=>$claimId,'status'=>'approved']);
    }

    public function test_approved_claim_cannot_be_changed_after_payroll_review(): void
    {
        [$company,$workspace] = $this->firm();
        [$partner] = $this->person($company, $workspace, 'partner');
        [, $studentId] = $this->person($company,$workspace,'student');

        $this->actingAs($partner)->post('/c/rac/app/payroll', ['month'=>'2026-10'])->assertRedirect();
        $run = DB::table('payroll_runs')->firstOrFail();
        $this->post("/c/rac/app/payroll/$run->id/transition", ['action'=>'review'])->assertRedirect();

        $claimId = DB::table('conveyance_claims')->insertGetId([
            'company_id'=>$company,'workspace_id'=>$workspace,'membership_id'=>$studentId,
            'journey_date'=>'2026-10-11','journey_route'=>'Office to Client',
            'purpose'=>'Example statutory audit client visit','amount'=>'200.00',
            'status'=>'pending','created_at'=>now(),'updated_at'=>now(),
        ]);

        $this->post("/c/rac/app/claims/$claimId/decision", ['decision'=>'approve'])
            ->assertSessionHasErrors('decision');
        $this->assertDatabaseHas('conveyance_claims', ['id'=>$claimId,'status'=>'pending']);
    }
}
