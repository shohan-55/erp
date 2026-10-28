<?php

namespace Tests\Feature;

use App\Livewire\Practice\PracticePreview;
use App\Models\Attendance;
use App\Models\ConveyanceClaim;
use App\Models\Firm;
use App\Models\LeaveRequest;
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
}
