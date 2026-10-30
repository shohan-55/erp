<?php

namespace Tests\Feature;

use App\Livewire\Practice\PracticePreview;
use App\Models\Attendance;
use App\Models\Firm;
use App\Models\PartnerPolicy;
use App\Models\PayrollRecord;
use App\Models\PracticeHoliday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Working Day Exceptions let a Partner change the hours of ONE date without
 * touching the standing policy: a hartal that delays opening, a storm that makes
 * lateness meaningless, an Eid half-day.
 *
 * The policy is never edited, because rewriting `partner_policies` would
 * re-derive every past day under the new rule and silently alter payroll that
 * was already approved.
 */
class WorkingDayExceptionTest extends TestCase
{
    use RefreshDatabase;

    protected Firm $firm;
    protected User $partner;
    protected User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->firm = Firm::create([
            'name'       => 'Rahman Anis & Co.',
            'slug'       => 'rac',
            'short_code' => 'RA&C',
            'status'     => 'active',
        ]);

        $this->partner = User::create([
            'firm_id'      => $this->firm->id,
            'name'         => 'A.K.M. Mohitul Haq, FCA',
            'email'        => 'mohitul.haq@rahmananis.com',
            'password'     => Hash::make('Secret123!'),
            'role'         => 'partner',
            'partner_unit' => 'Partner Unit 01',
        ]);

        $this->student = User::create([
            'firm_id'      => $this->firm->id,
            'name'         => 'Sadia Rahman',
            'email'        => 'sadia@rahmananis.com',
            'password'     => Hash::make('Secret123!'),
            'role'         => 'student',
            'partner_unit' => 'Partner Unit 01',
        ]);

        // Standing policy: office 10:00–18:00, 10 minutes of grace.
        PartnerPolicy::create([
            'firm_id'                  => $this->firm->id,
            'partner_id'               => $this->partner->id,
            'partner_unit'             => 'Partner Unit 01',
            'office_start'             => '10:00',
            'office_end'               => '18:00',
            'grace_minutes'            => 10,
            'manager_conveyance_limit' => 300,
            'conveyance_late_days'     => 2,
        ]);
    }

    protected function workspace(string $section = 'settings'): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(PracticePreview::class, ['firmSlug' => 'rac', 'section' => $section]);
    }

    protected function declareException(array $overrides = []): void
    {
        PracticeHoliday::create(array_merge([
            'firm_id'                  => $this->firm->id,
            'holiday_date'             => now()->toDateString(),
            'title'                    => 'Hartal — office opens at 12:00',
            'category'                 => PracticeHoliday::CATEGORY_EXCEPTION,
            'is_office_open_override'  => false,
            'absent_deduction_days'    => 0,
            'office_start_override'    => null,
            'office_end_override'      => null,
            'grace_minutes_override'   => null,
            'late_exempt'              => false,
        ], $overrides));
    }

    // ─── 1. The standing policy still governs an ordinary day ─────────────

    public function test_an_ordinary_day_uses_the_standing_policy(): void
    {
        $this->actingAs($this->partner);
        $component = $this->workspace('attendance');

        $hours = $component->instance()->effectiveOfficeHoursFor();
        $this->assertSame('10:00', $hours['start']);
        $this->assertSame('18:00', $hours['end']);
        $this->assertSame(10, $hours['grace']);
        $this->assertFalse($hours['late_exempt']);
        $this->assertFalse($hours['is_exception']);
    }

    // ─── 2. A shifted start time ────────────────────────────────────────────

    public function test_an_exception_shifts_the_start_time_for_that_date_only(): void
    {
        $this->declareException(['office_start_override' => '12:00', 'office_end_override' => '18:00']);

        $this->actingAs($this->partner);
        $component = $this->workspace('attendance');
        $instance = $component->instance();

        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        $todayHours = $instance->effectiveOfficeHoursFor($today);
        $this->assertSame('12:00', $todayHours['start']);
        $this->assertTrue($todayHours['is_exception']);

        // 11:40 used to be badly late; under the exception it is early.
        $this->assertSame('On time', $instance->classifyArrivalStatus('11:40', $today));

        // Tomorrow is untouched — the policy was never modified.
        $tomorrowHours = $instance->effectiveOfficeHoursFor($tomorrow);
        $this->assertSame('10:00', $tomorrowHours['start']);
        $this->assertFalse($tomorrowHours['is_exception']);
        $this->assertSame('Late', $instance->classifyArrivalStatus('11:40', $tomorrow));
    }

    // ─── 3. Lateness switched off ───────────────────────────────────────────

    public function test_a_declared_exception_excuses_lateness(): void
    {
        $this->declareException(['late_exempt' => true]);

        $this->actingAs($this->partner);
        $instance = $this->workspace('attendance')->instance();

        $today = now()->toDateString();

        $this->assertSame('Late (Excused)', $instance->classifyArrivalStatus('11:30', $today));
        // Somebody who arrived before the cutoff is simply on time.
        $this->assertSame('On time', $instance->classifyArrivalStatus('10:05', $today));
    }

    public function test_excused_lateness_is_never_counted_by_payroll(): void
    {
        $this->declareException(['late_exempt' => true]);

        Attendance::create([
            'firm_id'      => $this->firm->id,
            'user_id'      => $this->student->id,
            'date'         => now()->toDateString(),
            'work_mode'    => 'office',
            'in_time'      => now()->setTime(11, 30),
            'status'       => 'Late (Excused)',
            'session_state' => 'closed',
        ]);

        // This is the exact filter the payroll ledger uses to count late days.
        $lateDays = Attendance::where('firm_id', $this->firm->id)
            ->where('user_id', $this->student->id)
            ->whereIn('status', ['Late', 'Late Present'])
            ->count();

        $this->assertSame(0, $lateDays, 'An excused arrival must never reach the salary deduction.');
    }

    // ─── 4. Saving reclassifies the register ───────────────────────────────

    public function test_saving_an_exception_reclassifies_the_days_attendance(): void
    {
        Attendance::create([
            'firm_id'       => $this->firm->id,
            'user_id'       => $this->student->id,
            'date'          => now()->toDateString(),
            'work_mode'     => 'office',
            'in_time'       => now()->setTime(11, 30),
            'status'        => 'Late',
            'session_state' => 'closed',
        ]);

        $this->actingAs($this->partner);

        $this->workspace('settings')
            ->set('newExceptionDate', now()->toDateString())
            ->set('newExceptionTitle', 'Hartal — office opens at 12:00')
            ->set('newExceptionStart', '12:00')
            ->set('newExceptionTitle', 'Hartal — office opens at 12:00')
            ->set('newExceptionLateExempt', false)
            ->call('saveWorkingDayException')
            ->assertSet('error', null);

        $att = Attendance::where('user_id', $this->student->id)->first();
        $this->assertSame('On time', $att->status, 'The 11:30 arrival is inside the new 12:00 start.');

        // `holiday_date` is a date cast, so compare by date rather than by the
        // serialised "Y-m-d H:i:s" string the cast produces.
        $this->assertSame(1, PracticeHoliday::where('firm_id', $this->firm->id)
            ->whereDate('holiday_date', now()->toDateString())
            ->where('category', 'exception')
            ->count());
    }

    public function test_the_preview_reports_the_impact_without_writing_anything(): void
    {
        Attendance::create([
            'firm_id'       => $this->firm->id,
            'user_id'       => $this->student->id,
            'date'          => now()->toDateString(),
            'work_mode'     => 'office',
            'in_time'       => now()->setTime(11, 30),
            'status'        => 'Late',
            'session_state' => 'closed',
        ]);

        $this->actingAs($this->partner);

        $component = $this->workspace('settings')
            ->set('newExceptionDate', now()->toDateString())
            ->set('newExceptionStart', '12:00')
            ->call('previewWorkingDayException');

        $impact = $component->get('exceptionImpact');
        $this->assertSame(1, $impact['scanned']);
        $this->assertSame(1, $impact['changed']);
        $this->assertSame('Late', $impact['rows'][0]['from']);
        $this->assertSame('On time', $impact['rows'][0]['to']);

        // A preview must never persist.
        $this->assertSame('Late', Attendance::where('user_id', $this->student->id)->first()->status);
        $this->assertSame(0, PracticeHoliday::where('category', 'exception')->count());
    }

    // ─── 5. The policy is left alone ───────────────────────────────────────

    public function test_the_standing_policy_is_never_modified_by_an_exception(): void
    {
        $this->actingAs($this->partner);

        $this->workspace('settings')
            ->set('newExceptionDate', now()->toDateString())
            ->set('newExceptionTitle', 'Heavy rain — office opens late')
            ->set('newExceptionStart', '11:30')
            ->set('newExceptionEnd', '17:00')
            ->call('saveWorkingDayException')
            ->assertSet('error', null);

        $policy = PartnerPolicy::where('firm_id', $this->firm->id)->first();
        $this->assertSame('10:00', $policy->office_start);
        $this->assertSame('18:00', $policy->office_end);
        $this->assertSame(10, (int) $policy->grace_minutes);
    }

    // ─── 6. Validation ─────────────────────────────────────────────────────

    public function test_an_exception_needs_at_least_one_effect(): void
    {
        $this->actingAs($this->partner);

        $component = $this->workspace('settings')
            ->set('newExceptionDate', now()->toDateString())
            ->set('newExceptionTitle', 'Nothing changes')
            ->call('saveWorkingDayException');

        $this->assertStringContainsString('at least one effect', (string) $component->get('error'));
        $this->assertSame(0, PracticeHoliday::where('category', 'exception')->count());
    }

    public function test_the_end_time_must_be_after_the_start_time(): void
    {
        $this->actingAs($this->partner);

        $component = $this->workspace('settings')
            ->set('newExceptionDate', now()->toDateString())
            ->set('newExceptionTitle', 'Backwards day')
            ->set('newExceptionStart', '16:00')
            ->set('newExceptionEnd', '12:00')
            ->call('saveWorkingDayException');

        $this->assertStringContainsString('later than', (string) $component->get('error'));
    }

    public function test_a_malformed_time_is_rejected(): void
    {
        $this->actingAs($this->partner);

        $component = $this->workspace('settings')
            ->set('newExceptionDate', now()->toDateString())
            ->set('newExceptionTitle', 'Bad time')
            ->set('newExceptionStart', '25:99')
            ->call('saveWorkingDayException');

        $this->assertStringContainsString('24-hour', (string) $component->get('error'));
    }

    // ─── 7. Only a Partner may declare one ─────────────────────────────────

    public function test_a_manager_cannot_declare_a_working_day_exception(): void
    {
        $manager = User::create([
            'firm_id'      => $this->firm->id,
            'name'         => 'Khandaker Tanvir Ahmed, ACA',
            'email'        => 'tanvir@rahmananis.com',
            'password'     => Hash::make('Secret123!'),
            'role'         => 'manager',
            'partner_unit' => 'Partner Unit 01',
        ]);

        $this->actingAs($manager);

        $component = $this->workspace('settings')
            ->set('newExceptionDate', now()->toDateString())
            ->set('newExceptionTitle', 'Manager tries to excuse lateness')
            ->set('newExceptionLateExempt', true)
            ->call('saveWorkingDayException');

        $this->assertStringContainsString('Only a Partner', (string) $component->get('error'));
        $this->assertSame(0, PracticeHoliday::where('category', 'exception')->count());
    }

    // ─── 8. Partner-unit precedence ────────────────────────────────────────

    public function test_a_unit_specific_exception_wins_over_the_firm_wide_one(): void
    {
        $this->declareException(['title' => 'Firm-wide hartal', 'office_start_override' => '12:00']);
        $this->declareException([
            'title'                => 'Unit 01 keeps 10:00',
            'partner_unit'         => 'Partner Unit 01',
            'office_start_override' => '10:00',
        ]);

        $this->actingAs($this->partner);
        $instance = $this->workspace('attendance')->instance();

        $hours = $instance->effectiveOfficeHoursFor();
        $this->assertSame('10:00', $hours['start']);
        $this->assertSame('Unit 01 keeps 10:00', $hours['title']);
    }

    // ─── 9. Display must never call it a holiday ───────────────────────────

    public function test_an_exception_day_is_shown_as_a_shift_not_a_holiday(): void
    {
        $this->declareException(['office_start_override' => '12:00', 'late_exempt' => true]);

        $this->actingAs($this->partner);

        $this->workspace('settings')
            ->assertSee('Working Day Exception')
            ->assertSee('Hartal — office opens at 12:00')
            ->assertSee('12:00')
            ->assertDontSee('OFFICIAL HOLIDAY');
    }

    public function test_the_dashboard_shows_todays_exception_as_a_banner(): void
    {
        $this->declareException(['office_start_override' => '12:00']);

        $this->actingAs($this->student);

        // assertSee() escapes its needle by default, which turns the apostrophe
        // into &#039; — pass false so the literal HTML is matched.
        $this->workspace('dashboard')
            ->assertSee("Today's Office Timing", false)
            ->assertSee('Hartal — office opens at 12:00');
    }

    // ─── 10. Removing it restores the policy ───────────────────────────────

    public function test_removing_the_exception_stops_the_excuse(): void
    {
        $this->declareException(['late_exempt' => true]);
        $row = PracticeHoliday::where('category', 'exception')->first();

        $this->actingAs($this->partner);

        $this->workspace('settings')
            ->call('deleteWorkingDayException', $row->id)
            ->assertSet('error', null);

        $this->assertSame(0, PracticeHoliday::where('category', 'exception')->count());

        $instance = $this->workspace('attendance')->instance();
        $this->assertSame('Late', $instance->classifyArrivalStatus('11:30'));
    }

    // ─── 11. Payroll guard ─────────────────────────────────────────────────

    public function test_a_locked_payroll_period_is_flagged_after_saving(): void
    {
        if (! Schema::hasTable('payroll_records')) {
            $this->markTestSkipped('payroll_records table is not present in this schema.');
        }

        PayrollRecord::create([
            'firm_id'    => $this->firm->id,
            'user_id'    => $this->student->id,
            'period'     => now()->format('Y-m'),
            'status'     => 'approved',
            'net_payable' => 5000,
        ]);

        $this->actingAs($this->partner);

        $component = $this->workspace('settings')
            ->set('newExceptionDate', now()->toDateString())
            ->set('newExceptionTitle', 'Hartal')
            ->set('newExceptionLateExempt', true)
            ->call('saveWorkingDayException');

        $this->assertStringContainsString('reopen the payroll period', (string) $component->get('notice'));
    }
}
