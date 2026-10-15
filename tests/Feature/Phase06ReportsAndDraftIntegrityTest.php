<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase06ReportsAndDraftIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $slug): int
    {
        return DB::table('companies')->insertGetId([
            'slug' => $slug, 'name' => 'Synthetic QA '.$slug,
            'timezone' => 'Asia/Dhaka', 'office_start' => '10:00:00',
            'office_end' => '18:00:00', 'grace_minutes' => 10,
            'weekly_holidays' => '[5,6]', 'active' => true,
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

    private function member(int $company, int $workspace, string $role, ?int $manager = null): object
    {
        $user = User::factory()->create();
        $id = DB::table('memberships')->insertGetId([
            'company_id' => $company, 'workspace_id' => $workspace,
            'user_id' => $user->id, 'role' => $role,
            'manager_membership_id' => $manager, 'active' => true,
            'monthly_allowance' => $role === 'student' ? '8000.00' : '0.00',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return (object) ['id' => $id, 'user' => $user];
    }

    private function session(int $company, int $workspace, int $member, string $date, bool $late = false, bool $holiday = false, bool $open = false): void
    {
        DB::table('attendance_sessions')->insert([
            'company_id' => $company, 'workspace_id' => $workspace,
            'membership_id' => $member, 'work_date' => $date,
            'work_mode' => 'office', 'started_at' => $date.' 04:00:00',
            'ended_at' => $open ? null : $date.' 10:00:00',
            'late' => $late, 'holiday' => $holiday,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_monthly_report_respects_company_partner_and_manager_boundaries(): void
    {
        $company = $this->company('qa-pilot');
        $otherCompany = $this->company('other-firm');
        $one = $this->workspace($company, 'partner-one');
        $two = $this->workspace($company, 'partner-two');
        $outside = $this->workspace($otherCompany, 'outside');
        $partner = $this->member($company, $one, 'partner');
        $manager = $this->member($company, $one, 'manager');
        $anotherManager = $this->member($company, $one, 'manager');
        $student = $this->member($company, $one, 'student', $manager->id);
        $peer = $this->member($company, $one, 'student', $anotherManager->id);
        $otherPartnerStudent = $this->member($company, $two, 'student');
        $otherFirmStudent = $this->member($otherCompany, $outside, 'student');

        $this->session($company, $one, $student->id, '2026-10-12', true);
        $this->session($company, $one, $student->id, '2026-10-12', false, false, true);
        $this->session($company, $one, $student->id, '2026-10-16', false, true);
        $this->session($company, $one, $student->id, '2026-09-29');
        $this->session($company, $one, $peer->id, '2026-10-13');
        $this->session($company, $two, $otherPartnerStudent->id, '2026-10-13');
        $this->session($otherCompany, $outside, $otherFirmStudent->id, '2026-10-13');

        $url = '/c/qa-pilot/app/attendance/report?month=2026-10';
        $this->actingAs($partner->user)->get($url)
            ->assertOk()->assertHeader('Cache-Control', 'private, no-store, max-age=0')
            ->assertViewHas('rows', function ($rows) use ($student, $peer) {
                $this->assertCount(2, $rows);
                $row = $rows->firstWhere('membership_id', $student->id);
                $this->assertNotNull($row);
                $this->assertEquals(2, $row->attended_days);
                $this->assertEquals(3, $row->session_count);
                $this->assertEquals(1, $row->late_sessions);
                $this->assertEquals(1, $row->incomplete_sessions);
                $this->assertEquals(1, $row->holiday_days);
                return $rows->contains('membership_id', $peer->id);
            });
        $this->actingAs($manager->user)->get($url)->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 1 && $rows->first()->membership_id === $student->id);
        $this->actingAs($student->user)->get($url)->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 1 && $rows->first()->membership_id === $student->id);
        $this->actingAs($otherPartnerStudent->user)->get($url)->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 1 && $rows->first()->membership_id === $otherPartnerStudent->id);
        $this->actingAs($partner->user)->get('/c/other-firm/app/attendance/report?month=2026-10')->assertForbidden();
    }

    public function test_report_rejects_invalid_month_without_mutating_payroll_or_attendance(): void
    {
        $company = $this->company('qa-pilot');
        $workspace = $this->workspace($company, 'partner-one');
        $partner = $this->member($company, $workspace, 'partner');
        $this->actingAs($partner->user)
            ->get('/c/qa-pilot/app/attendance/report?month=not-a-month')
            ->assertSessionHasErrors('month');
        $this->get('/c/qa-pilot/app/attendance/report?month=2026-10')->assertOk();
        $this->assertDatabaseCount('payroll_runs', 0);
        $this->assertDatabaseCount('payroll_lines', 0);
        $this->assertDatabaseCount('attendance_sessions', 0);
        $this->assertDatabaseCount('tenant_audit_events', 0);
    }

    public function test_scheduled_draft_uses_partner_approved_claim_amount_not_original(): void
    {
        CarbonImmutable::setTestNow('2026-11-01 03:35:00 Asia/Dhaka');
        try {
            $company = $this->company('qa-pilot');
            $workspace = $this->workspace($company, 'partner-one');
            $this->member($company, $workspace, 'partner');
            $student = $this->member($company, $workspace, 'student');
            DB::table('conveyance_claims')->insert([
                'company_id' => $company, 'workspace_id' => $workspace,
                'membership_id' => $student->id, 'journey_date' => '2026-10-14',
                'journey_route' => 'Office to synthetic client',
                'purpose' => 'Synthetic travel for test',
                'amount' => '130.00', 'approved_amount' => '100.00',
                'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->artisan('erp:payroll:auto-draft')->assertExitCode(0);
            $this->assertDatabaseHas('payroll_lines', [
                'membership_id' => $student->id,
                'base_allowance' => 8000, 'approved_claims' => 100,
                'adjustment' => 0, 'total_amount' => 8100,
            ]);
            $this->assertDatabaseHas('payroll_runs', [
                'workspace_id' => $workspace, 'status' => 'draft', 'paid_at' => null,
            ]);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
