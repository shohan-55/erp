<?php

namespace Tests\Feature;

use App\Livewire\Auth\AccountActivation;
use App\Livewire\Practice\PracticePreview;
use App\Models\Firm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression tests for the security hardening pass of 30 October 2026.
 *
 * Each test pins down one previously exploitable behaviour so it can never
 * silently come back:
 *   1. Invitation activation must require a real, unexpired, single-use token.
 *   2. Platform authority must come from the role column, never an email match.
 *   3. A delegated Manager may only manage Articled Students.
 *   4. Changing the sign-in email requires the current password.
 *   5. Attendance/payroll clock runs in Asia/Dhaka.
 */
class SecurityHardeningTest extends TestCase
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
            'name'   => 'Rahman Anis & Co.',
            'slug'   => 'rac',
            'kind'   => 'Chartered Accountants',
            'status' => 'active',
        ]);

        $this->partner = User::create([
            'firm_id'      => $this->firm->id,
            'name'         => 'A.K.M. Mohitul Haq, FCA',
            'email'        => 'mohitul.haq@rahmananis.com',
            'password'     => Hash::make('PartnerSecret123!'),
            'role'         => 'partner',
            'partner_unit' => 'Partner Unit 01',
        ]);

        $this->manager = User::create([
            'firm_id'      => $this->firm->id,
            'name'         => 'Khandaker Tanvir Ahmed, ACA',
            'email'        => 'tanvir.ahmed@rahmananis.com',
            'password'     => Hash::make('ManagerSecret123!'),
            'role'         => 'manager',
            'partner_unit' => 'Partner Unit 01',
        ]);

        $this->student = User::create([
            'firm_id'           => $this->firm->id,
            'name'              => 'Zubair Hossain Siddique',
            'email'             => 'zubair.student@rahmananis.com',
            'password'          => Hash::make('StudentSecret123!'),
            'role'              => 'student',
            'partner_unit'      => 'Partner Unit 01',
            'student_code'      => 'S-101',
            'manager_id'        => $this->manager->id,
            'monthly_allowance' => 8000.00,
        ]);
    }

    protected function makeInvitedUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'firm_id'          => $this->firm->id,
            'name'             => 'Invited Student',
            'email'            => 'invited.student@rahmananis.com',
            'password'         => Hash::make('TempPass123!'),
            'role'             => 'student',
            'student_code'     => 'S-199',
            'invitation_token' => 'invite-token-valid-xyz',
            'invitation_sent_at' => now(),
        ], $overrides));
    }

    // ─── 1. Account activation ───────────────────────────────────────────────

    public function test_activation_email_parameter_can_no_longer_claim_an_account(): void
    {
        $invited = $this->makeInvitedUser();

        // The legacy attack: open the page with ?email= instead of a token.
        Livewire::withQueryParams(['email' => $invited->email])
            ->test(AccountActivation::class, ['firmSlug' => 'rac'])
            ->assertSet('targetUserId', null)
            ->set('password', 'HijackedPass2026!')
            ->set('passwordConfirmation', 'HijackedPass2026!')
            ->set('termsAccepted', true)
            ->call('submitActivation');

        $this->assertTrue(
            Hash::check('TempPass123!', $invited->fresh()->password),
            'The account password must not change when no valid invitation token is supplied.'
        );
        $this->assertNotNull($invited->fresh()->invitation_token);
    }

    public function test_unknown_invitation_token_is_rejected(): void
    {
        $invited = $this->makeInvitedUser();

        Livewire::withQueryParams(['token' => 'completely-made-up-token'])
            ->test(AccountActivation::class, ['firmSlug' => 'rac'])
            ->assertSet('previewState', 'expired')
            ->set('password', 'HijackedPass2026!')
            ->set('passwordConfirmation', 'HijackedPass2026!')
            ->set('termsAccepted', true)
            ->call('submitActivation')
            ->assertSet('previewState', 'expired');

        $this->assertTrue(Hash::check('TempPass123!', $invited->fresh()->password));
    }

    public function test_expired_invitation_token_is_rejected(): void
    {
        $invited = $this->makeInvitedUser([
            'invitation_sent_at' => now()->subDays(30),
        ]);

        Livewire::withQueryParams(['token' => 'invite-token-valid-xyz'])
            ->test(AccountActivation::class, ['firmSlug' => 'rac'])
            ->assertSet('previewState', 'expired')
            ->set('password', 'HijackedPass2026!')
            ->set('passwordConfirmation', 'HijackedPass2026!')
            ->set('termsAccepted', true)
            ->call('submitActivation')
            ->assertSet('previewState', 'expired');

        $this->assertTrue(Hash::check('TempPass123!', $invited->fresh()->password));
    }

    public function test_invitation_token_from_another_firm_cannot_be_used(): void
    {
        $otherFirm = Firm::create([
            'name'   => 'Other Practice',
            'slug'   => 'other-firm',
            'kind'   => 'Chartered Accountants',
            'status' => 'active',
        ]);

        $outsider = User::create([
            'firm_id'          => $otherFirm->id,
            'name'             => 'Outsider',
            'email'            => 'outsider@otherfirm.com',
            'password'         => Hash::make('OutsiderSecret123!'),
            'role'             => 'student',
            'invitation_token' => 'cross-firm-token-abc',
            'invitation_sent_at' => now(),
        ]);

        Livewire::withQueryParams(['token' => 'cross-firm-token-abc'])
            ->test(AccountActivation::class, ['firmSlug' => 'rac'])
            ->assertSet('previewState', 'expired')
            ->set('password', 'HijackedPass2026!')
            ->set('passwordConfirmation', 'HijackedPass2026!')
            ->set('termsAccepted', true)
            ->call('submitActivation');

        $this->assertTrue(Hash::check('OutsiderSecret123!', $outsider->fresh()->password));
    }

    // ─── 2. Platform authority comes from the role column only ───────────────

    public function test_platform_admin_email_alone_grants_no_authority(): void
    {
        $impostor = User::create([
            'firm_id'  => $this->firm->id,
            'name'     => 'Impostor',
            'email'    => 'admin@dhakawala.com',
            'password' => Hash::make('ImpostorSecret123!'),
            'role'     => 'student',
        ]);

        $this->assertFalse($impostor->isPlatformAdmin());
        $this->assertFalse($impostor->fresh()->isPlatformAdmin());

        $this->actingAs($impostor)
            ->get('/platform/dashboard')
            ->assertForbidden();
    }

    public function test_role_column_still_grants_platform_authority(): void
    {
        $admin = User::create([
            'name'     => 'Platform Operator',
            'email'    => 'operator@dhakawala.com',
            'password' => Hash::make('OperatorSecret123!'),
            'role'     => 'platform_admin',
        ]);

        $this->assertTrue($admin->isPlatformAdmin());
    }

    // ─── 3. Manager role ceiling ─────────────────────────────────────────────

    protected function delegatedManager(): User
    {
        $this->manager->update(['can_manage_team' => true]);

        return $this->manager->fresh();
    }

    public function test_manager_cannot_reset_a_partner_password(): void
    {
        $this->actingAs($this->delegatedManager());

        Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'manager',
            'section'     => 'team',
        ])
            ->set('editMemberId', $this->partner->id)
            ->set('editMemberRole', 'partner')
            ->set('editMemberNewPassword', 'TakeoverPass123!')
            ->call('updateMember')
            ->assertNotSet('error', null);

        $this->assertTrue(
            Hash::check('PartnerSecret123!', $this->partner->fresh()->password),
            'A delegated Manager must never be able to reset a Partner password.'
        );
    }

    public function test_manager_cannot_promote_themselves_to_partner(): void
    {
        $this->actingAs($this->delegatedManager());

        Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'manager',
            'section'     => 'team',
        ])
            ->set('editMemberId', $this->manager->id)
            ->set('editMemberRole', 'partner')
            ->call('updateMember')
            ->assertNotSet('error', null);

        $this->assertSame('manager', $this->manager->fresh()->role);
    }

    public function test_manager_cannot_delete_a_partner(): void
    {
        $this->actingAs($this->delegatedManager());

        Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'manager',
            'section'     => 'team',
        ])
            ->call('confirmDeleteMember', $this->partner->id)
            ->set('deleteConfirmationInput', 'DELETE')
            ->call('deleteMember')
            ->assertNotSet('error', null);

        $this->assertDatabaseHas('users', ['id' => $this->partner->id]);
    }

    public function test_manager_cannot_enroll_a_partner_account(): void
    {
        $this->actingAs($this->delegatedManager());

        Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'manager',
            'section'     => 'team',
        ])
            ->call('openAddMemberModal')
            ->set('newMemberName', 'Rogue Partner')
            ->set('newMemberEmail', 'rogue.partner@rahmananis.com')
            ->set('newMemberRole', 'partner')
            ->call('createMember')
            ->assertNotSet('error', null);

        $this->assertDatabaseMissing('users', ['email' => 'rogue.partner@rahmananis.com']);
    }

    public function test_manager_can_still_manage_students(): void
    {
        $this->actingAs($this->delegatedManager());

        Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'manager',
            'section'     => 'team',
        ])
            ->call('openAddMemberModal')
            ->set('newMemberName', 'New Articled Student')
            ->set('newMemberEmail', 'new.student@rahmananis.com')
            ->set('newMemberRole', 'student')
            ->set('newMemberStudentCode', 'S-150')
            ->call('createMember')
            ->assertSet('error', null);

        $this->assertDatabaseHas('users', [
            'firm_id' => $this->firm->id,
            'email'   => 'new.student@rahmananis.com',
            'role'    => 'student',
        ]);
    }

    public function test_partner_can_still_manage_every_role(): void
    {
        $this->actingAs($this->partner);

        Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'partner',
            'section'     => 'team',
        ])
            ->call('openAddMemberModal')
            ->set('newMemberName', 'Second Partner')
            ->set('newMemberEmail', 'second.partner@rahmananis.com')
            ->set('newMemberRole', 'partner')
            ->call('createMember')
            ->assertSet('error', null);

        $this->assertDatabaseHas('users', [
            'firm_id' => $this->firm->id,
            'email'   => 'second.partner@rahmananis.com',
            'role'    => 'partner',
        ]);
    }

    // ─── 4. Email change requires the current password ──────────────────────

    public function test_email_change_is_blocked_without_the_current_password(): void
    {
        $this->actingAs($this->student);

        Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'student',
            'section'     => 'settings',
        ])
            ->set('myEmail', 'attacker@evil-example.com')
            ->set('myCurrentPassword', 'wrong-password')
            ->call('updateOwnProfileAndPassword')
            ->assertNotSet('error', null);

        $this->assertSame('zubair.student@rahmananis.com', $this->student->fresh()->email);
    }

    public function test_email_change_succeeds_with_the_current_password(): void
    {
        $this->actingAs($this->student);

        Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'student',
            'section'     => 'settings',
        ])
            ->set('myEmail', 'zubair.new@rahmananis.com')
            ->set('myCurrentPassword', 'StudentSecret123!')
            ->call('updateOwnProfileAndPassword')
            ->assertSet('error', null);

        $this->assertSame('zubair.new@rahmananis.com', $this->student->fresh()->email);
    }

    // ─── 5. Business clock ──────────────────────────────────────────────────

    public function test_application_clock_runs_on_dhaka_time(): void
    {
        $this->assertSame('Asia/Dhaka', config('app.timezone'));
        $this->assertSame('Asia/Dhaka', now()->getTimezone()->getName());
    }

    // ─── 6. Private files require a signed-in session ───────────────────────

    public function test_avatar_file_route_requires_authentication(): void
    {
        $this->get('/c/rac/avatar-file/does-not-exist.png')
            ->assertStatus(302);
    }
}
