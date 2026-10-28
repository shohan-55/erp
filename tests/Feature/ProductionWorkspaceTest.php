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
            ->assertSee('SECURE NODE')
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
}
