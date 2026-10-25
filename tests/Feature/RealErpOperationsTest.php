<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RealErpOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $slug = 'rac'): int
    {
        return DB::table('companies')->insertGetId([
            'slug' => $slug, 'name' => 'Test Chartered Accountants '.$slug,
            'timezone' => 'Asia/Dhaka', 'office_start' => '10:00:00', 'office_end' => '18:00:00',
            'grace_minutes' => 10, 'weekly_holidays' => json_encode([5,6]), 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function workspace(int $companyId, string $slug): int
    {
        return DB::table('workspaces')->insertGetId([
            'company_id' => $companyId, 'slug' => $slug, 'name' => $slug,
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function member(int $companyId, int $workspaceId, string $role, ?int $managerId = null, string $allowance = '8000.00'): object
    {
        $user = User::factory()->create([
            'password' => Hash::make('TestVerySecret42'),
        ]);

        $id = DB::table('memberships')->insertGetId([
            'company_id' => $companyId, 'workspace_id' => $workspaceId,
            'user_id' => $user->id, 'role' => $role,
            'manager_membership_id' => $managerId, 'monthly_allowance' => $allowance,
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return (object) ['id' => $id, 'user' => $user, 'company_id' => $companyId, 'workspace_id' => $workspaceId];
    }

    public function test_unknown_company_and_guest_are_not_authenticated(): void
    {
        $this->get('/c/not-found/app/login')->assertNotFound();
        $this->get('/c/rac/app')->assertRedirect('/c/rac/app/login');
    }

    public function test_real_login_uses_credentials_and_company_membership(): void
    {
        $companyId = $this->company();
        $workspaceId = $this->workspace($companyId, 'partner-1');
        $student = $this->member($companyId, $workspaceId, 'student');

        $this->post('/c/rac/app/login', ['email' => $student->user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');

        $this->post('/c/rac/app/login', ['email' => $student->user->email, 'password' => 'TestVerySecret42'])
            ->assertRedirect('/c/rac/app');

        $this->assertAuthenticatedAs($student->user);
        $this->get('/c/rac/app')->assertOk()->assertSee('Workspace Dashboard');
    }

    public function test_mismatched_company_membership_is_forbidden(): void
    {
        $rac = $this->company('rac');
        $other = $this->company('other');
        $workspace = $this->workspace($rac, 'partner-1');
        $this->workspace($other, 'partner-1');
        $student = $this->member($rac, $workspace, 'student');

        $this->actingAs($student->user)
            ->get('/c/other/app')
            ->assertForbidden();

        $this->actingAs($student->user)
            ->post('/c/other/app/attendance/check-in', ['work_mode' => 'office'])
            ->assertForbidden();
    }

    public function test_real_attendance_has_one_active_session_and_audits_out(): void
    {
        $companyId = $this->company();
        $workspaceId = $this->workspace($companyId, 'partner-1');
        $student = $this->member($companyId, $workspaceId, 'student');

        $this->actingAs($student->user)
            ->post('/c/rac/app/attendance/check-in', ['work_mode'=>'client', 'client_site'=>'Audit Client'])
            ->assertRedirect();

        $this->assertDatabaseCount('attendance_sessions', 1);
        $this->assertDatabaseHas('attendance_sessions', ['membership_id'=>$student->id, 'work_mode'=>'client', 'client_site'=>'Audit Client']);

        $this->post('/c/rac/app/attendance/check-in', ['work_mode'=>'office'])
            ->assertSessionHasErrors('work_mode');
        $this->assertDatabaseCount('attendance_sessions', 1);

        $this->post('/c/rac/app/attendance/check-out')->assertRedirect();
        $this->assertNotNull(DB::table('attendance_sessions')->first()->ended_at);
        $this->assertEquals(2, DB::table('tenant_audit_events')->count());
    }

    public function test_partner_boundary_and_manager_team_scope_cannot_be_bypassed(): void
    {
        $companyId = $this->company();
        $w1 = $this->workspace($companyId, 'partner-1');
        $w2 = $this->workspace($companyId, 'partner-2');
        $p1 = $this->member($companyId, $w1, 'partner');
        $p2 = $this->member($companyId, $w2, 'partner');
        $manager = $this->member($companyId, $w1, 'manager');
        $managerOther = $this->member($companyId, $w1, 'manager');
        $student = $this->member($companyId, $w1, 'student', $manager->id);
        $otherStudent = $this->member($companyId, $w1, 'student', $managerOther->id);

        $firstId = $this->leaveRow($student->id, $companyId, $w1);
        $secondId = $this->leaveRow($otherStudent->id, $companyId, $w1);

        $this->actingAs($manager->user)->post("/c/rac/app/leave/$secondId/decision", [
            'decision'=>'approve',
        ])->assertNotFound();

        $this->post("/c/rac/app/leave/$firstId/decision", [
            'decision'=>'approve',
        ])->assertRedirect();
        $this->assertDatabaseHas('leave_requests', ['id'=>$firstId, 'status'=>'manager_reviewed']);
        $this->assertDatabaseHas('leave_requests', ['id'=>$secondId, 'status'=>'pending']);

        $this->actingAs($p2->user)->post("/c/rac/app/leave/$firstId/decision", [
            'decision'=>'approve',
        ])->assertNotFound();

        $this->actingAs($p1->user)->post("/c/rac/app/leave/$firstId/decision", [
            'decision'=>'approve',
        ])->assertRedirect();
        $this->assertDatabaseHas('leave_requests', ['id'=>$firstId, 'status'=>'approved']);
    }

    public function test_leave_student_submission_and_partner_review_are_real(): void
    {
        $companyId = $this->company();
        $w1 = $this->workspace($companyId, 'partner-1');
        $partner = $this->member($companyId, $w1, 'partner');
        $student = $this->member($companyId, $w1, 'student');

        $this->actingAs($student->user)->post('/c/rac/app/leave', [
            'leave_type'=>'annual', 'date_from'=>'2026-11-01',
            'date_to'=>'2026-11-02', 'reason'=>'Family commitment',
        ])->assertRedirect();

        $row = DB::table('leave_requests')->first();
        $this->assertNotNull($row);
        $this->assertSame('pending', $row->status);

        $this->actingAs($partner->user)->post("/c/rac/app/leave/{$row->id}/decision", [
            'decision'=>'approve',
        ])->assertRedirect();

        $this->assertDatabaseHas('leave_requests', ['id'=>$row->id, 'status'=>'approved']);
        $this->assertDatabaseHas('tenant_notifications', ['recipient_membership_id'=>$student->id]);
    }

    public function test_claim_evidence_is_private_and_other_partner_cannot_download_it(): void
    {
        Storage::fake('local');
        $companyId = $this->company();
        $w1 = $this->workspace($companyId, 'partner-1');
        $w2 = $this->workspace($companyId, 'partner-2');
        $student = $this->member($companyId, $w1, 'student');
        $other = $this->member($companyId, $w2, 'partner');

        $this->actingAs($student->user)->post('/c/rac/app/claims', [
            'journey_date'=>'2026-10-11', 'journey_route'=>'Office to sample client',
            'purpose'=>'Client audit engagement travel',
            'amount'=>'250.25',
            'receipt'=>UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf'),
        ])->assertRedirect();

        $claim = DB::table('conveyance_claims')->first();
        $this->assertNotNull($claim);
        Storage::disk('local')->assertExists($claim->receipt_path);

        $this->get("/c/rac/app/claims/{$claim->id}/receipt")->assertOk();
        $this->actingAs($other->user)
            ->get("/c/rac/app/claims/{$claim->id}/receipt")->assertNotFound();
    }

    public function test_partner_payroll_uses_snapshot_with_claim_once_and_locks(): void
    {
        $companyId = $this->company();
        $w1 = $this->workspace($companyId, 'partner-1');
        $partner = $this->member($companyId, $w1, 'partner');
        $student = $this->member($companyId, $w1, 'student', null, '8500.00');

        $claimId = DB::table('conveyance_claims')->insertGetId([
            'company_id'=>$companyId,'workspace_id'=>$w1,'membership_id'=>$student->id,
            'journey_date'=>'2026-10-03','journey_route'=>'Office to Client',
            'purpose'=>'Audit client visit and verification','amount'=>'125.50','status'=>'approved',
            'created_at'=>now(),'updated_at'=>now(),
        ]);

        $this->actingAs($partner->user)->post('/c/rac/app/payroll', ['month'=>'2026-10'])->assertRedirect();
        $run = DB::table('payroll_runs')->first();
        $this->assertNotNull($run);
        $this->assertDatabaseHas('payroll_lines', [
            'payroll_run_id'=>$run->id,'membership_id'=>$student->id,
            'base_allowance'=>8500,'approved_claims'=>125.5,'total_amount'=>8625.5,
        ]);

        $this->post("/c/rac/app/payroll/{$run->id}/transition", ['action'=>'review'])->assertRedirect();
        $this->assertDatabaseHas('conveyance_claims', ['id'=>$claimId,'reserved_payroll_run_id'=>$run->id]);
        $this->post("/c/rac/app/payroll/{$run->id}/transition", ['action'=>'approve'])->assertRedirect();
        $this->post("/c/rac/app/payroll/{$run->id}/transition", [
            'action'=>'record_paid', 'payment_reference'=>'TRANSFER-987654',
            'payment_channel'=>'bank_transfer',
            'payment_date'=>\Carbon\CarbonImmutable::now('Asia/Dhaka')->toDateString(),
            'reconciled_total'=>'8625.50',
            'payment_evidence'=>\Illuminate\Http\UploadedFile::fake()->create('bank-evidence.pdf', 32, 'application/pdf'),
        ])->assertRedirect();
        $this->assertDatabaseHas('conveyance_claims', ['id'=>$claimId,'status'=>'paid','paid_payroll_run_id'=>$run->id]);
        $this->post("/c/rac/app/payroll/{$run->id}/transition", ['action'=>'lock'])->assertRedirect();
        $this->assertDatabaseHas('payroll_runs', ['id'=>$run->id,'status'=>'locked']);
        $this->post("/c/rac/app/payroll/{$run->id}/transition", [
            'action'=>'record_paid','payment_reference'=>'TRANSFER-987654',
            'payment_channel'=>'bank_transfer',
            'payment_date'=>\Carbon\CarbonImmutable::now('Asia/Dhaka')->toDateString(),
            'reconciled_total'=>'8625.50',
            'payment_evidence'=>\Illuminate\Http\UploadedFile::fake()->create('bank-evidence.pdf', 32, 'application/pdf'),
        ])->assertSessionHasErrors('action');
    }

    public function test_manager_cannot_generate_or_advance_payroll(): void
    {
        $companyId = $this->company();
        $workspace = $this->workspace($companyId, 'partner-1');
        $manager = $this->member($companyId, $workspace, 'manager');

        $this->actingAs($manager->user)->post('/c/rac/app/payroll', ['month'=>'2026-10'])->assertForbidden();
        $this->assertDatabaseCount('payroll_runs', 0);
    }

    public function test_notifications_are_scoped_to_the_recipient(): void
    {
        $companyId = $this->company();
        $workspace = $this->workspace($companyId, 'partner-1');
        $a = $this->member($companyId, $workspace, 'student');
        $b = $this->member($companyId, $workspace, 'student');
        $id = DB::table('tenant_notifications')->insertGetId([
            'company_id'=>$companyId,'workspace_id'=>$workspace,
            'recipient_membership_id'=>$a->id,
            'title'=>'Test Notice','message'=>'Private notification',
            'created_at'=>now(),'updated_at'=>now(),
        ]);

        $this->actingAs($b->user)->post("/c/rac/app/notifications/$id/read")->assertRedirect();
        $this->assertNull(DB::table('tenant_notifications')->where('id', $id)->first()->read_at);
        $this->actingAs($a->user)->post("/c/rac/app/notifications/$id/read")->assertRedirect();
        $this->assertNotNull(DB::table('tenant_notifications')->where('id', $id)->first()->read_at);
    }

    private function leaveRow(int $memberId, int $companyId, int $workspaceId): int
    {
        return DB::table('leave_requests')->insertGetId([
            'company_id'=>$companyId,'workspace_id'=>$workspaceId,'membership_id'=>$memberId,
            'leave_type'=>'annual','date_from'=>'2026-11-05','date_to'=>'2026-11-06',
            'reason'=>'Family matters','status'=>'pending','created_at'=>now(),'updated_at'=>now(),
        ]);
    }
}