<?php

namespace Tests\Feature;

use App\Livewire\Platform\DashboardPreview;
use App\Livewire\Practice\PracticePreview;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\PayrollRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression tests for P0-7 (disaster recovery / destructive deletion).
 *
 * A tenant must never be destroyed without a backup, a half-finished deletion
 * must never survive, a numeric slug must never target a firm by id, and a
 * staff exit must never erase payroll or attendance history.
 */
class DataRetentionAndDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function makePlatformAdmin(): User
    {
        return User::create([
            'name'     => 'Platform Operator',
            'email'    => 'operator@dhakawala.com',
            'password' => Hash::make('OperatorSecret123!'),
            'role'     => 'platform_admin',
        ]);
    }

    protected function makeFirm(string $slug, string $name): Firm
    {
        return Firm::create([
            'name'   => $name,
            'slug'   => $slug,
            'status' => 'active',
        ]);
    }

    protected function makeMember(Firm $firm, string $email, string $role = 'partner'): User
    {
        return User::create([
            'firm_id'      => $firm->id,
            'name'         => 'Member ' . ucfirst($role),
            'email'        => $email,
            'password'     => Hash::make('MemberSecret123!'),
            'role'         => $role,
            'partner_unit' => 'Partner Unit 01',
        ]);
    }

    public function test_a_numeric_slug_can_no_longer_delete_a_firm_by_id(): void
    {
        Storage::fake('local');

        $this->actingAs($this->makePlatformAdmin());
        $firm = $this->makeFirm('alpha-co', 'Alpha & Co.');

        // Before the fix, `orWhere('id', $slug)` would have matched this firm.
        Livewire::test(DashboardPreview::class)
            ->call('deleteOrganization', (string) $firm->id);

        $this->assertDatabaseHas('firms', ['id' => $firm->id, 'slug' => 'alpha-co']);
    }

    public function test_firm_deletion_writes_a_backup_and_keeps_the_audit_trail(): void
    {
        Storage::fake('local');

        $this->actingAs($this->makePlatformAdmin());
        $firm = $this->makeFirm('beta-co', 'Beta & Co.');
        $partner = $this->makeMember($firm, 'partner@beta.test');

        AuditLog::create([
            'firm_id'    => $firm->id,
            'actor_id'   => $partner->id,
            'actor_name' => $partner->name,
            'actor_role' => 'partner',
            'module'     => 'team',
            'action'     => 'retention_probe',
            'summary'    => 'This history must survive tenant deletion.',
        ]);

        Livewire::test(DashboardPreview::class)
            ->call('deleteOrganization', 'beta-co');

        $this->assertDatabaseMissing('firms', ['slug' => 'beta-co']);
        $this->assertNotEmpty(
            Storage::disk('local')->files('backups/firms'),
            'A tenant must never be deleted without a verifiable on-disk backup.'
        );

        $log = AuditLog::where('action', 'retention_probe')->first();
        $this->assertNotNull($log, 'The platform audit trail must be append-only.');
        $this->assertNull($log->firm_id, 'Audit rows are detached from the tenant, not deleted.');
    }

    public function test_firm_deletion_leaves_no_orphaned_tenant_rows(): void
    {
        Storage::fake('local');

        $this->actingAs($this->makePlatformAdmin());
        $firm = $this->makeFirm('gamma-co', 'Gamma & Co.');
        $student = $this->makeMember($firm, 'student@gamma.test', 'student');

        Attendance::create([
            'firm_id' => $firm->id,
            'user_id' => $student->id,
            'date'    => now()->toDateString(),
            'status'  => 'On time',
        ]);

        PayrollRecord::create([
            'firm_id'    => $firm->id,
            'user_id'    => $student->id,
            'period'     => now()->format('Y-m'),
            'net_payable' => 8000,
        ]);

        Livewire::test(DashboardPreview::class)
            ->call('deleteOrganization', 'gamma-co');

        $this->assertDatabaseMissing('firms', ['slug' => 'gamma-co']);
        $this->assertSame(0, Attendance::where('firm_id', $firm->id)->count());
        $this->assertSame(0, PayrollRecord::where('firm_id', $firm->id)->count());
        $this->assertSame(0, User::where('firm_id', $firm->id)->count());
    }

    public function test_member_with_financial_history_is_deactivated_not_erased(): void
    {
        $firm = $this->makeFirm('delta-co', 'Delta & Co.');
        $partner = $this->makeMember($firm, 'partner@delta.test');
        $student = $this->makeMember($firm, 'student@delta.test', 'student');

        Attendance::create([
            'firm_id' => $firm->id,
            'user_id' => $student->id,
            'date'    => now()->toDateString(),
            'status'  => 'On time',
        ]);

        $this->actingAs($partner);

        Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'delta-co',
            'previewRole' => 'partner',
            'section'     => 'team',
        ])
            ->call('confirmDeleteMember', $student->id)
            ->set('deleteConfirmationInput', 'DELETE')
            ->call('deleteMember');

        $this->assertDatabaseHas('users', ['id' => $student->id, 'is_active' => false]);
        $this->assertDatabaseHas('attendances', ['user_id' => $student->id]);
        $this->assertTrue(
            Hash::check('MemberSecret123!', $student->fresh()->password) === false,
            'A deactivated account must not keep its old password.'
        );
    }

    public function test_member_without_history_is_still_removed_completely(): void
    {
        $firm = $this->makeFirm('epsilon-co', 'Epsilon & Co.');
        $partner = $this->makeMember($firm, 'partner@epsilon.test');
        $student = $this->makeMember($firm, 'student@epsilon.test', 'student');

        $this->actingAs($partner);

        Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'epsilon-co',
            'previewRole' => 'partner',
            'section'     => 'team',
        ])
            ->call('confirmDeleteMember', $student->id)
            ->set('deleteConfirmationInput', 'DELETE')
            ->call('deleteMember');

        $this->assertDatabaseMissing('users', ['id' => $student->id]);
    }

    public function test_daily_backup_command_writes_a_snapshot(): void
    {
        Storage::fake('local');

        $firm = $this->makeFirm('zeta-co', 'Zeta & Co.');
        $this->makeMember($firm, 'partner@zeta.test');

        $this->artisan('erp:backup')->assertExitCode(0);

        $files = Storage::disk('local')->files('backups/db');
        $this->assertNotEmpty($files, 'The backup command must produce a snapshot file.');

        $snapshot = json_decode(Storage::disk('local')->get($files[0]), true);
        $this->assertSame('zeta-co', $snapshot['tables']['firms'][0]['slug']);
        $this->assertArrayHasKey('users', $snapshot['tables']);
        $this->assertNull(
            $snapshot['tables']['users'][0]['password'],
            'Backup snapshots must never contain password hashes.'
        );
    }
}
