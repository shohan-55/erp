<?php

namespace Tests\Feature;

use App\Livewire\Practice\PracticePreview;
use App\Models\Firm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Weekly off days are a per-firm setting, not a constant of the codebase.
 *
 * Every Bangladesh practice closes on a different pattern: the Dhaka CA-firm
 * default is Friday + Saturday, but plenty work Saturday and close Sunday, and
 * some close only on Friday. Before this feature the calendar hardcoded
 * `in_array($dow, [5, 6])`, so a firm with a different weekend saw the wrong
 * days marked off — and had to declare all 52 Saturdays "office open" by hand.
 */
class WeeklyOffDaysAndProfileShortcutTest extends TestCase
{
    use RefreshDatabase;

    protected Firm $firm;
    protected User $partner;
    protected User $manager;

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

        $this->manager = User::create([
            'firm_id'      => $this->firm->id,
            'name'         => 'Khandaker Tanvir Ahmed, ACA',
            'email'        => 'tanvir.ahmed@rahmananis.com',
            'password'     => Hash::make('Secret123!'),
            'role'         => 'manager',
            'partner_unit' => 'Partner Unit 01',
        ]);
    }

    /** Find the calendar entry for the first date with this weekday (0 = Sun). */
    protected function calendarDayFor(array $days, int $weekday): ?array
    {
        foreach ($days as $day) {
            if (! empty($day['is_padding']) || empty($day['date'])) {
                continue;
            }
            $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $day['date']);
            if ($dt && (int) $dt->format('w') === $weekday) {
                return $day;
            }
        }

        return null;
    }

    // ─── 1. The default must not change for existing tenants ──────────────

    public function test_a_firm_that_never_configured_a_weekend_still_gets_friday_and_saturday(): void
    {
        $this->assertNull($this->firm->fresh()->weekly_off_days);

        $this->actingAs($this->partner);
        $component = Livewire::test(PracticePreview::class, ['firmSlug' => 'rac', 'section' => 'attendance']);

        $component->assertSet('weeklyOffDays', [5, 6])
            ->assertSet('weeklyOffSummary', 'Friday & Saturday');

        $calendar = $component->get('calendarDays');
        $friday = $this->calendarDayFor($calendar, 5);
        $sunday = $this->calendarDayFor($calendar, 0);

        $this->assertTrue($friday['is_weekend'], 'Friday must be a weekend by default.');
        $this->assertFalse($sunday['is_weekend'], 'Sunday must be a working day by default.');
    }

    // ─── 2. A firm that works Saturday and closes Sunday ──────────────────

    public function test_a_firm_can_move_its_weekend_to_sunday(): void
    {
        $this->firm->update(['weekly_off_days' => [0]]);

        $this->actingAs($this->partner);
        $component = Livewire::test(PracticePreview::class, ['firmSlug' => 'rac', 'section' => 'attendance']);

        $component->assertSet('weeklyOffDays', [0])
            ->assertSet('weeklyOffSummary', 'Sunday');

        $calendar = $component->get('calendarDays');
        $this->assertTrue($this->calendarDayFor($calendar, 0)['is_weekend'], 'Sunday must now be the weekend.');
        $this->assertFalse($this->calendarDayFor($calendar, 5)['is_weekend'], 'Friday must now be a working day.');
        $this->assertFalse($this->calendarDayFor($calendar, 6)['is_weekend'], 'Saturday must now be a working day.');
    }

    // ─── 3. Saving the pattern ────────────────────────────────────────────

    public function test_a_partner_can_save_a_single_day_weekend(): void
    {
        $this->actingAs($this->partner);

        $component = Livewire::test(PracticePreview::class, ['firmSlug' => 'rac', 'section' => 'settings'])
            ->set('weeklyOffDayDraft', ['5'])   // checkbox values arrive as strings
            ->call('saveWeeklyOffDays')
            ->assertSet('weeklyOffDays', [5])
            ->assertSet('weeklyOffSummary', 'Friday')
            ->assertSet('error', null);

        $this->assertSame([5], $this->firm->fresh()->weekly_off_days);
        $this->assertStringContainsString('Weekly holiday updated', (string) $component->get('notice'));
        $this->assertStringContainsString('Friday', (string) $component->get('notice'));
    }

    public function test_a_firm_can_declare_that_it_works_all_seven_days(): void
    {
        $this->actingAs($this->partner);

        Livewire::test(PracticePreview::class, ['firmSlug' => 'rac', 'section' => 'settings'])
            ->set('weeklyOffDayDraft', [])
            ->call('saveWeeklyOffDays')
            ->assertSet('weeklyOffDays', [])
            ->assertSet('weeklyOffSummary', 'No weekly off — open all 7 days');

        $this->assertSame([], $this->firm->fresh()->weekly_off_days);
    }

    // ─── 4. Guards ────────────────────────────────────────────────────────

    public function test_a_manager_cannot_change_the_weekly_off_pattern(): void
    {
        $this->actingAs($this->manager);

        $component = Livewire::test(PracticePreview::class, ['firmSlug' => 'rac', 'section' => 'settings'])
            ->set('weeklyOffDayDraft', ['5'])
            ->call('saveWeeklyOffDays');

        $this->assertNull($this->firm->fresh()->weekly_off_days, 'A manager must not be able to close the firm on a new day.');
        $this->assertStringContainsString('Only a Partner', (string) $component->get('error'));
    }

    public function test_all_seven_days_cannot_be_declared_holidays(): void
    {
        $this->actingAs($this->partner);

        $component = Livewire::test(PracticePreview::class, ['firmSlug' => 'rac', 'section' => 'settings'])
            ->set('weeklyOffDayDraft', [0, 1, 2, 3, 4, 5, 6])
            ->call('saveWeeklyOffDays');

        $this->assertNull($this->firm->fresh()->weekly_off_days);
        $this->assertStringContainsString('seven', (string) $component->get('error'));
    }

    // ─── 5. Profile shortcut ──────────────────────────────────────────────

    public function test_the_profile_shortcut_opens_settings_from_anywhere(): void
    {
        $this->actingAs($this->partner);

        Livewire::test(PracticePreview::class, ['firmSlug' => 'rac', 'section' => 'dashboard'])
            ->assertSee('My profile')
            ->call('openMyProfile')
            ->assertSet('section', 'settings')
            ->assertSee('My Profile, Password &amp; Stipend Payment Account', false)
            ->assertSee('Weekly Off Days');
    }

    // ─── 6. Where the pattern is shown ────────────────────────────────────

    public function test_the_policy_page_shows_the_configured_weekend(): void
    {
        $this->firm->update(['weekly_off_days' => [0]]);

        $this->actingAs($this->partner);

        Livewire::test(PracticePreview::class, ['firmSlug' => 'rac', 'section' => 'policies'])
            ->assertSee('Weekly Holiday')
            ->assertSee('Sunday')
            ->assertDontSee('Fri & Sat');
    }

    public function test_the_settings_page_renders_the_weekly_off_card_for_a_partner(): void
    {
        $this->actingAs($this->partner);

        Livewire::test(PracticePreview::class, ['firmSlug' => 'rac', 'section' => 'settings'])
            ->assertSee('Weekly Off Days')
            ->assertSee('Save Weekly Holiday')
            ->assertSee('Fri')
            ->assertSee('Sat');
    }

    // ─── 7. The model helper keeps old data readable ──────────────────────

    public function test_the_model_normalises_legacy_stored_values(): void
    {
        $this->assertSame([5, 6], Firm::normaliseWeeklyOffDays(null));
        $this->assertSame([5, 6], Firm::normaliseWeeklyOffDays('[5,6]'));
        $this->assertSame([0], Firm::normaliseWeeklyOffDays(['0']));
        $this->assertSame([], Firm::normaliseWeeklyOffDays([]));
        $this->assertSame([5, 6], Firm::normaliseWeeklyOffDays('5,6'));
        $this->assertSame('Friday & Saturday', Firm::describeWeeklyOffDays([5, 6]));
        $this->assertSame('Sunday, Friday & Saturday', Firm::describeWeeklyOffDays([5, 6, 0]));
        $this->assertSame('Fri & Sat', Firm::shortWeeklyOffDays([5, 6]));
    }
}
