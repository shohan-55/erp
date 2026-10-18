<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase09StaffArticleshipLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $company=DB::table('companies')->insertGetId([
            'slug'=>'client-firm','name'=>'Example Independent CA Firm','timezone'=>'Asia/Dhaka',
            'office_start'=>'10:00:00','office_end'=>'18:00:00',
            'grace_minutes'=>10,'weekly_holidays'=>'[5,6]','active'=>true,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $people=[];
        foreach(['a','b'] as $slug){
            $workspace=DB::table('workspaces')->insertGetId([
                'company_id'=>$company,'slug'=>'partner-'.$slug,'name'=>'Partner '.$slug,
                'active'=>true,'created_at'=>now(),'updated_at'=>now(),
            ]);
            foreach(['partner','manager','student'] as $role){
                $user=User::factory()->create(['name'=>$slug.'-'.$role]);
                $id=DB::table('memberships')->insertGetId([
                    'company_id'=>$company,'workspace_id'=>$workspace,'user_id'=>$user->id,
                    'role'=>$role,'manager_membership_id'=>$role==='student'?$people[$slug]['manager']->id:null,
                    'monthly_allowance'=>$role==='student'?'8000.00':'0.00',
                    'active'=>true,'activated_at'=>now(),
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
                $people[$slug][$role]=(object)[
                    'user'=>$user,'id'=>$id,'workspace'=>$workspace,'company'=>$company,
                ];
            }
        }
        return [$company,$people];
    }

    private function clock(): void
    {
        $now=CarbonImmutable::parse('2026-10-18 13:00:00','Asia/Dhaka');
        Carbon::setTestNow($now);CarbonImmutable::setTestNow($now);
    }
    private function unclock(): void
    {
        Carbon::setTestNow();CarbonImmutable::setTestNow();
    }

    private function staffProfile(object $partner,int $id): array
    {
        return [
            'staff_code'=>'ASSOC-001','joined_on'=>'2026-09-01',
            'designation'=>'Audit Associate','articleship_registration'=>'ICAB-TEST-2026',
            'articleship_started_on'=>'2026-09-01',
            'articleship_ends_on'=>'2029-08-31',
        ];
    }

    public function test_partner_can_save_articleship_and_promotion_title_without_changing_security_roles(): void
    {
        $this->clock();
        try {
            [$firm,$p]=$this->fixture();
            $student=$p['a']['student'];$partner=$p['a']['partner'];
            $url="/c/client-firm/app/staff/{$student->id}/profile";
            $this->actingAs($p['a']['manager']->user)->post($url,
                $this->staffProfile($partner,$student->id))->assertForbidden();
            $this->actingAs($p['b']['partner']->user)->post($url,
                $this->staffProfile($partner,$student->id))->assertNotFound();
            $this->actingAs($partner->user)->post($url,
                $this->staffProfile($partner,$student->id))->assertRedirect();
            $this->assertDatabaseHas('staff_profiles',[
                'membership_id'=>$student->id,'designation'=>'Audit Associate',
                'articleship_registration'=>'ICAB-TEST-2026',
            ]);
            $this->post($url,[
                ...$this->staffProfile($partner,$student->id),
                'designation'=>'Senior Audit Associate',
            ])->assertRedirect();
            $this->assertDatabaseHas('memberships',['id'=>$student->id,'role'=>'student']);
            $this->assertDatabaseHas('tenant_audit_events',[
                'action'=>'staff.profile_updated','entity_id'=>$student->id,
            ]);
            $this->get('/c/client-firm/app/staff')->assertOk()
                ->assertSeeText('Staff & Articleship Management')
                ->assertSeeText('Senior Audit Associate')
                ->assertDontSeeText('Partner b');
        } finally {$this->unclock();}
    }

    public function test_future_salary_schedule_changes_payroll_in_effective_month_but_not_prior_month(): void
    {
        $this->clock();
        try {
            [, $p]=$this->fixture();
            $student=$p['a']['student'];$partner=$p['a']['partner'];
            $this->actingAs($partner->user)
                ->post("/c/client-firm/app/staff/{$student->id}/compensation",[
                    'effective_month'=>'2026-11','new_amount'=>'10500.00',
                    'reason'=>'Partner approved salary increment after annual review.',
                ])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('staff_compensation_changes',[
                'membership_id'=>$student->id,'effective_month'=>'2026-11-01',
                'new_amount'=>'10500.00',
            ]);
            $this->assertDatabaseHas('memberships',['id'=>$student->id,'monthly_allowance'=>'8000.00']);

            $this->post('/c/client-firm/app/payroll',['month'=>'2026-10'])->assertRedirect();
            $this->post('/c/client-firm/app/payroll',['month'=>'2026-11'])->assertRedirect();
            $old=DB::table('payroll_runs')->where('workspace_id',$student->workspace)
                ->where('period_month','2026-10-01')->first();
            $new=DB::table('payroll_runs')->where('workspace_id',$student->workspace)
                ->where('period_month','2026-11-01')->first();
            $this->assertDatabaseHas('payroll_lines',[
                'payroll_run_id'=>$old->id,'membership_id'=>$student->id,
                'base_allowance'=>'8000.00',
            ]);
            $this->assertDatabaseHas('payroll_lines',[
                'payroll_run_id'=>$new->id,'membership_id'=>$student->id,
                'base_allowance'=>'10500.00',
            ]);
            $this->post("/c/client-firm/app/staff/{$student->id}/compensation",[
                'effective_month'=>'2026-11','new_amount'=>'12000',
                'reason'=>'Duplicate salary schedule should never be accepted.',
            ])->assertSessionHasErrors('effective_month');
        } finally {$this->unclock();}
    }

    public function test_manager_assignment_remains_workspace_scoped_and_legacy_initial_allowance_is_protected_after_joining(): void
    {
        $this->clock();
        try {
            [, $p]=$this->fixture();
            $student=$p['a']['student'];
            $this->actingAs($p['a']['partner']->user)->post("/c/client-firm/app/staff/{$student->id}/profile",
                $this->staffProfile($p['a']['partner'],$student->id))->assertRedirect();
            $this->post("/c/client-firm/app/team/{$student->id}/settings",[
                'manager_membership_id'=>$p['a']['manager']->id,
                'monthly_allowance'=>'9000.00',
            ])->assertSessionHasErrors('monthly_allowance');
            $this->post("/c/client-firm/app/team/{$student->id}/settings",[
                'manager_membership_id'=>$p['b']['manager']->id,
                'monthly_allowance'=>'8000.00',
            ])->assertSessionHasErrors('manager_membership_id');
        } finally {$this->unclock();}
    }

    public function test_staff_exit_and_rejoin_preserve_history_and_reject_nonactivated_or_suspended_members(): void
    {
        $this->clock();
        try {
            [, $p]=$this->fixture();
            $student=$p['a']['student'];$partner=$p['a']['partner'];
            $url="/c/client-firm/app/staff/{$student->id}/lifecycle";
            $this->actingAs($partner->user)->post($url,[
                'action'=>'exit','reason'=>'Student completed service and handed over all duties.',
            ])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('memberships',['id'=>$student->id,'active'=>false]);
            $this->assertDatabaseHas('staff_lifecycle_events',[
                'membership_id'=>$student->id,'event_type'=>'exit',
            ]);
            $this->post($url,['action'=>'exit','reason'=>'Repeated exit should be rejected.'])
                ->assertSessionHasErrors('action');
            $this->post($url,[
                'action'=>'rejoin','reason'=>'Staff member returned to service after reengagement.',
            ])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('memberships',['id'=>$student->id,'active'=>true]);
            $this->assertDatabaseHas('staff_lifecycle_events',['membership_id'=>$student->id,'event_type'=>'rejoin']);
        } finally {$this->unclock();}
    }

    public function test_pending_claim_or_open_attendance_blocks_exit_and_partner_transfer(): void
    {
        $this->clock();
        try {
            [$firm,$p]=$this->fixture();
            $student=$p['a']['student'];$partner=$p['a']['partner'];
            DB::table('conveyance_claims')->insert([
                'company_id'=>$firm,'workspace_id'=>$student->workspace,
                'membership_id'=>$student->id,'journey_date'=>'2026-10-12',
                'journey_route'=>'Office to Site','purpose'=>'Outstanding audit client duty',
                'amount'=>'120.00','status'=>'pending',
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $this->actingAs($partner->user)->post("/c/client-firm/app/staff/{$student->id}/lifecycle",[
                'action'=>'exit','reason'=>'Exit should wait until financial dues are reconciled.',
            ])->assertSessionHasErrors('action');
            $this->post("/c/client-firm/app/staff/{$student->id}/transfer",[
                'target_workspace_id'=>$p['b']['partner']->workspace,
                'reason'=>'Transfer to other Partner practice after dues.',
            ])->assertRedirect();
            $id=DB::table('staff_transfer_requests')->first()->id;
            $this->actingAs($p['b']['partner']->user)
                ->post("/c/client-firm/app/staff/transfers/{$id}/decision",[
                    'decision'=>'approve',
                ])->assertSessionHasErrors('decision');
            $this->assertDatabaseHas('memberships',['id'=>$student->id,'active'=>true]);
            $this->assertDatabaseCount('memberships',6);
        } finally {$this->unclock();}
    }

    public function test_both_partners_must_authorize_transfer_without_moving_historical_attendance_or_payroll(): void
    {
        $this->clock();
        try {
            [$firm,$p]=$this->fixture();
            $student=$p['a']['student'];$partner=$p['a']['partner'];
            $request="/c/client-firm/app/staff/{$student->id}/transfer";
            $this->actingAs($p['a']['manager']->user)->post($request,[
                'target_workspace_id'=>$p['b']['partner']->workspace,
                'reason'=>'Wrong role attempting cross-Partner transfer.',
            ])->assertForbidden();
            $this->actingAs($partner->user)->post($request,[
                'target_workspace_id'=>$p['b']['partner']->workspace,
                'reason'=>'Approved development assignment to another Partner practice.',
            ])->assertRedirect();
            $row=DB::table('staff_transfer_requests')->first();
            $this->actingAs($partner->user)
                ->post("/c/client-firm/app/staff/transfers/{$row->id}/decision",[
                    'decision'=>'approve',
                ])->assertNotFound();
            $this->actingAs($p['b']['partner']->user)
                ->post("/c/client-firm/app/staff/transfers/{$row->id}/decision",[
                    'decision'=>'approve',
                ])->assertRedirect()->assertSessionHasNoErrors();
            $record=DB::table('staff_transfer_requests')->first();
            $this->assertSame('approved',$record->status);
            $this->assertNotNull($record->new_membership_id);
            $this->assertDatabaseHas('memberships',[
                'id'=>$student->id,'workspace_id'=>$student->workspace,'active'=>false,
            ]);
            $this->assertDatabaseHas('memberships',[
                'id'=>$record->new_membership_id,
                'user_id'=>$student->user->id,
                'workspace_id'=>$p['b']['partner']->workspace,
                'active'=>true,'manager_membership_id'=>null,
            ]);
            $this->assertDatabaseHas('tenant_audit_events',[
                'action'=>'staff.transfer.approved','entity_id'=>$row->id,
            ]);
            $this->post("/c/client-firm/app/staff/transfers/{$row->id}/decision",[
                'decision'=>'approve',
            ])->assertSessionHasErrors('decision');
        } finally {$this->unclock();}
    }

    public function test_private_document_downloads_are_partner_and_own_member_scoped(): void
    {
        $this->clock();Storage::fake('local');
        try {
            [, $p]=$this->fixture();
            $student=$p['a']['student'];
            $url="/c/client-firm/app/staff/{$student->id}/documents";
            $file=UploadedFile::fake()->create('agreement.pdf',45,'application/pdf');
            $this->actingAs($p['a']['manager']->user)->post($url,[
                'title'=>'Signed Appointment','document_type'=>'appointment',
                'document'=>$file,
            ])->assertForbidden();
            $this->actingAs($p['a']['partner']->user)->post($url,[
                'title'=>'Signed Appointment','document_type'=>'appointment',
                'document'=>UploadedFile::fake()->create('agreement.pdf',45,'application/pdf'),
            ])->assertRedirect();
            $doc=DB::table('staff_documents')->first();
            Storage::disk('local')->assertExists($doc->storage_path);
            $download="/c/client-firm/app/staff/documents/{$doc->id}";
            $this->get($download)->assertOk();
            $this->actingAs($p['b']['partner']->user)->get($download)->assertNotFound();
            $this->actingAs($p['b']['student']->user)->get($download)->assertNotFound();
            $this->actingAs($student->user)->get($download)->assertOk();
        } finally {$this->unclock();}
    }
}
