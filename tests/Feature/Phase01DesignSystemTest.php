<?php

namespace Tests\Feature;

use App\Livewire\AppShell;
use Livewire\Livewire;
use Tests\TestCase;

class Phase01DesignSystemTest extends TestCase
{
    /**
     * Test that the application shell renders with HTTP 200 and authentic RA&C branding.
     */
    public function test_application_shell_boots_with_rac_branding(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Rahman Anis & Co.');
        $response->assertSee('Chartered Accountants');
        $response->assertSee('erp.dhakawala.com');
    }

    /**
     * Test Livewire AppShell component state and role switching.
     */
    public function test_livewire_app_shell_supports_role_preview_switching(): void
    {
        Livewire::test(AppShell::class)
            ->assertSet('currentRole', 'partner')
            ->assertSee('A.K.M. Mohitul Haq, FCA')
            ->call('setRole', 'manager')
            ->assertSet('currentRole', 'manager')
            ->assertSee('Khandaker Tanvir Ahmed, ACA')
            ->call('setRole', 'student')
            ->assertSet('currentRole', 'student')
            ->assertSee('Zubair Hossain Siddique')
            ->call('setRole', 'admin')
            ->assertSet('currentRole', 'admin')
            ->assertSee('Platform Super Admin');
    }

    /**
     * Test Livewire tab navigation between Phase 01 views.
     */
    public function test_livewire_app_shell_supports_tab_navigation(): void
    {
        Livewire::test(AppShell::class)
            ->assertSet('activeTab', 'dashboard')
            ->call('setActiveTab', 'components')
            ->assertSet('activeTab', 'components')
            ->assertSee('Design System Component Matrix')
            ->call('setActiveTab', 'tokens')
            ->assertSet('activeTab', 'tokens')
            ->assertSee('Official Corporate Color Palette')
            ->call('setActiveTab', 'architecture')
            ->assertSet('activeTab', 'architecture')
            ->assertSee('Multi-Tenant Blueprint');
    }

    /**
     * Test modal dialog toggle states.
     */
    public function test_livewire_modals_toggle_correctly(): void
    {
        Livewire::test(AppShell::class)
            ->assertSet('isModalOpen', false)
            ->call('openModal')
            ->assertSet('isModalOpen', true)
            ->assertSee('[Design Preview] Filter Workspace Engagement')
            ->call('closeModal')
            ->assertSet('isModalOpen', false)
            ->assertSet('isConfirmModalOpen', false)
            ->call('openConfirmModal')
            ->assertSet('isConfirmModalOpen', true)
            ->assertSee('[Design Preview] Confirm Sign-off Simulation')
            ->call('closeConfirmModal')
            ->assertSet('isConfirmModalOpen', false);
    }

    /**
     * Test demo safety banner is present and clearly identifies prototype mode.
     */
    public function test_demo_safety_notice_is_rendered(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Design Prototype');
        $response->assertSee('Phase 01 UI Foundation Review');
        $response->assertSee('Sandbox Safe');
    }

    /**
     * Test simulation toasts dispatch with explicit preview markers.
     */
    public function test_simulation_toasts_dispatch_preview_payload(): void
    {
        Livewire::test(AppShell::class)
            ->call('triggerToast', 'success')
            ->assertDispatched('toast', function ($event, $params) {
                $payload = $params[0] ?? $params;
                return str_contains($payload['title'], '[Preview]') &&
                       str_contains($payload['message'], 'No live financial ledger');
            });
    }
}
