<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PracticeReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class Phase21ScalabilitySafeAggregationTest extends TestCase
{
    use RefreshDatabase;

    private function firm(string $slug): array
    {
        $company=DB::table('companies')->insertGetId([
            'name'=>'Scaling Partner '.$slug,'slug'=>$slug,'timezone'=>'Asia/Dhaka',
            'office_start'=>'10:00:00','office_end'=>'18:00:00',
            'grace_minutes'=>10,'weekly_holidays'=>'[5,6]',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $workspace=DB::table('workspaces')->insertGetId([
            'company_id'=>$company,'slug'=>'unit-one','name'=>'Partner One',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $partner=User::factory()->create();
        $partnerId=DB::table('memberships')->insertGetId([
            'company_id'=>$company,'workspace_id'=>$workspace,'user_id'=>$partner->id,
            'role'=>'partner','active'=>true,'monthly_allowance'=>'0.00',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        return compact('company','workspace','partner','partnerId','slug');
    }

    private function student(array $f): int
    {
        $user=User::factory()->create();
        return DB::table('memberships')->insertGetId([
            'company_id'=>$f['company'],'workspace_id'=>$f['workspace'],
            'user_id'=>$user->id,'role'=>'student','active'=>true,
            'monthly_allowance'=>'3000.00',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    public function test_practice_aggregates_many_approved_claims_and_leaves_without_loading_source_rows():void
    {
        $a=$this->firm('scale-a');
        $b=$this->firm('scale-b');
        $student=$this->student($a);
        $other=$this->student($b);
        $claims=[];
        for($i=0;$i<720;$i++){
            $claims[]=[
                'company_id'=>$a['company'],'workspace_id'=>$a['workspace'],
                'membership_id'=>$student,'journey_date'=>'2026-10-12',
                'journey_route'=>'Office to audit client','purpose'=>'Statutory fieldwork',
                'amount'=>'15.00','approved_amount'=>$i%2===0?'10.00':null,
                'status'=>$i%5===0?'paid':'approved',
                'created_at'=>now(),'updated_at'=>now(),
            ];
        }
        foreach(array_chunk($claims,100) as $part)DB::table('conveyance_claims')->insert($part);
        DB::table('conveyance_claims')->insert([
            'company_id'=>$b['company'],'workspace_id'=>$b['workspace'],
            'membership_id'=>$other,'journey_date'=>'2026-10-12',
            'journey_route'=>'Other confidential client',
            'purpose'=>'External practice','amount'=>'990000.00',
            'approved_amount'=>'990000.00','status'=>'approved',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $leave=[];
        for($i=0;$i<145;$i++){
            $leave[]=[
                'company_id'=>$a['company'],'workspace_id'=>$a['workspace'],
                'membership_id'=>$student,'leave_type'=>'casual',
                'date_from'=>'2026-10-12','date_to'=>'2026-10-12',
                'reason'=>'Synthetic authorized leave for aggregation regression.',
                'status'=>$i%5===0?'rejected':'approved',
                'created_at'=>now(),'updated_at'=>now(),
            ];
        }
        DB::table('leave_requests')->insert($leave);
        $queries=[];
        DB::listen(function($event)use(&$queries) {
            if(str_contains($event->sql,'conveyance_claims')
                || str_contains($event->sql,'leave_requests'))$queries[]=$event->sql;
        });
        $actor=(object)[
            'company_id'=>$a['company'],'workspace_id'=>$a['workspace'],
        ];
        $report=PracticeReport::month($actor,'2026-10');
        $this->assertCount(1,$report['rows']);
        $row=$report['rows']->first();
        $this->assertSame($student,$row->member_id);
        $this->assertSame(116,$row->leave_requests);
        $this->assertEqualsWithDelta(9000.0,$row->approved_claim_bdt,0.001);
        $this->assertSame(1,$report['totals']['members']);
        $this->assertCount(2,$queries);
        foreach($queries as $sql)$this->assertStringContainsStringIgnoringCase('group by',$sql);
        $this->assertDatabaseHas('conveyance_claims',['company_id'=>$b['company'],'amount'=>'990000.00']);
    }

    public function test_attendance_report_uses_stable_100_member_pages_without_leaking_other_tenants():void
    {
        $a=$this->firm('attendance-scale-a');
        $b=$this->firm('attendance-scale-b');
        $ids=[];
        $students=User::factory()->count(123)->create();
        $membershipRows=[];$attendanceRows=[];
        foreach($students as $user){
            $membershipRows[]=[
                'company_id'=>$a['company'],'workspace_id'=>$a['workspace'],
                'user_id'=>$user->id,'role'=>'student','active'=>true,
                'monthly_allowance'=>'3000.00','created_at'=>now(),'updated_at'=>now(),
            ];
        }
        DB::table('memberships')->insert($membershipRows);
        $ids=DB::table('memberships')->where('company_id',$a['company'])
            ->where('role','student')->pluck('id');
        foreach($ids as $id){
            $attendanceRows[]=[
                'company_id'=>$a['company'],'workspace_id'=>$a['workspace'],
                'membership_id'=>$id,'work_date'=>'2026-10-12','work_mode'=>'office',
                'started_at'=>'2026-10-12 10:00:00','late'=>false,'holiday'=>false,
                'created_at'=>now(),'updated_at'=>now(),
            ];
        }
        DB::table('attendance_sessions')->insert($attendanceRows);
        $foreignMember=$this->student($b);
        DB::table('attendance_sessions')->insert([
            'company_id'=>$b['company'],'workspace_id'=>$b['workspace'],
            'membership_id'=>$foreignMember,'work_date'=>'2026-10-12',
            'work_mode'=>'office','started_at'=>'2026-10-12 10:00:00',
            'late'=>false,'holiday'=>false,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->actingAs($a['partner']);
        $base='/c/'.$a['slug'].'/app/attendance/report?month=2026-10';
        $this->get($base)->assertOk()
            ->assertViewHas('rows',fn($rows)=>$rows->count()===100
                && $rows->total()===123 && $rows->currentPage()===1);
        $this->get($base.'&page=2')->assertOk()
            ->assertViewHas('rows',fn($rows)=>$rows->count()===23
                && $rows->total()===123 && $rows->currentPage()===2);
        $this->get($base.'&page=999')->assertOk()
            ->assertViewHas('rows',fn($rows)=>$rows->count()===0);
        $this->assertDatabaseCount('attendance_sessions',124);
    }

    public function test_capacity_diagnostics_are_non_mutating_company_scoped_and_do_not_certify_200gb():void
    {
        $a=$this->firm('capacity-a');
        $b=$this->firm('capacity-b');
        DB::table('erp_vault_quotas')->insert([
            'company_id'=>$a['company'],'workspace_id'=>$a['workspace'],
            'limit_bytes'=>400000000,'used_bytes'=>80000000,
            'reserved_bytes'=>1000000,'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('erp_vault_quotas')->insert([
            'company_id'=>$b['company'],'workspace_id'=>$b['workspace'],
            'limit_bytes'=>900000000,'used_bytes'=>700000000,
            'reserved_bytes'=>0,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $before=DB::table('erp_vault_quotas')->orderBy('id')->get()->toJson();
        $this->assertSame(0,Artisan::call('erp:scale:diagnose',[
            '--company'=>(string)$a['company'],'--json'=>true,
        ]));
        $payload=json_decode(Artisan::output(),true,512,JSON_THROW_ON_ERROR);
        $this->assertSame($a['company'],$payload['scope']['company_id']);
        $this->assertSame(80000000,$payload['vault']['used_bytes']);
        $this->assertSame(1000000,$payload['vault']['reserved_bytes']);
        $this->assertFalse($payload['large_tenant_export']['verified_200_gb_end_to_end_export']);
        $this->assertFalse($payload['vault']['covers_all_legacy_customer_files']);
        $this->assertFalse($payload['safe_for_customer_deletion']);
        $this->assertStringNotContainsString($a['slug'],Artisan::output());
        $this->assertSame($before,DB::table('erp_vault_quotas')->orderBy('id')->get()->toJson());
        $this->assertSame(1,Artisan::call('erp:scale:diagnose',['--company'=>'invalid']));
        $this->assertSame(1,Artisan::call('erp:vault:expire-staging',['--max'=>'10000']));
    }

    public function test_phase21_operational_indexes_exist_without_altering_existing_records():void
    {
        foreach([
            'attendance_sessions'=>'p21_attendance_member_month',
            'leave_requests'=>'p21_leave_scope_status_dates',
            'conveyance_claims'=>'p21_claim_scope_status_month',
            'audit_timesheets'=>'p21_timesheet_scope_month_status',
            'tenant_notifications'=>'p21_notification_unread_lookup',
            'erp_vault_uploads'=>'p21_vault_expiration_lookup',
            'saas_subscriptions'=>'p21_subscription_renewal_lookup',
            'tenant_exit_cases'=>'p21_exit_case_queue_lookup',
        ] as $table=>$name){
            $indexes=collect(Schema::getIndexes($table))->pluck('name')->all();
            $this->assertContains($name,$indexes,"Missing scaling index on $table");
        }
    }
}
