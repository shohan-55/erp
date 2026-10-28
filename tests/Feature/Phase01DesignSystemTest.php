<?php

namespace Tests\Feature;

use App\Livewire\AppShell;
use Livewire\Livewire;
use Tests\TestCase;

class Phase01DesignSystemTest extends TestCase
{
    /**
     * Test that the application portal renders with HTTP 200 and authentic DhakaWala ERP branding.
     */
    public function test_application_portal_boots_with_dhakawala_erp_branding(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('DhakaWala');
        $response->assertSee('ERP');
        $response->assertSee('erp.dhakawala.com');
        $response->assertSee('Rahman Anis', false);
    }

    /**
     * Test that production portal does NOT show any mock sandbox prototype banners.
     */
    public function test_production_portal_does_not_contain_prototype_banners(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertDontSee('Phase 01 UI Foundation Review');
        $response->assertDontSee('Sandbox Safe');
        $response->assertDontSee('non-destructive UI simulations');
    }

    /**
     * Test Livewire AppShell unified portal sign-in and state.
     */
    public function test_livewire_portal_supports_unified_login_validation(): void
    {
        Livewire::test(AppShell::class)
            ->set('email', '')
            ->set('password', '')
            ->call('submitLogin')
            ->assertSet('loginError', 'Please enter your registered email address or User ID.')
            ->set('email', 'admin@dhakawala.com')
            ->set('password', '')
            ->call('submitLogin')
            ->assertSet('loginError', 'Please enter your password.')
            ->set('email', 'admin@dhakawala.com')
            ->set('password', 'password')
            ->call('submitLogin')
            ->assertSet('loginError', null);
    }

    /**
     * Test Livewire AppShell component state and role switching helpers.
     */
    public function test_livewire_app_shell_supports_role_helpers(): void
    {
        Livewire::test(AppShell::class)
            ->assertSet('currentRole', 'partner')
            ->call('setRole', 'manager')
            ->assertSet('currentRole', 'manager')
            ->call('setRole', 'student')
            ->assertSet('currentRole', 'student')
            ->call('setRole', 'admin')
            ->assertSet('currentRole', 'admin');
    }

    /**
     * Test Livewire tab navigation.
     */
    public function test_livewire_app_shell_supports_tab_navigation(): void
    {
        Livewire::test(AppShell::class)
            ->assertSet('activeTab', 'dashboard')
            ->call('setActiveTab', 'components')
            ->assertSet('activeTab', 'components')
            ->call('setActiveTab', 'tokens')
            ->assertSet('activeTab', 'tokens')
            ->call('setActiveTab', 'architecture')
            ->assertSet('activeTab', 'architecture');
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
            ->call('closeModal')
            ->assertSet('isModalOpen', false)
            ->assertSet('isConfirmModalOpen', false)
            ->call('openConfirmModal')
            ->assertSet('isConfirmModalOpen', true)
            ->call('closeConfirmModal')
            ->assertSet('isConfirmModalOpen', false);
    }

    /**
     * Test toast notifications dispatch properly.
     */
    public function test_toasts_dispatch_proper_payload(): void
    {
        Livewire::test(AppShell::class)
            ->call('triggerToast', 'success')
            ->assertDispatched('toast', function ($event, $params) {
                $payload = $params[0] ?? $params;
                return str_contains($payload['title'], 'Sign-off Recorded');
            });
    }
}
