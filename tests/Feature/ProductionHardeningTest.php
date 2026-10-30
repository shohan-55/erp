<?php

namespace Tests\Feature;

use App\Livewire\Practice\PracticePreview;
use App\Models\Attendance;
use App\Models\ConveyanceClaim;
use App\Models\Firm;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\CsvSafe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression tests for the second hardening pass (P1 / P2 from
 * docs/PRODUCTION_READINESS_AUDIT_2026-10-30.md).
 */
class ProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected Firm $firm;
    protected User $partner;
    protected User $manager;
    protected User $student;
    protected User $otherUnitStudent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->firm = Firm::create([
            'name'   => 'Rahman Anis & Co.',
            'slug'   => 'rac',
            'status' => 'active',
        ]);

        $this->partner = User::create([
            'firm_id'      => $this->firm->id,
            'name'         => 'A.K.M. Mohitul Haq, FCA',
            'email'        => 'mohitul.haq@rahmananis.com',
            'password'     => Hash::make('Secret123!'),
            'role'         => 'partner',
            'partner_unit' => 'Partner Unit 01',
        ]);

        $this->manager = User::create([
            'firm_id'      => $this->firm->id,
            'name'         => 'Khandaker Tanvir Ahmed, ACA',
            'email'        => 'tanvir.ahmed@rahmananis.com',
            'password'     => Hash::make('Secret123!'),
            'role'         => 'manager',
            'partner_unit' => 'Partner Unit 01',
            'can_proxy_attendance' => true,
            'can_edit_attendance'  => true,
            'can_approve_leave'    => true,
            'can_approve_claims'   => true,
        ]);

        $this->student = User::create([
            'firm_id'      => $this->firm->id,
            'name'         => 'Zubair Hossain Siddique',
            'email'        => 'zubair.student@rahmananis.com',
            'password'     => Hash::make('Secret123!'),
            'role'         => 'student',
            'partner_unit' => 'Partner Unit 01',
            'student_code' => 'S-101',
            'manager_id'   => $this->manager->id,
        ]);

        $this->otherUnitStudent = User::create([
            'firm_id'      => $this->firm->id,
            'name'         => 'Other Unit Student',
            'email'        => 'other.student@rahmananis.com',
            'password'     => Hash::make('Secret123!'),
            'role'         => 'student',
            'partner_unit' => 'Partner Unit 02',
            'student_code' => 'S-202',
        ]);
    }

    // ─── CSV formula injection ──────────────────────────────────────────────

    public function test_csv_cells_neutralise_spreadsheet_formulas(): void
    {
        $this->assertSame("'=HYPERLINK(\"http://evil\",\"x\")", CsvSafe::cell('=HYPERLINK("http://evil","x")'));
        $this->assertSame("'+8801711223344", CsvSafe::cell('+8801711223344'));
        $this->assertSame("'-500", CsvSafe::cell('-500'));
        $this->assertSame("'@SUM(A1:A9)", CsvSafe::cell('@SUM(A1:A9)'));
    }

    public function test_csv_cells_keep_real_numbers_and_plain_text(): void
    {
        $this->assertSame(8450.00, CsvSafe::cell(8450.00));
        $this->assertSame('8450.00', CsvSafe::cell('8450.00'));
        $this->assertSame('Zubair Hossain Siddique', CsvSafe::cell('Zubair Hossain Siddique'));
    }

    // ─── Privilege boundaries ───────────────────────────────────────────────

    public function test_manager_cannot_change_workspace_policy_defaults(): void
    {
        $this->manager->update(['can_manage_team' => true]);
        $this->actingAs($this->manager->fresh());

        Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'manager',
            'section'     => 'policies',
        ])
            ->set('managerConveyanceLimit', 9000)
            ->call('savePolicySettings')
            ->assertNotSet('error', null);

        $this->assertDatabaseMissing('partner_policies', [
            'firm_id'                  => $this->firm->id,
            'manager_conveyance_limit' => 9000,
        ]);
    }

    public function test_manager_cannot_approve_leave_for_another_unit(): void
    {
        $leave = LeaveRequest::create([
            'firm_id'         => $this->firm->id,
            'user_id'         => $this->otherUnitStudent->id,
            'leave_type'      => 'annual',
            'start_date'      => '2026-11-01',
            'end_date'        => '2026-11-02',
            'reason'          => 'Family programme outside Dhaka',
            'status'          => 'pending_manager',
            'requested_days'  => 2,
        ]);

        $this->actingAs($this->manager);

        Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'manager',
            'section'     => 'leave',
        ])
            ->call('managerRecommendLeave', 'LR-' . $leave->id)
            ->assertNotSet('error', null);

        $this->assertSame('pending_manager', $leave->fresh()->status);
    }

    public function test_manager_can_still_approve_leave_for_their_own_unit(): void
    {
        $leave = LeaveRequest::create([
            'firm_id'         => $this->firm->id,
            'user_id'         => $this->student->id,
            'leave_type'      => 'annual',
            'start_date'      => '2026-11-03',
            'end_date'        => '2026-11-04',
            'reason'          => 'Family programme outside Dhaka',
            'status'          => 'pending_manager',
            'requested_days'  => 2,
        ]);

        $this->actingAs($this->manager);

        Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'manager',
            'section'     => 'leave',
        ])
            ->call('managerRecommendLeave', 'LR-' . $leave->id)
            ->assertSet('error', null);

        $this->assertSame('pending_partner', $leave->fresh()->status);
    }

    public function test_student_cannot_open_a_colleagues_receipt(): void
    {
        $claim = ConveyanceClaim::create([
            'firm_id'         => $this->firm->id,
            'user_id'         => $this->otherUnitStudent->id,
            'claim_date'      => '2026-10-15',
            'amount'          => 250.00,
            'original_amount' => 250.00,
            'route'           => 'Office -> Client -> Office',
            'purpose'         => 'Audit verification',
            'status'          => 'approved',
        ]);

        $this->actingAs($this->student);

        Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'student',
            'section'     => 'conveyance',
        ])
            ->call('openVoucherPreviewModal', 'CC-' . $claim->id)
            ->assertSet('showVoucherPreviewModal', false)
            ->assertNotSet('error', null);
    }

    // ─── Leave quota ────────────────────────────────────────────────────────

    public function test_leave_beyond_the_annual_quota_is_rejected(): void
    {
        // Annual quota is 14 days; burn 13 of them within the same year.
        LeaveRequest::create([
            'firm_id'         => $this->firm->id,
            'user_id'         => $this->student->id,
            'leave_type'      => 'annual',
            'start_date'      => '2026-10-01',
            'end_date'        => '2026-10-13',
            'reason'          => 'Earlier approved leave this year',
            'status'          => 'approved',
            'requested_days'  => 13,
            'approved_days'   => 13,
        ]);

        $this->actingAs($this->student);

        Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'student',
            'section'     => 'leave',
        ])
            ->set('leaveType', 'annual')
            ->set('leaveStart', '2026-11-01')
            ->set('leaveEnd', '2026-11-05')
            ->set('leaveReason', 'Attending family programme outside Dhaka')
            ->call('submitLeaveApplication')
            ->assertNotSet('error', null);

        $this->assertSame(1, LeaveRequest::where('start_date', '2026-11-01')->count());
    }

    public function test_leave_within_the_annual_quota_is_accepted(): void
    {
        $this->actingAs($this->student);

        Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'student',
            'section'     => 'leave',
        ])
            ->set('leaveType', 'annual')
            ->set('leaveStart', '2026-11-01')
            ->set('leaveEnd', '2026-11-03')
            ->set('leaveReason', 'Attending family programme outside Dhaka')
            ->call('submitLeaveApplication')
            ->assertSet('error', null);

        $this->assertSame(1, LeaveRequest::where('start_date', '2026-11-01')->count());
    }

    // ─── Subscription enforcement ───────────────────────────────────────────

    public function test_expired_subscription_blocks_the_workspace(): void
    {
        $this->firm->update(['subscription_expires_at' => now()->subMonth()->toDateString()]);
        $this->actingAs($this->partner);

        $this->get('/c/rac/preview/partner')
            ->assertForbidden();

        $this->firm->update(['subscription_expires_at' => now()->addMonth()->toDateString()]);

        $this->get('/c/rac/preview/partner')
            ->assertOk();
    }

    public function test_firm_without_an_expiry_date_stays_operational(): void
    {
        $this->assertFalse($this->firm->fresh()->isSubscriptionExpired());
        $this->assertTrue($this->firm->fresh()->isWorkspaceOperational());
    }

    // ─── Session / route hardening ──────────────────────────────────────────

    public function test_logout_is_not_reachable_with_get(): void
    {
        $this->actingAs($this->student);

        $this->get('/logout')->assertStatus(405);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_security_headers_are_sent_with_every_page(): void
    {
        $response = $this->get('/platform/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Content-Security-Policy');
    }

    public function test_health_endpoint_reports_database_and_storage(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('checks.database', true);
        $response->assertJsonPath('checks.storage', true);
    }

    // ─── Payroll arithmetic ────────────────────────────────────────────────

    public function test_payroll_draft_counts_only_attendance_inside_the_period(): void
    {
        Attendance::create([
            'firm_id' => $this->firm->id,
            'user_id' => $this->student->id,
            'date'    => '2026-10-05',
            'status'  => 'On time',
        ]);
        Attendance::create([
            'firm_id' => $this->firm->id,
            'user_id' => $this->student->id,
            'date'    => '2026-11-05',
            'status'  => 'On time',
        ]);

        $this->actingAs($this->partner);

        Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'partner',
            'section'     => 'payroll',
        ])
            ->set('period', '2026-10')
            ->call('generatePayrollDraft')
            ->assertSet('error', null);

        $this->assertDatabaseHas('payroll_records', [
            'firm_id'      => $this->firm->id,
            'user_id'      => $this->student->id,
            'period'       => '2026-10',
            'present_days' => 1,
        ]);
    }

    // ─── Deactivation revokes access ───────────────────────────────────────

    public function test_deactivated_member_is_signed_out_of_an_existing_session(): void
    {
        $this->actingAs($this->student);

        $this->student->update(['is_active' => false]);

        $this->get('/c/rac/preview/student')
            ->assertForbidden();
    }
}
