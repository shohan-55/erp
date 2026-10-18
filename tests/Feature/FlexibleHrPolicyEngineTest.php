<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AttendanceClock;
use App\Support\PolicyResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FlexibleHrPolicyEngineTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $slug = 'firm-a'): array
    {
        $companyId = DB::table('companies')->insertGetId([
            'slug' => $slug, 'name' => 'QA '.$slug, 'timezone' => 'Asia/Dhaka',
            'office_start' => '10:00:00', 'office_end' => '18:00:00',
            'grace_minutes' => 10, 'weekly_holidays' => '[5,6]',
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $workspaceId = DB::table('workspaces')->insertGetId([
            'company_id' => $companyId, 'slug' => 'unit-1', 'name' => 'Partner Unit',
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $people = [];
        foreach (['partner','manager','student'] as $role) {
            $u = User::factory()->create();
            $id = DB::table('memberships')->insertGetId([
                'company_id' => $companyId, 'workspace_id' => $workspaceId,
                'user_id' => $u->id, 'role' => $role,
                'active' => true, 'monthly_allowance' => 0,
                'manager_membership_id' => $role === 'student' ? $people['manager']['id'] : null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $people[$role] = ['id' => $id,'user' => $u];
        }
        return [(object) DB::table('companies')->find($companyId),
            (object) DB::table('workspaces')->find($workspaceId), $people];
    }

    private function rules(array $changes = []): array
    {
        return array_replace([
            'effective_date' => '2026-11-01',
            'office_start' => '09:30',
            'office_end' => '18:00',
            'grace_minutes' => '10',
            'weekly_holidays' => [5,6],
            'half_day_warning_time' => '',
            'late_review_days' => '3',
            'grace_late_exempt' => '1',
            'reason' => 'Operational shift planning for next month',
        ], $changes);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-18 11:00:00', 'Asia/Dhaka'));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public function test_partner_and_manager_have_dedicated_menu_but_student_is_forbidden(): void
    {
        [, , $p] = $this->fixture();
        foreach (['partner','manager'] as $role) {
            $this->actingAs($p[$role]['user'])->get('/c/firm-a/app')
                ->assertOk()->assertSeeText('Smart HR Policy Center');
            $this->get('/c/firm-a/app/settings/hr-policies')->assertOk()
                ->assertSeeText('Create a Future Policy Version')
                ->assertSeeText('Version History & Approval Queue');
        }
        $this->actingAs($p['student']['user'])
            ->get('/c/firm-a/app/settings/hr-policies')->assertForbidden();
        $this->get('/c/firm-a/app')->assertOk()->assertDontSeeText('Smart HR Policy Center');
    }

    public function test_manager_default_requires_partner_approval_and_approved_version_is_future_only(): void
    {
        [$company,$workspace,$p] = $this->fixture();
        $this->actingAs($p['manager']['user'])
            ->post('/c/firm-a/app/settings/hr-policies/revisions', $this->rules())
            ->assertRedirect();
        $revision = DB::table('workspace_hr_policy_revisions')->first();
        $this->assertSame('pending', $revision->status);
        $this->assertSame(0, (int) $revision->sensitive);
        $this->assertSame('10:00:00', PolicyResolver::forDate($company,$workspace,'2026-11-03')['office_start']);

        $this->actingAs($p['partner']['user'])
            ->post('/c/firm-a/app/settings/hr-policies/revisions/'.$revision->id.'/decision',[
                'decision' => 'approve',
            ])->assertRedirect();
        $approved = DB::table('workspace_hr_policy_revisions')->first();
        $this->assertSame('approved', $approved->status);
        $this->assertSame('10:00:00', PolicyResolver::forDate($company,$workspace,'2026-10-31')['office_start']);
        $this->assertSame('09:30:00', PolicyResolver::forDate($company,$workspace,'2026-11-01')['office_start']);
        $this->assertDatabaseHas('tenant_audit_events',['action'=>'hr_policy.approve','actor_membership_id'=>$p['partner']['id']]);
    }

    public function test_partner_delegation_allows_only_non_sensitive_manager_direct_changes(): void
    {
        [$company,$workspace,$p] = $this->fixture();
        $this->actingAs($p['manager']['user'])
            ->post('/c/firm-a/app/settings/hr-policies/permissions',['manager_mode'=>'direct'])
            ->assertForbidden();

        $this->actingAs($p['partner']['user'])
            ->post('/c/firm-a/app/settings/hr-policies/permissions',['manager_mode'=>'direct'])
            ->assertRedirect();

        $this->actingAs($p['manager']['user'])
            ->post('/c/firm-a/app/settings/hr-policies/revisions',
                $this->rules(['effective_date'=>'2026-11-01']))
            ->assertRedirect();
        $first = DB::table('workspace_hr_policy_revisions')->orderBy('id')->first();
        $this->assertSame('approved',$first->status);
        $this->assertSame(0,(int) $first->sensitive);

        // Touch the count, grace exemption and optional visual threshold;
        // direct permissions cannot approve such changes.
        $this->post('/c/firm-a/app/settings/hr-policies/revisions',$this->rules([
            'effective_date'=>'2026-11-05',
            'late_review_days'=>4,
            'grace_late_exempt'=>0,
            'half_day_warning_time'=>'14:00',
        ]))->assertRedirect();

        $second = DB::table('workspace_hr_policy_revisions')->orderByDesc('id')->first();
        $this->assertSame('pending',$second->status);
        $this->assertSame(1,(int)$second->sensitive);
        $this->assertSame(3, PolicyResolver::forDate($company,$workspace,'2026-11-10')['late_review_days']);
        $this->assertSame('direct', DB::table('workspace_hr_policy_revisions')
            ->where('id',$first->id)->value('manager_mode_snapshot'));
    }

    public function test_versioned_late_policy_changes_review_counter_without_rewriting_past_days(): void
    {
        [$company,$workspace,$p] = $this->fixture();
        $student=$p['student'];
        DB::table('attendance_sessions')->insert([
            'company_id'=>$company->id,'workspace_id'=>$workspace->id,
            'membership_id'=>$student['id'],'work_date'=>'2026-10-20',
            'work_mode'=>'office','started_at'=>'2026-10-20 04:05:00',
            'late'=>true,'late_within_grace'=>true,'holiday'=>false,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('attendance_sessions')->insert([
            'company_id'=>$company->id,'workspace_id'=>$workspace->id,
            'membership_id'=>$student['id'],'work_date'=>'2026-11-04',
            'work_mode'=>'office','started_at'=>'2026-11-04 04:05:00',
            'late'=>true,'late_within_grace'=>true,'holiday'=>false,
            'created_at'=>now(),'updated_at'=>now(),
        ]);

        $this->actingAs($p['partner']['user'])
            ->post('/c/firm-a/app/settings/hr-policies/revisions',$this->rules([
                'effective_date'=>'2026-11-01',
                'office_start'=>'10:00',
                'grace_late_exempt'=>0, 'late_review_days'=>4,
            ]))->assertRedirect();
        $future=PolicyResolver::forDate($company,$workspace,'2026-11-04');
        $this->assertFalse($future['grace_late_exempt']);
        $this->assertSame(4,$future['late_review_days']);
        $this->assertTrue(PolicyResolver::forDate($company,$workspace,'2026-10-20')['grace_late_exempt']);

        $who=(object)['id'=>$student['id']];
        $this->assertSame(0, AttendanceClock::qualifyingLateDays($company,$workspace,$who,'2026-10-20'));
        $this->assertSame(1, AttendanceClock::qualifyingLateDays($company,$workspace,$who,'2026-11-04'));
        $this->assertDatabaseCount('payroll_line_adjustments',0);
        $this->assertDatabaseCount('leave_requests',0);
    }

    public function test_sensitive_policy_cannot_use_past_date_or_change_frozen_payroll_period(): void
    {
        [$company,$workspace,$p] = $this->fixture();
        $this->actingAs($p['partner']['user'])
            ->post('/c/firm-a/app/settings/hr-policies/revisions', $this->rules([
                'effective_date'=>'2026-10-18',
            ]))->assertSessionHasErrors('effective_date');

        DB::table('payroll_runs')->insert([
            'company_id'=>$company->id,'workspace_id'=>$workspace->id,
            'period_month'=>'2026-11-01','status'=>'reviewed',
            'policy_snapshot'=>'{}','created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->post('/c/firm-a/app/settings/hr-policies/revisions',$this->rules())
            ->assertSessionHasErrors('effective_date');
        $this->assertDatabaseCount('workspace_hr_policy_revisions',0);
    }

    public function test_partner_cannot_approve_other_tenant_proposal(): void
    {
        [,,$p] = $this->fixture();
        [,,$outside] = $this->fixture('firm-b');
        $this->actingAs($outside['manager']['user'])
            ->post('/c/firm-b/app/settings/hr-policies/revisions',$this->rules())
            ->assertRedirect();
        $id=DB::table('workspace_hr_policy_revisions')->first()->id;

        $this->actingAs($p['partner']['user'])
            ->post('/c/firm-a/app/settings/hr-policies/revisions/'.$id.'/decision',['decision'=>'approve'])
            ->assertNotFound();
        $this->assertSame('pending',DB::table('workspace_hr_policy_revisions')->first()->status);
    }
}
