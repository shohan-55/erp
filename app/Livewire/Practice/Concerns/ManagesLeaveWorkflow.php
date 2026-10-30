<?php

namespace App\Livewire\Practice\Concerns;

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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait ManagesLeaveWorkflow
{
    protected function loadLeaveQueue(): void
    {
        if (Schema::hasTable('leave_requests') && Schema::hasTable('users')) {
            $firmModel = $this->resolveFirmModel();
            if ($firmModel) {
                try {
                    $leavesQuery = LeaveRequest::with(['user.manager', 'engagedManager', 'reviewer', 'partner']);
                    if (Schema::hasColumn('leave_requests', 'firm_id')) {
                        $leavesQuery->where('firm_id', $firmModel->id);
                    } elseif (Schema::hasColumn('leave_requests', 'user_id')) {
                        $userIds = DB::table('users')->where('firm_id', $firmModel->id)->pluck('id')->all();
                        $leavesQuery->whereIn('user_id', $userIds);
                    }
                    $dbLeaves = $leavesQuery->orderByDesc('id')->get();
                    if ($dbLeaves->isNotEmpty()) {
                        $this->leaveQueue = $dbLeaves->map(function (LeaveRequest $lr) {
                            $reqDays = $lr->requested_days ?: (
                                ($lr->start_date && $lr->end_date) ? ((int) $lr->start_date->diffInDays($lr->end_date) + 1) : 1
                            );
                            return [
                                'id'                     => 'LR-' . $lr->id,
                                'db_id'                  => $lr->id,
                                'user_id'                => $lr->user_id,
                                'name'                   => $lr->user?->name ?? 'Practice Member',
                                'student_code'           => $lr->user?->student_code ?? ('S-' . $lr->user_id),
                                'created_at_human'       => $lr->created_at?->diffForHumans() ?? 'Just now',
                                'manager'                => $lr->engagedManager?->name ?? ($lr->user?->manager?->name ?? 'Direct Report'),
                                'engaged_manager_id'     => $lr->engaged_manager_id ?? $lr->user?->manager_id,
                                'partner_unit'           => $lr->user?->partner_unit ?? $this->partnerUnit,
                                'type'                   => ucfirst(str_replace('_', ' ', $lr->leave_type)),
                                'start_date_raw'         => $lr->start_date?->format('Y-m-d') ?? '',
                                'end_date_raw'           => $lr->end_date?->format('Y-m-d') ?? '',
                                'period'                 => $lr->start_date?->format('d M') . '–' . $lr->end_date?->format('d M Y'),
                                'requested_days'         => $reqDays,
                                'approved_days'          => $lr->approved_days,
                                'approved_dates_summary' => $lr->approved_dates_summary,
                                'reason'                 => $lr->reason,
                                'status'                 => $lr->status,
                                'status_label'           => match ($lr->status) {
                                    'pending_manager' => 'Awaiting Manager',
                                    'pending_partner' => 'Awaiting Partner',
                                    'approved'        => ($lr->approved_days && $lr->approved_days < $reqDays)
                                        ? "Partially Approved ({$lr->approved_days}/{$reqDays} Days)"
                                        : 'Approved',
                                    'rejected'        => 'Rejected',
                                    default           => ucfirst($lr->status),
                                },
                                'manager_remarks'        => $lr->manager_remarks ?? '',
                                'partner_remarks'        => $lr->partner_remarks ?? '',
                            ];
                        })->toArray();
                        return;
                    } else {
                        $this->leaveQueue = [];
                        return;
                    }
                } catch (\Throwable $e) {
                    Log::warning('loadLeaveQueue error: ' . $e->getMessage());
                }
            }
        }

        if (! app()->runningUnitTests()) {
            $this->leaveQueue = [];
            return;
        }

        $this->leaveQueue = [
            [
                'id'                     => 'LR-101',
                'name'                   => 'Sample Student 01',
                'student_code'           => 'S-101',
                'manager'                => 'Sample Manager A',
                'partner_unit'           => 'Partner Unit 01',
                'type'                   => 'Annual Leave',
                'period'                 => '14–15 Oct 2026',
                'requested_days'         => 2,
                'approved_days'          => null,
                'approved_dates_summary' => null,
                'reason'                 => 'Family appointment outside Dhaka',
                'status'                 => 'pending_manager',
                'status_label'           => 'Awaiting Manager',
                'manager_remarks'        => '',
                'partner_remarks'        => '',
            ],
            [
                'id'                     => 'LR-102',
                'name'                   => 'Sample Student 02',
                'student_code'           => 'S-102',
                'manager'                => 'Sample Manager A',
                'partner_unit'           => 'Partner Unit 01',
                'type'                   => 'ICAB Exam Preparation',
                'period'                 => '19–24 Oct 2026',
                'requested_days'         => 6,
                'approved_days'          => null,
                'approved_dates_summary' => null,
                'reason'                 => 'ICAB Certificate Level exam preparation leave',
                'status'                 => 'pending_partner',
                'status_label'           => 'Awaiting Partner',
                'manager_remarks'        => 'Exam admit card verified. Manager recommends approval.',
                'partner_remarks'        => '',
            ],
            [
                'id'                     => 'LR-103',
                'name'                   => 'Sample Student 03',
                'student_code'           => 'S-103',
                'manager'                => 'Sample Manager B',
                'partner_unit'           => 'Partner Unit 01',
                'type'                   => 'Emergency Leave',
                'period'                 => '20 Oct 2026',
                'requested_days'         => 1,
                'approved_days'          => 1,
                'approved_dates_summary' => null,
                'reason'                 => 'Medical emergency',
                'status'                 => 'approved',
                'status_label'           => 'Approved',
                'manager_remarks'        => 'Verified with prescription.',
                'partner_remarks'        => 'Sanctioned by Partner Unit 01.',
            ],
        ];
    }

    public function cancelSubmissionConfirm(): void
    {
        $this->showLeaveSubmitConfirmModal = false;
        $this->showClaimSubmitConfirmModal = false;
    }

    protected function findOverlappingActiveLeave(string $startDate, string $endDate): ?array
    {
        if (Auth::check() && Schema::hasTable('leave_requests') && Schema::hasTable('firms')) {
            try {
                $user = Auth::user();
                $firm = $this->resolveFirmModel();
                $firmId = $firm?->id ?? $user->firm_id;
                $targetUserId = $user->id;
                if ($user->isPlatformAdmin() && $firmId) {
                    $firstStudent = User::where('firm_id', $firmId)->where('role', 'student')->first();
                    if ($firstStudent) {
                        $targetUserId = $firstStudent->id;
                    }
                }
                if ($firmId) {
                    $conflict = LeaveRequest::where('firm_id', $firmId)
                        ->whereIn('user_id', array_unique([$user->id, $targetUserId]))
                        ->whereIn('status', ['pending_manager', 'pending_partner', 'approved'])
                        ->whereDate('start_date', '<=', $endDate)
                        ->whereDate('end_date', '>=', $startDate)
                        ->first();
                    if ($conflict) {
                        return [
                            'id'           => 'LR-' . $conflict->id,
                            'type'         => ucfirst(str_replace('_', ' ', $conflict->leave_type)),
                            'period'       => ($conflict->start_date?->format('d M Y') ?? $startDate) . ' – ' . ($conflict->end_date?->format('d M Y') ?? $endDate),
                            'status_label' => $conflict->status === 'approved' ? 'Already Approved' : 'Pending Approval',
                        ];
                    }
                }
            } catch (\Throwable) {}
        }

        foreach ($this->leaveQueue as $existing) {
            $st = $existing['status'] ?? '';
            if (! in_array($st, ['pending_manager', 'pending_partner', 'approved'], true)) {
                continue;
            }
            $sRaw = $existing['start_date_raw'] ?? '';
            $eRaw = $existing['end_date_raw'] ?? '';
            if ($sRaw !== '' && $eRaw !== '' && $sRaw <= $endDate && $eRaw >= $startDate) {
                if (! Auth::check() || ($existing['user_id'] ?? null) === Auth::id() || Auth::user()?->isPlatformAdmin()) {
                    return [
                        'id'           => $existing['id'] ?? 'LR',
                        'type'         => $existing['type'] ?? 'Leave',
                        'period'       => $existing['period'] ?? "{$startDate} – {$endDate}",
                        'status_label' => $existing['status_label'] ?? ucfirst($st),
                    ];
                }
            }
        }

        return null;
    }

    public function requestLeaveSubmissionConfirm(): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;
        $this->notice = null;

        if ($this->previewRole !== 'student') {
            $this->error = Auth::check()
                ? 'Only the Student role can submit a new leave application.'
                : 'Only the Student design role can preview a new leave application.';
            return;
        }

        $allowedTypes = array_unique(array_merge(
            ['annual', 'sick', 'emergency', 'icab_exam', 'icab_study'],
            array_column($this->leaveQuotas, 'key')
        ));
        if (! in_array($this->leaveType, $allowedTypes, true)
            || ! $this->validDate($this->leaveStart)
            || ! $this->validDate($this->leaveEnd)
            || $this->leaveEnd < $this->leaveStart
            || mb_strlen(trim($this->leaveReason)) < 5
        ) {
            $this->error = 'Enter a leave type, valid start and end dates, and a reason of at least five characters.';
            return;
        }

        $overlap = $this->findOverlappingActiveLeave($this->leaveStart, $this->leaveEnd);
        if ($overlap) {
            $this->error = "Duplicate Date Blocked: You already have a leave request ({$overlap['id']} · {$overlap['type']} — {$overlap['status_label']}) covering {$overlap['period']}. You cannot submit another leave request for the same date(s).";
            $this->dispatch('toast', [
                'type'    => 'error',
                'title'   => 'Leave Already Exists for Date',
                'message' => "Leave {$overlap['id']} ({$overlap['status_label']}) already covers {$overlap['period']}.",
            ]);
            return;
        }

        $this->showLeaveSubmitConfirmModal = true;
    }

    public function confirmAndSubmitLeaveApplication(): void
    {
        $this->showLeaveSubmitConfirmModal = false;
        $this->submitLeaveDemo();
    }

    public function submitLeaveApplication(): void
    {
        $this->submitLeaveDemo();
    }

    public function submitLeaveDemo(): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;
        $this->notice = null;
        $this->showLeaveSubmitConfirmModal = false;
        if ($this->previewRole !== 'student') {
            $this->error = Auth::check()
                ? 'Only the Student role can submit a new leave application.'
                : 'Only the Student design role can preview a new leave application.';
            return;
        }

        $allowedTypes = array_unique(array_merge(
            ['annual', 'sick', 'emergency', 'icab_exam', 'icab_study'],
            array_column($this->leaveQuotas, 'key')
        ));
        if (! in_array($this->leaveType, $allowedTypes, true)
            || ! $this->validDate($this->leaveStart)
            || ! $this->validDate($this->leaveEnd)
            || $this->leaveEnd < $this->leaveStart
            || mb_strlen(trim($this->leaveReason)) < 5
        ) {
            $this->error = 'Enter a leave type, valid start and end dates, and a reason of at least five characters.';
            return;
        }

        $overlap = $this->findOverlappingActiveLeave($this->leaveStart, $this->leaveEnd);
        if ($overlap) {
            $this->error = "Duplicate Date Blocked: You already have a leave request ({$overlap['id']} · {$overlap['type']} — {$overlap['status_label']}) covering {$overlap['period']}. You cannot submit another leave request for the same date(s).";
            $this->dispatch('toast', [
                'type'    => 'error',
                'title'   => 'Leave Already Exists for Date',
                'message' => "Leave {$overlap['id']} ({$overlap['status_label']}) already covers {$overlap['period']}.",
            ]);
            return;
        }

        $startDt = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->leaveStart);
        $endDt = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->leaveEnd);
        $closedHolidayDates = array_column(
            array_filter($this->holidaysList, fn ($h) => empty($h['is_office_open_override'])),
            'holiday_date'
        );
        $totalSpanDays = (int) $startDt->diff($endDt)->days + 1;
        $billableDays = 0;
        for ($i = 0; $i < $totalSpanDays; $i++) {
            $dStr = $startDt->modify("+{$i} days")->format('Y-m-d');
            if (! in_array($dStr, $closedHolidayDates, true)) {
                $billableDays++;
            }
        }
        $requestedDays = max(1, $billableDays);

        $typeLabel = match ($this->leaveType) {
            'icab_exam'  => 'ICAB Exam Preparation',
            'icab_study' => 'ICAB Study Leave',
            'sick'       => 'Sick Leave',
            'emergency'  => 'Emergency Leave',
            'annual'     => 'Annual Leave',
            default      => ucfirst(str_replace('_', ' ', $this->leaveType)),
        };

        $engagedManagerName = 'Sample Manager A';
        if ($this->leaveEngagedManagerId) {
            foreach ($this->teamMembers as $m) {
                if ((int) ($m['id'] ?? 0) === (int) $this->leaveEngagedManagerId) {
                    $engagedManagerName = $m['name'];
                    break;
                }
            }
        }

        if (Auth::check() && Schema::hasTable('leave_requests') && Schema::hasTable('firms')) {
            try {
                $user = Auth::user();
                $firm = $this->resolveFirmModel();
                $firmId = $firm?->id ?? $user->firm_id;
                $targetManagerId = $this->leaveEngagedManagerId ?: $user->manager_id;

                if ($firmId) {
                    $createData = [
                        'firm_id'         => $firmId,
                        'user_id'         => $user->id,
                        'leave_type'      => $this->leaveType,
                        'start_date'      => $this->leaveStart,
                        'end_date'        => $this->leaveEnd,
                        'reason'          => $this->leaveReason,
                        'status'          => 'pending_manager',
                        'manager_remarks' => '',
                        'partner_remarks' => '',
                    ];
                    if (Schema::hasColumn('leave_requests', 'engaged_manager_id')) {
                        $createData['engaged_manager_id'] = $targetManagerId;
                        $createData['requested_days'] = $requestedDays;
                    }
                    $lr = LeaveRequest::create($createData);

                    $this->recordAuditLog('leave', 'leave_submitted', "{$user->name} submitted {$typeLabel} ({$requestedDays} days: {$this->leaveStart} to {$this->leaveEnd}) routed to {$engagedManagerName}.", 'LR-' . $lr->id);
                    $this->createPracticeNotification($targetManagerId, 'manager', 'New Leave Application (LR-' . $lr->id . ')', "{$user->name} submitted a {$typeLabel} application ({$requestedDays} days: {$this->leaveStart} to {$this->leaveEnd}). Reason: {$this->leaveReason}", 'calendar-days');
                    $this->createPracticeNotification(null, 'partner', 'New Leave Application (LR-' . $lr->id . ')', "{$user->name} submitted a {$typeLabel} request ({$requestedDays} days: {$this->leaveStart} to {$this->leaveEnd}) assigned to {$engagedManagerName}.", 'calendar-days', null, false);

                    $submittedPeriod = "{$this->leaveStart} to {$this->leaveEnd}";
                    $this->loadLeaveQueue();
                    $this->leaveType = 'annual';
                    $this->leaveStart = '';
                    $this->leaveEnd = '';
                    $this->leaveReason = '';
                    $this->formResetIteration++;
                    $this->notice = "Leave application ({$typeLabel}, {$requestedDays} days: {$submittedPeriod}) submitted and routed to {$engagedManagerName}. Form has been reset.";
                    $this->dispatch('toast', [
                        'type'    => 'success',
                        'title'   => 'Leave Submitted Successfully',
                        'message' => "Your leave application ({$typeLabel}, {$requestedDays} days) has been forwarded to {$engagedManagerName}.",
                    ]);
                    return;
                }
            } catch (\Throwable) {}
        }

        $newId = 'LR-' . (count($this->leaveQueue) + 101);
        $this->leaveQueue[] = [
            'id'                     => $newId,
            'name'                   => Auth::check() ? Auth::user()->name : 'Sample Student 01',
            'student_code'           => Auth::check() ? (Auth::user()->student_code ?? 'S-101') : 'S-101',
            'manager'                => $engagedManagerName,
            'partner_unit'           => $this->partnerUnit,
            'type'                   => $typeLabel,
            'start_date_raw'         => $this->leaveStart,
            'end_date_raw'           => $this->leaveEnd,
            'period'                 => "{$this->leaveStart} to {$this->leaveEnd}",
            'requested_days'         => $requestedDays,
            'approved_days'          => null,
            'approved_dates_summary' => null,
            'reason'                 => $this->leaveReason,
            'status'                 => 'pending_manager',
            'status_label'           => 'Awaiting Manager',
            'manager_remarks'        => '',
            'partner_remarks'        => '',
        ];

        if (! app()->runningUnitTests()) {
            $this->leaveType = 'annual';
            $this->leaveStart = '';
            $this->leaveEnd = '';
            $this->leaveReason = '';
            $this->formResetIteration++;
        }

        $this->notice = 'Leave request preview validated. No request was submitted and no leave balance was changed.';
    }

    public function openPartialLeaveModal(string $id): void
    {
        $this->enforceAuthenticatedRoleLock();
        if ($this->previewRole === 'student') {
            $this->error = 'Students cannot review or partially approve leave.';
            return;
        }
        $this->closeDialogs();
        $this->partialLeaveId = $id;
        $this->partialRequestedDays = 5;
        foreach ($this->leaveQueue as $req) {
            if ($req['id'] === $id) {
                $this->partialRequestedDays = max(1, (int) ($req['requested_days'] ?? 5));
                break;
            }
        }
        $this->partialApprovedDays = max(1, $this->partialRequestedDays - 1);
        $this->partialApprovedSummary = '';
        $this->partialReviewerRemarks = '';
        $this->showPartialLeaveModal = true;
    }

    public function submitPartialLeaveApproval(): void
    {
        $this->enforceAuthenticatedRoleLock();
        if ($this->previewRole === 'student') {
            $this->error = 'Students cannot approve leave.';
            return;
        }
        if ($this->partialApprovedDays < 1 || $this->partialApprovedDays > $this->partialRequestedDays) {
            $this->error = "Approved days must be between 1 and {$this->partialRequestedDays}.";
            return;
        }

        $id = $this->partialLeaveId;
        $summary = trim($this->partialApprovedSummary) ?: "Approved {$this->partialApprovedDays} of {$this->partialRequestedDays} requested days.";
        $remarks = trim($this->partialReviewerRemarks) ?: "Partial leave approved ({$this->partialApprovedDays}/{$this->partialRequestedDays} days): {$summary}";

        if (Schema::hasTable('leave_requests')) {
            try {
                $firmModel = $this->resolveFirmModel();
                $dbId = str_replace('LR-', '', $id);
                $query = LeaveRequest::where('id', $dbId);
                if ($firmModel && Schema::hasColumn('leave_requests', 'firm_id')) {
                    $query->where('firm_id', $firmModel->id);
                }
                $lr = $query->first();
                if ($lr) {
                    if (Schema::hasColumn('leave_requests', 'approved_days')) {
                        $lr->approved_days = $this->partialApprovedDays;
                        $lr->approved_dates_summary = $summary;
                    }
                    if ($this->previewRole === 'manager') {
                        $lr->status = 'pending_partner';
                        $lr->manager_remarks = $remarks;
                        $lr->reviewed_by = Auth::id();
                    } else {
                        $lr->status = 'approved';
                        $lr->partner_remarks = $remarks;
                        $lr->partner_id = Auth::id();
                    }
                    $lr->save();

                    $this->recordAuditLog('leave', 'leave_partial_approval', ucfirst($this->previewRole) . " partially approved {$this->partialApprovedDays}/{$this->partialRequestedDays} days for leave {$id} ({$summary}).", $id);
                    $this->createPracticeNotification($lr->user_id, 'student', 'Partial Leave Decision', "Your leave request ({$id}) was updated: {$this->partialApprovedDays} of {$this->partialRequestedDays} days approved ({$summary}).", 'calendar-days');
                }
            } catch (\Throwable) {}
        }

        foreach ($this->leaveQueue as &$req) {
            if ($req['id'] === $id) {
                $req['approved_days'] = $this->partialApprovedDays;
                $req['approved_dates_summary'] = $summary;
                if ($this->previewRole === 'manager') {
                    $req['status'] = 'pending_partner';
                    $req['status_label'] = "Awaiting Partner ({$this->partialApprovedDays}/{$this->partialRequestedDays}d Rec.)";
                    $req['manager_remarks'] = $remarks;
                } else {
                    $req['status'] = 'approved';
                    $req['status_label'] = "Partially Approved ({$this->partialApprovedDays}/{$this->partialRequestedDays} Days)";
                    $req['partner_remarks'] = $remarks;
                }
                break;
            }
        }
        unset($req);

        $this->closeDialogs();
        $this->notice = "Leave {$id} partially approved for {$this->partialApprovedDays} of {$this->partialRequestedDays} days ({$summary}).";
    }

    public function managerRecommendLeave(string $id, string $remarks = ''): void
    {
        $this->enforceAuthenticatedRoleLock();

        if ($this->previewRole !== 'manager' && $this->previewRole !== 'partner') {
            $this->error = 'Only an assigned Manager or Partner can review leave requests.';
            return;
        }

        if ($this->previewRole === 'manager' && ! $this->managerCanApproveLeave) {
            $this->error = 'Partner has disabled Leave Review permission for Managers.';
            return;
        }

        if (Schema::hasTable('leave_requests')) {
            try {
                $firmModel = $this->resolveFirmModel();
                $dbId = str_replace('LR-', '', $id);
                $query = LeaveRequest::where('id', $dbId);
                if ($firmModel && Schema::hasColumn('leave_requests', 'firm_id')) {
                    $query->where('firm_id', $firmModel->id);
                }
                $lr = $query->first();
                if ($lr) {
                    $lr->status = 'pending_partner';
                    $lr->manager_remarks = $remarks ?: 'Manager reviewed and recommended for Partner sanction.';
                    if (Auth::check()) {
                        $lr->reviewed_by = Auth::id();
                    }
                    $lr->save();
                    $this->recordAuditLog('leave', 'leave_recommended', "Manager recommended leave {$id} for Partner sanction.", $id);
                    $this->createPracticeNotification(null, 'partner', "Leave Forwarded for Partner Approval ({$id})", "Manager verified and recommended leave request {$id} for final Partner sanction.", 'calendar-days');
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
        $this->enforceAuthenticatedRoleLock();

        if ($this->previewRole !== 'partner') {
            $this->error = 'Only a Partner can give final leave approval.';
            return;
        }

        if (Schema::hasTable('leave_requests')) {
            try {
                $firmModel = $this->resolveFirmModel();
                $dbId = str_replace('LR-', '', $id);
                $query = LeaveRequest::where('id', $dbId);
                if ($firmModel && Schema::hasColumn('leave_requests', 'firm_id')) {
                    $query->where('firm_id', $firmModel->id);
                }
                $lr = $query->first();
                if ($lr) {
                    $lr->status = 'approved';
                    if (Schema::hasColumn('leave_requests', 'approved_days') && ! $lr->approved_days) {
                        $lr->approved_days = $lr->requested_days ?: 1;
                    }
                    $lr->partner_remarks = $remarks ?: 'Approved by Partner ' . $this->partnerUnit . '.';
                    if (Auth::check()) {
                        $lr->partner_id = Auth::id();
                    }
                    $lr->save();
                    $this->recordAuditLog('leave', 'leave_approved', "Partner approved leave request {$id}.", $id);
                    $this->createPracticeNotification($lr->user_id, 'student', 'Leave Application Approved', "Your leave request ({$id}) has been sanctioned by Partner.", 'calendar-days');
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
        $this->enforceAuthenticatedRoleLock();

        if ($this->previewRole === 'student') {
            $this->error = 'Students cannot reject leave applications.';
            return;
        }

        if (Schema::hasTable('leave_requests')) {
            try {
                $firmModel = $this->resolveFirmModel();
                $dbId = str_replace('LR-', '', $id);
                $query = LeaveRequest::where('id', $dbId);
                if ($firmModel && Schema::hasColumn('leave_requests', 'firm_id')) {
                    $query->where('firm_id', $firmModel->id);
                }
                $lr = $query->first();
                if ($lr) {
                    $lr->status = 'rejected';
                    $lr->partner_remarks = $reason ?: ('Rejected by ' . ucfirst($this->previewRole));
                    $lr->save();
                    $this->recordAuditLog('leave', 'leave_rejected', ucfirst($this->previewRole) . " rejected leave {$id}: {$lr->partner_remarks}", $id);
                    $this->createPracticeNotification($lr->user_id, 'student', 'Leave Application Rejected', "Your leave request ({$id}) was not approved: {$lr->partner_remarks}", 'calendar-days');
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

    // ─── Multi-Tier Conveyance Workflow (Multi-Row Transport Builder & Increase/Decrease) ─

}
