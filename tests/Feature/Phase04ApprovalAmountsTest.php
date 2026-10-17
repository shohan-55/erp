<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase04ApprovalAmountsTest extends TestCase
{
    use RefreshDatabase;

    private function team(): array
    {
        $company = DB::table('companies')->insertGetId([
            'slug' => 'qa-pilot', 'name' => 'Test Company',
            'timezone' => 'Asia/Dhaka',
            'office_start' => '10:00:00', 'office_end' => '18:00:00',
            'grace_minutes' => 10, 'weekly_holidays' => '[5,6]',
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $workspace = DB::table('workspaces')->insertGetId([
            'company_id' => $company, 'slug' => 'partner-01',
            'name' => 'Partner Unit 01', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $members = [];
        foreach (['partner','manager','student'] as $role) {
            $user = User::factory()->create();
            $id = DB::table('memberships')->insertGetId([
                'company_id' => $company, 'workspace_id' => $workspace,
                'user_id' => $user->id, 'role' => $role,
                'monthly_allowance' => $role === 'student' ? '8000.00' : '0.00',
                'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $members[$role] = (object)['user'=>$user, 'id'=>$id];
        }
        DB::table('memberships')->where('id', $members['student']->id)
            ->update(['manager_membership_id' => $members['manager']->id]);

        return [$company, $workspace, $members];
    }

    private function leave(int $company, int $workspace, int $student): int
    {
        return DB::table('leave_requests')->insertGetId([
            'company_id'=>$company, 'workspace_id'=>$workspace,
            'membership_id'=>$student, 'leave_type'=>'annual',
            'date_from'=>'2026-11-18','date_to'=>'2026-11-19',
            'reason'=>'Test annual leave for an assigned student',
            'status'=>'pending', 'created_at'=>now(), 'updated_at'=>now(),
        ]);
    }

    private function claim(int $company, int $workspace, int $student): int
    {
        return DB::table('conveyance_claims')->insertGetId([
            'company_id'=>$company, 'workspace_id'=>$workspace,
            'membership_id'=>$student, 'journey_date'=>'2026-10-14',
            'journey_route'=>'Office to sample client',
            'purpose'=>'Internal audit fieldwork and document verification',
            'amount'=>'130.00', 'status'=>'pending',
            'created_at'=>now(), 'updated_at'=>now(),
        ]);
    }

    public function test_assigned_student_leave_needs_manager_review_before_partner_approval(): void
    {
        [$company,$workspace,$team] = $this->team();
        $id=$this->leave($company,$workspace,$team['student']->id);

        $this->actingAs($team['partner']->user)
            ->post("/c/qa-pilot/app/leave/{$id}/decision", ['decision'=>'approve'])
            ->assertSessionHasErrors('decision');
        $this->assertDatabaseHas('leave_requests', ['id'=>$id, 'status'=>'pending']);

        $this->actingAs($team['manager']->user)
            ->post("/c/qa-pilot/app/leave/{$id}/decision", ['decision'=>'approve'])
            ->assertRedirect();
        $this->assertDatabaseHas('leave_requests', ['id'=>$id, 'status'=>'manager_reviewed']);

        $this->actingAs($team['partner']->user)
            ->post("/c/qa-pilot/app/leave/{$id}/decision", ['decision'=>'approve'])
            ->assertRedirect();
        $this->assertDatabaseHas('leave_requests', ['id'=>$id, 'status'=>'approved']);
    }

    public function test_original_conveyance_is_preserved_and_only_partner_amount_enters_payroll(): void
    {
        [$company,$workspace,$team] = $this->team();
        $id = $this->claim($company,$workspace,$team['student']->id);

        $this->actingAs($team['partner']->user)
            ->post("/c/qa-pilot/app/claims/{$id}/decision", [
                'decision'=>'approve', 'approved_amount'=>'100.00',
                'decision_note'=>'Approved as per verified travel route',
            ])->assertSessionHasErrors('decision');

        $this->actingAs($team['manager']->user)
            ->post("/c/qa-pilot/app/claims/{$id}/decision", [
                'decision'=>'approve', 'approved_amount'=>'110.00',
                'decision_note'=>'Reduced to authorized travel reimbursement',
            ])->assertRedirect();
        $this->assertDatabaseHas('conveyance_claims', [
            'id'=>$id, 'amount'=>130, 'manager_recommended_amount'=>110,
            'approved_amount'=>null, 'status'=>'manager_reviewed',
        ]);

        $this->actingAs($team['partner']->user)
            ->post("/c/qa-pilot/app/claims/{$id}/decision", [
                'decision'=>'approve', 'approved_amount'=>'100.00',
                'decision_note'=>'Reduced according to the office travel policy',
            ])->assertRedirect();
        $this->assertDatabaseHas('conveyance_claims', [
            'id'=>$id, 'amount'=>130,
            'manager_recommended_amount'=>110, 'approved_amount'=>100,
            'status'=>'approved',
        ]);

        $this->post('/c/qa-pilot/app/payroll', ['month'=>'2026-10'])->assertRedirect();
        $this->assertDatabaseHas('payroll_lines', [
            'membership_id'=>$team['student']->id,
            'base_allowance'=>8000,
            'approved_claims'=>100,
            'total_amount'=>8100,
        ]);
        $this->assertDatabaseHas('tenant_audit_events', [
            'action'=>'claim.approved', 'entity_id'=>$id,
        ]);
    }

    public function test_reduction_requires_reason_and_approved_amount_cannot_exceed_claimed(): void
    {
        [$company,$workspace,$team] = $this->team();
        $id = $this->claim($company,$workspace,$team['student']->id);
        $this->actingAs($team['manager']->user)
            ->post("/c/qa-pilot/app/claims/{$id}/decision", [
                'decision'=>'approve',
            ])->assertRedirect();

        $this->actingAs($team['partner']->user)
            ->post("/c/qa-pilot/app/claims/{$id}/decision", [
                'decision'=>'approve', 'approved_amount'=>'140.00',
            ])->assertSessionHasErrors('approved_amount');
        $this->post("/c/qa-pilot/app/claims/{$id}/decision", [
            'decision'=>'approve', 'approved_amount'=>'100.00',
            'decision_note'=>'No',
        ])->assertSessionHasErrors('decision_note');

        $this->assertDatabaseHas('conveyance_claims', [
            'id'=>$id, 'status'=>'manager_reviewed', 'amount'=>130,
            'approved_amount'=>null,
        ]);
    }
}
