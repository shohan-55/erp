<?php

namespace Tests\Feature;

use App\Livewire\Platform\DashboardPreview;
use Livewire\Livewire;
use Tests\TestCase;

class Phase03PlatformDashboardTest extends TestCase
{
    public function test_platform_dashboard_route_renders(): void
    {
        $this->get('/platform/dashboard-preview')
            ->assertOk()
            ->assertSee('DhakaWala')
            ->assertSee('CENTRAL OPERATIONS');
    }

    public function test_original_platform_login_and_firm_login_still_render(): void
    {
        $this->get('/platform/login')->assertOk()->assertSee('Platform Administration');
        $this->get('/c/rac/login')->assertOk()->assertSee('Rahman Anis');
        $this->get('/')->assertOk()->assertSee('DhakaWala')->assertSee('Rahman Anis');
    }

    public function test_navigation_changes_only_between_known_preview_sections(): void
    {
        Livewire::test(DashboardPreview::class)
            ->assertSet('section', 'overview')
            ->call('setSection', 'firms')
            ->assertSet('section', 'firms')
            ->assertSee('Organizations')
            ->call('setSection', 'support')
            ->assertSet('section', 'support')
            ->assertSee('Access requires permission.')
            ->call('setSection', 'platform')
            ->assertSet('section', 'platform')
            ->assertSee('Not connected')
            ->call('setSection', 'unauthorized-section')
            ->assertSet('section', 'platform');
    }

    public function test_directory_search_and_status_filters_use_only_demo_metadata(): void
    {
        Livewire::test(DashboardPreview::class)
            ->call('setSection', 'firms')
            ->set('firmSearch', 'alpha')
            ->assertSee('Sample Practice Alpha')
            ->set('firmSearch', 'xyz-no-match')
            ->assertSee('No demo organizations match those filters.')
            ->set('firmSearch', '')
            ->set('statusFilter', 'preview-pending')
            ->assertSee('Sample Practice Beta')
            ->assertSet('statusFilter', 'preview-pending');
    }

    public function test_unknown_organization_ids_cannot_open_details(): void
    {
        Livewire::test(DashboardPreview::class)
            ->call('selectFirm', 'unknown')
            ->assertSet('selectedFirmId', null)
            ->call('selectFirm', 'sample-rac')
            ->assertSet('selectedFirmId', 'sample-rac')
            ->assertSee('Metadata Only')
            ->assertSee('no student records')
            ->call('closeFirmDetail')
            ->assertSet('selectedFirmId', null);
    }

    public function test_new_firm_action_is_preview_only_and_creates_nothing(): void
    {
        Livewire::test(DashboardPreview::class)
            ->call('openProvisionPreview')
            ->assertSet('showProvisionPreview', true)
            ->assertSee('No tenant can be created in Phase 03')
            ->call('acknowledgePreview', 'provision')
            ->assertSet('showProvisionPreview', false)
            ->assertSee('No organization was created or provisioned.');
    }

    public function test_support_request_ui_never_grants_access(): void
    {
        Livewire::test(DashboardPreview::class)
            ->call('setSection', 'support')
            ->assertSee('Firm approval')
            ->call('openSupportPreview')
            ->assertSet('showSupportPreview', true)
            ->assertSee('No access request will be sent')
            ->call('acknowledgePreview', 'support')
            ->assertSet('showSupportPreview', false)
            ->assertSee('No support access was granted, requested, or logged.');
    }

    public function test_invalid_preview_acknowledgement_is_ignored(): void
    {
        Livewire::test(DashboardPreview::class)
            ->call('acknowledgePreview', 'grant-admin-access')
            ->assertSet('previewNotice', null);
    }

    public function test_real_organization_provisioning_creates_firm_and_partner_user(): void
    {
        $this->artisan('migrate');

        Livewire::test(DashboardPreview::class)
            ->call('openProvisionPreview')
            ->assertSet('showProvisionPreview', true)
            ->set('newFirmName', 'Apex Accounting & Co.')
            ->set('newFirmSlug', 'apex-ac')
            ->set('newFirmKind', 'Chartered Accountants')
            ->set('newPartnerName', 'Kamrul Islam, FCA')
            ->set('newPartnerEmail', 'kamrul@apex.com')
            ->set('newPartnerPassword', 'ApexSecret123!')
            ->set('newFirmPlan', 'Practice')
            ->set('newFirmSeats', 15)
            ->set('newFirmRegion', 'Dhaka')
            ->call('createOrganization')
            ->assertHasNoErrors()
            ->assertSet('showProvisionPreview', false)
            ->assertSee("Organization 'Apex Accounting & Co.' created successfully");

        $this->assertDatabaseHas('firms', [
            'slug' => 'apex-ac',
            'name' => 'Apex Accounting & Co.',
            'seats' => 15,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'kamrul@apex.com',
            'role' => 'partner',
        ]);

        // Now test deleting the firm
        Livewire::test(DashboardPreview::class)
            ->call('backupAndDeleteFirm', 'apex-ac');

        $this->assertDatabaseMissing('firms', [
            'slug' => 'apex-ac',
        ]);
    }

    public function test_organization_can_be_provisioned_with_custom_domain_and_subdomain(): void
    {
        $this->artisan('migrate');

        Livewire::test(DashboardPreview::class)
            ->call('openProvisionPreview')
            ->set('newFirmName', 'Global Audit Partners')
            ->set('newFirmSlug', 'global-audit')
            ->set('newPartnerEmail', 'info@globalaudit.com')
            ->set('newFirmCustomDomain', 'erp.globalaudit.com')
            ->set('newFirmCustomSubdomain', 'global')
            ->call('createOrganization')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('firms', [
            'slug' => 'global-audit',
            'custom_domain' => 'erp.globalaudit.com',
            'custom_subdomain' => 'global',
        ]);

        // Test updating domain settings
        Livewire::test(DashboardPreview::class)
            ->set('editCustomDomain', 'portal.globalaudit.com')
            ->set('editCustomSubdomain', 'global-portal')
            ->call('updateDomainSettings', 'global-audit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('firms', [
            'slug' => 'global-audit',
            'custom_domain' => 'portal.globalaudit.com',
            'custom_subdomain' => 'global-portal',
        ]);

        // Test direct permanent deletion
        Livewire::test(DashboardPreview::class)
            ->call('deleteOrganization', 'global-audit');

        $this->assertDatabaseMissing('firms', [
            'slug' => 'global-audit',
        ]);
    }
}

