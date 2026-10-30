<?php

namespace Tests\Feature;

use App\Livewire\Platform\DashboardPreview;
use App\Models\Firm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pins the copy and layout decisions taken after the first live deploy
 * (30 Oct 2026), when a real operator read the console and asked:
 *
 *  - "Practice Scope" — what is that?  -> it is the user's Partner Unit.
 *  - "Registered practice tenants / Dedicated practice nodes" — what are
 *    those? They were marketing words for plain counts.
 *  - "00", "01" on every KPI — that reads like a design mock-up, not a
 *    live ERP.
 *
 * These are cosmetic-looking changes, so they are easy to undo by accident
 * during a later rewrite. The assertions below make that visible.
 */
class ConsoleCopyAndResponsiveTest extends TestCase
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
            'seats'      => 10,
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
            'name'         => 'Zubair Hossain Siddique',
            'email'        => 'zubair.student@rahmananis.com',
            'password'     => Hash::make('Secret123!'),
            'role'         => 'student',
            'partner_unit' => 'Partner Unit 01',
        ]);
    }

    // ─── 1. The sidebar identity card ─────────────────────────────────────

    public function test_workspace_sidebar_names_the_users_unit_not_a_scope(): void
    {
        $this->actingAs($this->partner);

        $this->get('/c/rac/dashboard')
            ->assertOk()
            ->assertSee('Your Unit')
            ->assertSee('Partner Unit 01')
            ->assertDontSee('Practice Scope');
    }

    // ─── 2. The platform console speaks plainly ───────────────────────────

    public function test_platform_overview_uses_plain_operational_labels(): void
    {
        $this->get('/platform/dashboard-preview')
            ->assertOk()
            ->assertSee('Firms')
            ->assertSee('Partner Units')
            ->assertSee('Seats')
            ->assertSee('Pending Setup')
            ->assertDontSee('Registered practice tenants')
            ->assertDontSee('Dedicated practice nodes')
            ->assertDontSee('Authorized user licenses')
            ->assertDontSee('Tenants awaiting setup');
    }

    public function test_platform_overview_drops_the_cluster_branding(): void
    {
        $this->get('/platform/dashboard-preview')
            ->assertOk()
            ->assertDontSee('CLUSTER 01')
            ->assertDontSee('Cluster 01')
            ->assertSee('ALL SYSTEMS OPERATIONAL')
            ->assertSee('Manage every firm from here.');
    }

    public function test_platform_kpis_are_numbers_not_zero_padded_strings(): void
    {
        Livewire::test(DashboardPreview::class)
            ->assertSet('section', 'overview')
            ->tap(function ($component) {
                $metrics = $component->viewData('metrics');

                $this->assertIsInt($metrics['firms']);
                $this->assertIsInt($metrics['workspaces']);
                $this->assertIsInt($metrics['seats']);
                $this->assertIsInt($metrics['pending']);

                // "00" was the old rendering of an empty platform.
                $this->assertSame('1', (string) $metrics['firms']);
            });
    }

    public function test_seat_metric_reports_usage_as_well_as_allocation(): void
    {
        Livewire::test(DashboardPreview::class)
            ->tap(function ($component) {
                $detail = $component->viewData('metrics')['seatDetail'];

                // 2 accounts exist against 10 allocated seats.
                $this->assertStringContainsString('2 in use of 10 allocated', $detail);
            });
    }

    // ─── 3. Small screens ─────────────────────────────────────────────────

    public function test_calendar_grid_is_driven_by_classes_not_a_fixed_inline_grid(): void
    {
        $this->actingAs($this->partner);

        $response = $this->get('/c/rac/dashboard');

        $response->assertOk();

        // The inline `style="display: grid; grid-template-columns: repeat(7…"`
        // made every cell ~41px on a 360px phone with no way to respond to a
        // breakpoint. The class-based grid can shrink its gaps and cells.
        $this->assertStringNotContainsString(
            'style="display: grid; grid-template-columns',
            $response->getContent(),
            'The attendance calendar must use responsive classes, not an inline fixed 7-column grid.'
        );

        $this->assertStringContainsString('grid-cols-7', $response->getContent());
    }
}
