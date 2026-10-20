<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ErpFeatures;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class Phase08CompanyFeatureGovernanceTest extends TestCase
{
    use RefreshDatabase;

    private function firm(string $slug, int $partners = 3): array
    {
        $company = DB::table('companies')->insertGetId([
            'slug' => $slug, 'name' => $slug.' Firm',
            'timezone' => 'Asia/Dhaka', 'active' => true,
            'office_start' => '10:00:00', 'office_end' => '18:00:00',
            'grace_minutes' => 10, 'weekly_holidays' => '[5,6]',
            'min_active_partners_for_company_policy' => 3,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $people = [];
        for ($i = 0; $i < $partners; $i++) {
            $ws = DB::table('workspaces')->insertGetId([
                'company_id' => $company, 'name' => 'Partner '.$i,
                'slug' => 'p'.$i, 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $user = User::factory()->create();
            $mid = DB::table('memberships')->insertGetId([
                'company_id' => $company, 'workspace_id' => $ws,
                'user_id' => $user->id, 'role' => 'partner', 'active' => true,
                'monthly_allowance' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $people[] = (object) ['id' => $mid, 'user' => $user, 'workspace' => $ws];
        }
        return compact('company', 'people');
    }

    private function month(int $offset = 1): string
    {
        return CarbonImmutable::now('Asia/Dhaka')->startOfMonth()
            ->addMonths($offset)->toDateString();
    }

    private function propose(string $slug, string $module = 'leave', string $operation = 'disable', ?string $date = null)
    {
        return $this->post("/c/{$slug}/app/settings/features/company/proposals", [
            'feature_key' => $module, 'operation' => $operation,
            'effective_date' => $date ?? $this->month(),
            'reason' => 'Proposed to all active Partners for a future monthly policy.',
        ]);
    }

    private function vote(string $slug, int $id, string $decision = 'approve')
    {
        return $this->post("/c/{$slug}/app/settings/features/company/proposals/{$id}/vote", [
            'decision' => $decision,
        ]);
    }

    private function atPolicyDate(string $date, callable $callback): void
    {
        $then = CarbonImmutable::parse($date, 'Asia/Dhaka')->addDay()->setTime(12, 0);
        Carbon::setTestNow($then);
        CarbonImmutable::setTestNow($then);
        try { $callback(); }
        finally { Carbon::setTestNow(); CarbonImmutable::setTestNow(); }
    }

    public function test_three_votes_are_required_before_a_future_feature_change(): void
    {
        $f = $this->firm('rac');
        $this->actingAs($f['people'][0]->user)->get('/c/rac/app/settings/features')
            ->assertOk()->assertSee('Company-wide Feature Policy Proposals');
        $this->propose('rac')->assertRedirect()->assertSessionHasNoErrors();
        $p = DB::table('company_feature_policy_proposals')->firstOrFail();
        $this->assertSame('pending', $p->status);
        $this->get('/c/rac/app/leave')->assertOk();
        $this->vote('rac', $p->id)->assertRedirect();
        $this->vote('rac', $p->id)->assertSessionHasErrors('decision');
        $this->actingAs($f['people'][1]->user)->vote('rac', $p->id)->assertRedirect();
        $this->assertDatabaseHas('company_feature_policy_proposals', ['id' => $p->id, 'status' => 'pending']);
        $this->actingAs($f['people'][2]->user)->vote('rac', $p->id)->assertRedirect();
        $this->assertDatabaseHas('company_feature_policy_proposals', ['id' => $p->id, 'status' => 'approved']);
        $this->assertDatabaseCount('company_feature_policy_votes', 3);
        $this->get('/c/rac/app/leave')->assertOk();
        $this->atPolicyDate($p->effective_date, function () use ($f) {
            foreach ($f['people'] as $person) {
                $this->actingAs($person->user)->get('/c/rac/app/leave')->assertForbidden();
            }
            $this->get('/c/rac/app/settings/features')->assertOk();
        });
    }

    public function test_rejection_roster_change_and_minimum_quorum_fail_closed(): void
    {
        $f = $this->firm('rac');
        $this->actingAs($f['people'][0]->user)->propose('rac')->assertRedirect();
        $p = DB::table('company_feature_policy_proposals')->firstOrFail();
        $this->actingAs($f['people'][1]->user)->vote('rac', $p->id, 'reject')->assertRedirect();
        $this->assertDatabaseHas('company_feature_policy_proposals', ['id' => $p->id, 'status' => 'rejected']);
        $this->actingAs($f['people'][2]->user)->vote('rac', $p->id)
            ->assertSessionHasErrors('decision');

        $this->actingAs($f['people'][0]->user)->propose('rac', 'attendance')->assertRedirect();
        $next = DB::table('company_feature_policy_proposals')->where('feature_key', 'attendance')->firstOrFail();
        DB::table('memberships')->where('id', $f['people'][2]->id)->update(['active' => false]);
        $this->vote('rac', $next->id)->assertRedirect();
        $this->assertDatabaseHas('company_feature_policy_proposals', ['id' => $next->id, 'status' => 'suspended']);

        $few = $this->firm('limited', 2);
        $this->actingAs($few['people'][0]->user)->propose('limited')
            ->assertSessionHasErrors('effective_date');
    }

    public function test_company_enable_never_overrides_local_partner_workspace_denial(): void
    {
        $f = $this->firm('rac');
        $first = $f['people'][0];
        $this->actingAs($first->user)->post('/c/rac/app/settings/features', [
            'feature_key' => 'leave', 'scope' => 'workspace',
            'operation' => 'disable',
            'reason' => 'Local Partner requested a temporary leave restriction.',
        ])->assertRedirect();
        $this->propose('rac', 'leave', 'enable')->assertRedirect();
        $p = DB::table('company_feature_policy_proposals')->firstOrFail();
        foreach ($f['people'] as $person) {
            $this->actingAs($person->user)->vote('rac', $p->id)->assertRedirect();
        }
        $this->atPolicyDate($p->effective_date, function () use ($f) {
            $this->assertFalse(ErpFeatures::enabled(DB::table('memberships')->find($f['people'][0]->id), 'leave'));
            $this->assertTrue(ErpFeatures::enabled(DB::table('memberships')->find($f['people'][1]->id), 'leave'));
        });
    }
    public function test_backdated_midmonth_duplicate_and_invalid_keys_are_refused(): void
    {
        $f = $this->firm('date-rac');
        $this->actingAs($f['people'][0]->user);
        $this->propose('date-rac', 'leave', 'disable', now('Asia/Dhaka')->toDateString())
            ->assertSessionHasErrors('effective_date');
        $mid = CarbonImmutable::parse($this->month(), 'Asia/Dhaka')->addDay()->toDateString();
        $this->propose('date-rac', 'leave', 'disable', $mid)
            ->assertSessionHasErrors('effective_date');
        $this->propose('date-rac', 'platform.admin')->assertSessionHasErrors('feature_key');
        $this->propose('date-rac')->assertRedirect();
        $this->propose('date-rac')->assertSessionHasErrors('feature_key');
        $this->assertDatabaseCount('company_feature_policy_proposals', 1);
    }

    public function test_future_reset_creates_a_new_immutable_approved_version(): void
    {
        $f = $this->firm('version-rac');
        $this->actingAs($f['people'][0]->user)->propose('version-rac')->assertRedirect();
        $first = DB::table('company_feature_policy_proposals')->firstOrFail();
        foreach ($f['people'] as $person) {
            $this->actingAs($person->user)->vote('version-rac', $first->id)->assertRedirect();
        }

        $this->actingAs($f['people'][0]->user)
            ->propose('version-rac', 'leave', 'reset', $this->month(2))->assertRedirect();
        $second = DB::table('company_feature_policy_proposals')
            ->where('id', '<>', $first->id)->firstOrFail();
        foreach ($f['people'] as $person) {
            $this->actingAs($person->user)->vote('version-rac', $second->id)->assertRedirect();
        }
        $this->atPolicyDate($first->effective_date, function () use ($f) {
            $actor = DB::table('memberships')->find($f['people'][0]->id);
            $this->assertFalse(ErpFeatures::enabled($actor, 'leave'));
        });
        $this->atPolicyDate($second->effective_date, function () use ($f) {
            $actor = DB::table('memberships')->find($f['people'][0]->id);
            $this->assertTrue(ErpFeatures::enabled($actor, 'leave'));
        });
        $this->assertDatabaseHas('company_feature_policy_proposals', [
            'id' => $first->id, 'operation' => 'disable', 'status' => 'approved',
        ]);
        $this->assertDatabaseHas('company_feature_policy_proposals', [
            'id' => $second->id, 'operation' => 'reset', 'status' => 'approved',
        ]);
    }

    public function test_other_firms_and_students_cannot_propose_or_vote(): void
    {
        $a = $this->firm('firm-a');
        $b = $this->firm('firm-b');
        $this->actingAs($a['people'][0]->user)->propose('firm-a')->assertRedirect();
        $p = DB::table('company_feature_policy_proposals')->firstOrFail();
        $this->actingAs($b['people'][0]->user)->vote('firm-b', $p->id)->assertNotFound();

        $student = User::factory()->create();
        DB::table('memberships')->insert([
            'company_id' => $a['company'], 'workspace_id' => $a['people'][0]->workspace,
            'user_id' => $student->id, 'role' => 'student', 'active' => true,
            'monthly_allowance' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($student)->propose('firm-a')->assertForbidden();
        $this->vote('firm-a', $p->id)->assertForbidden();
        $this->assertDatabaseCount('company_feature_policy_votes', 0);
    }

}
