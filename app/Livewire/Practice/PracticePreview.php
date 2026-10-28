<?php

namespace App\Livewire\Practice;

use App\Models\Attendance;
use App\Models\ConveyanceClaim;
use App\Models\Firm;
use App\Models\LeaveRequest;
use App\Models\PartnerPolicy;
use App\Models\User;
use App\Services\FirmRegistry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class PracticePreview extends Component
{
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

    // Multi-Partner Unit Architecture
    public string $partnerUnit = 'Partner Unit 01';
    public array $availablePartnerUnits = ['Partner Unit 01', 'Partner Unit 02'];

    // Partner Email Modification
    public string $partnerEmail = 'partner@dhakawala.com';
    public string $newPartnerEmail = '';

    // Attendance & Live GPS Geolocation state
    public string $workMode = 'office';
    public bool $previewSessionActive = false;
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
    public int $conveyanceLateDays = 3;

    // Configurable Student Dashboard Grids (Active/Deactive toggles)
    public array $studentGrids = [
        'attendance_clock'   => true,
        'live_gps_status'    => true,
        'leave_balance'      => true,
        'conveyance_tracker' => true,
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
    ];

    // Manager / Partner Proxy & Edit Attendance Form State
    public string $selectedStudentCode = 'S-101';
    public string $editModeType = 'edit'; // 'edit' or 'proxy'
    public string $editCheckinTime = '10:00';
    public string $editCheckoutTime = '18:00';
    public string $editStatus = 'On time'; // 'On time', 'Late', 'Incomplete'
    public string $editLocationName = 'EDB Trade Centre, Karwan Bazar, Dhaka';
    public string $editRemarks = '';

    // Leave & Multi-Tier Workflow State
    public string $leaveType = 'annual';
    public string $leaveStart = '';
    public string $leaveEnd = '';
    public string $leaveReason = '';
    public array $leaveQueue = [];
    public string $selectedLeaveId = 'LR-101';
    public string $leaveReviewRemarks = '';

    // Conveyance Claims & Multi-Tier Workflow State
    public string $claimDate = '';
    public string $claimAmount = '';
    public string $claimRoute = '';
    public string $claimPurpose = '';
    public string $claimClientName = 'BEXIMCO Textiles Audit';
    public array $auditClients = [
        'BEXIMCO Textiles Audit',
        'Square Pharmaceuticals Audit',
        'Brac Bank Motijheel Branch',
        'ACI Logistics Site',
        'General Office / Firm HQ',
    ];
    public array $claimQueue = [];
    public string $selectedClaimId = 'CC-201';
    public float $claimAdjustedAmount = 220.00;
    public string $claimAdjustmentRemark = '';
    public string $claimReviewAction = 'approve'; // 'approve', 'escalate', 'reject'

    public array $readNotificationIds = [];
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

    private const ROLES = ['partner', 'manager', 'student'];
    private const SECTIONS = [
        'dashboard', 'attendance', 'leave', 'conveyance', 'payroll',
        'notifications', 'policies', 'settings', 'team',
    ];
    private const NOTIFICATION_IDS = ['demo-note-1', 'demo-note-2', 'demo-note-3'];

    public function mount(string $firmSlug, string $previewRole = 'partner', string $section = 'dashboard'): void
    {
        $this->firmSlug = strtolower(trim($firmSlug));
        $this->firm = FirmRegistry::get($this->firmSlug);
        $this->previewRole = in_array($previewRole, self::ROLES, true) ? $previewRole : 'partner';
        $this->section = in_array($section, self::SECTIONS, true) ? $section : 'dashboard';

        $domain = $this->firm['custom_domain'] ?? ($this->firmSlug . '.dhakawala.com');
        $this->partnerEmail = 'partner@' . $domain;

        // Load authenticated user role and partner unit when present
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();
            if ($user->isFirmMember() && in_array($user->role, self::ROLES, true)) {
                $this->previewRole = $user->role;
            }
            if (! empty($user->partner_unit)) {
                $this->partnerUnit = $user->partner_unit;
            }
            if ($user->isPartner()) {
                $this->partnerEmail = $user->email;
            }
            if ($user->isManager() || $user->isPartner()) {
                $this->managerCanProxyAttendance = (bool) $user->can_proxy_attendance;
                $this->managerCanEditAttendance  = (bool) $user->can_edit_attendance;
                $this->managerCanApproveLeave    = (bool) $user->can_approve_leave;
                $this->managerCanApproveClaims   = (bool) $user->can_approve_claims;
            }
        }

        $this->loadPartnerPolicy();
        $this->loadAttendanceRows();
        $this->loadLeaveQueue();
        $this->loadClaimQueue();
        $this->loadTeamMembers();
        $this->canManageTeam = $this->userCanManageTeam();
    }

    public function setPartnerUnit(string $unit): void
    {
        if (in_array($unit, $this->availablePartnerUnits, true)) {
            $this->partnerUnit = $unit;

            if (Schema::hasTable('firms') && Schema::hasTable('users')) {
                try {
                    $firmModel = Firm::where('slug', $this->firmSlug)->first();
                    if ($firmModel) {
                        $partnerUser = User::where('firm_id', $firmModel->id)
                            ->where('role', 'partner')
                            ->where('partner_unit', $unit)
                            ->first();

                        if ($partnerUser) {
                            $this->partnerEmail = $partnerUser->email;
                        } else {
                            $domain = $firmModel->custom_domain ?: ($firmModel->slug . '.dhakawala.com');
                            $this->partnerEmail = 'partner.' . Str::slug($unit) . '@' . $domain;
                        }
                    }
                } catch (\Throwable) {}
            }

            if ($unit === 'Partner Unit 02') {
                $this->officeStart = '09:30';
                $this->officeEnd = '17:30';
                $this->graceMinutes = 15;
                $this->managerConveyanceLimit = 500.00;
                $this->conveyanceLateDays = 5;
            } else {
                $this->officeStart = '10:00';
                $this->officeEnd = '18:00';
                $this->graceMinutes = 10;
                $this->managerConveyanceLimit = 300.00;
                $this->conveyanceLateDays = 3;
            }

            $this->notice = "Switched to {$unit}. Policy rules and employee scope updated.";
            $this->loadPartnerPolicy();
            $this->loadAttendanceRows();
            $this->loadLeaveQueue();
            $this->loadClaimQueue();
            $this->loadTeamMembers();
        }
    }

    protected function loadPartnerPolicy(): void
    {
        if (Schema::hasTable('partner_policies')) {
            try {
                $firmModel = Firm::where('slug', $this->firmSlug)->first();
                $query = PartnerPolicy::query();
                if ($firmModel) {
                    $query->where('firm_id', $firmModel->id);
                }
                $policy = $query->where('partner_unit', $this->partnerUnit)->first();

                if ($policy) {
                    $this->officeStart = $policy->office_start;
                    $this->officeEnd = $policy->office_end;
                    $this->graceMinutes = $policy->grace_minutes;
                    $this->managerConveyanceLimit = (float) $policy->manager_conveyance_limit;
                    $this->conveyanceLateDays = $policy->conveyance_late_days;
                    if (! empty($policy->student_grids)) {
                        $this->studentGrids = array_merge($this->studentGrids, $policy->student_grids);
                    }
                    if (! empty($policy->manager_permissions)) {
                        $this->managerPermissions = array_merge($this->managerPermissions, $policy->manager_permissions);
                    }
                }
            } catch (\Throwable) {
            }
        }
    }

    public function updatePartnerEmail(): void
    {
        $this->error = null;
        $this->notice = null;

        $target = trim($this->newPartnerEmail);
        if (! filter_var($target, FILTER_VALIDATE_EMAIL)) {
            $this->error = 'Please enter a valid email address.';
            return;
        }

        if (Schema::hasTable('users')) {
            try {
                $exists = User::where('email', $target)->where('email', '!=', $this->partnerEmail)->exists();
                if ($exists) {
                    $this->error = 'This email address is already assigned to another user.';
                    return;
                }
                User::where('email', $this->partnerEmail)->update(['email' => $target]);
            } catch (\Throwable) {
            }
        }

        $old = $this->partnerEmail;
        $this->partnerEmail = $target;
        $this->newPartnerEmail = '';

        $this->dispatch('toast', [
            'type'    => 'success',
            'title'   => 'Partner Email Updated',
            'message' => "Email updated from {$old} to {$target}.",
        ]);

        $this->notice = "Partner email address updated successfully to {$target}.";
    }

    public function savePolicySettings(): void
    {
        if ($this->previewRole !== 'partner') {
            $this->error = 'Only a Partner can modify workspace policy defaults.';
            return;
        }

        if ($this->managerConveyanceLimit < 50 || $this->managerConveyanceLimit > 10000) {
            $this->error = 'Manager daily conveyance limit must be between ৳50 and ৳10,000.';
            return;
        }

        if ($this->conveyanceLateDays < 1 || $this->conveyanceLateDays > 30) {
            $this->error = 'Late submission threshold must be between 1 and 30 days.';
            return;
        }

        if (Schema::hasTable('partner_policies')) {
            try {
                $firmModel = Firm::where('slug', $this->firmSlug)->first();
                if ($firmModel) {
                    $partnerUser = User::where('firm_id', $firmModel->id)->where('role', 'partner')->first();
                    $partnerId = $partnerUser ? $partnerUser->id : (Auth::id() ?? 1);

                    PartnerPolicy::updateOrCreate(
                        [
                            'firm_id'      => $firmModel->id,
                            'partner_unit' => $this->partnerUnit,
                        ],
                        [
                            'partner_id'               => $partnerId,
                            'office_start'             => $this->officeStart,
                            'office_end'               => $this->officeEnd,
                            'grace_minutes'            => $this->graceMinutes,
                            'manager_conveyance_limit' => $this->managerConveyanceLimit,
                            'conveyance_late_days'     => $this->conveyanceLateDays,
                            'student_grids'            => $this->studentGrids,
                            'manager_permissions'      => $this->managerPermissions,
                        ]
                    );
                }
            } catch (\Throwable) {
            }
        }

        $this->dispatch('toast', [
            'type'    => 'success',
            'title'   => 'Policy Saved',
            'message' => "Policy updated: Manager Conveyance Limit ৳{$this->managerConveyanceLimit}, Late Claim window {$this->conveyanceLateDays} days.",
        ]);

        $this->notice = "Policy settings saved for {$this->partnerUnit}.";
    }

    public function toggleStudentGrid(string $gridKey): void
    {
        if ($this->previewRole !== 'partner') {
            $this->error = 'Only a Partner can configure Student dashboard grid visibility.';
            return;
        }

        if (array_key_exists($gridKey, $this->studentGrids)) {
            $this->studentGrids[$gridKey] = ! $this->studentGrids[$gridKey];
            $status = $this->studentGrids[$gridKey] ? 'ACTIVE' : 'DEACTIVATED';
            $this->dispatch('toast', [
                'type'    => 'info',
                'title'   => 'Dashboard Grid Toggled',
                'message' => "Student widget '{$gridKey}' is now {$status}.",
            ]);
            $this->notice = "Student profile grid '{$gridKey}' set to {$status}.";
        }
    }

    public function toggleManagerPermissionKey(string $permKey): void
    {
        if ($this->previewRole !== 'partner') {
            $this->error = 'Only a Partner can modify Manager permissions.';
            return;
        }

        if (array_key_exists($permKey, $this->managerPermissions)) {
            $this->managerPermissions[$permKey] = ! $this->managerPermissions[$permKey];
            $status = $this->managerPermissions[$permKey] ? 'ALLOWED' : 'DISABLED';
            $this->dispatch('toast', [
                'type'    => 'info',
                'title'   => 'Manager Permission Changed',
                'message' => "Manager permission '{$permKey}' is now {$status}.",
            ]);
            $this->notice = "Manager feature '{$permKey}' set to {$status}.";
        }
    }

    protected function loadAttendanceRows(): void
    {
        if (Schema::hasTable('attendances') && Schema::hasTable('users')) {
            $firmModel = Firm::where('slug', $this->firmSlug)->first();

            if ($firmModel) {
                $students = User::where('firm_id', $firmModel->id)
                    ->where('role', 'student')
                    ->with('manager')
                    ->get();

                if ($students->isNotEmpty()) {
                    $todayAttendances = Attendance::where('firm_id', $firmModel->id)
                        ->whereDate('date', now()->toDateString())
                        ->get()
                        ->keyBy('user_id');

                    $this->studentRows = $students->map(function (User $s) use ($todayAttendances) {
                        /** @var Attendance|null $att */
                        $att = $todayAttendances->get($s->id);
                        return [
                            'id'             => $s->student_code ?? ('U-' . $s->id),
                            'db_id'          => $s->id,
                            'name'           => $s->name,
                            'manager'        => $s->manager?->name ?? 'Direct Report',
                            'partner'        => $s->partner_unit ?? $this->partnerUnit,
                            'status'         => $att ? $att->status : 'Incomplete',
                            'mode'           => $att ? ucfirst($att->work_mode) : 'Office',
                            'day'            => now()->format('D'),
                            'checkin'        => $att?->in_time?->format('H:i') ?? '—',
                            'checkout'       => $att?->out_time?->format('H:i') ?? '—',
                            'in_lat'         => $att?->in_lat,
                            'in_lng'         => $att?->in_lng,
                            'in_location'    => $att?->in_location_name ?? '—',
                            'out_lat'        => $att?->out_lat,
                            'out_lng'        => $att?->out_lng,
                            'out_location'   => $att?->out_location_name ?? '—',
                            'is_proxy'       => $att ? (bool) $att->is_proxy : false,
                            'edited_by_role' => $att?->editor?->role,
                            'remarks'        => $att?->edit_remarks ?? '',
                        ];
                    })->toArray();
                    return;
                } else {
                    $this->studentRows = [];
                    return;
                }
            }
        }

        $defaultRows = [
            [
                'id' => 'S-101', 'name' => 'Sample Student 01 (Zubair)', 'manager' => 'Sample Manager A',
                'partner' => 'Partner Unit 01', 'status' => 'On time', 'mode' => 'Office', 'day' => 'Mon',
                'checkin' => '10:05', 'checkout' => '18:00',
                'in_lat' => 23.7516, 'in_lng' => 90.3943, 'in_location' => 'Karwan Bazar Office, Dhaka',
                'out_lat' => 23.7516, 'out_lng' => 90.3943, 'out_location' => 'Karwan Bazar Office, Dhaka',
                'is_proxy' => false, 'edited_by_role' => null, 'remarks' => '',
            ],
            [
                'id' => 'S-102', 'name' => 'Sample Student 02 (Nusrat)', 'manager' => 'Sample Manager A',
                'partner' => 'Partner Unit 01', 'status' => 'Late', 'mode' => 'Client', 'day' => 'Tue',
                'checkin' => '10:14', 'checkout' => '18:15',
                'in_lat' => 23.7279, 'in_lng' => 90.4178, 'in_location' => 'Motijheel Commercial Area, Dhaka',
                'out_lat' => 23.7279, 'out_lng' => 90.4178, 'out_location' => 'Motijheel Commercial Area, Dhaka',
                'is_proxy' => false, 'edited_by_role' => null, 'remarks' => 'Traffic delay at Shahbagh',
            ],
            [
                'id' => 'S-103', 'name' => 'Sample Student 03 (Rakibul)', 'manager' => 'Sample Manager B',
                'partner' => 'Partner Unit 01', 'status' => 'Incomplete', 'mode' => 'Office', 'day' => 'Wed',
                'checkin' => '10:08', 'checkout' => '—',
                'in_lat' => 23.7937, 'in_lng' => 90.4066, 'in_location' => 'Banani Audit Site, Dhaka',
                'out_lat' => null, 'out_lng' => null, 'out_location' => '—',
                'is_proxy' => false, 'edited_by_role' => null, 'remarks' => '',
            ],
            [
                'id' => 'S-104', 'name' => 'Sample Student 04 (Tania)', 'manager' => 'Sample Manager B',
                'partner' => 'Partner Unit 01', 'status' => 'On time', 'mode' => 'Office', 'day' => 'Thu',
                'checkin' => '10:01', 'checkout' => '18:10',
                'in_lat' => 23.7516, 'in_lng' => 90.3943, 'in_location' => 'Karwan Bazar Office, Dhaka',
                'out_lat' => 23.7516, 'out_lng' => 90.3943, 'out_location' => 'Karwan Bazar Office, Dhaka',
                'is_proxy' => false, 'edited_by_role' => null, 'remarks' => '',
            ],
        ];

        $this->studentRows = session('erp_student_rows', $defaultRows);
    }

    protected function persistAttendanceRows(): void
    {
        session([
            'erp_student_rows' => $this->studentRows,
            'erp_perm_proxy'   => $this->managerCanProxyAttendance,
            'erp_perm_edit'    => $this->managerCanEditAttendance,
            'erp_perm_leave'   => $this->managerCanApproveLeave,
            'erp_perm_claims'  => $this->managerCanApproveClaims,
        ]);
    }

    protected function loadLeaveQueue(): void
    {
        if (Schema::hasTable('leave_requests') && Schema::hasTable('users')) {
            $firmModel = Firm::where('slug', $this->firmSlug)->first();
            if ($firmModel) {
                try {
                    $leavesQuery = LeaveRequest::with(['user.manager', 'reviewer', 'partner']);
                    if (Schema::hasColumn('leave_requests', 'firm_id')) {
                        $leavesQuery->where('firm_id', $firmModel->id);
                    } elseif (Schema::hasColumn('leave_requests', 'user_id')) {
                        $userIds = DB::table('users')->where('firm_id', $firmModel->id)->pluck('id')->all();
                        $leavesQuery->whereIn('user_id', $userIds);
                    }
                    $dbLeaves = $leavesQuery->get();
                if ($dbLeaves->isNotEmpty()) {
                    $this->leaveQueue = $dbLeaves->map(fn (LeaveRequest $lr) => [
                        'id'              => 'LR-' . $lr->id,
                        'db_id'           => $lr->id,
                        'name'            => $lr->user->name,
                        'student_code'    => $lr->user->student_code ?? ('S-' . $lr->user_id),
                        'manager'         => $lr->user->manager?->name ?? 'Direct Report',
                        'partner_unit'    => $lr->user->partner_unit ?? $this->partnerUnit,
                        'type'            => ucfirst(str_replace('_', ' ', $lr->leave_type)),
                        'period'          => $lr->start_date?->format('d M') . '–' . $lr->end_date?->format('d M Y'),
                        'reason'          => $lr->reason,
                        'status'          => $lr->status,
                        'status_label'    => match($lr->status) {
                            'pending_manager' => 'Awaiting Manager',
                            'pending_partner' => 'Awaiting Partner',
                            'approved'        => 'Approved',
                            'rejected'        => 'Rejected',
                            default           => ucfirst($lr->status),
                        },
                        'manager_remarks' => $lr->manager_remarks ?? '',
                        'partner_remarks' => $lr->partner_remarks ?? '',
                    ])->toArray();
                    return;
                } elseif (Auth::check()) {
                    $this->leaveQueue = [];
                    return;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('loadLeaveQueue error: ' . $e->getMessage());
            }
            }
        }

        $this->leaveQueue = [
            [
                'id'              => 'LR-101',
                'name'            => 'Sample Student 01',
                'student_code'    => 'S-101',
                'manager'         => 'Sample Manager A',
                'partner_unit'    => 'Partner Unit 01',
                'type'            => 'Annual Leave',
                'period'          => '14–15 Oct 2026',
                'reason'          => 'Family appointment outside Dhaka',
                'status'          => 'pending_manager',
                'status_label'    => 'Awaiting Manager',
                'manager_remarks' => '',
                'partner_remarks' => '',
            ],
            [
                'id'              => 'LR-102',
                'name'            => 'Sample Student 02',
                'student_code'    => 'S-102',
                'manager'         => 'Sample Manager A',
                'partner_unit'    => 'Partner Unit 01',
                'type'            => 'ICAB Exam Preparation',
                'period'          => '19–24 Oct 2026',
                'reason'          => 'ICAB Certificate Level exam preparation leave',
                'status'          => 'pending_partner',
                'status_label'    => 'Awaiting Partner',
                'manager_remarks' => 'Exam admit card verified. Manager recommends approval.',
                'partner_remarks' => '',
            ],
            [
                'id'              => 'LR-103',
                'name'            => 'Sample Student 03',
                'student_code'    => 'S-103',
                'manager'         => 'Sample Manager B',
                'partner_unit'    => 'Partner Unit 01',
                'type'            => 'Emergency Leave',
                'period'          => '20 Oct 2026',
                'reason'          => 'Medical emergency',
                'status'          => 'approved',
                'status_label'    => 'Approved',
                'manager_remarks' => 'Verified with prescription.',
                'partner_remarks' => 'Sanctioned by Partner Unit 01.',
            ],
        ];
    }

    protected function loadClaimQueue(): void
    {
        if (Schema::hasTable('conveyance_claims') && Schema::hasTable('users')) {
            $firmModel = Firm::where('slug', $this->firmSlug)->first();
            if ($firmModel) {
                try {
                    $claimsQuery = ConveyanceClaim::with(['user.manager', 'reviewer']);
                    if (Schema::hasColumn('conveyance_claims', 'firm_id')) {
                        $claimsQuery->where('firm_id', $firmModel->id);
                    } elseif (Schema::hasColumn('conveyance_claims', 'user_id')) {
                        $userIds = DB::table('users')->where('firm_id', $firmModel->id)->pluck('id')->all();
                        $claimsQuery->whereIn('user_id', $userIds);
                    }
                    $dbClaims = $claimsQuery->get();
                    if ($dbClaims->isNotEmpty()) {
                        $this->claimQueue = $dbClaims->map(fn (ConveyanceClaim $cc) => [
                            'id'                => 'CC-' . $cc->id,
                            'db_id'             => $cc->id,
                            'name'              => $cc->user->name,
                            'student_code'      => $cc->user->student_code ?? ('S-' . $cc->user_id),
                            'manager'           => $cc->user->manager?->name ?? 'Direct Report',
                            'partner_unit'      => $cc->user->partner_unit ?? $this->partnerUnit,
                            'client_name'       => $cc->client_name,
                            'route'             => $cc->route,
                            'amount'            => (float) $cc->amount,
                            'original_amount'   => (float) $cc->original_amount,
                            'adjustment_remark' => $cc->adjustment_remark ?? '',
                            'is_late'           => (bool) $cc->is_late,
                            'status'            => $cc->status,
                            'status_label'      => match($cc->status) {
                                'pending_manager' => 'Awaiting Manager',
                                'pending_partner' => 'Escalated to Partner',
                                'approved'        => 'Approved',
                                'rejected'        => 'Rejected',
                                default           => ucfirst($cc->status),
                            },
                            'manager_remarks'   => $cc->manager_remarks ?? '',
                            'partner_remarks'   => $cc->partner_remarks ?? '',
                        ])->toArray();
                        return;
                    } elseif (Auth::check()) {
                        $this->claimQueue = [];
                        return;
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('loadClaimQueue error: ' . $e->getMessage());
                }
            }
        }

        $this->claimQueue = [
            [
                'id'                => 'CC-201',
                'name'              => 'Sample Student 01',
                'student_code'      => 'S-101',
                'manager'           => 'Sample Manager A',
                'partner_unit'      => 'Partner Unit 01',
                'client_name'       => 'BEXIMCO Textiles Audit',
                'route'             => 'Office → Sample Client → Return',
                'amount'            => 220.00,
                'original_amount'   => 220.00,
                'adjustment_remark' => '',
                'is_late'           => false,
                'status'            => 'approved',
                'status_label'      => 'Approved by Manager',
                'manager_remarks'   => 'Within ৳300 limit. Standard local bus/rickshaw fare.',
                'partner_remarks'   => '',
            ],
            [
                'id'                => 'CC-202',
                'name'              => 'Sample Student 02',
                'student_code'      => 'S-102',
                'manager'           => 'Sample Manager A',
                'partner_unit'      => 'Partner Unit 01',
                'client_name'       => 'Square Pharmaceuticals Audit',
                'route'             => 'Office → Gazipur Plant → Return',
                'amount'            => 550.00,
                'original_amount'   => 650.00,
                'adjustment_remark' => 'Overclaimed AC premium fare deducted; approved Express bus fare.',
                'is_late'           => false,
                'status'            => 'pending_partner',
                'status_label'      => 'Escalated to Partner',
                'manager_remarks'   => 'Amount exceeds ৳300 Manager limit. Adjusted and escalated to Partner.',
                'partner_remarks'   => '',
            ],
            [
                'id'                => 'CC-203',
                'name'              => 'Sample Student 03',
                'student_code'      => 'S-103',
                'manager'           => 'Sample Manager B',
                'partner_unit'      => 'Partner Unit 01',
                'client_name'       => 'Brac Bank Motijheel Branch',
                'route'             => 'Office → Motijheel Branch → Return',
                'amount'            => 180.00,
                'original_amount'   => 180.00,
                'adjustment_remark' => '',
                'is_late'           => true,
                'status'            => 'pending_partner',
                'status_label'      => 'Escalated to Partner (Late)',
                'manager_remarks'   => 'Submitted 6 days late (> 3 days policy). Escalated for Partner sanction.',
                'partner_remarks'   => '',
            ],
        ];
    }

    public function setSection(string $section): void
    {
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
        $this->deleteConfirmationInput = '';
        $this->selectedMemberId = null;
        $this->selectedMemberName = '';
    }

    public function toggleManagerPermission(string $permission): void
    {
        if ($this->previewRole !== 'partner') {
            $this->error = 'Only a Partner can modify Manager permissions.';
            return;
        }

        match ($permission) {
            'proxy'  => $this->managerCanProxyAttendance = ! $this->managerCanProxyAttendance,
            'edit'   => $this->managerCanEditAttendance  = ! $this->managerCanEditAttendance,
            'leave'  => $this->managerCanApproveLeave    = ! $this->managerCanApproveLeave,
            'claims' => $this->managerCanApproveClaims   = ! $this->managerCanApproveClaims,
            default  => null,
        };

        $this->persistAttendanceRows();
        $this->notice = 'Manager permissions updated by Partner successfully.';
    }

    public function saveProxyOrEditAttendance(): void
    {
        $this->error = null;
        $this->notice = null;

        if ($this->previewRole === 'student') {
            $this->error = 'Students cannot edit or proxy attendance.';
            return;
        }

        if ($this->previewRole === 'manager') {
            if ($this->editModeType === 'proxy' && ! $this->managerCanProxyAttendance) {
                $this->error = 'Partner has disabled Proxy Attendance permission for Managers.';
                return;
            }
            if ($this->editModeType === 'edit' && ! $this->managerCanEditAttendance) {
                $this->error = 'Partner has disabled Attendance Edit permission for Managers.';
                return;
            }
        }

        foreach ($this->studentRows as &$row) {
            if ($row['id'] === $this->selectedStudentCode) {
                $row['checkin'] = $this->editCheckinTime ?: '10:00';
                $row['checkout'] = $this->editCheckoutTime ?: '18:00';
                $row['status'] = in_array($this->editStatus, ['On time', 'Late', 'Incomplete'], true)
                    ? $this->editStatus
                    : 'On time';
                $row['in_location'] = $this->editLocationName ?: ($row['in_location'] ?? 'Office (Proxy/Edited)');
                $row['out_location'] = $this->editLocationName ?: ($row['out_location'] ?? 'Office (Proxy/Edited)');
                $row['is_proxy'] = $this->editModeType === 'proxy';
                $row['edited_by_role'] = ucfirst($this->previewRole);
                $row['remarks'] = trim($this->editRemarks) !== ''
                    ? $this->editRemarks
                    : ($this->editModeType === 'proxy' ? 'Proxy attendance recorded by ' . ucfirst($this->previewRole) : 'Edited by ' . ucfirst($this->previewRole));
                break;
            }
        }
        unset($row);

        $this->persistAttendanceRows();
        $this->closeDialogs();
        $this->notice = ($this->editModeType === 'proxy' ? 'Proxy attendance' : 'Attendance edit')
            . " saved for {$this->selectedStudentCode} by " . ucfirst($this->previewRole) . '.';
    }

    public function acknowledgeDialog(string $kind): void
    {
        $allowed = match ($kind) {
            'policy'     => $this->showPolicyModal,
            'correction' => $this->showCorrectionModal,
            'claim'      => $this->showClaimReviewModal,
            'leave'      => $this->showLeaveReviewModal,
            default      => false,
        };

        if (! $allowed) {
            return;
        }

        if ($kind === 'correction') {
            $this->saveProxyOrEditAttendance();
            return;
        }

        $this->closeDialogs();
        $this->notice = match ($kind) {
            'policy'     => 'Policy proposal illustrated only. No partner votes, approvals or policy revisions were saved.',
            'correction' => 'Attendance correction workflow illustrated only. No attendance record was changed.',
            'claim'      => 'Claim review illustrated only. No payment or expense approval was recorded.',
            'leave'      => 'Leave review illustrated only. No leave request, entitlement or approval was changed.',
            default      => 'This is a design-only demonstration.',
        };
    }

    public function togglePreviewSessionWithGps(?float $lat = null, ?float $lng = null, ?string $locationLabel = null): void
    {
        if ($lat !== null && $lng !== null) {
            $this->currentLat = round($lat, 6);
            $this->currentLng = round($lng, 6);
            $this->currentLocationName = $locationLabel ?: ("GPS: {$this->currentLat}, {$this->currentLng}");
        }
        $this->togglePreviewSession();
    }

    public function togglePreviewSession(): void
    {
        $this->error = null;
        if ($this->previewRole !== 'student') {
            $this->notice = 'Attendance self check-in preview is available only in the Student design role.';
            return;
        }

        if (! in_array($this->workMode, ['office', 'client'], true)) {
            $this->error = 'Select a valid attendance mode.';
            return;
        }

        if (! $this->previewSessionActive && $this->workMode === 'client' && trim($this->clientSite) === '') {
            $this->error = 'Choose a demo client site before checking in.';
            return;
        }

        $this->previewSessionActive = ! $this->previewSessionActive;
        $nowTime = now()->timezone('Asia/Dhaka')->format('H:i');
        $lat = $this->currentLat ?? ($this->workMode === 'client' ? 23.7279 : 23.7516);
        $lng = $this->currentLng ?? ($this->workMode === 'client' ? 90.4178 : 90.3943);
        $locName = $this->workMode === 'client'
            ? ($this->clientSite . ' (' . ($this->currentLocationName ?: 'GPS Verified') . ')')
            : ($this->currentLocationName ?: 'EDB Trade Centre, Karwan Bazar, Dhaka');

        foreach ($this->studentRows as &$row) {
            if ($row['id'] === 'S-101') {
                if ($this->previewSessionActive) {
                    $row['mode'] = ucfirst($this->workMode);
                    $row['checkin'] = $nowTime;
                    $row['checkout'] = '—';
                    $row['in_lat'] = $lat;
                    $row['in_lng'] = $lng;
                    $row['in_location'] = $locName;
                    $row['status'] = $nowTime > '10:10' ? 'Late' : 'On time';
                } else {
                    $row['checkout'] = $nowTime;
                    $row['out_lat'] = $lat;
                    $row['out_lng'] = $lng;
                    $row['out_location'] = $locName;
                }
                break;
            }
        }
        unset($row);

        $this->persistAttendanceRows();

        // Persist to real database for authenticated firm members
        if (Auth::check() && Schema::hasTable('attendances') && Schema::hasTable('firms')) {
            try {
                $user = Auth::user();
                $firm = Firm::where('slug', $this->firmSlug)->first();
                $firmId = $firm?->id ?? $user->firm_id;

                if ($firmId) {
                    $att = Attendance::firstOrNew([
                        'firm_id' => $firmId,
                        'user_id' => $user->id,
                        'date'    => now()->toDateString(),
                    ]);

                    if ($this->previewSessionActive) {
                        $att->work_mode = $this->workMode;
                        $att->client_site = $this->workMode === 'client' ? $this->clientSite : null;
                        $att->in_time = now();
                        $att->in_lat = $lat;
                        $att->in_lng = $lng;
                        $att->in_location_name = $locName;
                        $isLate = $nowTime > ($this->officeStart ?: '10:00');
                        $att->status = $isLate ? 'Late' : 'On time';
                    } else {
                        $att->out_time = now();
                        $att->out_lat = $lat;
                        $att->out_lng = $lng;
                        $att->out_location_name = $locName;
                    }
                    $att->save();

                    $this->loadAttendanceRows();
                    $this->notice = $this->previewSessionActive
                        ? "Check-in logged successfully at {$nowTime} from {$locName}."
                        : "Check-out logged successfully at {$nowTime}.";
                    return;
                }
            } catch (\Throwable) {}
        }

        $this->notice = $this->previewSessionActive
            ? "Check-in appearance simulated. Location captured: {$locName} ({$lat}, {$lng}). No timestamp, geolocation or attendance record was saved."
            : "Check-out appearance simulated. Out Location captured: {$locName} ({$lat}, {$lng}). No timestamp, geolocation or attendance record was saved.";
    }

    // ─── Multi-Tier Leave Workflow Methods ─────────────────────────────────────

    public function submitLeaveApplication(): void
    {
        $this->submitLeaveDemo();
    }

    public function submitLeaveDemo(): void
    {
        $this->error = null;
        $this->notice = null;
        if ($this->previewRole !== 'student') {
            $this->error = Auth::check()
                ? 'Only the Student role can submit a new leave application.'
                : 'Only the Student design role can preview a new leave application.';
            return;
        }

        $allowedTypes = ['annual', 'sick', 'emergency', 'icab_exam', 'icab_study'];
        if (! in_array($this->leaveType, $allowedTypes, true)
            || ! $this->validDate($this->leaveStart)
            || ! $this->validDate($this->leaveEnd)
            || $this->leaveEnd < $this->leaveStart
            || mb_strlen(trim($this->leaveReason)) < 5
        ) {
            $this->error = 'Enter a leave type, valid start and end dates, and a reason of at least five characters.';
            return;
        }

        $typeLabel = match ($this->leaveType) {
            'icab_exam'  => 'ICAB Exam Preparation',
            'icab_study' => 'ICAB Study Leave',
            'sick'       => 'Sick Leave',
            'emergency'  => 'Emergency Leave',
            default      => 'Annual Leave',
        };

        if (Auth::check() && Schema::hasTable('leave_requests') && Schema::hasTable('firms')) {
            try {
                $user = Auth::user();
                $firm = Firm::where('slug', $this->firmSlug)->first();
                $firmId = $firm?->id ?? $user->firm_id;

                if ($firmId) {
                    LeaveRequest::create([
                        'firm_id'         => $firmId,
                        'user_id'         => $user->id,
                        'leave_type'      => $this->leaveType,
                        'start_date'      => $this->leaveStart,
                        'end_date'        => $this->leaveEnd,
                        'reason'          => $this->leaveReason,
                        'status'          => 'pending_manager',
                        'manager_remarks' => '',
                        'partner_remarks' => '',
                    ]);

                    $this->loadLeaveQueue();
                    $this->leaveReason = '';
                    $this->notice = "Leave application ({$typeLabel}) submitted successfully. Forwarded to Manager for review.";
                    $this->dispatch('toast', [
                        'type'    => 'success',
                        'title'   => 'Leave Submitted',
                        'message' => "Your leave application ({$typeLabel}) has been forwarded to your Manager.",
                    ]);
                    return;
                }
            } catch (\Throwable) {}
        }

        // Add to active leave queue (in-memory / test environment)
        $newId = 'LR-' . (count($this->leaveQueue) + 101);
        $this->leaveQueue[] = [
            'id'              => $newId,
            'name'            => Auth::check() ? Auth::user()->name : 'Sample Student 01',
            'student_code'    => Auth::check() ? (Auth::user()->student_code ?? 'S-101') : 'S-101',
            'manager'         => 'Sample Manager A',
            'partner_unit'    => $this->partnerUnit,
            'type'            => $typeLabel,
            'period'          => "{$this->leaveStart} to {$this->leaveEnd}",
            'reason'          => $this->leaveReason,
            'status'          => 'pending_manager',
            'status_label'    => 'Awaiting Manager',
            'manager_remarks' => '',
            'partner_remarks' => '',
        ];

        $this->notice = 'Leave request preview validated. No request was submitted and no leave balance was changed.';
    }

    public function managerRecommendLeave(string $id, string $remarks = ''): void
    {
        if ($this->previewRole !== 'manager' && $this->previewRole !== 'partner') {
            $this->error = 'Only an assigned Manager or Partner can review leave requests.';
            return;
        }

        if (Schema::hasTable('leave_requests')) {
            try {
                $dbId = str_replace('LR-', '', $id);
                $lr = LeaveRequest::find($dbId);
                if ($lr) {
                    $lr->status = 'pending_partner';
                    $lr->manager_remarks = $remarks ?: 'Manager reviewed and recommended for Partner sanction.';
                    if (Auth::check()) {
                        $lr->reviewed_by = Auth::id();
                    }
                    $lr->save();
                }
            } catch (\Throwable) {}
        }

        foreach ($this->leaveQueue as &$req) {
            if ($req['id'] === $id) {
                $req['status'] = 'pending_partner';
                $req['status_label'] = 'Awaiting Partner';
                $req['manager_remarks'] = $remarks ?: 'Manager reviewed and recommended for Partner sanction.';
                $this->notice = "Leave application {$id} verified by Manager and forwarded to Partner for final approval.";
                break;
            }
        }
        unset($req);
    }

    public function partnerApproveLeave(string $id, string $remarks = ''): void
    {
        if ($this->previewRole !== 'partner') {
            $this->error = 'Only a Partner can give final leave approval.';
            return;
        }

        if (Schema::hasTable('leave_requests')) {
            try {
                $dbId = str_replace('LR-', '', $id);
                $lr = LeaveRequest::find($dbId);
                if ($lr) {
                    $lr->status = 'approved';
                    $lr->partner_remarks = $remarks ?: 'Approved by Partner ' . $this->partnerUnit . '.';
                    if (Auth::check()) {
                        $lr->partner_id = Auth::id();
                    }
                    $lr->save();
                }
            } catch (\Throwable) {}
        }

        foreach ($this->leaveQueue as &$req) {
            if ($req['id'] === $id) {
                $req['status'] = 'approved';
                $req['status_label'] = 'Approved';
                $req['partner_remarks'] = $remarks ?: 'Approved by Partner ' . $this->partnerUnit . '.';
                $this->notice = "Leave application {$id} approved by Partner.";
                break;
            }
        }
        unset($req);
    }

    public function rejectLeave(string $id, string $reason = ''): void
    {
        if ($this->previewRole === 'student') {
            $this->error = 'Students cannot reject leave applications.';
            return;
        }

        if (Schema::hasTable('leave_requests')) {
            try {
                $dbId = str_replace('LR-', '', $id);
                $lr = LeaveRequest::find($dbId);
                if ($lr) {
                    $lr->status = 'rejected';
                    $lr->partner_remarks = $reason ?: ('Rejected by ' . ucfirst($this->previewRole));
                    $lr->save();
                }
            } catch (\Throwable) {}
        }

        foreach ($this->leaveQueue as &$req) {
            if ($req['id'] === $id) {
                $req['status'] = 'rejected';
                $req['status_label'] = 'Rejected';
                $req['partner_remarks'] = $reason ?: ('Rejected by ' . ucfirst($this->previewRole));
                $this->notice = "Leave application {$id} marked as Rejected.";
                break;
            }
        }
        unset($req);
    }

    // ─── Multi-Tier Conveyance Workflow Methods ───────────────────────────────

    public function submitConveyanceClaim(): void
    {
        $this->submitClaimDemo();
    }

    public function submitClaimDemo(): void
    {
        $this->error = null;
        $this->notice = null;
        if ($this->previewRole !== 'student') {
            $this->error = Auth::check()
                ? 'Only the Student role can submit a conveyance claim.'
                : 'Only the Student design role can preview a new conveyance claim.';
            return;
        }

        $amount = filter_var($this->claimAmount, FILTER_VALIDATE_FLOAT);
        if (! $this->validDate($this->claimDate)
            || $amount === false || $amount <= 0 || $amount > 50000
            || mb_strlen(trim($this->claimRoute)) < 3
            || mb_strlen(trim($this->claimPurpose)) < 5
        ) {
            $this->error = 'Enter a valid date, amount (1–50,000), journey, and a purpose of at least five characters.';
            return;
        }

        $claimDateTime = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->claimDate);
        $daysAgo = (int) now()->diffInDays($claimDateTime);
        $isLate = $daysAgo > $this->conveyanceLateDays;

        if (Auth::check() && Schema::hasTable('conveyance_claims') && Schema::hasTable('firms')) {
            try {
                $user = Auth::user();
                $firm = Firm::where('slug', $this->firmSlug)->first();
                $firmId = $firm?->id ?? $user->firm_id;

                if ($firmId) {
                    ConveyanceClaim::create([
                        'firm_id'           => $firmId,
                        'user_id'           => $user->id,
                        'claim_date'        => $this->claimDate,
                        'amount'            => $amount,
                        'original_amount'   => $amount,
                        'route'             => $this->claimRoute,
                        'purpose'           => $this->claimPurpose,
                        'client_name'       => $this->claimClientName ?: 'General Practice Site',
                        'is_late'           => $isLate,
                        'status'            => $isLate ? 'pending_partner' : 'pending_manager',
                        'manager_remarks'   => $isLate ? "Submitted {$daysAgo} days late (> {$this->conveyanceLateDays} days policy)." : '',
                        'partner_remarks'   => '',
                    ]);

                    $this->loadClaimQueue();
                    $this->claimAmount = '';
                    $this->claimRoute = '';
                    $this->claimPurpose = '';
                    $statusLabel = $isLate ? 'Escalated to Partner (Late Submission)' : 'Forwarded to Manager';
                    $this->notice = "Conveyance claim of ৳{$amount} submitted successfully. Status: {$statusLabel}.";
                    $this->dispatch('toast', [
                        'type'    => 'success',
                        'title'   => 'Claim Submitted',
                        'message' => "Your conveyance claim for ৳{$amount} has been logged.",
                    ]);
                    return;
                }
            } catch (\Throwable) {}
        }

        $newId = 'CC-' . (count($this->claimQueue) + 201);
        $this->claimQueue[] = [
            'id'                => $newId,
            'name'              => Auth::check() ? Auth::user()->name : 'Sample Student 01',
            'student_code'      => Auth::check() ? (Auth::user()->student_code ?? 'S-101') : 'S-101',
            'manager'           => 'Sample Manager A',
            'partner_unit'      => $this->partnerUnit,
            'client_name'       => $this->claimClientName ?: 'General Practice Site',
            'route'             => $this->claimRoute,
            'amount'            => $amount,
            'original_amount'   => $amount,
            'adjustment_remark' => '',
            'is_late'           => $isLate,
            'status'            => $isLate ? 'pending_partner' : 'pending_manager',
            'status_label'      => $isLate ? 'Escalated to Partner (Late)' : 'Manager Review',
            'manager_remarks'   => $isLate ? "Submitted {$daysAgo} days late (> {$this->conveyanceLateDays} days policy)." : '',
            'partner_remarks'   => '',
        ];

        $this->notice = 'Conveyance claim preview validated. No invoice, receipt, reimbursement or payment was recorded.';
    }

    public function managerReviewClaim(string $id, float $adjustedAmount, string $adjustmentRemark = '', string $action = 'approve'): void
    {
        if ($this->previewRole !== 'manager' && $this->previewRole !== 'partner') {
            $this->error = 'Only an assigned Manager or Partner can review conveyance claims.';
            return;
        }

        if (Schema::hasTable('conveyance_claims')) {
            try {
                $dbId = str_replace('CC-', '', $id);
                $cc = ConveyanceClaim::find($dbId);
                if ($cc) {
                    if ($action === 'reject') {
                        $cc->status = 'rejected';
                        $cc->manager_remarks = $adjustmentRemark ?: 'Rejected by Manager.';
                    } else {
                        $cc->amount = $adjustedAmount;
                        $cc->adjustment_remark = $adjustmentRemark;
                        $exceedsLimit = $adjustedAmount > $this->managerConveyanceLimit;
                        $cc->status = ($exceedsLimit || $cc->is_late) ? 'pending_partner' : 'approved';
                    }
                    if (Auth::check()) {
                        $cc->reviewed_by = Auth::id();
                    }
                    $cc->save();
                }
            } catch (\Throwable) {}
        }

        foreach ($this->claimQueue as &$claim) {
            if ($claim['id'] === $id) {
                if ($action === 'reject') {
                    $claim['status'] = 'rejected';
                    $claim['status_label'] = 'Rejected by Manager';
                    $claim['manager_remarks'] = $adjustmentRemark ?: 'Rejected by Manager.';
                    $this->notice = "Claim {$id} rejected.";
                    break;
                }

                $claim['amount'] = $adjustedAmount;
                if ($adjustedAmount !== $claim['original_amount']) {
                    $claim['adjustment_remark'] = $adjustmentRemark ?: 'Amount modified by Manager.';
                }

                $exceedsLimit = $adjustedAmount > $this->managerConveyanceLimit;
                $isLate = ! empty($claim['is_late']);

                if ($exceedsLimit || $isLate) {
                    $claim['status'] = 'pending_partner';
                    $claim['status_label'] = 'Escalated to Partner';
                    $reason = ($exceedsLimit ? "Amount (৳{$adjustedAmount}) exceeds Manager limit (৳{$this->managerConveyanceLimit}). " : "")
                        . ($isLate ? "Late claim exceeds allowed policy window." : "");
                    $claim['manager_remarks'] = trim($reason . ' ' . $adjustmentRemark);
                    $this->notice = "Claim {$id} escalated to Partner for final authorization: {$reason}";
                } else {
                    $claim['status'] = 'approved';
                    $claim['status_label'] = 'Approved by Manager';
                    $claim['manager_remarks'] = $adjustmentRemark ?: "Approved within Manager limit (<= ৳{$this->managerConveyanceLimit}).";
                    $this->notice = "Claim {$id} (৳{$adjustedAmount}) approved directly by Manager.";
                }
                break;
            }
        }
        unset($claim);
    }

    public function partnerApproveClaim(string $id, string $remarks = ''): void
    {
        if ($this->previewRole !== 'partner') {
            $this->error = 'Only a Partner can give final claim sanction.';
            return;
        }

        if (Schema::hasTable('conveyance_claims')) {
            try {
                $dbId = str_replace('CC-', '', $id);
                $cc = ConveyanceClaim::find($dbId);
                if ($cc) {
                    $cc->status = 'approved';
                    $cc->partner_remarks = $remarks ?: ('Sanctioned by Partner ' . $this->partnerUnit . '.');
                    if (Auth::check()) {
                        $cc->partner_id = Auth::id();
                    }
                    $cc->save();
                }
            } catch (\Throwable) {}
        }

        foreach ($this->claimQueue as &$claim) {
            if ($claim['id'] === $id) {
                $claim['status'] = 'approved';
                $claim['status_label'] = 'Approved by Partner';
                $claim['partner_remarks'] = $remarks ?: 'Approved by Partner ' . $this->partnerUnit . '.';
                $this->notice = "Conveyance claim {$id} approved by Partner.";
                break;
            }
        }
        unset($claim);
    }

    public function rejectClaim(string $id, string $remarks = ''): void
    {
        if ($this->previewRole === 'student') {
            $this->error = 'Students cannot reject claims.';
            return;
        }
        if (Schema::hasTable('conveyance_claims')) {
            try {
                $dbId = str_replace('CC-', '', $id);
                $cc = ConveyanceClaim::find($dbId);
                if ($cc) {
                    $cc->status = 'rejected';
                    $cc->partner_remarks = $remarks ?: ('Rejected by ' . ucfirst($this->previewRole));
                    $cc->save();
                }
            } catch (\Throwable) {}
        }

        foreach ($this->claimQueue as &$claim) {
            if ($claim['id'] === $id) {
                $claim['status'] = 'rejected';
                $claim['status_label'] = 'Rejected';
                $claim['partner_remarks'] = $remarks ?: ('Rejected by ' . ucfirst($this->previewRole));
                $this->notice = "Claim {$id} rejected.";
                break;
            }
        }
        unset($claim);
    }

    public function markPreviewRead(string $id): void
    {
        if (! in_array($id, self::NOTIFICATION_IDS, true)) {
            return;
        }

        if (! in_array($id, $this->readNotificationIds, true)) {
            $this->readNotificationIds[] = $id;
        }

        $this->notice = 'Read status updated in this temporary browser preview only.';
    }

    public function dismissNotice(): void
    {
        $this->notice = null;
        $this->error = null;
    }

    private function validDate(string $value): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    public function userCanManageTeam(): bool
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();
            return $user->canManageTeam();
        }
        return $this->previewRole === 'partner';
    }

    public function getAvailableManagersProperty(): array
    {
        return array_values(array_filter($this->teamMembers, function ($m) {
            return ($m['role'] ?? '') === 'manager';
        }));
    }

    public function loadTeamMembers(): void
    {
        if (Schema::hasTable('users') && Schema::hasTable('firms')) {
            $firmModel = Firm::where('slug', $this->firmSlug)->first();
            if ($firmModel) {
                try {
                    $dbUsers = User::with('manager')
                        ->where('firm_id', $firmModel->id)
                        ->orderByRaw("CASE role WHEN 'partner' THEN 1 WHEN 'manager' THEN 2 WHEN 'student' THEN 3 ELSE 4 END")
                        ->orderBy('name')
                        ->get();

                    if ($dbUsers->isNotEmpty()) {
                        $this->teamMembers = $dbUsers->map(function (User $u) {
                            return [
                                'id'                   => $u->id,
                                'name'                 => $u->name,
                                'email'                => $u->email,
                                'role'                 => $u->role,
                                'role_label'           => match ($u->role) {
                                    'partner' => 'Partner / Principal',
                                    'manager' => 'Audit Manager',
                                    'student' => 'Articled Student',
                                    default   => ucfirst($u->role),
                                },
                                'phone'                => '—',
                                'student_code'         => $u->student_code ?? ($u->role === 'student' ? ('S-' . $u->id) : null),
                                'partner_unit'         => $u->partner_unit ?? $this->partnerUnit,
                                'manager_id'           => $u->manager_id,
                                'manager_name'         => $u->manager?->name ?? '—',
                                'monthly_allowance'    => (float) ($u->monthly_allowance ?? ($u->role === 'student' ? 6000 : ($u->role === 'manager' ? 25000 : 0))),
                                'can_manage_team'      => (bool) $u->can_manage_team,
                                'can_proxy_attendance' => (bool) $u->can_proxy_attendance,
                                'can_edit_attendance'  => (bool) $u->can_edit_attendance,
                                'can_approve_leave'    => (bool) $u->can_approve_leave,
                                'can_approve_claims'   => (bool) $u->can_approve_claims,
                                'created_at'           => $u->created_at?->format('d M Y') ?? 'Recent',
                            ];
                        })->toArray();
                        return;
                    }
                } catch (\Throwable) {}
            }
        }

        $this->teamMembers = [
            [
                'id'                   => 1,
                'name'                 => 'Rahman Anis, FCA',
                'email'                => 'partner@rac.dhakawala.com',
                'role'                 => 'partner',
                'role_label'           => 'Partner / Principal',
                'phone'                => '+880 1711 000001',
                'student_code'         => null,
                'partner_unit'         => 'Partner Unit 01',
                'manager_id'           => null,
                'manager_name'         => '—',
                'monthly_allowance'    => 0,
                'can_manage_team'      => true,
                'can_proxy_attendance' => true,
                'can_edit_attendance'  => true,
                'can_approve_leave'    => true,
                'can_approve_claims'   => true,
                'created_at'           => '01 Jan 2026',
            ],
            [
                'id'                   => 2,
                'name'                 => 'Sample Manager A',
                'email'                => 'manager.a@rac.dhakawala.com',
                'role'                 => 'manager',
                'role_label'           => 'Audit Manager',
                'phone'                => '+880 1711 000002',
                'student_code'         => null,
                'partner_unit'         => 'Partner Unit 01',
                'manager_id'           => null,
                'manager_name'         => 'Rahman Anis, FCA',
                'monthly_allowance'    => 28000,
                'can_manage_team'      => true,
                'can_proxy_attendance' => true,
                'can_edit_attendance'  => true,
                'can_approve_leave'    => true,
                'can_approve_claims'   => true,
                'created_at'           => '15 Jan 2026',
            ],
            [
                'id'                   => 3,
                'name'                 => 'Sample Manager B',
                'email'                => 'manager.b@rac.dhakawala.com',
                'role'                 => 'manager',
                'role_label'           => 'Audit Manager',
                'phone'                => '+880 1711 000003',
                'student_code'         => null,
                'partner_unit'         => 'Partner Unit 01',
                'manager_id'           => null,
                'manager_name'         => 'Rahman Anis, FCA',
                'monthly_allowance'    => 26000,
                'can_manage_team'      => false,
                'can_proxy_attendance' => true,
                'can_edit_attendance'  => false,
                'can_approve_leave'    => true,
                'can_approve_claims'   => false,
                'created_at'           => '01 Feb 2026',
            ],
            [
                'id'                   => 4,
                'name'                 => 'Sample Student 01 (Zubair)',
                'email'                => 'zubair@rac.dhakawala.com',
                'role'                 => 'student',
                'role_label'           => 'Articled Student',
                'phone'                => '+880 1811 000004',
                'student_code'         => 'S-101',
                'partner_unit'         => 'Partner Unit 01',
                'manager_id'           => 2,
                'manager_name'         => 'Sample Manager A',
                'monthly_allowance'    => 7000,
                'can_manage_team'      => false,
                'can_proxy_attendance' => false,
                'can_edit_attendance'  => false,
                'can_approve_leave'    => false,
                'can_approve_claims'   => false,
                'created_at'           => '10 Mar 2026',
            ],
            [
                'id'                   => 5,
                'name'                 => 'Sample Student 02 (Nusrat)',
                'email'                => 'nusrat@rac.dhakawala.com',
                'role'                 => 'student',
                'role_label'           => 'Articled Student',
                'phone'                => '+880 1811 000005',
                'student_code'         => 'S-102',
                'partner_unit'         => 'Partner Unit 01',
                'manager_id'           => 2,
                'manager_name'         => 'Sample Manager A',
                'monthly_allowance'    => 6500,
                'can_manage_team'      => false,
                'can_proxy_attendance' => false,
                'can_edit_attendance'  => false,
                'can_approve_leave'    => false,
                'can_approve_claims'   => false,
                'created_at'           => '12 Mar 2026',
            ],
            [
                'id'                   => 6,
                'name'                 => 'Sample Student 03 (Rakibul)',
                'email'                => 'rakibul@rac.dhakawala.com',
                'role'                 => 'student',
                'role_label'           => 'Articled Student',
                'phone'                => '+880 1811 000006',
                'student_code'         => 'S-103',
                'partner_unit'         => 'Partner Unit 01',
                'manager_id'           => 3,
                'manager_name'         => 'Sample Manager B',
                'monthly_allowance'    => 6000,
                'can_manage_team'      => false,
                'can_proxy_attendance' => false,
                'can_edit_attendance'  => false,
                'can_approve_leave'    => false,
                'can_approve_claims'   => false,
                'created_at'           => '15 Mar 2026',
            ],
        ];
    }

    public function openAddMemberModal(): void
    {
        if (! $this->userCanManageTeam()) {
            $this->error = 'Only Partners or authorized Managers can enroll new team members.';
            return;
        }
        $this->closeDialogs();
        $this->resetNewMemberFields();
        $this->showAddMemberModal = true;
    }

    private function resetNewMemberFields(): void
    {
        $this->newMemberName = '';
        $this->newMemberEmail = '';
        $this->newMemberPassword = '';
        $this->newMemberRole = 'student';
        $this->newMemberStudentCode = '';
        $this->newMemberManagerId = null;
        $this->newMemberPartnerUnit = $this->partnerUnit;
        $this->newMemberMonthlyAllowance = 6000.00;
        $this->newMemberCanManageTeam = false;
        $this->newMemberCanProxyAttendance = false;
        $this->newMemberCanEditAttendance = false;
        $this->newMemberCanApproveLeave = false;
        $this->newMemberCanApproveClaims = false;
    }

    public function createMember(): void
    {
        if (! $this->userCanManageTeam()) {
            $this->error = 'Unauthorized action: Only Partners or authorized Managers can enroll team members.';
            return;
        }

        $this->validate([
            'newMemberName'  => 'required|string|min:2|max:120',
            'newMemberEmail' => 'required|email|max:150',
            'newMemberRole'  => 'required|in:partner,manager,student',
        ]);

        $firmModel = Firm::where('slug', $this->firmSlug)->first();

        if ($firmModel && Schema::hasTable('users')) {
            $existing = User::where('email', strtolower(trim($this->newMemberEmail)))->first();
            if ($existing) {
                $this->error = "A user with email {$this->newMemberEmail} already exists.";
                return;
            }

            $user = new User();
            $user->firm_id = $firmModel->id;
            $user->name = trim($this->newMemberName);
            $user->email = strtolower(trim($this->newMemberEmail));
            $plainPassword = $this->newMemberPassword ?: 'Password@123';
            $user->password = bcrypt($plainPassword);
            $user->role = $this->newMemberRole;
            $user->partner_unit = $this->newMemberPartnerUnit ?: $this->partnerUnit;
            $user->monthly_allowance = (float) $this->newMemberMonthlyAllowance;
            $user->student_code = $this->newMemberRole === 'student' ? ($this->newMemberStudentCode ?: ('S-' . rand(100, 999))) : null;
            $user->manager_id = ($this->newMemberRole === 'student' && !empty($this->newMemberManagerId)) ? (int) $this->newMemberManagerId : null;
            $user->can_manage_team = (bool) $this->newMemberCanManageTeam;
            $user->can_proxy_attendance = (bool) $this->newMemberCanProxyAttendance;
            $user->can_edit_attendance = (bool) $this->newMemberCanEditAttendance;
            $user->can_approve_leave = (bool) $this->newMemberCanApproveLeave;
            $user->can_approve_claims = (bool) $this->newMemberCanApproveClaims;
            $user->save();

            $this->notice = "Successfully enrolled {$user->name} as " . ucfirst($user->role) . " (Temporary password: {$plainPassword}).";
        } else {
            $newId = count($this->teamMembers) + 1;
            $managerName = '—';
            if ($this->newMemberManagerId) {
                foreach ($this->teamMembers as $m) {
                    if ($m['id'] == $this->newMemberManagerId) {
                        $managerName = $m['name'];
                        break;
                    }
                }
            }

            $this->teamMembers[] = [
                'id'                   => $newId,
                'name'                 => trim($this->newMemberName),
                'email'                => strtolower(trim($this->newMemberEmail)),
                'role'                 => $this->newMemberRole,
                'role_label'           => match ($this->newMemberRole) {
                    'partner' => 'Partner / Principal',
                    'manager' => 'Audit Manager',
                    'student' => 'Articled Student',
                    default   => ucfirst($this->newMemberRole),
                },
                'phone'                => '—',
                'student_code'         => $this->newMemberRole === 'student' ? ($this->newMemberStudentCode ?: ('S-' . $newId)) : null,
                'partner_unit'         => $this->newMemberPartnerUnit ?: $this->partnerUnit,
                'manager_id'           => $this->newMemberManagerId ? (int) $this->newMemberManagerId : null,
                'manager_name'         => $managerName,
                'monthly_allowance'    => (float) $this->newMemberMonthlyAllowance,
                'can_manage_team'      => (bool) $this->newMemberCanManageTeam,
                'can_proxy_attendance' => (bool) $this->newMemberCanProxyAttendance,
                'can_edit_attendance'  => (bool) $this->newMemberCanEditAttendance,
                'can_approve_leave'    => (bool) $this->newMemberCanApproveLeave,
                'can_approve_claims'   => (bool) $this->newMemberCanApproveClaims,
                'created_at'           => date('d M Y'),
            ];
            $this->notice = "Successfully added {$this->newMemberName} to the team roster.";
        }

        $this->closeDialogs();
        $this->loadTeamMembers();
        $this->loadAttendanceRows();
    }

    public function openEditMemberModal($id): void
    {
        if (! $this->userCanManageTeam()) {
            $this->error = 'Only Partners or authorized Managers can edit member profiles and permissions.';
            return;
        }

        $member = collect($this->teamMembers)->firstWhere('id', $id);
        if (! $member) {
            $this->error = 'Member not found.';
            return;
        }

        $this->closeDialogs();
        $this->editMemberId = (int) $member['id'];
        $this->editMemberName = $member['name'];
        $this->editMemberEmail = $member['email'];
        $this->editMemberRole = $member['role'];
        $this->editMemberStudentCode = $member['student_code'] ?? '';
        $this->editMemberManagerId = $member['manager_id'] ?? null;
        $this->editMemberPartnerUnit = $member['partner_unit'] ?? $this->partnerUnit;
        $this->editMemberMonthlyAllowance = (float) ($member['monthly_allowance'] ?? 0);
        $this->editMemberCanManageTeam = (bool) ($member['can_manage_team'] ?? false);
        $this->editMemberCanProxyAttendance = (bool) ($member['can_proxy_attendance'] ?? false);
        $this->editMemberCanEditAttendance = (bool) ($member['can_edit_attendance'] ?? false);
        $this->editMemberCanApproveLeave = (bool) ($member['can_approve_leave'] ?? false);
        $this->editMemberCanApproveClaims = (bool) ($member['can_approve_claims'] ?? false);

        $this->showEditMemberModal = true;
    }

    public function updateMember(): void
    {
        if (! $this->userCanManageTeam()) {
            $this->error = 'Unauthorized action: Only Partners or authorized Managers can modify permissions.';
            return;
        }

        $firmModel = Firm::where('slug', $this->firmSlug)->first();

        if ($firmModel && Schema::hasTable('users')) {
            $user = User::where('firm_id', $firmModel->id)->where('id', $this->editMemberId)->first();
            if ($user) {
                $user->role = $this->editMemberRole;
                $user->partner_unit = $this->editMemberPartnerUnit;
                $user->monthly_allowance = (float) $this->editMemberMonthlyAllowance;
                if ($this->editMemberRole === 'student') {
                    $user->student_code = $this->editMemberStudentCode ?: $user->student_code;
                    $user->manager_id = !empty($this->editMemberManagerId) ? (int) $this->editMemberManagerId : null;
                } else {
                    $user->manager_id = null;
                }
                $user->can_manage_team = (bool) $this->editMemberCanManageTeam;
                $user->can_proxy_attendance = (bool) $this->editMemberCanProxyAttendance;
                $user->can_edit_attendance = (bool) $this->editMemberCanEditAttendance;
                $user->can_approve_leave = (bool) $this->editMemberCanApproveLeave;
                $user->can_approve_claims = (bool) $this->editMemberCanApproveClaims;
                $user->save();

                $this->notice = "Updated permissions and profile for {$user->name}.";
            }
        } else {
            foreach ($this->teamMembers as &$m) {
                if ($m['id'] == $this->editMemberId) {
                    $m['role'] = $this->editMemberRole;
                    $m['role_label'] = match ($this->editMemberRole) {
                        'partner' => 'Partner / Principal',
                        'manager' => 'Audit Manager',
                        'student' => 'Articled Student',
                        default   => ucfirst($this->editMemberRole),
                    };
                    $m['partner_unit'] = $this->editMemberPartnerUnit;
                    $m['monthly_allowance'] = (float) $this->editMemberMonthlyAllowance;
                    if ($this->editMemberRole === 'student') {
                        $m['student_code'] = $this->editMemberStudentCode;
                        $m['manager_id'] = $this->editMemberManagerId ? (int) $this->editMemberManagerId : null;
                        if ($this->editMemberManagerId) {
                            foreach ($this->teamMembers as $mgr) {
                                if ($mgr['id'] == $this->editMemberManagerId) {
                                    $m['manager_name'] = $mgr['name'];
                                    break;
                                }
                            }
                        } else {
                            $m['manager_name'] = '—';
                        }
                    }
                    $m['can_manage_team'] = (bool) $this->editMemberCanManageTeam;
                    $m['can_proxy_attendance'] = (bool) $this->editMemberCanProxyAttendance;
                    $m['can_edit_attendance'] = (bool) $this->editMemberCanEditAttendance;
                    $m['can_approve_leave'] = (bool) $this->editMemberCanApproveLeave;
                    $m['can_approve_claims'] = (bool) $this->editMemberCanApproveClaims;
                    break;
                }
            }
            unset($m);
            $this->notice = "Updated permissions and profile for {$this->editMemberName}.";
        }

        $this->closeDialogs();
        $this->loadTeamMembers();
        $this->loadAttendanceRows();
    }

    public function confirmDeleteMember($id): void
    {
        if (! $this->userCanManageTeam()) {
            $this->error = 'Only Partners or authorized Managers can remove practice members.';
            return;
        }

        if (Auth::check() && Auth::id() == $id) {
            $this->error = 'Security rule: You cannot delete your own active account.';
            return;
        }

        $member = collect($this->teamMembers)->firstWhere('id', $id);
        if (! $member) {
            $this->error = 'Member not found.';
            return;
        }

        $this->closeDialogs();
        $this->selectedMemberId = (int) $member['id'];
        $this->selectedMemberName = $member['name'];
        $this->deleteConfirmationInput = '';
        $this->showDeleteMemberModal = true;
    }

    public function deleteMember(): void
    {
        if (! $this->userCanManageTeam()) {
            $this->error = 'Unauthorized: Only Partners or authorized Managers can remove team members.';
            return;
        }

        $typed = strtoupper(trim($this->deleteConfirmationInput));
        if ($typed !== 'DELETE' && $typed !== 'CONFIRM') {
            $this->error = 'Security confirmation failed: You must type "DELETE" or "CONFIRM" exactly to proceed.';
            return;
        }

        if (Auth::check() && Auth::id() == $this->selectedMemberId) {
            $this->error = 'Security rule: You cannot delete your own active account.';
            return;
        }

        $memberName = $this->selectedMemberName;
        $memberId = $this->selectedMemberId;

        $firmModel = Firm::where('slug', $this->firmSlug)->first();

        if ($firmModel && Schema::hasTable('users')) {
            $user = User::where('firm_id', $firmModel->id)->where('id', $memberId)->first();
            if ($user) {
                User::where('firm_id', $firmModel->id)->where('manager_id', $user->id)->update(['manager_id' => null]);
                $user->delete();
                $this->notice = "Member {$memberName} has been permanently removed from the practice.";
            }
        } else {
            $this->teamMembers = array_values(array_filter($this->teamMembers, fn ($m) => $m['id'] != $memberId));
            $this->notice = "Member {$memberName} has been permanently removed.";
        }

        $this->closeDialogs();
        $this->loadTeamMembers();
        $this->loadAttendanceRows();
    }

    public function render()
    {
        if (! in_array($this->previewRole, self::ROLES, true)) {
            $this->previewRole = 'student';
        }
        if (! in_array($this->section, self::SECTIONS, true)) {
            $this->section = 'dashboard';
        }
        if (! in_array($this->filter, ['all', 'on-time', 'late', 'incomplete'], true)) {
            $this->filter = 'all';
        }

        if (empty($this->studentRows)) {
            $this->loadAttendanceRows();
        }

        $studentRows = $this->studentRows;

        $rows = match ($this->previewRole) {
            'manager' => array_values(array_filter(
                $studentRows, function (array $row) {
                    if (Auth::check() && Auth::user()->isManager()) {
                        return $row['manager'] === Auth::user()->name;
                    }
                    return $row['manager'] === 'Sample Manager A';
                }
            )),
            'student' => array_values(array_filter(
                $studentRows, function (array $row) {
                    if (Auth::check() && Auth::user()->isStudent()) {
                        return ($row['db_id'] ?? null) === Auth::id() || (Auth::user()->student_code && $row['id'] === Auth::user()->student_code);
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
            $rows = array_values(array_filter(
                $rows, fn (array $row) => strtolower(str_replace(' ', '-', $row['status'])) === $this->filter
            ));
        }

        // Filter queues by role
        $scopedLeaveQueue = $this->previewRole === 'student'
            ? array_values(array_filter($this->leaveQueue, function ($q) {
                if (Auth::check() && Auth::user()->isStudent()) {
                    return ($q['user_id'] ?? null) === Auth::id() || ($q['student_code'] ?? '') === (Auth::user()->student_code ?? 'S-101');
                }
                return ($q['student_code'] ?? '') === 'S-101';
            }))
            : ($this->previewRole === 'manager'
                ? array_values(array_filter($this->leaveQueue, function ($q) {
                    if (Auth::check() && Auth::user()->isManager()) {
                        return ($q['manager'] ?? '') === Auth::user()->name;
                    }
                    return ($q['manager'] ?? '') === 'Sample Manager A';
                }))
                : $this->leaveQueue);

        $scopedClaimQueue = $this->previewRole === 'student'
            ? array_values(array_filter($this->claimQueue, function ($q) {
                if (Auth::check() && Auth::user()->isStudent()) {
                    return ($q['user_id'] ?? null) === Auth::id() || ($q['student_code'] ?? '') === (Auth::user()->student_code ?? 'S-101');
                }
                return ($q['student_code'] ?? '') === 'S-101';
            }))
            : ($this->previewRole === 'manager'
                ? array_values(array_filter($this->claimQueue, function ($q) {
                    if (Auth::check() && Auth::user()->isManager()) {
                        return ($q['manager'] ?? '') === Auth::user()->name;
                    }
                    return ($q['manager'] ?? '') === 'Sample Manager A';
                }))
                : $this->claimQueue);

        // Filter team roster
        $this->canManageTeam = $this->userCanManageTeam();
        $filteredTeamMembers = $this->teamMembers;

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
            'rows'              => $rows,
            'allRows'           => $studentRows,
            'leaveQueue'        => $scopedLeaveQueue,
            'claimQueue'        => $scopedClaimQueue,
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
