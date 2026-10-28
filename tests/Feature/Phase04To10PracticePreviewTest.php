<?php

namespace Tests\Feature;

use App\Livewire\Practice\PracticePreview;
use Livewire\Livewire;
use Tests\TestCase;

class Phase04To10PracticePreviewTest extends TestCase
{
    public function test_all_planned_practice_roles_render_as_ui_previews(): void
    {
        foreach (['partner', 'manager', 'student'] as $role) {
            $this->get("/c/rac/preview/{$role}")
                ->assertOk()
                ->assertSee('Powered by DhakaWala ERP')
                ->assertSee('Rahman Anis &amp; Co.', false);
        }
    }

    public function test_firm_does_not_exist_preview_renders_safe_fallback(): void
    {
        $this->get('/c/nonexistent/preview/partner')
            ->assertOk()
            ->assertSee('Tenant Workspace Unavailable')
            ->assertSee('Firm Node Not Found');
    }

    public function test_all_module_urls_load_without_backend_database(): void
    {
        foreach (['dashboard', 'attendance', 'leave', 'conveyance', 'payroll', 'notifications', 'policies', 'settings'] as $section) {
            $this->get("/c/rac/preview/partner/{$section}")
                ->assertOk()
                ->assertSee('Powered by DhakaWala ERP');
        }
    }

    public function test_student_and_manager_views_are_scoped_in_sample_presentation(): void
    {
        Livewire::test(PracticePreview::class, ['firmSlug'=>'rac', 'previewRole'=>'student', 'section'=>'attendance'])
            ->assertSee('Sample Student 01')
            ->assertDontSee('Sample Student 02')
            ->assertDontSee('Sample Student 03');

        Livewire::test(PracticePreview::class, ['firmSlug'=>'rac', 'previewRole'=>'manager', 'section'=>'attendance'])
            ->assertSee('Sample Student 01')
            ->assertSee('Sample Student 02')
            ->assertDontSee('Sample Student 03');

        Livewire::test(PracticePreview::class, ['firmSlug'=>'rac', 'previewRole'=>'partner', 'section'=>'attendance'])
            ->assertSee('Sample Student 04');
    }

    public function test_role_switching_is_visual_only_and_blocks_invalid_values(): void
    {
        Livewire::test(PracticePreview::class, ['firmSlug'=>'rac', 'previewRole'=>'partner'])
            ->call('setPreviewRole', 'student')
            ->assertSet('previewRole', 'student')
            ->assertSet('section', 'dashboard')
            ->assertSee('No authenticated user')
            ->call('setPreviewRole', 'super-admin')
            ->assertSet('previewRole', 'student');
    }

    public function test_only_known_sections_are_selectable(): void
    {
        Livewire::test(PracticePreview::class, ['firmSlug'=>'rac', 'previewRole'=>'manager'])
            ->call('setSection', 'attendance')
            ->assertSet('section', 'attendance')
            ->call('setSection', 'admin')
            ->assertSet('section', 'attendance');
    }

    public function test_student_attendance_is_only_local_state_and_requires_client_site(): void
    {
        Livewire::test(PracticePreview::class, ['firmSlug'=>'rac', 'previewRole'=>'student'])
            ->call('setSection', 'attendance')
            ->set('workMode','client')
            ->set('clientSite','')
            ->call('togglePreviewSession')
            ->assertSet('previewSessionActive', false)
            ->assertSee('Choose a demo client site')
            ->set('clientSite','Sample Audit Client')
            ->call('togglePreviewSession')
            ->assertSet('previewSessionActive', true)
            ->assertSee('No timestamp, geolocation or attendance record was saved')
            ->call('togglePreviewSession')
            ->assertSet('previewSessionActive', false);
    }

    public function test_manager_cannot_simulate_student_self_checkin(): void
    {
        Livewire::test(PracticePreview::class, ['firmSlug'=>'rac', 'previewRole'=>'manager'])
            ->call('togglePreviewSession')
            ->assertSet('previewSessionActive', false)
            ->assertSee('available only in the Student design role');
    }

    public function test_leave_form_validates_without_creating_a_request(): void
    {
        Livewire::test(PracticePreview::class, ['firmSlug'=>'rac', 'previewRole'=>'student'])
            ->call('setSection','leave')
            ->call('submitLeaveDemo')
            ->assertNotSet('error', null)
            ->set('leaveStart','2026-10-16')
            ->set('leaveEnd','2026-10-15')
            ->set('leaveReason','Personal appointment')
            ->call('submitLeaveDemo')
            ->assertNotSet('error', null)
            ->set('leaveEnd','2026-10-17')
            ->call('submitLeaveDemo')
            ->assertSet('error', null)
            ->assertSee('No request was submitted');
    }

    public function test_claim_form_validates_amount_and_does_not_submit_a_payment(): void
    {
        Livewire::test(PracticePreview::class, ['firmSlug'=>'rac', 'previewRole'=>'student'])
            ->call('setSection','conveyance')
            ->set('claimDate','2026-10-15')
            ->set('claimAmount','100000')
            ->set('claimRoute','Office to Client')
            ->set('claimPurpose','Audit engagement travel')
            ->call('submitClaimDemo')
            ->assertNotSet('error', null)
            ->set('claimAmount','200')
            ->call('submitClaimDemo')
            ->assertSet('error', null)
            ->assertSee('No invoice, receipt, reimbursement or payment was recorded');
    }

    public function test_manager_cannot_submit_student_forms(): void
    {
        Livewire::test(PracticePreview::class, ['firmSlug'=>'rac', 'previewRole'=>'manager'])
            ->call('submitLeaveDemo')
            ->assertNotSet('error', null)
            ->call('submitClaimDemo')
            ->assertSee('Only the Student design role');
    }

    public function test_partner_policy_dialog_cannot_save_votes(): void
    {
        Livewire::test(PracticePreview::class, ['firmSlug'=>'rac', 'previewRole'=>'partner'])
            ->call('setSection','policies')
            ->assertSee('All 3 Partners Vote')
            ->call('openDialog','policy')
            ->assertSet('showPolicyModal', true)
            ->call('acknowledgeDialog','policy')
            ->assertSet('showPolicyModal', false)
            ->assertSee('No partner votes, approvals or policy revisions were saved');
    }

    public function test_student_cannot_open_partner_governance_dialog(): void
    {
        Livewire::test(PracticePreview::class, ['firmSlug'=>'rac', 'previewRole'=>'student'])
            ->call('openDialog','policy')
            ->assertSet('showPolicyModal', false)
            ->call('openDialog','claim')
            ->assertSet('showClaimReviewModal', false)
            ->call('openDialog','leave')
            ->assertSet('showLeaveReviewModal', false);
    }

    public function test_leave_and_claim_review_actions_remain_uncommitted_previews(): void
    {
        Livewire::test(PracticePreview::class, ['firmSlug'=>'rac', 'previewRole'=>'manager'])
            ->call('openDialog','leave')
            ->assertSet('showLeaveReviewModal', true)
            ->call('acknowledgeDialog','leave')
            ->assertSee('No leave request, entitlement or approval was changed')
            ->call('openDialog','claim')
            ->assertSet('showClaimReviewModal', true)
            ->call('acknowledgeDialog','claim')
            ->assertSee('No payment or expense approval was recorded');
    }

    public function test_notification_read_is_temporary_and_known_ids_only(): void
    {
        Livewire::test(PracticePreview::class, ['firmSlug'=>'rac', 'previewRole'=>'student'])
            ->call('setSection','notifications')
            ->call('markPreviewRead','demo-note-1')
            ->assertSet('readNotificationIds',['demo-note-1'])
            ->call('markPreviewRead','does-not-exist')
            ->assertSet('readNotificationIds',['demo-note-1']);
    }

    public function test_payroll_and_governance_do_not_claim_real_payments(): void
    {
        $this->get('/c/rac/preview/student/payroll')
            ->assertOk()->assertSee('NOT A PAYSLIP')->assertSee('No real payroll records');
        $this->get('/c/rac/preview/partner/policies')
            ->assertOk()->assertSee('unanimous approval rule')
            ->assertSee('No live votes');
    }

    public function test_attendance_search_and_status_filter_are_scoped(): void
    {
        Livewire::test(PracticePreview::class, ['firmSlug'=>'rac', 'previewRole'=>'manager', 'section'=>'attendance'])
            ->set('search','Sample Student 02')
            ->assertSee('Sample Student 02')
            ->assertDontSee('Sample Student 01')
            ->set('search','')
            ->set('filter','late')
            ->assertSee('Sample Student 02')
            ->assertDontSee('Sample Student 01');
    }

    public function test_client_mutated_role_section_and_filter_are_whitelisted(): void
    {
        Livewire::test(PracticePreview::class, ['firmSlug'=>'rac', 'previewRole'=>'student'])
            ->set('previewRole', 'platform-admin')
            ->assertSet('previewRole', 'student')
            ->set('section', '../auth/firm-login')
            ->assertSet('section', 'dashboard')
            ->set('filter', 'untrusted-status')
            ->assertSet('filter', 'all');
    }

    public function test_preview_acknowledgement_requires_visible_permitted_dialog(): void
    {
        Livewire::test(PracticePreview::class, ['firmSlug'=>'rac', 'previewRole'=>'student'])
            ->call('acknowledgeDialog', 'policy')
            ->assertSet('notice', null)
            ->call('openDialog','correction')
            ->assertSet('showCorrectionModal',false);
    }

    public function test_prior_phase_routes_remain_available(): void
    {
        $this->get('/')->assertOk();
        $this->get('/platform/login')->assertOk();
        $this->get('/platform/dashboard-preview')->assertOk();
        $this->get('/c/rac/login')->assertOk();
        $this->get('/c/rac/forgot-password')->assertOk();
    }
}
