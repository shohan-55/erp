<?php

namespace App\Livewire\Practice;

use App\Models\Attendance;
use App\Models\AuditClient;
use App\Models\AuditLog;
use App\Models\ConveyanceClaim;
use App\Models\Firm;
use App\Models\LeaveRequest;
use App\Models\PartnerPolicy;
use App\Models\PayrollRecord;
use App\Models\PolicyProposal;
use App\Models\PracticeHoliday;
use App\Models\PracticeNotification;
use App\Models\User;
use App\Services\FirmRegistry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class PracticePreview extends Component
{
    use WithFileUploads;
    use Concerns\ManagesPoliciesHolidaysAndSettings;
    use Concerns\ManagesAttendanceCalendarAndRadar;
    use Concerns\ManagesLeaveWorkflow;
    use Concerns\ManagesConveyanceWorkflow;
    use Concerns\ManagesPayrollAndVouchers;
    use Concerns\ManagesTeamAndNotifications;
    use Concerns\ManagesUnitScope;


    public string $firmSlug = 'rac';
    public ?array $firm = null;
    public string $previewRole = 'partner';
    public string $section = 'dashboard';
    public string $search = '';
    public string $period = '2026-10';
    public string $filter = 'all';
    public bool $sidebarOpen = false;
    public bool $showPolicyModal = false;
    public bool $showCorrectionModal = false;
    public bool $showClaimReviewModal = false;
    public bool $showLeaveReviewModal = false;
    public ?string $notice = null;
    public ?string $error = null;

    // Multi-Partner Unit Architecture & Shared Attendance View
    public string $partnerUnit = 'Partner Unit 01';
    public array $availablePartnerUnits = ['Partner Unit 01', 'Partner Unit 02'];
    public bool $canViewAllPartnersAttendance = true;
    public bool $viewAllPartnersAttendance = false;
    public string $attendanceReportStart = '';
    public string $attendanceReportEnd = '';

    // Partner Email Modification & Dynamic Firm Color Palette
    public string $partnerEmail = 'partner@dhakawala.com';
    public string $newPartnerEmail = '';
    public string $firmThemePalette = 'sea_green';
    public string $firmCustomPrimaryColor = '#0D9488';

    // Attendance & Multi-Segment Close vs On-Duty State
    public string $workMode = 'office';
    public bool $previewSessionActive = false;
    public string $sessionState = 'inactive'; // inactive, active, on_duty_transit, closed
    public bool $showCheckoutModeModal = false;
    // Two-step final check-out: choosing "Close" opens a review panel, and the
    // day only ends once the member ticks the confirmation box and confirms.
    // A single click can therefore never end somebody's duty day by accident
    // while they are only travelling between client sites.
    public bool $showFinalCheckoutReview = false;
    public bool $finalCloseConfirmed = false;
    public array $finalCheckoutSummary = [];
    public bool $showMovementTimelineModal = false;
    public ?array $selectedTimelineRow = null;
    public string $clientSite = 'Client Audit Site';
    public ?float $currentLat = null;
    public ?float $currentLng = null;
    public string $currentLocationName = 'EDB Trade Centre, Karwan Bazar, Dhaka';

    // Partner Permission Controls for Manager
    public bool $managerCanProxyAttendance = true;
    public bool $managerCanEditAttendance = true;
    public bool $managerCanApproveLeave = true;
    public bool $managerCanApproveClaims = true;

    // Configurable Partner Policies (Independent per Partner)
    public string $officeStart = '10:00';
    public string $officeEnd = '18:00';
    public int $graceMinutes = 10;
    public float $managerConveyanceLimit = 300.00;
    public int $conveyanceLateDays = 2; // 1-2 working days window
    public bool $latePenaltyEnabled = true;
    public int $lateDaysPerDeduction = 3; // e.g. 3 days late = 1 day deduction
    public float $deductionDaysCount = 1.00;

    // Check-in Celebration Animation Overlay (#UserProposal2)
    public bool $showCheckinCelebration = false;
    public ?array $celebrationData = null;

    // Interactive Monthly Activity Calendar & Daily Inspector (#UserProposal3)
    public string $calendarMonth = '';
    public string $selectedCalendarDate = '';
    public array $calendarDays = [];
    public array $selectedDayActivity = [];
    public array $leaveQuotas = [];
    public string $newLeaveTypeLabel = '';
    public int $newLeaveTypeDays = 10;

    // Weekly off days for this firm (0 = Sunday … 6 = Saturday). NULL in the
    // database means the Dhaka default, Friday + Saturday; a firm that works
    // Saturday and closes Sunday changes it in Workspace Settings.
    public array $weeklyOffDays = Firm::DEFAULT_WEEKLY_OFF_DAYS;
    public array $weeklyOffDayDraft = Firm::DEFAULT_WEEKLY_OFF_DAYS;
    public string $weeklyOffSummary = 'Friday & Saturday';

    // Holiday Calendar & Partner "Office Open on Holiday" Override (#2)
    public array $holidaysList = [];
    public int $holidayYear = 2026;
    public string $newHolidayDate = '';
    public string $newHolidayTitle = '';
    public string $newHolidayCategory = 'government'; // government, sudden, weekend_override
    public bool $newHolidayIsOfficeOpen = false;
    public float $newHolidayAbsentCutDays = 1.00;
    public string $newHolidayOverrideReason = '';

    // Working Day Exceptions — one date whose hours differ from the policy:
    // a hartal that delays opening, a flood that makes lateness meaningless,
    // a half-day that ends at 16:00. Never edits the standing policy, because
    // that would rewrite past days and change approved payroll.
    public string $newExceptionDate = '';
    public string $newExceptionTitle = '';
    public string $newExceptionStart = '';
    public string $newExceptionEnd = '';
    public string $newExceptionGrace = '';
    public bool $newExceptionLateExempt = false;
    public string $newExceptionReason = '';
    public bool $newExceptionAllUnits = true;
    public array $exceptionImpact = [];

    // Resolved for "today" so the dashboard and the attendance page can show a
    // banner without every view recomputing it.
    public array $todayWorkException = [];
    public string $todayWorkHoursLabel = '';

    // Payroll Voucher & Subscription Alert (#3 & #5)
    public string $selectedPaymentMethod = 'bKash / Bank';
    public ?array $subscriptionAlert = null;

    // Self-Service Profile, Password & bKash/Nagad/Bank Account (#1)
    public string $myName = '';
    public string $myEmail = '';
    public string $myPhone = '';
    public $myAvatar = null;
    public ?string $myAvatarUrl = null;
    public string $myPaymentMode = 'bKash';
    public string $myPaymentAccountNo = '';
    public string $myNewPassword = '';
    public string $myNewPasswordConfirm = '';
    public string $myCurrentPassword = '';

    // Optional Conveyance Voucher Preview Modal (#2)
    public bool $showVoucherPreviewModal = false;
    public ?array $previewVoucherClaim = null;

    // Student Missed Check-out Correction Request (#3)
    public string $missedOutTime = '18:00';
    public string $missedOutReason = '';

    // ICAB Articleship Start & Completion Dates (#4)
    public string $newMemberArticleshipStart = '';
    public string $newMemberArticleshipEnd = '';
    public string $editMemberArticleshipStart = '';
    public string $editMemberArticleshipEnd = '';

    // Configurable Student Dashboard Grids (Active/Deactive toggles)
    public array $studentGrids = [
        'attendance_clock'   => true,
        'live_gps_status'    => true,
        'leave_balance'      => true,
        'conveyance_tracker' => true,
        'payroll_allowance'  => true,
        'client_site_duty'   => true,
        'firm_notices'       => true,
    ];

    // Configurable Manager Permissions
    public array $managerPermissions = [
        'proxy_attendance'  => true,
        'edit_attendance'   => true,
        'review_leave'      => true,
        'review_claims'     => true,
        'view_team_reports' => true,
        'manage_policies'   => true,
    ];

    // Manager / Partner Proxy & Edit Attendance Form State
    public string $selectedStudentCode = 'S-101';
    public string $editModeType = 'edit'; // 'edit' or 'proxy'
    public string $editCheckinTime = '10:00';
    public string $editCheckoutTime = '18:00';
    public string $editStatus = 'On time'; // 'On time', 'Late', 'Incomplete'
    public string $editLocationName = 'EDB Trade Centre, Karwan Bazar, Dhaka';
    public string $editRemarks = '';

    // Leave & Multi-Tier Workflow State (with Engaged Manager Mention & Partial Approval)
    public string $leaveType = 'annual';
    public string $leaveStart = '';
    public string $leaveEnd = '';
    public string $leaveReason = '';
    public ?int $leaveEngagedManagerId = null;
    public array $leaveQueue = [];
    public string $selectedLeaveId = 'LR-101';
    public string $leaveReviewRemarks = '';
    public bool $showLeaveSubmitConfirmModal = false;
    public bool $showClaimSubmitConfirmModal = false;
    public int $formResetIteration = 1;
    public bool $showPartialLeaveModal = false;
    public string $partialLeaveId = '';
    public int $partialRequestedDays = 5;
    public int $partialApprovedDays = 2;
    public string $partialApprovedSummary = '';
    public string $partialReviewerRemarks = '';

    // Conveyance Claims & Multi-Row Transport Builder State
    public string $claimDate = '';
    public string $claimAmount = '';
    public string $claimRoute = '';
    public string $claimPurpose = '';
    public string $claimClientName = 'BEXIMCO Textiles Audit';
    public $claimAttachment = null;
    public array $transportRows = [
        ['from' => '', 'to' => '', 'mode' => 'Bus', 'amount' => ''],
    ];
    public bool $hasOtherExpense = false;
    public string $otherExpenseDesc = '';
    public string $otherExpenseAmount = '';
    public bool $showClaimAdjustModal = false;
    public string $adjustTargetClaimId = '';
    public float $adjustNewAmount = 0.00;
    public string $adjustRemarks = '';
    public bool $adjustEscalateToPartner = false;

    public array $auditClients = [
        'BEXIMCO Textiles Audit',
        'Square Pharmaceuticals Audit',
        'BRAC Bank PLC (Motijheel Branch)',
        'ACI Logistics Limited (Shwapno)',
        'General Office / Firm HQ',
    ];
    public string $newClientName = '';
    public string $newClientLocation = '';
    public bool $showAddNewClientModal = false;
    public array $claimQueue = [];
    public string $selectedClaimId = 'CC-201';
    public float $claimAdjustedAmount = 220.00;
    public string $claimAdjustmentRemark = '';
    public string $claimReviewAction = 'approve';

    // Dynamic Payroll & Lifecycle State
    public array $payrollRows = [];
    public string $payrollStatus = 'draft';

    // Notifications, Targeted Broadcast Composer, Login Popup & Audit Logs
    public array $readNotificationIds = [];
    public array $notificationsList = [];
    public ?array $activePopupNotification = null;
    public string $notifTargetMode = 'all'; // 'all', 'single', 'multiple'
    public ?int $notifSingleUserId = null;
    public array $notifSelectedUserIds = [];
    public string $notifTitle = '';
    public string $notifMessage = '';
    public bool $notifShowPopup = true;

    public array $policyProposals = [];
    public string $proposalTitle = '';
    public string $proposalDescription = '';
    public string $proposalEffectiveDate = '';
    public array $auditLogsList = [];

    public array $studentRows = [];

    // Team & Member Roster Management
    public array $teamMembers = [];
    public string $teamFilter = 'all';
    public bool $canManageTeam = false;
    public bool $showAddMemberModal = false;
    public bool $showEditMemberModal = false;
    public bool $showDeleteMemberModal = false;
    public ?int $selectedMemberId = null;
    public string $selectedMemberName = '';
    public string $deleteConfirmationInput = '';

    // Add Member Form Fields
    public string $newMemberRole = 'student';
    public string $newMemberName = '';
    public string $newMemberEmail = '';
    public string $newMemberPassword = '';
    public string $newMemberPartnerUnit = 'Partner Unit 01';
    public string $newMemberStudentCode = '';
    public ?int $newMemberManagerId = null;
    public float $newMemberMonthlyAllowance = 6000.00;
    public bool $newMemberCanManageTeam = false;
    public bool $newMemberCanProxyAttendance = false;
    public bool $newMemberCanEditAttendance = false;
    public bool $newMemberCanApproveLeave = false;
    public bool $newMemberCanApproveClaims = false;

    // Edit Member Form Fields
    public ?int $editMemberId = null;
    public string $editMemberName = '';
    public string $editMemberEmail = '';
    public string $editMemberPhone = '';
    public string $editMemberNewPassword = '';
    public string $editMemberRole = 'student';
    public string $editMemberPartnerUnit = 'Partner Unit 01';
    public string $editMemberStudentCode = '';
    public ?int $editMemberManagerId = null;
    public float $editMemberMonthlyAllowance = 6000.00;
    public bool $editMemberCanManageTeam = false;
    public bool $editMemberCanProxyAttendance = false;
    public bool $editMemberCanEditAttendance = false;
    public bool $editMemberCanApproveLeave = false;
    public bool $editMemberCanApproveClaims = false;

    protected const ROLES = ['partner', 'manager', 'student'];
    protected const SECTIONS = [
        'dashboard', 'attendance', 'leave', 'conveyance', 'payroll',
        'notifications', 'policies', 'settings', 'team',
    ];
    protected const NOTIFICATION_IDS = ['demo-note-1', 'demo-note-2', 'demo-note-3'];

    public function mount(string $firmSlug, string $previewRole = 'partner', string $section = 'dashboard'): void
    {
        Firm::ensureRuntimeSchemaColumns();
        $this->firmSlug = strtolower(trim($firmSlug));
        $this->firm = FirmRegistry::get($this->firmSlug);
        if ($this->firm) {
            $this->firmThemePalette = $this->firm['theme_palette'] ?? 'sea_green';
            $this->firmCustomPrimaryColor = $this->firm['primary_color'] ?? '#0D9488';
        }
        $this->previewRole = in_array($previewRole, self::ROLES, true) ? $previewRole : 'partner';
        $this->section = in_array($section, self::SECTIONS, true) ? $section : 'dashboard';
        $this->attendanceReportStart = now()->startOfMonth()->toDateString();
        $this->attendanceReportEnd = now()->toDateString();
        $this->leaveQuotas = PartnerPolicy::defaultLeaveQuotas();

        $domain = $this->firm['custom_domain'] ?? ($this->firmSlug . '.dhakawala.com');
        $this->partnerEmail = 'partner@' . $domain;

        if (! app()->runningUnitTests()) {
            $this->period = now()->format('Y-m');
            $this->holidayYear = (int) now()->format('Y');
            $this->clientSite = 'General Office / Firm HQ';
            $this->claimClientName = 'General Office / Firm HQ';
            $this->auditClients = ['General Office / Firm HQ'];
        }

        $firmModel = $this->resolveFirmModel();
        if ($firmModel) {
            // Enforced for every render, not just the HTTP middleware path,
            // because Livewire components can also be mounted directly.
            if (! $firmModel->isWorkspaceOperational()) {
                abort(403, $firmModel->is_suspended
                    ? 'This organization workspace is currently suspended by DhakaWala Platform Administration.'
                    : 'This organization subscription has expired. Please contact DhakaWala billing to restore access.');
            }
            $this->canViewAllPartnersAttendance = (bool) ($firmModel->shared_attendance_view ?? true);

            // Weekly holiday pattern: per firm, defaulting to Friday + Saturday
            // for every tenant that has never opened the setting.
            $this->weeklyOffDays = $firmModel->weeklyOffDayIndexes();
            $this->weeklyOffDayDraft = $this->weeklyOffDays;
            $this->weeklyOffSummary = Firm::describeWeeklyOffDays($this->weeklyOffDays);

            try {
                $units = [];
                $wsCount = max(1, (int) ($firmModel->workspaces ?: 1));
                for ($i = 1; $i <= $wsCount; $i++) {
                    $units[] = sprintf('Partner Unit %02d', $i);
                }
                if (Schema::hasTable('users')) {
                    $dbUnits = User::where('firm_id', $firmModel->id)->whereNotNull('partner_unit')->pluck('partner_unit')->unique()->all();
                    foreach ($dbUnits as $du) {
                        if ($du && ! in_array($du, $units, true)) {
                            $units[] = $du;
                        }
                    }
                }
                if (! empty($units)) {
                    $this->availablePartnerUnits = array_values($units);
                }
            } catch (\Throwable) {}

            $payStatus = $firmModel->payment_status ?? 'paid';
            $expiresAt = $firmModel->subscription_expires_at;
            $daysLeft = $expiresAt ? (int) now()->diffInDays($expiresAt, false) : 99;
            if (in_array($payStatus, ['due', 'overdue'], true) || $daysLeft <= 7) {
                $this->subscriptionAlert = [
                    'status'     => strtoupper($payStatus),
                    'fee'        => (float) ($firmModel->subscription_fee ?? 5000.00),
                    'cycle'      => $firmModel->billing_cycle ?? 'Monthly',
                    'expires_at' => $expiresAt?->format('d M Y') ?? 'Soon',
                    'days_left'  => $daysLeft,
                ];
            }
        }

        // Enforce strict tenant isolation & role locking when authenticated
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            // A deactivated (departed) member may still hold a session cookie.
            // is_active exists precisely to retain their payroll history while
            // removing access, so it has to be enforced here too — the preview
            // routes are not behind firm.member.
            if (! $user->isActive()) {
                abort(403, 'This account has been deactivated. Please contact your practice administrator.');
            }

            if ($user->isFirmMember()) {
                if ($user->firm && ! $user->belongsToFirm($this->firmSlug)) {
                    abort(403, 'You do not have access to this practice workspace.');
                }
                if (in_array($user->role, self::ROLES, true)) {
                    $this->previewRole = $user->role;
                }
            }
            if (! empty($user->partner_unit)) {
                $this->partnerUnit = $user->partner_unit;
            }
            if ($user->isPartner()) {
                $this->partnerEmail = $user->email;
            }
            if ($user->isStudent() && $user->manager_id) {
                $this->leaveEngagedManagerId = $user->manager_id;
            }
            $this->myName = $user->name ?? '';
            $this->myEmail = $user->email ?? '';
            $this->myPhone = $user->phone ?? '';
            $this->myAvatarUrl = $user->avatar_url ?? null;
            $this->myPaymentMode = $user->payment_account_mode ?? 'bKash';
            $this->myPaymentAccountNo = $user->payment_account_no ?? '';
            if ($user->isManager() || $user->isPartner()) {
                $this->managerCanProxyAttendance = (bool) $user->can_proxy_attendance;
                $this->managerCanEditAttendance  = (bool) $user->can_edit_attendance;
                $this->managerCanApproveLeave    = (bool) $user->can_approve_leave;
                $this->managerCanApproveClaims   = (bool) $user->can_approve_claims;
            }
        }

        $this->loadPartnerPolicy();
        $this->loadHolidays();
        $this->refreshTodayWorkException();
        $this->loadTeamMembers();
        $this->loadAttendanceRows();
        $this->loadLeaveQueue();
        $this->loadClaimQueue();
        $this->loadAuditClients();
        $this->loadPayrollRows();
        $this->loadNotifications();
        $this->loadPolicyProposals();
        $this->loadAuditLogs();
        $this->calendarMonth = now()->format('Y-m');
        $this->selectedCalendarDate = now()->toDateString();
        $this->loadMonthlyCalendarData();
        $this->canManageTeam = $this->userCanManageTeam();
    }

    public function hydrate(): void
    {
        $this->enforceAuthenticatedRoleLock();
    }

    public function updatedPreviewRole(string $value): void
    {
        if (Auth::check() && ! Auth::user()->isPlatformAdmin()) {
            $this->previewRole = Auth::user()->role;
            return;
        }
        if (! in_array($value, self::ROLES, true)) {
            $this->previewRole = 'student';
        }
    }

    public function updatedPeriod(): void
    {
        $this->loadPayrollRows();
    }

    protected function enforceAuthenticatedRoleLock(): void
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();
            if ($user->isFirmMember() && in_array($user->role, self::ROLES, true)) {
                $this->previewRole = $user->role;
            }
        }
    }

    protected function resolveFirmModel(): ?Firm
    {
        if (! Schema::hasTable('firms')) {
            return null;
        }
        try {
            return Firm::where('slug', $this->firmSlug)->first();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function recordAuditLog(string $module, string $action, string $summary, ?string $targetRef = null, array $metadata = []): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }
        try {
            $firmModel = $this->resolveFirmModel();
            AuditLog::create([
                'firm_id'    => $firmModel?->id ?? (Auth::user()?->firm_id),
                'actor_id'   => Auth::id(),
                'actor_name' => Auth::user()?->name ?? ucfirst($this->previewRole),
                'actor_role' => Auth::user()?->role ?? $this->previewRole,
                'module'     => $module,
                'action'     => $action,
                'target_ref' => $targetRef,
                'summary'    => $summary,
                'metadata'   => $metadata ?: null,
            ]);
            $this->loadAuditLogs();
        } catch (\Throwable) {
        }
    }

    protected function createPracticeNotification(?int $userId, ?string $targetRole, string $title, string $message, string $icon = 'bell', ?array $recipientIds = null, bool $showAsPopup = true): void
    {
        if (! Schema::hasTable('practice_notifications')) {
            return;
        }
        try {
            $firmModel = $this->resolveFirmModel();
            if (! $firmModel) {
                return;
            }
            $payload = [
                'firm_id'     => $firmModel->id,
                'user_id'     => $userId,
                'target_role' => $targetRole,
                'icon'        => $icon,
                'title'       => $title,
                'message'     => $message,
                'is_read'     => false,
            ];
            if (Schema::hasColumn('practice_notifications', 'sender_id')) {
                $payload['sender_id'] = Auth::id();
                $payload['sender_name'] = Auth::user()?->name ?? ucfirst($this->previewRole);
                $payload['recipient_ids'] = $recipientIds;
                $payload['show_as_popup'] = $showAsPopup;
            }
            PracticeNotification::create($payload);
            $this->loadNotifications();
        } catch (\Throwable) {
        }
    }

    public function setSection(string $section): void
    {
        $this->enforceAuthenticatedRoleLock();

        if (! in_array($section, self::SECTIONS, true)) {
            return;
        }

        $this->section = $section;
        $this->sidebarOpen = false;
        $this->search = '';
        $this->filter = 'all';
        $this->closeDialogs();
        $this->error = null;
        $this->notice = null;
    }

    public function setPreviewRole(string $role): void
    {
        if (Auth::check() && ! Auth::user()->isPlatformAdmin()) {
            $this->previewRole = Auth::user()->role;
            $this->error = 'Role switching is disabled for authenticated practice accounts.';
            return;
        }

        if (! in_array($role, self::ROLES, true)) {
            return;
        }

        $this->previewRole = $role;
        $this->section = 'dashboard';
        $this->sidebarOpen = false;
        $this->search = '';
        $this->filter = 'all';
        $this->notice = 'Design preview role changed. Active role: ' . ucfirst($role) . '. No authenticated user session is active.';
        $this->error = null;
        $this->canManageTeam = $this->userCanManageTeam();
        $this->closeDialogs();
    }

    public function openDialog(string $kind, ?string $studentCode = null, string $mode = 'edit'): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->closeDialogs();

        if ($kind === 'correction' && $studentCode !== null) {
            $this->selectedStudentCode = $studentCode;
            $this->editModeType = $mode;
            foreach ($this->studentRows as $r) {
                if ($r['id'] === $studentCode) {
                    $this->editCheckinTime = $r['checkin'] !== '—' ? $r['checkin'] : '10:00';
                    $this->editCheckoutTime = $r['checkout'] !== '—' ? $r['checkout'] : '18:00';
                    $this->editStatus = $r['status'];
                    $this->editLocationName = $r['in_location'] ?? 'Karwan Bazar Office, Dhaka';
                    $this->editRemarks = $r['remarks'] ?? '';
                    break;
                }
            }
        }

        match ($kind) {
            'policy'     => $this->showPolicyModal = $this->previewRole === 'partner',
            'correction' => $this->showCorrectionModal = $this->previewRole !== 'student',
            'claim'      => $this->showClaimReviewModal = $this->previewRole !== 'student',
            'leave'      => $this->showLeaveReviewModal = $this->previewRole !== 'student',
            default      => null,
        };
    }

    public function closeDialogs(): void
    {
        $this->showPolicyModal = false;
        $this->showCorrectionModal = false;
        $this->showClaimReviewModal = false;
        $this->showLeaveReviewModal = false;
        $this->showAddMemberModal = false;
        $this->showEditMemberModal = false;
        $this->showDeleteMemberModal = false;
        $this->showAddNewClientModal = false;
        $this->showCheckoutModeModal = false;
        $this->showFinalCheckoutReview = false;
        $this->finalCloseConfirmed = false;
        $this->finalCheckoutSummary = [];
        $this->showMovementTimelineModal = false;
        $this->showPartialLeaveModal = false;
        $this->showClaimAdjustModal = false;
        $this->showVoucherPreviewModal = false;
        $this->previewVoucherClaim = null;
        $this->selectedTimelineRow = null;
        $this->newClientName = '';
        $this->newClientLocation = '';
        $this->deleteConfirmationInput = '';
        $this->selectedMemberId = null;
        $this->selectedMemberName = '';
    }

    protected function validDate(string $value): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    public function render()
    {
        $this->enforceAuthenticatedRoleLock();

        if (! in_array($this->previewRole, self::ROLES, true)) {
            $this->previewRole = 'student';
        }
        if (! in_array($this->section, self::SECTIONS, true)) {
            $this->section = 'dashboard';
        }
        if (! in_array($this->filter, ['all', 'on-time', 'late', 'late-present', 'incomplete'], true)) {
            $this->filter = 'all';
        }

        if (empty($this->studentRows)) {
            $this->loadAttendanceRows();
        }
        if (empty($this->payrollRows)) {
            $this->loadPayrollRows();
        }
        if (empty($this->notificationsList)) {
            $this->loadNotifications();
        }
        if (empty($this->calendarDays)) {
            $this->loadMonthlyCalendarData();
        }

        $studentRows = $this->studentRows;

        $rows = match ($this->previewRole) {
            'manager' => array_values(array_filter(
                $studentRows, function (array $row) {
                    if (Auth::check()) {
                        if (Auth::user()->isPlatformAdmin()) {
                            return true;
                        }
                        if (Auth::user()->isManager()) {
                            return $row['manager'] === Auth::user()->name || (($row['partner'] ?? '') === $this->partnerUnit && ($row['manager'] ?? '') === 'Direct Report') || ($row['db_id'] ?? null) === Auth::id();
                        }
                    }
                    return $row['manager'] === 'Sample Manager A';
                }
            )),
            'student' => array_values(array_filter(
                $studentRows, function (array $row) {
                    if (Auth::check()) {
                        if (Auth::user()->isPlatformAdmin()) {
                            return true;
                        }
                        if (Auth::user()->isStudent()) {
                            return ($row['db_id'] ?? null) === Auth::id() || (Auth::user()->student_code && $row['id'] === Auth::user()->student_code);
                        }
                    }
                    return $row['id'] === 'S-101';
                }
            )),
            default => $studentRows,
        };

        if ($this->section !== 'team' && trim($this->search) !== '') {
            $query = mb_strtolower(trim($this->search));
            $rows = array_values(array_filter(
                $rows,
                fn (array $row) => str_contains(mb_strtolower($row['name'].' '.$row['id'].' '.$row['mode']), $query)
            ));
        }

        if ($this->filter !== 'all') {
            $rows = array_values(array_filter($rows, function (array $row) {
                $slugStatus = strtolower(str_replace(' ', '-', $row['status'] ?? ''));
                if ($this->filter === 'on-time') {
                    return in_array($slugStatus, ['on-time', 'right-time'], true);
                }
                if ($this->filter === 'late') {
                    return in_array($slugStatus, ['late', 'late-present'], true);
                }
                return $slugStatus === $this->filter;
            }));
        }

        // Filter queues by role and Partner Unit
        $scopedLeaveQueue = $this->previewRole === 'student'
            ? array_values(array_filter($this->leaveQueue, function ($q) {
                if (Auth::check()) {
                    if (Auth::user()->isPlatformAdmin()) {
                        return true;
                    }
                    if (Auth::user()->isStudent()) {
                        return ($q['user_id'] ?? null) === Auth::id() || ($q['student_code'] ?? '') === (Auth::user()->student_code ?? 'S-101');
                    }
                }
                return ($q['student_code'] ?? '') === 'S-101';
            }))
            : ($this->previewRole === 'manager'
                ? array_values(array_filter($this->leaveQueue, function ($q) {
                    if (Auth::check()) {
                        if (Auth::user()->isPlatformAdmin()) {
                            return true;
                        }
                        if (Auth::user()->isManager()) {
                            return ($q['manager'] ?? '') === Auth::user()->name
                                || (int) ($q['engaged_manager_id'] ?? 0) === (int) Auth::id()
                                || ($q['manager'] ?? '') === 'Direct Report'
                                || ($q['partner_unit'] ?? $this->partnerUnit) === $this->partnerUnit;
                        }
                    }
                    return ($q['manager'] ?? '') === 'Sample Manager A';
                }))
                : array_values(array_filter($this->leaveQueue, fn ($q) => ! Auth::check() || Auth::user()->isPlatformAdmin() || empty($q['partner_unit']) || ($q['partner_unit'] ?? $this->partnerUnit) === $this->partnerUnit || count($this->availablePartnerUnits) <= 1)));

        $scopedClaimQueue = $this->previewRole === 'student'
            ? array_values(array_filter($this->claimQueue, function ($q) {
                if (Auth::check()) {
                    if (Auth::user()->isPlatformAdmin()) {
                        return true;
                    }
                    if (Auth::user()->isStudent()) {
                        return ($q['user_id'] ?? null) === Auth::id() || ($q['student_code'] ?? '') === (Auth::user()->student_code ?? 'S-101');
                    }
                }
                return ($q['student_code'] ?? '') === 'S-101';
            }))
            : ($this->previewRole === 'manager'
                ? array_values(array_filter($this->claimQueue, function ($q) {
                    if (Auth::check()) {
                        if (Auth::user()->isPlatformAdmin()) {
                            return true;
                        }
                        if (Auth::user()->isManager()) {
                            return ($q['manager'] ?? '') === Auth::user()->name
                                || ($q['manager'] ?? '') === 'Direct Report'
                                || ($q['partner_unit'] ?? $this->partnerUnit) === $this->partnerUnit;
                        }
                    }
                    return ($q['manager'] ?? '') === 'Sample Manager A';
                }))
                : array_values(array_filter($this->claimQueue, fn ($q) => ! Auth::check() || Auth::user()->isPlatformAdmin() || empty($q['partner_unit']) || ($q['partner_unit'] ?? $this->partnerUnit) === $this->partnerUnit || count($this->availablePartnerUnits) <= 1)));

        // Compute actionable unread/pending items for Manager & Partner notification badges & Dashboard Inbox
        $pendingLeaveItems = $this->previewRole === 'manager'
            ? array_values(array_filter($scopedLeaveQueue, fn ($q) => ($q['status'] ?? '') === 'pending_manager'))
            : ($this->previewRole === 'partner'
                ? array_values(array_filter($scopedLeaveQueue, fn ($q) => in_array($q['status'] ?? '', ['pending_partner', 'pending_manager'], true)))
                : []);

        $pendingClaimItems = $this->previewRole === 'manager'
            ? array_values(array_filter($scopedClaimQueue, fn ($q) => in_array($q['status'] ?? '', ['pending_manager', 'Manager Review'], true)))
            : ($this->previewRole === 'partner'
                ? array_values(array_filter($scopedClaimQueue, fn ($q) => in_array($q['status'] ?? '', ['pending_partner', 'Escalated to Partner', 'Escalated to Partner (Late)', 'pending_manager'], true)))
                : []);

        $pendingCorrectionItems = $this->previewRole !== 'student'
            ? array_values(array_filter($rows, fn ($r) => ! empty($r['correction_requested']) && ($r['correction_status'] ?? 'pending') === 'pending'))
            : [];

        $pendingLeaveCount = count($pendingLeaveItems);
        $pendingClaimCount = count($pendingClaimItems);
        $pendingCorrectionCount = count($pendingCorrectionItems);
        $totalPendingApprovalsCount = $pendingLeaveCount + $pendingClaimCount + $pendingCorrectionCount;

        $scopedPayrollRows = $this->previewRole === 'student'
            ? array_values(array_filter($this->payrollRows, function ($r) {
                if (Auth::check() && Auth::user()->isStudent()) {
                    return ($r['db_id'] ?? null) === Auth::id() || ($r['id'] ?? '') === (Auth::user()->student_code ?? 'S-101');
                }
                return ($r['id'] ?? '') === 'S-101';
            }))
            : ($this->previewRole === 'manager'
                ? array_values(array_filter($this->payrollRows, function ($r) {
                    if (Auth::check() && Auth::user()->isManager()) {
                        return ($r['manager'] ?? '') === Auth::user()->name || (($r['partner_unit'] ?? '') === $this->partnerUnit && ($r['manager'] ?? '') === 'Direct Report');
                    }
                    return ($r['manager'] ?? '') === 'Sample Manager A';
                }))
                : $this->payrollRows);

        // Filter team roster by Partner Unit when authenticated Partner/Manager
        $this->canManageTeam = $this->userCanManageTeam();
        $filteredTeamMembers = Auth::check()
            ? array_values(array_filter($this->teamMembers, fn (array $m) => ($m['partner_unit'] ?? $this->partnerUnit) === $this->partnerUnit))
            : $this->teamMembers;

        if ($this->teamFilter !== 'all') {
            $filteredTeamMembers = array_values(array_filter(
                $filteredTeamMembers,
                fn (array $m) => ($m['role'] ?? '') === $this->teamFilter
            ));
        }

        if ($this->section === 'team' && trim($this->search) !== '') {
            $sq = mb_strtolower(trim($this->search));
            $filteredTeamMembers = array_values(array_filter(
                $filteredTeamMembers,
                fn (array $m) => str_contains(mb_strtolower(($m['name'] ?? '').' '.($m['email'] ?? '').' '.($m['student_code'] ?? '').' '.($m['role'] ?? '')), $sq)
            ));
        }

        return view('livewire.practice.preview', [
            'colorPalettes'              => Firm::colorPalettes(),
            'pendingLeaveItems'          => $pendingLeaveItems,
            'pendingClaimItems'          => $pendingClaimItems,
            'pendingLeaveCount'          => $pendingLeaveCount,
            'pendingClaimCount'          => $pendingClaimCount,
            'pendingCorrectionCount'     => $pendingCorrectionCount,
            'totalPendingApprovalsCount' => $totalPendingApprovalsCount,
            'graceEndFormatted'      => $this->graceEndFormatted,
            'teamRadar'              => $this->teamRadar,
            'clientCostingSummary'   => $this->clientCostingSummary,
            'punctualityLeaderboard' => $this->punctualityLeaderboard,
            'rows'              => $rows,
            'allRows'           => $studentRows,
            'leaveQueue'        => $scopedLeaveQueue,
            'claimQueue'        => $scopedClaimQueue,
            'payrollRows'       => $scopedPayrollRows,
            'payrollStatus'     => $this->payrollStatus,
            'notificationsList' => $this->notificationsList,
            'policyProposals'   => $this->policyProposals,
            'auditLogsList'     => $this->auditLogsList,
            'holidaysList'      => $this->holidaysList,
            'subscriptionAlert' => $this->subscriptionAlert,
            'teamMembers'       => $filteredTeamMembers,
            'allTeamMembers'    => $this->teamMembers,
            'availableManagers' => $this->availableManagers,
            'canManageTeam'     => $this->userCanManageTeam(),
            'studentGrids'      => $this->studentGrids,
            'partnerUnit'       => $this->partnerUnit,
            'roleLabel'         => match ($this->previewRole) {
                'manager' => 'Audit Manager',
                'student' => 'Articled Student',
                default   => 'Partner / Principal',
            },
            'sectionLabel'      => [
                'dashboard'     => 'Overview',
                'attendance'    => 'Attendance',
                'leave'         => 'Leave Management',
                'conveyance'    => 'Conveyance Claims',
                'payroll'       => 'Monthly Allowance & Payroll',
                'notifications' => 'Notifications',
                'policies'      => 'Policy & Governance',
                'settings'      => 'Workspace Settings',
                'team'          => 'Team & Members',
            ][$this->section],
        ])->layout('layouts.practice', [
            'title' => ($this->firm ? $this->firm['name'] : 'Practice Workspace') . ' — ' . ucfirst($this->section) . ' | DhakaWala ERP'
        ]);
    }

}
