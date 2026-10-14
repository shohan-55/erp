<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\ConveyanceClaim;
use App\Models\LeaveRequest;
use App\Models\OperationalNotification;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function firm(): array
    {
        $company = Company::create(['name'=>'RA&C Test Firm','slug'=>'rac','timezone'=>'Asia/Dhaka']);
        $first = Workspace::create(['company_id'=>$company->id,'name'=>'Partner One']);
        $second = Workspace::create(['company_id'=>$company->id,'name'=>'Partner Two']);
        return [$company,$first,$second];
    }

    private function member(Company $company, Workspace $workspace, string $role, ?CompanyMembership $manager = null): array
    {
        $user = User::factory()->create();
        $member = CompanyMembership::create([
            'company_id'=>$company->id,'workspace_id'=>$workspace->id,'user_id'=>$user->id,
            'role'=>$role,'manager_membership_id'=>$manager?->id,
        ]);
        return [$user,$member];
    }

    public function test_student_submits_real_leave_and_claim_in_home_partner_only(): void
    {
        [$company,$workspace] = $this->firm();
        [$student,$member] = $this->member($company,$workspace,'student');
        $this->actingAs($student)->post('/app/c/rac/leave',[
            'type'=>'annual','starts_on'=>'2026-10-17','ends_on'=>'2026-10-18',
            'reason'=>'Family event',
        ])->assertRedirect();

        $this->actingAs($student)->post('/app/c/rac/claims',[
            'journey_date'=>'2026-10-14','amount'=>'240.50','journey_route'=>'Office to Sample Client',
            'purpose'=>'Audit field work',
        ])->assertRedirect();

        $this->assertSame('pending',LeaveRequest::firstOrFail()->status);
        $this->assertSame($member->id,LeaveRequest::firstOrFail()->membership_id);
        $this->assertSame($workspace->id,ConveyanceClaim::firstOrFail()->workspace_id);
        $this->assertSame('240.50',(string)ConveyanceClaim::firstOrFail()->amount);
        $this->assertNull(ConveyanceClaim::firstOrFail()->paid_at);
    }

    public function test_student_cannot_approve_own_leave_or_claim(): void
    {
        [$company,$workspace] = $this->firm();
        [$student,$member] = $this->member($company,$workspace,'student');
        $leave = LeaveRequest::create(['company_id'=>$company->id,'workspace_id'=>$workspace->id,'membership_id'=>$member->id,'type'=>'annual','starts_on'=>'2026-10-18','ends_on'=>'2026-10-18','reason'=>'Personal appointment']);
        $claim = ConveyanceClaim::create(['company_id'=>$company->id,'workspace_id'=>$workspace->id,'membership_id'=>$member->id,'journey_date'=>'2026-10-18','amount'=>150,'journey_route'=>'Office to Client','purpose'=>'Audit site visit']);

        $this->actingAs($student)->post('/app/c/rac/leave/'.$leave->id.'/review',['decision'=>'approved'])->assertForbidden();
        $this->actingAs($student)->post('/app/c/rac/claims/'.$claim->id.'/review',['decision'=>'approved'])->assertForbidden();
        $this->assertSame('pending',$leave->fresh()->status);
        $this->assertSame('pending',$claim->fresh()->status);
    }

    public function test_manager_can_review_only_assigned_students_and_records_notification(): void
    {
        [$company,$workspace] = $this->firm();
        [$manager,$managerMembership] = $this->member($company,$workspace,'manager');
        [$student,$assigned] = $this->member($company,$workspace,'student',$managerMembership);
        [$otherStudent,$unassigned] = $this->member($company,$workspace,'student');

        $leave = LeaveRequest::create(['company_id'=>$company->id,'workspace_id'=>$workspace->id,'membership_id'=>$assigned->id,'type'=>'sick','starts_on'=>'2026-10-16','ends_on'=>'2026-10-16','reason'=>'Medical appointment']);
        $other = LeaveRequest::create(['company_id'=>$company->id,'workspace_id'=>$workspace->id,'membership_id'=>$unassigned->id,'type'=>'annual','starts_on'=>'2026-10-16','ends_on'=>'2026-10-16','reason'=>'Personal leave']);

        $this->actingAs($manager)->post('/app/c/rac/leave/'.$other->id.'/review',['decision'=>'approved'])->assertForbidden();
        $this->actingAs($manager)->post('/app/c/rac/leave/'.$leave->id.'/review',['decision'=>'approved'])->assertRedirect();

        $this->assertSame('approved',$leave->fresh()->status);
        $this->assertSame($managerMembership->id,$leave->fresh()->reviewer_membership_id);
        $this->assertSame('pending',$other->fresh()->status);
        $this->assertSame($assigned->id,OperationalNotification::firstOrFail()->membership_id);
    }

    public function test_repeated_approval_cannot_duplicate_notification(): void
    {
        [$company,$workspace] = $this->firm();
        [$partner] = $this->member($company,$workspace,'partner');
        [$student,$studentMembership] = $this->member($company,$workspace,'student');
        $claim = ConveyanceClaim::create(['company_id'=>$company->id,'workspace_id'=>$workspace->id,'membership_id'=>$studentMembership->id,'journey_date'=>'2026-10-14','amount'=>125,'journey_route'=>'Office to Client','purpose'=>'Sample client visit']);

        $this->actingAs($partner)->post('/app/c/rac/claims/'.$claim->id.'/review',['decision'=>'approved'])->assertRedirect();
        $this->actingAs($partner)->post('/app/c/rac/claims/'.$claim->id.'/review',['decision'=>'approved'])->assertSessionHasErrors('decision');
        $this->assertDatabaseCount('operational_notifications',1);
        $this->assertSame('approved',$claim->fresh()->status);
        $this->assertNull($claim->fresh()->paid_at);
    }

    public function test_other_partner_cannot_read_or_review_its_neighbor_records(): void
    {
        [$company,$one,$two] = $this->firm();
        [$otherPartner] = $this->member($company,$two,'partner');
        [$student,$studentMembership] = $this->member($company,$one,'student');
        $leave = LeaveRequest::create(['company_id'=>$company->id,'workspace_id'=>$one->id,'membership_id'=>$studentMembership->id,'type'=>'annual','starts_on'=>'2026-10-16','ends_on'=>'2026-10-17','reason'=>'Family travel']);

        $this->actingAs($otherPartner)->get('/app/c/rac/requests')->assertOk()->assertDontSee('Family travel');
        $this->actingAs($otherPartner)->post('/app/c/rac/leave/'.$leave->id.'/review',['decision'=>'approved'])->assertNotFound();
        $this->assertSame('pending',$leave->fresh()->status);
    }

    public function test_claim_input_is_validated_and_personal_notification_is_private(): void
    {
        [$company,$one] = $this->firm();
        [$first,$firstMember] = $this->member($company,$one,'student');
        [$second] = $this->member($company,$one,'student');

        $this->actingAs($first)->post('/app/c/rac/claims',[
            'journey_date'=>'2026-10-14','amount'=>'-1',
            'journey_route'=>'XX','purpose'=>'X',
        ])->assertSessionHasErrors(['amount','journey_route','purpose']);
        $this->assertDatabaseCount('conveyance_claims',0);

        $notice=OperationalNotification::create(['company_id'=>$company->id,'workspace_id'=>$one->id,
            'membership_id'=>$firstMember->id,'title'=>'Private notice','body'=>'Not for other students']);
        $this->actingAs($second)->get('/app/c/rac/requests')->assertOk()->assertDontSee('Private notice');
        $this->actingAs($second)->post('/app/c/rac/notifications/'.$notice->id.'/read')->assertNotFound();
        $this->assertNull($notice->fresh()->read_at);
        $this->actingAs($first)->post('/app/c/rac/notifications/'.$notice->id.'/read')->assertRedirect();
        $this->assertNotNull($notice->fresh()->read_at);
    }

    public function test_cross_company_workflow_access_is_denied(): void
    {
        [$company,$workspace] = $this->firm();
        $otherCompany = Company::create(['name'=>'Second Firm','slug'=>'other','timezone'=>'Asia/Dhaka']);
        $otherWorkspace = Workspace::create(['company_id'=>$otherCompany->id,'name'=>'Unit One']);
        [$user] = $this->member($otherCompany,$otherWorkspace,'student');

        $this->actingAs($user)->post('/app/c/rac/leave',[
            'type'=>'annual','starts_on'=>'2026-10-16','ends_on'=>'2026-10-17','reason'=>'Personal leave',
        ])->assertNotFound();
        $this->actingAs($user)->get('/app/c/rac/requests')->assertNotFound();
        $this->assertDatabaseCount('leave_requests',0);
    }
}
