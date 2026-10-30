<?php

namespace Tests\Feature;

use App\Livewire\Auth\AccountActivation;
use App\Livewire\Platform\DashboardPreview;
use App\Livewire\Practice\PracticePreview;
use App\Models\Attendance;
use App\Models\ConveyanceClaim;
use App\Models\Firm;
use App\Models\LeaveRequest;
use App\Models\PayrollRecord;
use App\Models\PolicyProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ProductionWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected Firm $firm;
    protected User $partner;
    protected User $manager;
    protected User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->firm = Firm::create([
            'name'          => 'Rahman Anis & Co.',
            'slug'          => 'rac',
            'short_code'    => 'RA&C',
            'status'        => 'active',
            'tier'          => 'professional',
            'custom_domain' => 'rahmananis.com',
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
            'firm_id'           => $this->firm->id,
            'name'              => 'Zubair Hossain Siddique',
            'email'             => 'zubair.student@rahmananis.com',
            'password'          => Hash::make('Secret123!'),
            'role'              => 'student',
            'partner_unit'      => 'Partner Unit 01',
            'student_code'      => 'S-101',
            'manager_id'        => $this->manager->id,
            'monthly_allowance' => 8000.00,
        ]);
    }

    public function test_production_routes_enforce_authentication_and_tenant_isolation(): void
    {
        // Guest is redirected to firm login when accessing protected firm dashboard
        $this->get('/c/rac/dashboard')->assertRedirect(route('firm.login', ['firmSlug' => 'rac']));

        // Guest is redirected to platform login when accessing protected platform dashboard
        $this->get('/platform/dashboard')->assertRedirect(route('platform.login'));

        // Firm member cannot access platform admin dashboard
        $this->actingAs($this->partner)
            ->get('/platform/dashboard')
            ->assertForbidden();

        // Cross-firm user cannot access another firm's workspace
        $otherFirm = Firm::create([
            'name'   => 'Other Practice',
            'slug'   => 'other-firm',
            'status' => 'active',
        ]);
        $otherUser = User::create([
            'firm_id'  => $otherFirm->id,
            'name'     => 'Other Partner',
            'email'    => 'partner@otherfirm.com',
            'password' => Hash::make('Secret123!'),
            'role'     => 'partner',
        ]);

        $this->actingAs($otherUser)
            ->get('/c/rac/dashboard')
            ->assertForbidden();
    }

    public function test_authenticated_student_cannot_spoof_partner_role_in_livewire(): void
    {
        $this->actingAs($this->student);

        Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'partner', // Attempted spoof on mount
            'section'     => 'dashboard',
        ])
            ->assertSet('previewRole', 'student')
            ->call('setPreviewRole', 'partner')
            ->assertSet('previewRole', 'student')
            ->set('previewRole', 'partner')
            ->assertSet('previewRole', 'student');
    }

    public function test_authenticated_user_sees_identity_card_without_mock_role_switcher(): void
    {
        $this->actingAs($this->partner);

        $response = $this->get('/c/rac/dashboard');
        $response->assertOk()
            ->assertSee('A.K.M. Mohitul Haq, FCA')
            ->assertSee('PRODUCTION ACTIVE')
            ->assertDontSee('LIVE PRACTICE NODE')
            ->assertDontSee('SECURE NODE')
            ->assertDontSee('Isolated Scope')
            ->assertDontSee('aria-label="Practice persona"', false);
    }

    public function test_dashboard_does_not_contain_prototype_or_fictional_text(): void
    {
        $this->actingAs($this->partner);

        $response = $this->get('/c/rac/dashboard');
        $response->assertOk()
            ->assertDontSee('Fictional office and client engagement activity')
            ->assertDontSee('Example Partner Unit 01 dashboard')
            ->assertSee('Daily office and client site audit activity');
    }

    public function test_authenticated_student_submits_leave_directly_to_database(): void
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
            ->set('leaveReason', 'Attending family program outside Dhaka')
            ->call('submitLeaveApplication')
            ->assertSet('error', null);

        $this->assertDatabaseHas('leave_requests', [
            'firm_id'    => $this->firm->id,
            'user_id'    => $this->student->id,
            'leave_type' => 'annual',
            'status'     => 'pending_manager',
        ]);
    }

    public function test_authenticated_student_submits_conveyance_directly_to_database(): void
    {
        $this->actingAs($this->student);

        Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'student',
            'section'     => 'conveyance',
        ])
            ->set('claimDate', '2026-10-28')
            ->set('claimAmount', '350.00')
            ->set('claimRoute', 'Office -> Client Site -> Return')
            ->set('claimPurpose', 'Audit field stock verification')
            ->set('claimClientName', 'BEXIMCO Textiles')
            ->call('submitConveyanceClaim')
            ->assertSet('error', null);

        $this->assertDatabaseHas('conveyance_claims', [
            'firm_id'     => $this->firm->id,
            'user_id'     => $this->student->id,
            'client_name' => 'BEXIMCO Textiles',
            'amount'      => 350.00,
        ]);
    }

    public function test_authenticated_student_attendance_persists_to_database(): void
    {
        $this->actingAs($this->student);

        Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'student',
            'section'     => 'attendance',
        ])
            ->set('workMode', 'office')
            ->call('togglePreviewSession')
            ->assertSet('previewSessionActive', true);

        $this->assertDatabaseHas('attendances', [
            'firm_id' => $this->firm->id,
            'user_id' => $this->student->id,
        ]);
    }

    public function test_manager_proxy_attendance_persists_to_database_and_creates_audit_log(): void
    {
        $this->actingAs($this->manager);

        Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'manager',
            'section'     => 'attendance',
        ])
            ->call('openDialog', 'correction', 'S-101', 'proxy')
            ->assertSet('showCorrectionModal', true)
            ->set('editCheckinTime', '10:02')
            ->set('editCheckoutTime', '18:05')
            ->set('editStatus', 'On time')
            ->set('editRemarks', 'Client field proxy verified by Manager')
            ->call('saveProxyOrEditAttendance')
            ->assertSet('error', null);

        $this->assertDatabaseHas('attendances', [
            'firm_id'      => $this->firm->id,
            'user_id'      => $this->student->id,
            'status'       => 'On time',
            'is_proxy'     => true,
            'proxy_by'     => $this->manager->id,
            'edit_remarks' => 'Client field proxy verified by Manager',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'firm_id'    => $this->firm->id,
            'module'     => 'attendance',
            'action'     => 'proxy_attendance',
            'target_ref' => 'S-101',
        ]);
    }

    public function test_dynamic_payroll_lifecycle_draft_review_approve_paid_and_lock(): void
    {
        // Create an approved conveyance claim for October 2026
        ConveyanceClaim::create([
            'firm_id'         => $this->firm->id,
            'user_id'         => $this->student->id,
            'claim_date'      => '2026-10-15',
            'amount'          => 450.00,
            'original_amount' => 450.00,
            'route'           => 'Office -> Client -> Office',
            'purpose'         => 'Audit verification',
            'client_name'     => 'BEXIMCO Textiles Audit',
            'status'          => 'approved',
        ]);

        $this->actingAs($this->partner);

        $component = Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'partner',
            'section'     => 'payroll',
        ])
            ->set('period', '2026-10')
            ->call('generatePayrollDraft')
            ->assertSet('error', null);

        $this->assertDatabaseHas('payroll_records', [
            'firm_id'             => $this->firm->id,
            'user_id'             => $this->student->id,
            'period'              => '2026-10',
            'base_allowance'      => 8000.00,
            'approved_conveyance' => 450.00,
            'net_payable'         => 8450.00,
            'status'              => 'draft',
        ]);

        // Transition through Reviewed -> Approved -> Paid -> Locked
        $component->call('transitionPayrollPeriod', 'reviewed')
            ->assertSet('payrollStatus', 'reviewed')
            ->call('transitionPayrollPeriod', 'approved')
            ->assertSet('payrollStatus', 'approved')
            ->call('transitionPayrollPeriod', 'paid')
            ->assertSet('payrollStatus', 'paid')
            ->call('transitionPayrollPeriod', 'locked')
            ->assertSet('payrollStatus', 'locked');

        // Verify locked period rejects retroactive mutation
        $component->call('transitionPayrollPeriod', 'draft')
            ->assertNotSet('error', null);
    }

    public function test_partner_can_propose_and_vote_on_company_policy(): void
    {
        $this->actingAs($this->partner);

        Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'partner',
            'section'     => 'policies',
        ])
            ->call('openDialog', 'policy')
            ->assertSet('showPolicyModal', true)
            ->call('acknowledgeDialog', 'policy')
            ->assertSet('showPolicyModal', false);

        $proposal = PolicyProposal::where('firm_id', $this->firm->id)->first();
        $this->assertNotNull($proposal);
        $this->assertSame('pending_consensus', $proposal->status);
    }

    public function test_firm_member_cannot_execute_platform_admin_organization_mutations(): void
    {
        $this->actingAs($this->partner);

        Livewire::test(DashboardPreview::class)
            ->assertForbidden();
    }

    public function test_real_invitation_token_activates_invited_user_account(): void
    {
        $invited = User::create([
            'firm_id'          => $this->firm->id,
            'name'             => 'Invited Student',
            'email'            => 'invited.student@rahmananis.com',
            'password'         => Hash::make('TempPass123!'),
            'role'             => 'student',
            'student_code'     => 'S-199',
            'invitation_token' => 'valid-invite-token-xyz',
        ]);

        Livewire::withQueryParams(['token' => 'valid-invite-token-xyz'])
            ->test(AccountActivation::class, ['firmSlug' => 'rac'])
            ->assertSet('targetUserId', $invited->id)
            ->set('password', 'NewStrongPass2026!')
            ->set('passwordConfirmation', 'NewStrongPass2026!')
            ->set('termsAccepted', true)
            ->call('submitActivation')
            ->assertSet('previewState', 'completed')
            ->assertSet('validationError', null);

        $invited->refresh();
        $this->assertNull($invited->invitation_token);
        $this->assertNotNull($invited->email_verified_at);
        $this->assertTrue(Hash::check('NewStrongPass2026!', $invited->password));
    }

    public function test_partner_can_view_team_roster_and_enroll_new_manager_and_student(): void
    {
        $this->actingAs($this->partner);

        Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'partner',
            'section'     => 'team',
        ])
            ->assertSet('canManageTeam', true)
            ->call('openAddMemberModal')
            ->assertSet('showAddMemberModal', true)
            ->set('newMemberName', 'Sayeda Tasnim, ACA')
            ->set('newMemberEmail', 'tasnim.manager@rahmananis.com')
            ->set('newMemberRole', 'manager')
            ->set('newMemberPartnerUnit', 'Partner Unit 01')
            ->set('newMemberMonthlyAllowance', 30000)
            ->set('newMemberCanManageTeam', true)
            ->call('createMember')
            ->assertSet('error', null);

        $this->assertDatabaseHas('users', [
            'firm_id'         => $this->firm->id,
            'name'            => 'Sayeda Tasnim, ACA',
            'email'           => 'tasnim.manager@rahmananis.com',
            'role'            => 'manager',
            'can_manage_team' => true,
        ]);
    }

    public function test_delegated_manager_can_add_members_but_non_delegated_cannot(): void
    {
        // 1. Initially manager has can_manage_team = false
        $this->actingAs($this->manager);

        Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'manager',
            'section'     => 'team',
        ])
            ->assertSet('canManageTeam', false)
            ->call('openAddMemberModal')
            ->assertSet('showAddMemberModal', false)
            ->assertSet('error', 'Only Partners or authorized Managers can enroll new team members.');

        // 2. Partner delegates can_manage_team = true to the manager
        $this->manager->update(['can_manage_team' => true]);
        $this->actingAs($this->manager->fresh());

        Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'manager',
            'section'     => 'team',
        ])
            ->assertSet('canManageTeam', true)
            ->call('openAddMemberModal')
            ->assertSet('showAddMemberModal', true)
            ->set('newMemberName', 'Kamrul Hasan')
            ->set('newMemberEmail', 'kamrul.student@rahmananis.com')
            ->set('newMemberRole', 'student')
            ->set('newMemberStudentCode', 'S-105')
            ->set('newMemberManagerId', $this->manager->id)
            ->call('createMember')
            ->assertSet('error', null);

        $this->assertDatabaseHas('users', [
            'firm_id'      => $this->firm->id,
            'name'         => 'Kamrul Hasan',
            'email'        => 'kamrul.student@rahmananis.com',
            'role'         => 'student',
            'student_code' => 'S-105',
            'manager_id'   => $this->manager->id,
        ]);
    }

    public function test_delete_member_requires_security_confirmation_and_prevents_self_deletion(): void
    {
        $this->actingAs($this->partner);

        $component = Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'partner',
            'section'     => 'team',
        ]);

        // Attempt self-deletion
        $component->call('confirmDeleteMember', $this->partner->id)
            ->assertSet('error', 'Security rule: You cannot delete your own active account.');

        // Open delete modal for student
        $component->call('confirmDeleteMember', $this->student->id)
            ->assertSet('showDeleteMemberModal', true)
            ->assertSet('selectedMemberId', $this->student->id);

        // Fail with wrong text
        $component->set('deleteConfirmationInput', 'WRONG')
            ->call('deleteMember')
            ->assertSet('error', 'Security confirmation failed: You must type "DELETE" or "CONFIRM" exactly to proceed.');

        $this->assertDatabaseHas('users', ['id' => $this->student->id]);

        // Proceed with DELETE
        $component->set('deleteConfirmationInput', 'DELETE')
            ->call('deleteMember')
            ->assertSet('showDeleteMemberModal', false);

        $this->assertDatabaseMissing('users', ['id' => $this->student->id]);
    }

    public function test_client_site_can_be_added_on_the_fly_and_persists_to_audit_clients(): void
    {
        $this->actingAs($this->partner);

        Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'partner',
            'section'     => 'attendance',
        ])
            ->call('openNewClientModal')
            ->assertSet('showAddNewClientModal', true)
            ->set('newClientName', 'Apex Footwear Ltd.')
            ->set('newClientLocation', 'Kalaikoir, Gazipur')
            ->call('saveNewClientSite')
            ->assertSet('showAddNewClientModal', false)
            ->assertSet('clientSite', 'Apex Footwear Ltd.')
            ->assertSet('claimClientName', 'Apex Footwear Ltd.');

        $this->assertDatabaseHas('audit_clients', [
            'firm_id'  => $this->firm->id,
            'name'     => 'Apex Footwear Ltd.',
            'location' => 'Kalaikoir, Gazipur',
        ]);
    }

    public function test_student_checkin_with_client_site_records_client_site_in_attendance(): void
    {
        $this->actingAs($this->student);

        Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'student',
            'section'     => 'attendance',
        ])
            ->set('workMode', 'client')
            ->set('clientSite', 'Square Pharmaceuticals Audit')
            ->call('togglePreviewSession')
            ->assertSet('previewSessionActive', true);

        $this->assertDatabaseHas('attendances', [
            'firm_id'     => $this->firm->id,
            'user_id'     => $this->student->id,
            'client_site' => 'Square Pharmaceuticals Audit',
        ]);
    }

    public function test_attendance_conveyance_and_payroll_csv_exports_return_streamed_csv_response(): void
    {
        $this->actingAs($this->partner);

        $component = Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'partner',
            'section'     => 'attendance',
        ]);

        $component->call('exportAttendanceCsv')
            ->assertFileDownloaded('Attendance_Register_rac_' . date('Y_m_d') . '.csv');

        $component->call('exportConveyanceCsv')
            ->assertFileDownloaded('Conveyance_Claims_rac_' . date('Y_m_d') . '.csv');

        $component->call('exportPayrollCsv')
            ->assertFileDownloaded('Payroll_Register_rac_2026-10.csv');
    }
    public function test_student_can_record_on_duty_movement_and_close_duty_with_movement_timeline(): void
    {
        $this->actingAs($this->student);

        $component = Livewire::test(PracticePreview::class, [
            "firmSlug"    => "rac",
            "previewRole" => "student",
            "section"     => "attendance",
        ]);

        // 1. Check in at Office
        $component->set("workMode", "office")
            ->call("togglePreviewSession")
            ->assertSet("previewSessionActive", true)
            ->assertSet("sessionState", "active");

        // 2. Click Out -> Choose Option B: On Duty (Move to Client)
        $component->call("initiateCheckoutPrompt", 23.7516, 90.3943, "Karwan Bazar Office")
            ->assertSet("showCheckoutModeModal", true)
            ->call("confirmCheckoutWithAction", "on_duty")
            ->assertSet("previewSessionActive", false)
            ->assertSet("sessionState", "on_duty_transit");

        // 3. Arrive at Client Site & Check in again on same day
        $component->set("workMode", "client")
            ->set("clientSite", "BEXIMCO Textiles Audit")
            ->call("togglePreviewSession")
            ->assertSet("previewSessionActive", true)
            ->assertSet("sessionState", "active");

        // 4. End day -> Choose Option A: Close Duty.
        //    Closing is two-step by design: the first call only opens the
        //    review panel, and the day ends once the member confirms.
        $component->call("confirmCheckoutWithAction", "close")
            ->assertSet("showFinalCheckoutReview", true)
            ->assertSet("previewSessionActive", true)
            ->assertSet("sessionState", "active");

        $component->set("finalCloseConfirmed", true)
            ->call("confirmFinalCheckout")
            ->assertSet("previewSessionActive", false)
            ->assertSet("sessionState", "closed");

        $att = Attendance::where("user_id", $this->student->id)->first();
        $this->assertNotNull($att);
        $this->assertSame("closed", $att->session_state);
        $this->assertCount(2, $att->movement_logs);
    }

    public function test_student_leave_mentions_engaged_manager_and_supports_partial_approval(): void
    {
        $this->actingAs($this->student);

        Livewire::test(PracticePreview::class, [
            "firmSlug"    => "rac",
            "previewRole" => "student",
            "section"     => "leave",
        ])
            ->set("leaveType", "annual")
            ->set("leaveEngagedManagerId", $this->manager->id)
            ->set("leaveStart", "2026-11-10")
            ->set("leaveEnd", "2026-11-14")
            ->set("leaveReason", "5 days personal visit")
            ->call("submitLeaveApplication")
            ->assertSet("error", null);

        $lr = LeaveRequest::where("user_id", $this->student->id)->latest("id")->first();
        $this->assertNotNull($lr);
        $this->assertSame(5, $lr->requested_days);
        $this->assertSame($this->manager->id, $lr->engaged_manager_id);

        // Partner partially approves 2 out of 5 days
        $this->actingAs($this->partner);
        Livewire::test(PracticePreview::class, [
            "firmSlug"    => "rac",
            "previewRole" => "partner",
            "section"     => "leave",
        ])
            ->call("openPartialLeaveModal", "LR-" . $lr->id)
            ->assertSet("showPartialLeaveModal", true)
            ->set("partialApprovedDays", 2)
            ->set("partialApprovedSummary", "Approved 10 & 14 Nov; middle 3 days cancelled for audit")
            ->call("submitPartialLeaveApproval")
            ->assertSet("showPartialLeaveModal", false);

        $lr->refresh();
        $this->assertSame("approved", $lr->status);
        $this->assertSame(2, $lr->approved_days);
    }

    public function test_student_conveyance_multi_transport_rows_other_expense_and_manager_amount_adjustment(): void
    {
        $this->actingAs($this->student);

        $component = Livewire::test(PracticePreview::class, [
            "firmSlug"    => "rac",
            "previewRole" => "student",
            "section"     => "conveyance",
        ])
            ->set("claimDate", now()->toDateString())
            ->set("claimClientName", "Square Pharmaceuticals Audit")
            ->set("transportRows", [
                ["from" => "Office", "to" => "Motijheel", "mode" => "Bus", "amount" => "50"],
                ["from" => "Motijheel", "to" => "Client HQ", "mode" => "Rickshaw", "amount" => "80"],
            ])
            ->set("hasOtherExpense", true)
            ->set("otherExpenseDesc", "Audit working paper photocopy")
            ->set("otherExpenseAmount", "40")
            ->set("claimPurpose", "Multi-leg field audit visit")
            ->call("submitConveyanceClaim")
            ->assertSet("error", null);

        $cc = ConveyanceClaim::where("user_id", $this->student->id)->latest("id")->first();
        $this->assertNotNull($cc);
        $this->assertEquals(170.00, (float) $cc->amount);
        $this->assertCount(2, $cc->transport_items);

        // Manager increases amount from 170 to 200
        $this->actingAs($this->manager);
        Livewire::test(PracticePreview::class, [
            "firmSlug"    => "rac",
            "previewRole" => "manager",
            "section"     => "conveyance",
        ])
            ->call("openClaimAdjustModal", "CC-" . $cc->id)
            ->set("adjustNewAmount", 200.00)
            ->set("adjustRemarks", "Added return bus fare")
            ->call("submitClaimAdjustment")
            ->assertSet("error", null);

        $cc->refresh();
        $this->assertEquals(200.00, (float) $cc->amount);
        $this->assertSame("approved", $cc->status);
    }

    public function test_manager_and_partner_can_send_targeted_popup_notifications(): void
    {
        $this->actingAs($this->partner);

        Livewire::test(PracticePreview::class, [
            "firmSlug"    => "rac",
            "previewRole" => "partner",
            "section"     => "notifications",
        ])
            ->set("notifTargetMode", "single")
            ->set("notifSingleUserId", $this->student->id)
            ->set("notifTitle", "Urgent Audit Review")
            ->set("notifMessage", "Please bring BEXIMCO inventory sheets at 11 AM.")
            ->set("notifShowPopup", true)
            ->call("sendPracticeNotification")
            ->assertSet("error", null);

        // Student logs in and sees popup notification
        $this->actingAs($this->student);
        Livewire::test(PracticePreview::class, [
            "firmSlug"    => "rac",
            "previewRole" => "student",
            "section"     => "dashboard",
        ])
            ->assertNotSet("activePopupNotification", null)
            ->call("dismissPopupNotification")
            ->assertSet("activePopupNotification", null);
    }
    public function test_partner_can_seed_govt_holidays_and_declare_holiday_office_open_with_absent_deduction(): void
    {
        $this->actingAs($this->partner);

        $component = Livewire::test(PracticePreview::class, [
            "firmSlug"    => "rac",
            "previewRole" => "partner",
            "section"     => "settings",
        ])
            ->call("seedAnnualGovernmentHolidays")
            ->assertSet("error", null)
            ->set("newHolidayDate", "2026-10-16")
            ->set("newHolidayTitle", "Urgent Tax Closing Friday")
            ->set("newHolidayCategory", "weekend_override")
            ->set("newHolidayIsOfficeOpen", true)
            ->set("newHolidayAbsentCutDays", 1.50)
            ->set("newHolidayOverrideReason", "Tax return filing deadline")
            ->call("addOrUpdateHoliday")
            ->assertSet("error", null);

        $this->assertDatabaseHas("practice_holidays", [
            "firm_id"                 => $this->firm->id,
            "title"                   => "Urgent Tax Closing Friday",
            "is_office_open_override" => true,
            "absent_deduction_days"   => 1.50,
        ]);

        // Since student did not attend on 2026-10-16, generating payroll draft deducts 1.5 days (8000 / 30 * 1.5 = 400.00)
        $component->set("period", "2026-10")
            ->call("generatePayrollDraft")
            ->assertSet("error", null);

        $this->assertDatabaseHas("payroll_records", [
            "firm_id"                  => $this->firm->id,
            "user_id"                  => $this->student->id,
            "period"                   => "2026-10",
            "holiday_absent_days"      => 1,
            "holiday_absent_deduction" => 400.00,
            "net_payable"              => 7600.00,
        ]);

        // Printable voucher export works
        $component->call("exportStudentPayrollVoucher", "S-101")
            ->assertFileDownloaded("Payment_Voucher_S-101_rac_2026-10.html");
    }

    public function test_gps_distance_mismatch_flags_attendance_when_far_from_target(): void
    {
        $this->actingAs($this->student);

        // Check in from Uttara (23.8759, 90.3795), which is ~14 km away from Karwan Bazar Office (23.7516, 90.3943)
        Livewire::test(PracticePreview::class, [
            "firmSlug"    => "rac",
            "previewRole" => "student",
            "section"     => "attendance",
        ])
            ->set("workMode", "office")
            ->call("togglePreviewSessionWithGps", 23.8759, 90.3795, "Uttara Sector 7, Dhaka")
            ->assertSet("previewSessionActive", true);

        $att = Attendance::where("user_id", $this->student->id)->first();
        $this->assertNotNull($att);
        $this->assertTrue((bool) $att->location_mismatch);
        $this->assertGreaterThan(10.0, (float) $att->distance_km);
    }
    public function test_self_service_profile_bkash_and_password_update(): void
    {
        $this->actingAs($this->student);

        Livewire::test(PracticePreview::class, [
            "firmSlug"    => "rac",
            "previewRole" => "student",
            "section"     => "settings",
        ])
            ->set("myPhone", "+8801711223344")
            ->set("myPaymentMode", "bKash")
            ->set("myPaymentAccountNo", "01711-223344")
            ->set("myNewPassword", "UpdatedPass2026!")
            ->set("myNewPasswordConfirm", "UpdatedPass2026!")
            ->call("updateOwnProfileAndPassword")
            ->assertSet("error", null);

        $this->student->refresh();
        $this->assertSame("+8801711223344", $this->student->phone);
        $this->assertSame("bKash", $this->student->payment_account_mode);
        $this->assertSame("01711-223344", $this->student->payment_account_no);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check("UpdatedPass2026!", $this->student->password));
    }

    public function test_student_missed_checkout_request_and_manager_one_click_approval(): void
    {
        $this->actingAs($this->student);

        Livewire::test(PracticePreview::class, [
            "firmSlug"    => "rac",
            "previewRole" => "student",
            "section"     => "attendance",
        ])
            ->set("missedOutTime", "18:20")
            ->set("missedOutReason", "Rushed out from client site for train")
            ->call("submitMissedCheckoutRequest")
            ->assertSet("error", null);

        $this->assertDatabaseHas("attendances", [
            "user_id"              => $this->student->id,
            "correction_requested" => true,
            "requested_out_time"   => "18:20",
            "correction_status"    => "pending",
        ]);

        // Manager approves in 1 click
        $this->actingAs($this->manager);
        Livewire::test(PracticePreview::class, [
            "firmSlug"    => "rac",
            "previewRole" => "manager",
            "section"     => "attendance",
        ])
            ->call("approveMissedCheckoutRequest", "S-101")
            ->assertSet("error", null);

        $this->assertDatabaseHas("attendances", [
            "user_id"              => $this->student->id,
            "correction_requested" => false,
            "correction_status"    => "approved",
            "status"               => "On time",
        ]);
    }
}
