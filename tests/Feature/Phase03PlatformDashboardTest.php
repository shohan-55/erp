<?php

namespace Tests\Feature;

use App\Livewire\Platform\DashboardPreview;
use App\Models\Firm;
use App\Models\User;
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

        $this->assertNull(\App\Services\FirmRegistry::get('global-audit'));
        $this->assertFalse(\App\Services\FirmRegistry::has('global-audit'));
    }

    public function test_when_all_firms_deleted_registry_returns_empty_and_dashboard_shows_no_ghost_firms(): void
    {
        $this->artisan('migrate');
        $this->seed();
        $admin = User::where('role', 'platform_admin')->first();
        $this->actingAs($admin);

        // Delete all firms in the database
        $firms = Firm::all();
        foreach ($firms as $firm) {
            Livewire::test(DashboardPreview::class)
                ->call('deleteOrganization', $firm->slug);
        }

        $this->assertSame(0, Firm::count());
        $this->assertSame([], \App\Services\FirmRegistry::all());

        // Refresh dashboard component and assert demoFirms is empty
        $component = Livewire::test(DashboardPreview::class);
        $this->assertEmpty($component->get('demoFirms'));
    }

    public function test_secure_deletion_modal_requires_confirm_keyword_to_permanently_delete_firm(): void
    {
        $this->artisan('migrate');
        $this->seed();

        $firm = Firm::first();
        $this->assertNotNull($firm);

        Livewire::test(DashboardPreview::class)
            ->call('openDeleteModal', $firm->slug)
            ->assertSet('showDeleteModal', true)
            ->assertSet('firmToDeleteSlug', $firm->slug)
            ->assertSee('Delete Organization')
            ->assertSee('To proceed, please type')
            ->assertSee('confirm')
            // Trying with wrong confirmation string fails
            ->set('deleteConfirmationInput', 'wrong')
            ->call('confirmDeleteOrganization')
            ->assertSet('showDeleteModal', true);

        // Firm still exists
        $this->assertDatabaseHas('firms', ['id' => $firm->id]);

        // Trying with 'confirm' succeeds
        Livewire::test(DashboardPreview::class)
            ->call('openDeleteModal', $firm->slug)
            ->set('deleteConfirmationInput', 'confirm')
            ->call('confirmDeleteOrganization')
            ->assertSet('showDeleteModal', false);

        // Firm permanently deleted
        $this->assertDatabaseMissing('firms', ['id' => $firm->id]);
    }

    public function test_firm_deletion_handles_tables_safely_without_crashing_on_missing_columns(): void
    {
        $this->artisan('migrate');
        $this->seed();

        $firm = Firm::create([
            'name'       => 'Safe Delete Test Firm',
            'slug'       => 'safe-delete-firm',
            'kind'       => 'Chartered Accountants',
            'status'     => 'active',
            'workspaces' => 1,
            'seats'      => 10,
            'plan'       => 'Practice',
            'region'     => 'Dhaka',
            'initials'   => 'SD',
        ]);

        $user = User::create([
            'name'              => 'Firm Student',
            'email'             => 'student.safedelete@dhakawala.com',
            'password'          => bcrypt('secret123'),
            'firm_id'           => $firm->id,
            'role'              => 'student',
            'student_code'      => 'S-SD-1',
            'monthly_allowance' => 8000.00,
        ]);

        // Create leave request and conveyance claim if tables exist
        if (\Illuminate\Support\Facades\Schema::hasTable('leave_requests')) {
            \Illuminate\Support\Facades\DB::table('leave_requests')->insert([
                'firm_id'    => $firm->id,
                'user_id'    => $user->id,
                'leave_type' => 'annual',
                'start_date' => now()->toDateString(),
                'end_date'   => now()->addDays(2)->toDateString(),
                'reason'     => 'Test leave',
                'status'     => 'pending_manager',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('conveyance_claims')) {
            \Illuminate\Support\Facades\DB::table('conveyance_claims')->insert([
                'firm_id'         => $firm->id,
                'user_id'         => $user->id,
                'claim_date'      => now()->toDateString(),
                'amount'          => 150.00,
                'route'           => 'Office -> Client',
                'purpose'         => 'Audit field work',
                'status'          => 'pending_manager',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        // Test deletion with confirmation
        Livewire::test(DashboardPreview::class)
            ->call('openDeleteModal', $firm->slug)
            ->set('deleteConfirmationInput', 'confirm')
            ->call('confirmDeleteOrganization')
            ->assertSet('showDeleteModal', false)
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('firms', ['id' => $firm->id]);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_create_organization_succeeds_even_when_orphaned_partner_email_exists(): void
    {
        $this->artisan('migrate');

        // Create an old firm, assign user, then delete firm with foreign keys off to simulate orphaned user
        $oldFirm = Firm::create([
            'name'       => 'Old Deleted Firm',
            'slug'       => 'old-deleted-firm',
            'kind'       => 'Chartered Accountants',
            'status'     => 'active',
            'workspaces' => 1,
            'seats'      => 10,
            'plan'       => 'Practice',
            'region'     => 'Dhaka',
            'initials'   => 'OD',
        ]);

        $orphan = User::create([
            'name'     => 'Old Orphan User',
            'email'    => 'shohan.orphan@example.com',
            'password' => bcrypt('oldpass'),
            'role'     => 'partner',
            'firm_id'  => $oldFirm->id,
        ]);

        \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = OFF;');
        $oldFirm->delete();
        \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = ON;');

        Livewire::test(DashboardPreview::class)
            ->set('newFirmName', 'A. Qasem & Co.')
            ->set('newFirmSlug', 'aqasem-resilient')
            ->set('newPartnerEmail', 'shohan.orphan@example.com')
            ->set('newPartnerName', 'Ramendra Nath Kar FCA')
            ->set('newPartnerPassword', 'SecurePass2026!')
            ->set('newFirmPlan', 'Starter')
            ->set('newFirmSeats', 20)
            ->call('createOrganization')
            ->assertSet('showProvisionPreview', false)
            ->assertSet('modalError', null)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('firms', ['slug' => 'aqasem-resilient']);
        $this->assertDatabaseHas('users', [
            'email' => 'shohan.orphan@example.com',
            'name'  => 'Ramendra Nath Kar FCA',
            'role'  => 'partner',
        ]);
    }

    public function test_create_organization_cleans_up_dangling_zero_user_firm_from_failed_attempt(): void
    {
        $this->artisan('migrate');

        // Create a dangling firm with 0 users
        $dangling = Firm::create([
            'name'       => 'Dangling Firm',
            'slug'       => 'dangling-firm',
            'kind'       => 'Chartered Accountants',
            'status'     => 'active',
            'workspaces' => 1,
            'seats'      => 10,
            'plan'       => 'Practice',
            'region'     => 'Dhaka',
            'initials'   => 'DF',
        ]);

        Livewire::test(DashboardPreview::class)
            ->set('newFirmName', 'Dangling Firm Recovered')
            ->set('newFirmSlug', 'dangling-firm')
            ->set('newPartnerEmail', 'partner.recovered@example.com')
            ->set('newPartnerName', 'Recovered Partner')
            ->set('newPartnerPassword', 'Partner@2026')
            ->call('createOrganization')
            ->assertSet('showProvisionPreview', false)
            ->assertSet('modalError', null);

        $this->assertDatabaseHas('firms', ['slug' => 'dangling-firm', 'name' => 'Dangling Firm Recovered']);
        $this->assertDatabaseHas('users', ['email' => 'partner.recovered@example.com']);
    }
}


