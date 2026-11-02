<?php

namespace Tests\Feature;

use App\Livewire\Auth\FirmLogin;
use App\Livewire\Practice\PracticePreview;
use App\Models\Firm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression tests for the 2 Nov 2026 pre-launch review
 * (docs/PRODUCTION_REVIEW_2026-11-02.md).
 *
 * NOTE: written without a PHP runtime available. Run `php artisan test` before
 * deploying; if one of these fails, fix the test setup, not the guard.
 */
class ReviewFixes20261102Test extends TestCase
{
    use RefreshDatabase;

    protected Firm $firm;
    protected User $partner;
    protected User $studentA;
    protected User $studentB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->firm = Firm::create(['name' => 'Firm A', 'slug' => 'firm-a', 'status' => 'active', 'tier' => 'professional']);

        $this->partner  = $this->makeUser('partner', 'partner@firm-a.test');
        $this->studentA = $this->makeUser('student', 'student.a@firm-a.test');
        $this->studentB = $this->makeUser('student', 'student.b@firm-a.test', [
            'payment_account_mode' => 'bKash',
            'payment_account_no'   => '01700000001',
        ]);
    }

    private function makeUser(string $role, string $email, array $extra = []): User
    {
        return User::create(array_merge([
            'firm_id'      => $this->firm->id,
            'name'         => ucfirst($role) . ' ' . $email,
            'email'        => $email,
            'password'     => Hash::make('Secret123!'),
            'role'         => $role,
            'partner_unit' => 'Partner Unit 01',
        ], $extra));
    }

    public function test_login_page_does_not_reveal_staff_emails_when_demo_flags_are_off(): void
    {
        config(['erp.allow_test_logins' => false, 'app.demo_mode' => false]);

        Livewire::test(FirmLogin::class, ['firmSlug' => 'firm-a'])
            ->call('fillDemoCredentials', 'partner')
            ->assertSet('email', '');
    }

    public function test_student_cannot_download_the_disbursement_sheet(): void
    {
        $this->actingAs($this->studentA);

        Livewire::test(PracticePreview::class, ['firmSlug' => 'firm-a'])
            ->call('exportDisbursementSheetCsv')
            ->assertNoFileDownloaded()
            ->assertSet('error', 'Only a Manager or Partner can download the disbursement sheet.');
    }

    public function test_student_never_receives_colleagues_payroll_rows(): void
    {
        $this->actingAs($this->studentA);

        $rows = Livewire::test(PracticePreview::class, ['firmSlug' => 'firm-a'])
            ->get('payrollRows');

        foreach ($rows as $row) {
            $this->assertSame($this->studentA->id, (int) ($row['db_id'] ?? 0));
            $this->assertNotSame('01700000001', (string) ($row['payment_account_no'] ?? ''));
        }
    }

    public function test_payroll_period_only_accepts_year_month(): void
    {
        $this->actingAs($this->partner);

        Livewire::test(PracticePreview::class, ['firmSlug' => 'firm-a'])
            ->set('period', '2026-10"><script>')
            ->assertSet('period', now()->format('Y-m'));
    }

    public function test_firm_logo_upload_no_longer_accepts_svg(): void
    {
        $source = file_get_contents(app_path('Livewire/Practice/Concerns/ManagesPoliciesHolidaysAndSettings.php'));

        $this->assertStringContainsString("mimes:png,jpg,jpeg,webp|max:2048", $source);
        $this->assertStringNotContainsString("mimes:png,jpg,jpeg,svg,webp", $source);
    }

    public function test_student_never_receives_colleagues_attendance_rows_or_sensitive_team_fields(): void
    {
        \App\Models\Attendance::create([
            'firm_id'          => $this->firm->id,
            'user_id'          => $this->studentB->id,
            'date'             => now()->timezone('Asia/Dhaka')->toDateString(),
            'work_mode'        => 'client',
            'client_site'      => 'Confidential Client B',
            'in_time'          => now()->timezone('Asia/Dhaka')->setTime(9, 15),
            'in_location_name' => 'Secret Location B',
            'status'           => 'Right Time',
        ]);

        $this->actingAs($this->studentA);

        $comp = Livewire::test(PracticePreview::class, ['firmSlug' => 'firm-a']);

        $attRows = $comp->get('studentRows');
        foreach ($attRows as $r) {
            $this->assertSame($this->studentA->id, (int) ($r['db_id'] ?? 0));
            $this->assertNotSame('Confidential Client B', (string) ($r['client_site'] ?? ''));
        }

        $teamRows = $comp->get('teamMembers');
        foreach ($teamRows as $m) {
            if ((int) ($m['id'] ?? 0) !== $this->studentA->id) {
                $this->assertSame('—', $m['email']);
                $this->assertSame('—', $m['payment_account_no']);
            }
        }
    }

    public function test_student_cannot_download_printable_attendance_register_or_colleague_certificate(): void
    {
        $this->actingAs($this->studentA);

        Livewire::test(PracticePreview::class, ['firmSlug' => 'firm-a'])
            ->call('exportPrintableAttendanceSheet')
            ->assertNoFileDownloaded()
            ->assertSet('error', 'Only a Manager or Partner can download the printable attendance register.')
            ->call('exportStudentCertificate', $this->studentB->id)
            ->assertNoFileDownloaded()
            ->assertSet('error', 'Students may only download their own ICAB certificate.');
    }
}
