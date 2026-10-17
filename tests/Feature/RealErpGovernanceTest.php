<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PolicyResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RealErpGovernanceTest extends TestCase
{
    use RefreshDatabase;

    private function setupCompany(int $partners = 3): array
    {
        $companyId = DB::table('companies')->insertGetId([
            'slug'=>'rac', 'name'=>'Rahman Anis & Co.', 'timezone'=>'Asia/Dhaka',
            'office_start'=>'10:00:00', 'office_end'=>'18:00:00',
            'grace_minutes'=>10, 'weekly_holidays'=>'[5,6]',
            'active'=>true, 'created_at'=>now(), 'updated_at'=>now(),
        ]);

        $members = [];
        $workspaces = [];
        for ($i = 1; $i <= $partners; $i++) {
            $w = DB::table('workspaces')->insertGetId([
                'company_id'=>$companyId,'slug'=>"partner-$i",'name'=>"Partner Unit $i",
                'active'=>true,'created_at'=>now(),'updated_at'=>now(),
            ]);
            $user = User::factory()->create();
            $m = DB::table('memberships')->insertGetId([
                'company_id'=>$companyId,'workspace_id'=>$w,'user_id'=>$user->id,
                'role'=>'partner','active'=>true,'monthly_allowance'=>'0.00',
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $members[] = (object) ['id'=>$m,'user'=>$user];
            $workspaces[] = $w;
        }

        return [$companyId,$workspaces,$members];
    }

    private function nextMonth(): string
    {
        return CarbonImmutable::now('Asia/Dhaka')->startOfMonth()->addMonth()->toDateString();
    }

    private function proposalPayload(): array
    {
        return [
            'effective_date'=>$this->nextMonth(),
            'office_start'=>'09:30',
            'office_end'=>'17:30',
            'grace_minutes'=>12,
            'weekly_holidays'=>[5,6],
            'justification'=>'Improve office scheduling across the practice partners.',
        ];
    }

    public function test_three_separate_partner_approvals_are_required_for_company_policy(): void
    {
        [$companyId,$workspaces,$partners] = $this->setupCompany();
        $data = $this->proposalPayload();

        $this->actingAs($partners[0]->user)->post('/c/rac/app/policies/company/propose', $data)->assertRedirect();
        $proposal = DB::table('company_policy_proposals')->firstOrFail();
        $this->assertSame('pending', $proposal->status);

        $this->post("/c/rac/app/policies/company/{$proposal->id}/vote", ['decision'=>'approve'])->assertRedirect();
        $this->assertDatabaseHas('company_policy_proposals', ['id'=>$proposal->id, 'status'=>'pending']);

        $this->actingAs($partners[1]->user)
            ->post("/c/rac/app/policies/company/{$proposal->id}/vote", ['decision'=>'approve'])->assertRedirect();
        $this->assertDatabaseHas('company_policy_proposals', ['id'=>$proposal->id, 'status'=>'pending']);

        $this->actingAs($partners[2]->user)
            ->post("/c/rac/app/policies/company/{$proposal->id}/vote", ['decision'=>'approve'])->assertRedirect();
        $this->assertDatabaseHas('company_policy_proposals', ['id'=>$proposal->id, 'status'=>'approved']);
        $this->assertDatabaseCount('company_policy_votes', 3);

        $company = DB::table('companies')->firstOrFail();
        $workspace = DB::table('workspaces')->where('id',$workspaces[0])->firstOrFail();

        $today = PolicyResolver::forDate($company,$workspace,now('Asia/Dhaka')->toDateString());
        $future = PolicyResolver::forDate($company,$workspace,$data['effective_date']);
        $this->assertSame('10:00:00',$today['office_start']);
        $this->assertSame('09:30:00',$future['office_start']);
        $this->assertSame(12,$future['grace_minutes']);
        $this->assertSame($proposal->id,$future['company_policy_proposal_id']);
    }

    public function test_one_rejection_stops_proposal_immediately(): void
    {
        [,,$partners] = $this->setupCompany();
        $this->actingAs($partners[0]->user)->post('/c/rac/app/policies/company/propose',$this->proposalPayload())->assertRedirect();
        $proposal = DB::table('company_policy_proposals')->firstOrFail();

        $this->actingAs($partners[1]->user)
            ->post("/c/rac/app/policies/company/{$proposal->id}/vote",['decision'=>'reject'])->assertRedirect();

        $this->assertDatabaseHas('company_policy_proposals',['id'=>$proposal->id,'status'=>'rejected']);
        $this->actingAs($partners[2]->user)
            ->post("/c/rac/app/policies/company/{$proposal->id}/vote",['decision'=>'approve'])
            ->assertSessionHasErrors('decision');
    }

    public function test_roster_change_suspends_pending_proposal(): void
    {
        [,,$partners] = $this->setupCompany();
        $this->actingAs($partners[0]->user)
            ->post('/c/rac/app/policies/company/propose',$this->proposalPayload())->assertRedirect();
        $proposal = DB::table('company_policy_proposals')->firstOrFail();

        DB::table('memberships')->where('id',$partners[2]->id)->update(['active'=>false]);
        $this->post("/c/rac/app/policies/company/{$proposal->id}/vote",['decision'=>'approve'])
            ->assertRedirect();
        $this->assertDatabaseHas('company_policy_proposals',['id'=>$proposal->id,'status'=>'suspended']);
        $this->assertDatabaseCount('company_policy_votes',0);
    }

    public function test_company_policy_proposal_rejects_fewer_than_three_rac_partners(): void
    {
        [,,$partners] = $this->setupCompany(2);
        $this->actingAs($partners[0]->user)
            ->post('/c/rac/app/policies/company/propose',$this->proposalPayload())
            ->assertSessionHasErrors('effective_date');
        $this->assertDatabaseCount('company_policy_proposals',0);
    }

    public function test_partner_override_is_private_and_effective_dated(): void
    {
        [$companyId,$workspaces,$partners] = $this->setupCompany();
        $payload = $this->proposalPayload();
        unset($payload['justification']);
        $payload['grace_minutes'] = 7;

        $this->actingAs($partners[0]->user)
            ->post('/c/rac/app/policies/workspace/override',$payload)->assertRedirect();

        $company = DB::table('companies')->firstOrFail();
        $own = DB::table('workspaces')->where('id',$workspaces[0])->firstOrFail();
        $other = DB::table('workspaces')->where('id',$workspaces[1])->firstOrFail();

        $ownFuture = PolicyResolver::forDate($company,$own,$payload['effective_date']);
        $otherFuture = PolicyResolver::forDate($company,$other,$payload['effective_date']);
        $this->assertSame(7,$ownFuture['grace_minutes']);
        $this->assertSame(10,$otherFuture['grace_minutes']);
        $this->assertNotNull($ownFuture['workspace_override_id']);
        $this->assertNull($otherFuture['workspace_override_id']);

        $this->actingAs($partners[1]->user)->get('/c/rac/app/policies')
            ->assertOk()->assertDontSee('Partner Unit 01 · Override');
    }

    public function test_backdated_policy_changes_are_refused(): void
    {
        [,,$partners] = $this->setupCompany();
        $payload = $this->proposalPayload();
        $payload['effective_date'] = '2026-01-01';
        $this->actingAs($partners[0]->user)
            ->post('/c/rac/app/policies/company/propose',$payload)
            ->assertSessionHasErrors('effective_date');

        $this->post('/c/rac/app/policies/workspace/override',$payload)
            ->assertSessionHasErrors('effective_date');
    }
}