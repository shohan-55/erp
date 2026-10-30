<?php

namespace Tests\Feature;

use App\Livewire\Practice\PracticePreview;
use App\Models\Attendance;
use App\Models\Firm;
use App\Models\PracticeNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The final check-out guard (30 Oct 2026).
 *
 * A member who is travelling from client A to client B taps "Out" on the
 * dashboard, then "Close" — and their whole duty day ends at 11:00 by
 * accident. Closing is therefore a two-step action now:
 *
 *   1. "Close" opens a review panel (every leg, first in, elapsed, location).
 *   2. The day only ends after the confirmation box is ticked and confirmed.
 *
 * The gate is enforced on the server, so a double submit, a browser back
 * button or a hand-crafted Livewire call cannot skip it.
 */
class FinalCheckoutGuardTest extends TestCase
{
    use RefreshDatabase;

    protected Firm $firm;
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

        $this->student = User::create([
            'firm_id'      => $this->firm->id,
            'name'         => 'Zubair Hossain Siddique',
            'email'        => 'zubair.student@rahmananis.com',
            'password'     => Hash::make('Secret123!'),
            'role'         => 'student',
            'partner_unit' => 'Partner Unit 01',
            'student_code' => 'S-101',
        ]);
    }

    protected function attendanceComponent()
    {
        $this->actingAs($this->student);

        return Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'student',
            'section'     => 'attendance',
        ]);
    }

    protected function checkInOffice($component)
    {
        return $component
            ->call('selectAttendanceWorkMode', 'office')
            ->call('togglePreviewSessionWithGps', 23.7516, 90.3943, 'Karwan Bazar Office');
    }

    protected function checkInClient($component, string $client)
    {
        return $component
            ->call('selectAttendanceWorkMode', 'client')
            ->set('clientSite', $client)
            ->call('togglePreviewSessionWithGps', 23.7279, 90.4178, $client . ' Premises');
    }

    // ─── 1. One click must not end the day ────────────────────────────────

    public function test_a_single_click_cannot_end_the_duty_day(): void
    {
        $component = $this->checkInOffice($this->attendanceComponent());

        $component->call('initiateCheckoutPrompt', 23.7516, 90.3943, 'Karwan Bazar Office')
            ->assertSet('showCheckoutModeModal', true)
            ->call('confirmCheckoutWithAction', 'close');

        // The day is still running and the review panel is on screen.
        $component->assertSet('showFinalCheckoutReview', true)
            ->assertSet('previewSessionActive', true)
            ->assertSet('sessionState', 'active');

        $attendance = Attendance::where('user_id', $this->student->id)->firstOrFail();

        $this->assertNull($attendance->out_time, 'A refused close must not write an out-time.');
        $this->assertSame('active', $attendance->session_state);
    }

    public function test_the_review_panel_shows_every_leg_of_the_day(): void
    {
        $component = $this->checkInOffice($this->attendanceComponent());

        // Office -> Client A -> Client B, i.e. exactly the travelling case.
        $component->call('confirmCheckoutWithAction', 'on_duty');
        $this->checkInClient($component, 'Square Pharma Audit');
        $component->call('confirmCheckoutWithAction', 'on_duty');
        $this->checkInClient($component, 'BRAC Bank Audit');

        $component->call('openFinalCheckoutReview');

        $summary = $component->get('finalCheckoutSummary');

        $this->assertSame(3, $summary['leg_count'], 'Every leg of today must be listed before closing.');
        $this->assertCount(3, $summary['legs']);
        $this->assertNotSame('—', $summary['first_in']);
        $this->assertMatchesRegularExpression('/^\d+h \d{2}m$/', $summary['elapsed']);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $summary['now_time']);
        $this->assertNotEmpty($summary['location']);

        $this->assertSame('Office', $summary['legs'][0]['mode']);
        $this->assertSame('on_duty', $summary['legs'][0]['out_action']);
        $this->assertSame('BRAC Bank Audit', $summary['legs'][2]['site']);
    }

    // ─── 2. The confirmation box is what actually closes the day ──────────

    public function test_confirming_without_ticking_the_box_does_nothing(): void
    {
        $component = $this->checkInOffice($this->attendanceComponent())
            ->call('openFinalCheckoutReview')
            ->call('confirmFinalCheckout');

        $component->assertSet('previewSessionActive', true)
            ->assertSet('sessionState', 'active')
            ->assertSet('showFinalCheckoutReview', true);

        $this->assertNotNull($component->get('error'));
        $this->assertNull(Attendance::where('user_id', $this->student->id)->firstOrFail()->out_time);
    }

    public function test_ticking_the_box_and_confirming_closes_the_day_once(): void
    {
        $component = $this->checkInOffice($this->attendanceComponent())
            ->call('openFinalCheckoutReview')
            ->set('finalCloseConfirmed', true)
            ->call('confirmFinalCheckout');

        $component->assertSet('previewSessionActive', false)
            ->assertSet('sessionState', 'closed')
            ->assertSet('showFinalCheckoutReview', false);

        $attendance = Attendance::where('user_id', $this->student->id)->firstOrFail();
        $this->assertNotNull($attendance->out_time);
        $this->assertSame('closed', $attendance->session_state);
        $closedAt = $attendance->out_time->format('H:i:s');

        // A second close (double click, stale tab) must not re-write the record.
        $component->call('confirmCheckoutWithAction', 'close');

        $attendance->refresh();
        $this->assertSame($closedAt, $attendance->out_time->format('H:i:s'));
        $this->assertSame('closed', $attendance->session_state);
        $this->assertStringContainsString('already closed', (string) $component->get('notice'));
    }

    public function test_the_review_panel_offers_keep_on_duty_as_the_way_out(): void
    {
        $component = $this->checkInOffice($this->attendanceComponent())
            ->call('openFinalCheckoutReview')
            ->call('confirmCheckoutWithAction', 'on_duty');

        $component->assertSet('sessionState', 'on_duty_transit')
            ->assertSet('showFinalCheckoutReview', false);

        $attendance = Attendance::where('user_id', $this->student->id)->firstOrFail();
        $this->assertNotNull($attendance->out_time, 'The departure leg is recorded.');
        $this->assertSame('on_duty_transit', $attendance->session_state);
    }

    // ─── 3. A new leg clears the previous departure ───────────────────────

    public function test_checking_in_again_clears_the_previous_departure_time(): void
    {
        $component = $this->checkInOffice($this->attendanceComponent());

        $component->call('confirmCheckoutWithAction', 'on_duty');
        $departure = Attendance::where('user_id', $this->student->id)->firstOrFail();
        $this->assertNotNull($departure->out_time, 'Leaving the office records a departure.');
        $firstIn = $departure->in_time->format('H:i');

        // Arrive at the client site and check in again.
        $this->checkInClient($component, 'Square Pharma Audit');

        $attendance = Attendance::where('user_id', $this->student->id)->firstOrFail();

        // The register must not read "active" and "checked out at 11:20" at once.
        $this->assertNull($attendance->out_time, 'The previous leg departure is not the day check-out.');
        $this->assertNull($attendance->out_location_name);
        $this->assertSame('active', $attendance->session_state);
        $this->assertSame($firstIn, $attendance->in_time->format('H:i'), 'First check-in of the day is preserved.');

        $row = collect($component->get('studentRows'))->firstWhere('db_id', $this->student->id);
        $this->assertSame('—', $row['checkout']);
        $this->assertSame(2, $row['movement_count']);
    }

    // ─── 4. The nightly sweep catches an open On-Duty day ─────────────────

    public function test_the_sweep_catches_an_on_duty_move_that_was_never_closed(): void
    {
        // Left the office on duty two days ago and never came back.
        Attendance::create([
            'firm_id'        => $this->firm->id,
            'user_id'        => $this->student->id,
            'date'           => now()->subDays(2)->toDateString(),
            'in_time'        => now()->subDays(2)->setTime(10, 0),
            'out_time'       => now()->subDays(2)->setTime(11, 0),
            'work_mode'      => 'office',
            'status'         => 'Right Time',
            'session_state'  => 'on_duty_transit',
            'movement_logs'  => [[
                'seq' => 1, 'mode' => 'Office', 'site' => 'Principal Office HQ',
                'in_time' => '10:00', 'in_location' => 'Office',
                'out_time' => '11:00', 'out_location' => 'Office', 'out_action' => 'on_duty',
            ]],
        ]);

        $this->artisan('erp:flag-missed-checkouts')->assertExitCode(0);

        $attendance = Attendance::where('user_id', $this->student->id)->firstOrFail();

        $this->assertTrue((bool) $attendance->correction_requested, 'An open On-Duty day must be flagged.');
        $this->assertGreaterThanOrEqual(1, PracticeNotification::count());
    }

    public function test_the_sweep_still_catches_a_day_with_no_checkout_at_all(): void
    {
        Attendance::create([
            'firm_id'   => $this->firm->id,
            'user_id'   => $this->student->id,
            'date'      => now()->subDay()->toDateString(),
            'in_time'   => now()->subDay()->setTime(10, 0),
            'out_time'  => null,
            'work_mode' => 'office',
            'status'    => 'Right Time',
        ]);

        $this->artisan('erp:flag-missed-checkouts')->assertExitCode(0);

        $this->assertTrue((bool) Attendance::where('user_id', $this->student->id)->firstOrFail()->correction_requested);
    }

    public function test_the_sweep_leaves_a_properly_closed_day_alone(): void
    {
        Attendance::create([
            'firm_id'       => $this->firm->id,
            'user_id'       => $this->student->id,
            'date'          => now()->subDay()->toDateString(),
            'in_time'       => now()->subDay()->setTime(10, 0),
            'out_time'      => now()->subDay()->setTime(18, 30),
            'work_mode'     => 'office',
            'status'        => 'Right Time',
            'session_state' => 'closed',
        ]);

        $this->artisan('erp:flag-missed-checkouts')->assertExitCode(0);

        $this->assertFalse((bool) Attendance::where('user_id', $this->student->id)->firstOrFail()->correction_requested);
        $this->assertSame(0, PracticeNotification::count());
    }

    // ─── 5. The dialog is reachable from the dashboard too ────────────────

    public function test_the_dashboard_can_open_the_checkout_dialog(): void
    {
        $this->actingAs($this->student);

        // Before this change the dialogs lived inside the attendance partial,
        // so the dashboard's own "Out" button set the flag but rendered
        // nothing at all.
        $component = Livewire::test(PracticePreview::class, [
            'firmSlug'    => 'rac',
            'previewRole' => 'student',
            'section'     => 'dashboard',
        ]);

        $component->call('selectAttendanceWorkMode', 'office')
            ->call('togglePreviewSessionWithGps', 23.7516, 90.3943, 'Office')
            ->call('initiateCheckoutPrompt')
            ->assertSet('showCheckoutModeModal', true)
            ->assertSee('Select Out Mode');

        // ... and the same dialog can reach the guarded final review.
        $component->call('confirmCheckoutWithAction', 'close')
            ->assertSet('showFinalCheckoutReview', true)
            ->assertSee('Keep On Duty')
            ->assertSee('Confirm Final Check-out');
    }
}
