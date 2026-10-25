<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A child table's tenant IDs are not a substitute for verifying the joined
 * membership's tenant IDs. Composite cross-tenant corruption must fail closed.
 */
class Phase07TenantJoinIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $slug): int
    {
        return DB::table('companies')->insertGetId([
            'slug' => $slug,
            'name' => 'Synthetic '.$slug,
            'timezone' => 'Asia/Dhaka',
            'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function workspace(int $company, string $slug): int
    {
        return DB::table('workspaces')->insertGetId([
            'company_id' => $company, 'slug' => $slug, 'name' => $slug,
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function member(int $company, int $workspace, string $role, string $name): object
    {
        $user = User::factory()->create(['name' => $name]);
        $id = DB::table('memberships')->insertGetId([
            'company_id' => $company, 'workspace_id' => $workspace,
            'user_id' => $user->id, 'role' => $role,
            'active' => true, 'monthly_allowance' => '0.00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return (object) ['id' => $id, 'user' => $user];
    }

    private function insertRows(int $company, int $workspace, int $member): void
    {
        DB::table('attendance_sessions')->insert([
            'company_id' => $company, 'workspace_id' => $workspace,
            'membership_id' => $member, 'work_date' => '2026-10-13',
            'work_mode' => 'office',
            'started_at' => '2026-10-13 04:00:00',
            'ended_at' => '2026-10-13 10:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('leave_requests')->insert([
            'company_id' => $company, 'workspace_id' => $workspace,
            'membership_id' => $member, 'leave_type' => 'annual',
            'date_from' => '2026-11-12', 'date_to' => '2026-11-13',
            'reason' => 'Synthetic cross-tenant regression fixture',
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('conveyance_claims')->insert([
            'company_id' => $company, 'workspace_id' => $workspace,
            'membership_id' => $member, 'journey_date' => '2026-10-13',
            'journey_route' => 'Synthetic audit location',
            'purpose' => 'Synthetic regression travel expense',
            'amount' => '15.00', 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_partner_lists_ignore_corrupted_cross_company_and_cross_workspace_membership_links(): void
    {
        $companyA = $this->company('synthetic-a');
        $companyB = $this->company('synthetic-b');
        $workspaceA = $this->workspace($companyA, 'partner-one');
        $workspaceB = $this->workspace($companyA, 'partner-two');
        $workspaceC = $this->workspace($companyB, 'partner-outside');

        $partner = $this->member($companyA, $workspaceA, 'partner', 'Private Partner A');
        $ownStudent = $this->member($companyA, $workspaceA, 'student', 'Own Student A');
        $otherPartnerStudent = $this->member($companyA, $workspaceB, 'student', 'Outside Partner B');
        $otherCompanyStudent = $this->member($companyB, $workspaceC, 'student', 'Outside Company C');

        // Simulate individually-valid foreign keys in a malformed imported or
        // corrupted tenant row. The app must still not join another firm's user.
        $this->insertRows($companyA, $workspaceA, $ownStudent->id);
        $this->insertRows($companyA, $workspaceA, $otherPartnerStudent->id);
        $this->insertRows($companyA, $workspaceA, $otherCompanyStudent->id);

        $this->actingAs($partner->user)
            ->get('/c/synthetic-a/app/attendance')
            ->assertOk()
            ->assertViewHas('records', fn ($rows) =>
                $rows->count() === 1 && $rows->first()->membership_id === $ownStudent->id
            )
            ->assertSee('Own Student A')
            ->assertDontSee('Outside Partner B')
            ->assertDontSee('Outside Company C');

        $this->get('/c/synthetic-a/app/leave')->assertOk()
            ->assertViewHas('rows', fn ($rows) =>
                $rows->count() === 1 && $rows->first()->membership_id === $ownStudent->id
            )
            ->assertDontSee('Outside Partner B')
            ->assertDontSee('Outside Company C');

        $this->get('/c/synthetic-a/app/claims')->assertOk()
            ->assertViewHas('rows', fn ($rows) =>
                $rows->count() === 1 && $rows->first()->membership_id === $ownStudent->id
            )
            ->assertDontSee('Outside Partner B')
            ->assertDontSee('Outside Company C');

        // Read-only list queries must not try to silently repair payroll,
        // memberships or corrupted rows.
        $this->assertDatabaseCount('attendance_sessions', 3);
        $this->assertDatabaseCount('leave_requests', 3);
        $this->assertDatabaseCount('conveyance_claims', 3);
        $this->assertDatabaseCount('tenant_audit_events', 0);
    }
}
