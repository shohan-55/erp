<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Livewire\Auth\FirmLogin;
use App\Livewire\Auth\PlatformLogin;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\AccountActivation;

class Phase02AuthenticationTest extends TestCase
{
    /**
     * Test CA Firm login page renders with official RA&C branding.
     */
    public function test_firm_login_page_renders_with_rac_branding(): void
    {
        $response = $this->get('/c/rac/login');

        $response->assertStatus(200);
        $response->assertSee('Rahman Anis & Co.');
        $response->assertSee('Chartered Accountants');
        $response->assertSee('Your Practice.');
        $response->assertSee('One Connected Workspace.');
        $response->assertSee('Sign In to Your Account');
        $response->assertSee('images/rac_logo.svg');
    }

    /**
     * Test unknown firm slug displays graceful unavailable-firm state.
     */
    public function test_unknown_firm_slug_shows_unavailable_state(): void
    {
        $response = $this->get('/c/unknown-firm-xyz/login');

        $response->assertStatus(200);
        $response->assertSee('Firm Node Not Found');
        $response->assertSee('Tenant Workspace Unavailable');
        $response->assertSee('/c/unknown-firm-xyz');
    }

    /**
     * Test Platform Super Admin login page renders with DhakaWala platform branding.
     */
    public function test_platform_login_page_renders_with_platform_branding(): void
    {
        $response = $this->get('/platform/login');

        $response->assertStatus(200);
        $response->assertSee('ERP SaaS Platform');
        $response->assertSee('Powered by DhakaWala');
        $response->assertSee('Platform Administration');
        $response->assertSee('Privacy-First Architecture');
    }

    /**
     * Test Account Recovery (Forgot Password) renders for both Firm and Platform.
     */
    public function test_forgot_password_pages_render_successfully(): void
    {
        $firmResponse = $this->get('/c/rac/forgot-password');
        $firmResponse->assertStatus(200);
        $firmResponse->assertSee('Forgot your password?');
        $firmResponse->assertSee('Rahman Anis & Co.');

        $platformResponse = $this->get('/platform/forgot-password');
        $platformResponse->assertStatus(200);
        $platformResponse->assertSee('Forgot your password?');
        $platformResponse->assertSee('DhakaWala ERP Platform');
    }

    /**
     * Test Reset Password UI preview renders for both Firm and Platform.
     */
    public function test_reset_password_pages_render_successfully(): void
    {
        $firmResponse = $this->get('/c/rac/reset-password');
        $firmResponse->assertStatus(200);
        $firmResponse->assertSee('Set a New Password');
        $firmResponse->assertSee('Rahman Anis & Co.');

        $platformResponse = $this->get('/platform/reset-password');
        $platformResponse->assertStatus(200);
        $platformResponse->assertSee('Set a New Password');
        $platformResponse->assertSee('DhakaWala ERP Platform');
    }

    /**
     * Test First-Time Account Activation invitation page renders for invited member.
     */
    public function test_account_activation_page_renders_successfully(): void
    {
        $response = $this->get('/c/rac/activate');

        $response->assertStatus(200);
        $response->assertSee('Activate Your Practice Account');
        $response->assertSee('Farhan Tanvir');
        $response->assertSee('Articled Student');
        $response->assertSee('Rahman Anis & Co.');
    }

    /**
     * Test FirmLogin Livewire interactive operations.
     */
    public function test_firm_login_livewire_component_interactions(): void
    {
        $component = Livewire::test(FirmLogin::class, ['firmSlug' => 'rac'])
            ->assertSet('showPassword', false)
            ->call('togglePassword')
            ->assertSet('showPassword', true)
            // Test demo quick-fill (verifying no hardcoded password is populated)
            ->call('fillDemoCredentials', 'partner')
            ->assertSet('email', 'mohitul.haq@rahmananis.com')
            ->assertSet('password', '')
            ->assertDispatched('toast')
            // Test empty validation
            ->set('email', '')
            ->set('password', '')
            ->call('submitLogin')
            ->assertSet('validationError', 'Please enter your registered practice email or User ID.')
            // Test valid submission simulation for designated persona
            ->set('email', 'mohitul.haq@rahmananis.com')
            ->set('password', 'AnyDesignPreviewPass123!')
            ->call('submitLogin')
            ->assertSet('validationError', null)
            ->assertDispatched('toast');

        $this->assertNotNull($component->get('simulatedSuccessMessage'));

        // Test error simulation
        $component->call('triggerErrorSimulation');
        $this->assertNotNull($component->get('validationError'));
    }

    /**
     * Test PlatformLogin Livewire interactive operations.
     */
    public function test_platform_login_livewire_component_interactions(): void
    {
        $component = Livewire::test(PlatformLogin::class)
            ->assertSet('showPassword', false)
            ->call('togglePassword')
            ->assertSet('showPassword', true)
            ->call('fillPlatformCredentials')
            ->assertSet('email', 'admin@dhakawala.com')
            ->assertSet('password', '')
            ->assertDispatched('toast')
            ->set('password', 'AnyPreviewPass123!')
            ->call('submitLogin')
            ->assertDispatched('toast');

        $this->assertNotNull($component->get('simulatedSuccessMessage'));
    }

    /**
     * Test arbitrary credentials do not authenticate or claim success on firm login.
     */
    public function test_arbitrary_credentials_do_not_authenticate_on_firm_login(): void
    {
        $component = Livewire::test(FirmLogin::class, ['firmSlug' => 'rac'])
            ->set('email', 'unauthorized.user@example.com')
            ->set('password', 'ArbitraryPass123!')
            ->call('submitLogin');

        $this->assertNull($component->get('simulatedSuccessMessage'));
        $this->assertStringContainsString('Arbitrary credentials cannot be verified or authenticated', $component->get('validationError'));
    }

    /**
     * Test arbitrary credentials do not authenticate on platform login.
     */
    public function test_arbitrary_credentials_do_not_authenticate_on_platform_login(): void
    {
        $component = Livewire::test(PlatformLogin::class)
            ->set('email', 'unknown.operator@external.org')
            ->set('password', 'SomePass123!')
            ->call('submitLogin');

        $this->assertNull($component->get('simulatedSuccessMessage'));
        $this->assertStringContainsString('Arbitrary credentials cannot be verified or authenticated', $component->get('validationError'));
    }

    /**
     * Test unknown firm slug consistently shows unavailable state across all 4 firm routes.
     */
    public function test_unknown_firm_slug_shows_unavailable_state_across_all_routes(): void
    {
        $this->get('/c/unknown-slug/login')
            ->assertStatus(200)
            ->assertSee('Firm Node Not Found')
            ->assertSee('Tenant Workspace Unavailable');

        $this->get('/c/unknown-slug/forgot-password')
            ->assertStatus(200)
            ->assertSee('Firm Node Not Found')
            ->assertSee('Tenant Workspace Unavailable');

        $this->get('/c/unknown-slug/reset-password')
            ->assertStatus(200)
            ->assertSee('Firm Node Not Found')
            ->assertSee('Tenant Workspace Unavailable');

        $this->get('/c/unknown-slug/activate')
            ->assertStatus(200)
            ->assertSee('Firm Node Not Found')
            ->assertSee('Tenant Workspace Unavailable');
    }

    /**
     * Test ForgotPassword Livewire privacy-first submission.
     */
    public function test_forgot_password_livewire_privacy_submission(): void
    {
        Livewire::test(ForgotPassword::class, ['firmSlug' => 'rac'])
            ->assertSet('submitted', false)
            ->set('email', 'mohitul.haq@rahmananis.com')
            ->call('submitRecovery')
            ->assertSet('submitted', true)
            ->assertDispatched('toast')
            ->call('resetForm')
            ->assertSet('submitted', false);
    }

    /**
     * Test ResetPassword Livewire state switching and live requirements checking.
     */
    public function test_reset_password_livewire_state_switching(): void
    {
        $component = Livewire::test(ResetPassword::class, ['firmSlug' => 'rac'])
            ->assertSet('previewState', 'form')
            ->call('setPreviewState', 'expired')
            ->assertSet('previewState', 'expired')
            ->call('setPreviewState', 'completed')
            ->assertSet('previewState', 'completed')
            ->call('setPreviewState', 'mismatch')
            ->assertSet('previewState', 'mismatch');

        $this->assertNotNull($component->get('validationError'));

        // Test password checklist logic
        $component->call('setPreviewState', 'form')
            ->set('password', 'Simple');
        $this->assertFalse($component->get('hasMinLength'));

        $component->set('password', 'SecurePass123!');
        $this->assertTrue($component->get('hasMinLength'));
        $this->assertTrue($component->get('hasUppercase'));
        $this->assertTrue($component->get('hasNumber'));
        $this->assertTrue($component->get('hasSpecialChar'));
    }

    /**
     * Test AccountActivation Livewire state switching and validation.
     */
    public function test_account_activation_livewire_state_switching(): void
    {
        Livewire::test(AccountActivation::class, ['firmSlug' => 'rac'])
            ->assertSet('previewState', 'valid')
            ->call('setPreviewState', 'expired')
            ->assertSet('previewState', 'expired')
            ->call('setPreviewState', 'used')
            ->assertSet('previewState', 'used')
            ->call('setPreviewState', 'completed')
            ->assertSet('previewState', 'completed')
            ->call('setPreviewState', 'valid')
            ->assertSet('previewState', 'valid');
    }
}
