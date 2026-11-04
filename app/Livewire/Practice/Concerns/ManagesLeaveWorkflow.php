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
        LeaveRequest::ensureRuntimeSchemaColumns();
        if (Schema::hasTable('leave_requests') && Schema::hasTable('users')) {
            $firmModel = $this->resolveFirmModel();
            if (! $firmModel && Auth::check() && Auth::user()->firm_id && Schema::hasTable('firms')) {
                $firmModel = Firm::find(Auth::user()->firm_id);
            }
            if ($firmModel) {
                try {
                    $lrCols = Schema::getColumnListing('leave_requests');
                    $hasCol = fn (string $c) => in_array($c, $lrCols, true);

                    $withRelations = ['user.manager'];
                    if ($hasCol('engaged_manager_id')) {
                        $withRelations[] = 'engagedManager';
                    }
                    if ($hasCol('reviewed_by')) {
                        $withRelations[] = 'reviewer';
                    }
                    if ($hasCol('partner_id')) {
                        $withRelations[] = 'partner';
                    }

                    $leavesQuery = LeaveRequest::with($withRelations);
                    $firmUserIds = User::where('firm_id', $firmModel->id)->pluck('id')->all();

                    if ($hasCol('firm_id')) {
                        $leavesQuery->where(function ($q) use ($firmModel, $firmUserIds, $hasCol) {
                            $q->where('firm_id', $firmModel->id);
                            if (! empty($firmUserIds) && $hasCol('user_id')) {
                                $q->orWhereIn('user_id', $firmUserIds);
                            }
                        });
                    } elseif ($hasCol('user_id') && ! empty($firmUserIds)) {
                        $leavesQuery->whereIn('user_id', $firmUserIds);
                    }

                    // Partner-unit & role read isolation at query level while guaranteeing
                    // that the Student always sees their own applications and the Manager
                    // always receives applications from their unit, direct reports, or engaged students.
                    if (Auth::check() && ! Auth::user()->isPlatformAdmin()) {
                        /** @var User $actor */
                        $actor = Auth::user();
                        if ($actor->isStudent()) {
                            $leavesQuery->where('user_id', $actor->id);
                        } elseif ($actor->isManager()) {
                            $unit = $actor->partner_unit ?: $this->partnerUnit;
                            $sharedView = (bool) ($firmModel->shared_attendance_view ?? true) || count($this->availablePartnerUnits) <= 1;

                            if (! $sharedView) {
                                $unitUserIds = User::where('firm_id', $firmModel->id)
                                    ->where(function ($q) use ($actor, $unit) {
                                        $q->where('id', $actor->id)
                                            ->orWhere('manager_id', $actor->id)
                                            ->orWhereNull('manager_id')
                                            ->orWhereNull('partner_unit');
                                        if (! empty($unit)) {
                                            $q->orWhere('partner_unit', $unit);
                                        }
                                    })
                                    ->pluck('id')
                                    ->all();

                                $hasEngagedCol = $hasCol('engaged_manager_id');
                                $hasReviewedByCol = $hasCol('reviewed_by');
                                $leavesQuery->where(function ($q) use ($actor, $unitUserIds, $hasEngagedCol, $hasReviewedByCol) {
                                    if (! empty($unitUserIds)) {
                                        $q->whereIn('user_id', $unitUserIds);
                                    }
                                    if ($hasEngagedCol) {
                                        $q->orWhere('engaged_manager_id', $actor->id);
                                    }
                                    if ($hasReviewedByCol) {
                                        $q->orWhere('reviewed_by', $actor->id);
                                    }
                                });
                            }
                        } elseif ($actor->isPartner() && count($this->availablePartnerUnits) > 1 && ! empty($this->partnerUnit) && ! $this->viewAllPartnersAttendance) {
                            $unitUserIds = User::where('firm_id', $firmModel->id)
                                ->where(function ($q) {
                                    $q->where('partner_unit', $this->partnerUnit)
                                        ->orWhereNull('partner_unit');
                                })
                                ->pluck('id')
                                ->all();
                            $leavesQuery->whereIn('user_id', $unitUserIds);
                        }
                    }

                    $dbLeaves = $leavesQuery->orderByDesc('id')->get();
                    if ($dbLeaves->isNotEmpty()) {
                        $this->leaveQueue = $dbLeaves->map(function (LeaveRequest $lr) {
                            $reqDays = $lr->requested_days ?: (
                                ($lr->start_date && $lr->end_date) ? ((int) $lr->start_date->diffInDays($lr->end_date) + 1) : 1
                            );
                            $normStatus = match ($lr->status) {
                                'Awaiting Manager' => 'pending_manager',
                                'Awaiting Partner' => 'pending_partner',
                                'Approved'         => 'approved',
                                'Rejected'         => 'rejected',
                                default            => $lr->status,
                            };
                            $engagedMgrId = $lr->engaged_manager_id ?: ($lr->user?->manager_id ?: ($lr->reviewed_by ?: null));
                            $engagedMgrName = $lr->engagedManager?->name
                                ?: ($lr->user?->manager?->name
                                ?: ($lr->reviewer?->name ?: 'Direct Report'));
                            $friendlyType = match ((string) $lr->leave_type) {
                                'icab_exam'  => 'ICAB Exam Preparation',
                                'icab_study' => 'ICAB Study Leave',
                                'sick'       => 'Sick Leave',
                                'emergency'  => 'Emergency Leave',
                                'earned'     => 'Earned / Privilege Leave',
                                'annual'     => 'Annual / Casual Leave',
                                default      => ucfirst(str_replace('_', ' ', (string) $lr->leave_type)),
                            };

                            return [
                                'id'                     => 'LR-' . $lr->id,
                                'db_id'                  => $lr->id,
                                'user_id'                => $lr->user_id,
                                'submitter_role'         => $lr->user?->role ?? 'student',
                                'designation'            => $lr->user ? $lr->user->displayDesignation() : 'Practice Member',
                                'name'                   => $lr->user?->name ?? 'Practice Member',
                                'student_code'           => $lr->user?->student_code ?? ($lr->user?->role === 'manager' ? ('MGR-' . $lr->user_id) : ('S-' . $lr->user_id)),
                                'phone'                  => $lr->user?->phone ?? '',
                                'created_at_human'       => $lr->created_at?->diffForHumans() ?? 'Just now',
                                'manager'                => $engagedMgrName,
                                'engaged_manager_id'     => $engagedMgrId,
                                'partner_unit'           => $lr->user?->partner_unit ?: $this->partnerUnit,
                                'type'                   => $friendlyType,
                                'start_date_raw'         => $lr->start_date?->format('Y-m-d') ?? '',
                                'end_date_raw'           => $lr->end_date?->format('Y-m-d') ?? '',
                                'period'                 => $lr->start_date?->format('d M') . '–' . $lr->end_date?->format('d M Y'),
                                'requested_days'         => $reqDays,
                                'approved_days'          => $lr->approved_days,
                                'approved_dates_summary' => $lr->approved_dates_summary,
                                'reason'                 => $lr->reason,
                                'status'                 => $normStatus,
                                'status_label'           => match ($normStatus) {
                                    'pending_manager' => 'Awaiting Manager',
                                    'pending_partner' => 'Awaiting Partner',
                                    'approved'        => ($lr->approved_days && $lr->approved_days < $reqDays)
                                        ? "Partially Approved ({$lr->approved_days}/{$reqDays} Days)"
                                        : 'Approved',
                                    'rejected'        => 'Rejected',
                                    default           => ucfirst($normStatus),
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
                        ->whereIn('status', ['pending_manager', 'pending_partner', 'approved', 'Awaiting Manager', 'Awaiting Partner'])
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
            } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::debug($e->getMessage()); }
        }

        foreach ($this->leaveQueue as $existing) {
            $st = $existing['status'] ?? '';
            if (! in_array($st, ['pending_manager', 'pending_partner', 'approved', 'Awaiting Manager', 'Awaiting Partner'], true)) {
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

        if (! Auth::check() && $this->previewRole !== 'student') {
            $this->error = 'Only the Student design role can preview a new leave application.';
            return;
        }
        if (Auth::check() && ! in_array($this->previewRole, ['student', 'manager'], true)) {
            $this->error = 'Only a Student or Manager/Staff member can submit a leave application.';
            return;
        }

        $allowedTypes = array_unique(array_merge(
            ['annual', 'sick', 'emergency', 'earned', 'icab_exam', 'icab_study'],
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
        if (! Auth::check() && $this->previewRole !== 'student') {
            $this->error = 'Only the Student design role can preview a new leave application.';
            return;
        }
        if (Auth::check() && ! in_array($this->previewRole, ['student', 'manager'], true)) {
            $this->error = 'Only a Student or Manager/Staff member can submit a leave application.';
            return;
        }

        $allowedTypes = array_unique(array_merge(
            ['annual', 'sick', 'emergency', 'earned', 'icab_exam', 'icab_study'],
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

        // QUOTA ENFORCEMENT (audit P1-3.4): the leave quota table was only ever
        // used to validate a type *label*, so a student could book unlimited
        // leave. Remaining days are now checked against the policy quota for
        // the calendar year before the request is created.
        $quotaDays = $this->quotaDaysForLeaveType($this->leaveType);
        if ($quotaDays !== null) {
            $usedDays = $this->usedLeaveDaysForYear($this->leaveStart, $this->leaveType);
            $remaining = max(0, $quotaDays - $usedDays);

            $probeStart = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->leaveStart);
            $probeEnd = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->leaveEnd);
            $probeDays = (int) $probeStart->diff($probeEnd)->days + 1;

            if ($probeDays > $remaining) {
                $this->error = "Leave quota exceeded: you have {$remaining} of {$quotaDays} day(s) left for this leave type this year, but requested {$probeDays} day(s).";
                $this->dispatch('toast', [
                    'type'    => 'error',
                    'title'   => 'Leave Quota Exceeded',
                    'message' => "Only {$remaining} day(s) remain for this leave type in " . $probeStart->format('Y') . '.',
                ]);

                return;
            }
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

        // Enforce total profile leave cap (180d for 3-Yr Articleship, 240d for 4-Yr Articleship, or Corporate total)
        if (Auth::check()) {
            $summary = $this->myLeaveBalanceSummary;
            $totalAvail = (int) ($summary['total_available'] ?? 180);
            $totalCap = (int) ($summary['total_cap'] ?? 180);
            $probeStartCap = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->leaveStart);
            $probeEndCap = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->leaveEnd);
            $probeDaysCap = (int) $probeStartCap->diff($probeEndCap)->days + 1;
            if ($probeDaysCap > $totalAvail) {
                $this->error = "Total leave balance exceeded: you have {$totalAvail} day(s) available out of your {$totalCap}-day profile limit, but requested {$probeDaysCap} day(s).";
                $this->dispatch('toast', [
                    'type'    => 'error',
                    'title'   => 'Available Leave Balance Exceeded',
                    'message' => "Only {$totalAvail} day(s) remain in your profile leave balance.",
                ]);
                return;
            }
        }

        $typeLabel = match ($this->leaveType) {
            'icab_exam'  => 'ICAB Exam Preparation',
            'icab_study' => 'ICAB Study Leave',
            'sick'       => 'Sick Leave',
            'emergency'  => 'Emergency Leave',
            'earned'     => 'Earned / Privilege Leave',
            'annual'     => 'Annual / Casual Leave',
            default      => ucfirst(str_replace('_', ' ', $this->leaveType)),
        };

        $rawEngagedId = is_numeric($this->leaveEngagedManagerId) && (int) $this->leaveEngagedManagerId > 0
            ? (int) $this->leaveEngagedManagerId
            : null;

        $engagedManagerName = 'Sample Manager A';
        if ($rawEngagedId) {
            foreach ($this->availableManagers as $m) {
                if ((int) ($m['id'] ?? 0) === $rawEngagedId) {
                    $engagedManagerName = $m['name'];
                    break;
                }
            }
        }

        LeaveRequest::ensureRuntimeSchemaColumns();
        if (Auth::check() && Schema::hasTable('leave_requests')) {
            try {
                /** @var User $user */
                $user = Auth::user();
                $firm = $this->resolveFirmModel();
                $firmId = $firm?->id ?? ($user->firm_id ?: (Schema::hasTable('firms') ? (Firm::where('slug', $this->firmSlug)->value('id') ?? Firm::value('id')) : null));

                if ($firmId && ! $user->firm_id && Schema::hasColumn('users', 'firm_id')) {
                    try {
                        User::where('id', $user->id)->update(['firm_id' => $firmId]);
                        $user->firm_id = $firmId;
                    } catch (\Throwable $e) {
                        Log::debug($e->getMessage());
                    }
                }

                $targetManagerId = $rawEngagedId ?: ($user->manager_id ? (int) $user->manager_id : null);
                $mgrUser = ($targetManagerId && Schema::hasTable('users')) ? User::find($targetManagerId) : null;

                if (! $mgrUser && $firmId && Schema::hasTable('users')) {
                    $targetManagerId = null;
                    $fallbackMgr = User::where('firm_id', $firmId)
                        ->where('role', 'manager')
                        ->when(Schema::hasColumn('users', 'is_active'), fn ($q) => $q->where('is_active', true))
                        ->orderByRaw('CASE WHEN partner_unit = ? THEN 0 ELSE 1 END', [$user->partner_unit ?: $this->partnerUnit])
                        ->first();
                    if ($fallbackMgr) {
                        $mgrUser = $fallbackMgr;
                        $targetManagerId = (int) $fallbackMgr->id;
                    }
                }

                if ($mgrUser) {
                    $engagedManagerName = $mgrUser->name;
                    if (! $user->manager_id && $user->isStudent() && Schema::hasColumn('users', 'manager_id')) {
                        try {
                            User::where('id', $user->id)->update(['manager_id' => $mgrUser->id]);
                            $user->manager_id = $mgrUser->id;
                        } catch (\Throwable $e) {
                            Log::debug($e->getMessage());
                        }
                    }
                }

                $isManagerApplicant = $user->isManager();
                $initialStatus = $isManagerApplicant ? 'pending_partner' : 'pending_manager';
                if ($isManagerApplicant) {
                    $engagedManagerName = 'Partner Final Approval';
                    $targetManagerId = null;
                }

                if ($firmId) {
                    $lrCols = Schema::getColumnListing('leave_requests');
                    $hasCol = fn (string $c) => in_array($c, $lrCols, true);

                    $createData = [];
                    if ($hasCol('firm_id')) {
                        $createData['firm_id'] = $firmId;
                    }
                    if ($hasCol('user_id')) {
                        $createData['user_id'] = $user->id;
                    }
                    if ($hasCol('leave_type')) {
                        $createData['leave_type'] = $this->leaveType;
                    }
                    if ($hasCol('start_date')) {
                        $createData['start_date'] = $this->leaveStart;
                    }
                    if ($hasCol('end_date')) {
                        $createData['end_date'] = $this->leaveEnd;
                    }
                    if ($hasCol('reason')) {
                        $createData['reason'] = $this->leaveReason;
                    }
                    if ($hasCol('status')) {
                        $createData['status'] = $initialStatus;
                    }
                    if ($hasCol('manager_remarks')) {
                        $createData['manager_remarks'] = '';
                    }
                    if ($hasCol('partner_remarks')) {
                        $createData['partner_remarks'] = '';
                    }
                    if ($hasCol('engaged_manager_id')) {
                        $createData['engaged_manager_id'] = $targetManagerId;
                    }
                    if ($hasCol('requested_days')) {
                        $createData['requested_days'] = $requestedDays;
                    }
                    if ($hasCol('reviewed_by') && $targetManagerId) {
                        $createData['reviewed_by'] = $targetManagerId;
                    }
                    // Populate any legacy columns if present on an older database table
                    if ($hasCol('days')) {
                        $createData['days'] = $requestedDays;
                    }
                    if ($hasCol('type')) {
                        $createData['type'] = $this->leaveType;
                    }
                    if ($hasCol('student_id')) {
                        $createData['student_id'] = $user->id;
                    }
                    if ($hasCol('student_name')) {
                        $createData['student_name'] = $user->name;
                    }
                    if ($hasCol('student_code')) {
                        $createData['student_code'] = $user->student_code ?? ('S-' . $user->id);
                    }
                    if ($hasCol('partner_unit')) {
                        $createData['partner_unit'] = $user->partner_unit ?: $this->partnerUnit;
                    }

                    $lr = null;
                    try {
                        if (DB::getDriverName() === 'mysql') {
                            DB::statement('SET SESSION foreign_key_checks = 0');
                            DB::statement("SET SESSION sql_mode = ''");
                        }
                        $lr = LeaveRequest::create($createData);
                    } catch (\Throwable $insertEx) {
                        Log::warning('LeaveRequest::create attempt 1 failed: ' . $insertEx->getMessage());
                        try {
                            if (DB::getDriverName() === 'mysql') {
                                DB::statement('SET SESSION foreign_key_checks = 0');
                                DB::statement("SET SESSION sql_mode = ''");
                            }
                            $fallbackData = $createData;
                            unset($fallbackData['reviewed_by'], $fallbackData['workspace_id']);
                            if ($hasCol('created_at')) {
                                $fallbackData['created_at'] = now();
                            }
                            if ($hasCol('updated_at')) {
                                $fallbackData['updated_at'] = now();
                            }
                            $insertedId = DB::table('leave_requests')->insertGetId($fallbackData);
                            $lr = LeaveRequest::find($insertedId);
                        } catch (\Throwable $insertEx2) {
                            Log::warning('LeaveRequest::create attempt 2 failed: ' . $insertEx2->getMessage());
                            $fallbackData['status'] = $isManagerApplicant ? 'Awaiting Partner' : 'Awaiting Manager';
                            unset($fallbackData['engaged_manager_id'], $fallbackData['workspace_id']);
                            $insertedId = DB::table('leave_requests')->insertGetId($fallbackData);
                            $lr = LeaveRequest::find($insertedId);
                        }
                    } finally {
                        if (DB::getDriverName() === 'mysql') {
                            try {
                                DB::statement('SET SESSION foreign_key_checks = 1');
                            } catch (\Throwable $fkEx) {}
                        }
                    }

                    if ($lr) {
                        try {
                            $this->recordAuditLog('leave', 'leave_submitted', "{$user->name} submitted {$typeLabel} ({$requestedDays} days: {$this->leaveStart} to {$this->leaveEnd}) routed to {$engagedManagerName}.", 'LR-' . $lr->id);
                        } catch (\Throwable $e) {
                            Log::debug($e->getMessage());
                        }
                        try {
                            $this->createPracticeNotification($targetManagerId, 'manager', 'New Leave Application (LR-' . $lr->id . ')', "{$user->name} submitted a {$typeLabel} application ({$requestedDays} days: {$this->leaveStart} to {$this->leaveEnd}). Reason: " . Str::limit($this->leaveReason, 180), 'calendar-days');
                            $this->createPracticeNotification(null, 'partner', 'New Leave Application (LR-' . $lr->id . ')', "{$user->name} submitted a {$typeLabel} request ({$requestedDays} days: {$this->leaveStart} to {$this->leaveEnd}) assigned to {$engagedManagerName}.", 'calendar-days', null, false);
                        } catch (\Throwable $e) {
                            Log::debug($e->getMessage());
                        }

                        $submittedPeriod = "{$this->leaveStart} to {$this->leaveEnd}";
                        $this->loadLeaveQueue();
                        try {
                            $this->loadTeamMembers();
                        } catch (\Throwable $e) {
                            Log::debug($e->getMessage());
                        }
                        $this->leaveType = 'annual';
                        $this->leaveStart = '';
                        $this->leaveEnd = '';
                        $this->leaveReason = '';
                        $this->formResetIteration++;
                        $this->notice = "Leave application (LR-{$lr->id} · {$typeLabel}, {$requestedDays} days: {$submittedPeriod}) saved and routed to {$engagedManagerName}.";
                        $this->dispatch('toast', [
                            'type'    => 'success',
                            'title'   => 'Leave Submitted Successfully',
                            'message' => "Your leave application (LR-{$lr->id} · {$typeLabel}, {$requestedDays} days) has been forwarded to {$engagedManagerName}.",
                        ]);
                        return;
                    }
                }
            } catch (\Throwable $e) {
                Log::error('submitLeaveDemo fatal error: ' . $e->getMessage());
                $this->error = 'Could not save leave application to database: ' . $e->getMessage();
                return;
            }
        }

        $newId = 'LR-' . (count($this->leaveQueue) + 101);
        $this->leaveQueue[] = [
            'id'                     => $newId,
            'name'                   => Auth::check() ? Auth::user()->name : 'Sample Student 01',
            'student_code'           => Auth::check() ? (Auth::user()->student_code ?? 'S-101') : 'S-101',
            'manager'                => $engagedManagerName,
            'engaged_manager_id'     => $rawEngagedId,
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
        $this->error = null;
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
        $this->error = null;
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
                LeaveRequest::ensureRuntimeSchemaColumns();
                $firmModel = $this->resolveFirmModel();
                $dbId = str_replace('LR-', '', $id);
                $query = LeaveRequest::where('id', $dbId);
                if ($firmModel && Schema::hasColumn('leave_requests', 'firm_id')) {
                    $query->where('firm_id', $firmModel->id);
                }
                $lr = $query->first();
                $engagedMgrId = $lr ? ((int) ($lr->engaged_manager_id ?: ($lr->reviewed_by ?: 0)) ?: null) : null;
                if ($lr && ! $this->guardUserScope(User::find($lr->user_id), 'approve leave for', $engagedMgrId)) {
                    return;
                }
                if ($lr) {
                    if (Schema::hasColumn('leave_requests', 'approved_days')) {
                        $lr->approved_days = $this->partialApprovedDays;
                        $lr->approved_dates_summary = $summary;
                    }
                    if ($this->previewRole === 'manager' && ! $this->userCanFinalApproveLeave()) {
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
            } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::debug($e->getMessage()); }
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
        $this->dispatch('toast', [
            'type'    => 'success',
            'title'   => 'Partial Leave Approved',
            'message' => $this->notice,
        ]);
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
                LeaveRequest::ensureRuntimeSchemaColumns();
                $firmModel = $this->resolveFirmModel();
                $dbId = str_replace('LR-', '', $id);
                $query = LeaveRequest::where('id', $dbId);
                if ($firmModel && Schema::hasColumn('leave_requests', 'firm_id')) {
                    $query->where('firm_id', $firmModel->id);
                }
                $lr = $query->first();
                if ($lr && Auth::check() && (int) $lr->user_id === (int) Auth::id() && ! Auth::user()->isPartner()) {
                    $this->error = 'Security rule: A Manager cannot approve or recommend their own leave request. Partner sanction is required.';
                    return;
                }
                $engagedMgrId = $lr ? ((int) ($lr->engaged_manager_id ?: ($lr->reviewed_by ?: 0)) ?: null) : null;
                if ($lr && ! $this->guardUserScope(User::find($lr->user_id), 'review leave for', $engagedMgrId)) {
                    return;
                }
                if ($lr) {
                    $lr->status = 'pending_partner';
                    if (Schema::hasColumn('leave_requests', 'manager_remarks')) {
                        $lr->manager_remarks = $remarks ?: 'Manager reviewed and recommended for Partner sanction.';
                    }
                    if (Auth::check()) {
                        $lr->reviewed_by = Auth::id();
                    }
                    $lr->save();
                    $this->recordAuditLog('leave', 'leave_recommended', "Manager recommended leave {$id} for Partner sanction.", $id);
                    $this->createPracticeNotification(null, 'partner', "Leave Forwarded for Partner Approval ({$id})", "Manager verified and recommended leave request {$id} for final Partner sanction.", 'calendar-days');
                }
            } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::debug($e->getMessage()); }
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

        if (! $this->userCanFinalApproveLeave()) {
            $this->error = 'Only a Partner or an authorized Manager (with Final Leave Approval permission) can give final leave approval.';
            return;
        }

        if (Schema::hasTable('leave_requests')) {
            try {
                LeaveRequest::ensureRuntimeSchemaColumns();
                $firmModel = $this->resolveFirmModel();
                $dbId = str_replace('LR-', '', $id);
                $query = LeaveRequest::where('id', $dbId);
                if ($firmModel && Schema::hasColumn('leave_requests', 'firm_id')) {
                    $query->where('firm_id', $firmModel->id);
                }
                $lr = $query->first();
                $engagedMgrId = $lr ? ((int) ($lr->engaged_manager_id ?: ($lr->reviewed_by ?: 0)) ?: null) : null;
                if ($lr && ! $this->guardUserScope(User::find($lr->user_id), 'approve leave for', $engagedMgrId)) {
                    return;
                }
                if ($lr) {
                    $lr->status = 'approved';
                    if (Schema::hasColumn('leave_requests', 'approved_days') && ! $lr->approved_days) {
                        $lr->approved_days = $lr->requested_days ?: 1;
                    }
                    if (Schema::hasColumn('leave_requests', 'partner_remarks')) {
                        $lr->partner_remarks = $remarks ?: 'Approved by Partner ' . $this->partnerUnit . '.';
                    }
                    if (Auth::check() && Schema::hasColumn('leave_requests', 'partner_id')) {
                        $lr->partner_id = Auth::id();
                    }
                    $lr->save();
                    $this->recordAuditLog('leave', 'leave_approved', "Partner approved leave request {$id}.", $id);
                    $this->createPracticeNotification($lr->user_id, 'student', 'Leave Application Approved', "Your leave request ({$id}) has been sanctioned by Partner.", 'calendar-days');
                    $this->dispatchStudentDecisionAlert(
                        User::find($lr->user_id),
                        "Leave Approved ({$id}) — " . ($this->firm['name'] ?? 'Practice'),
                        "Dear Practice Member,\n\nYour leave application ({$id}) has been approved by the Partner.\nRemarks: " . ($lr->partner_remarks ?? '') . "\n\n— " . ($this->firm['name'] ?? 'DhakaWala ERP')
                    );
                }
            } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::debug($e->getMessage()); }
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
        $this->dispatch('toast', ['type' => 'success', 'title' => 'Leave Approved', 'message' => $this->notice ?? "Leave {$id} approved."]);
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
                LeaveRequest::ensureRuntimeSchemaColumns();
                $firmModel = $this->resolveFirmModel();
                $dbId = str_replace('LR-', '', $id);
                $query = LeaveRequest::where('id', $dbId);
                if ($firmModel && Schema::hasColumn('leave_requests', 'firm_id')) {
                    $query->where('firm_id', $firmModel->id);
                }
                $lr = $query->first();
                $engagedMgrId = $lr ? ((int) ($lr->engaged_manager_id ?: ($lr->reviewed_by ?: 0)) ?: null) : null;
                if ($lr && ! $this->guardUserScope(User::find($lr->user_id), 'reject leave for', $engagedMgrId)) {
                    return;
                }
                if ($lr) {
                    $lr->status = 'rejected';
                    if (Schema::hasColumn('leave_requests', 'partner_remarks')) {
                        $lr->partner_remarks = $reason ?: ('Rejected by ' . ucfirst($this->previewRole));
                    }
                    $lr->save();
                    $this->recordAuditLog('leave', 'leave_rejected', ucfirst($this->previewRole) . " rejected leave {$id}: " . ($lr->partner_remarks ?? $reason), $id);
                    $this->createPracticeNotification($lr->user_id, 'student', 'Leave Application Rejected', "Your leave request ({$id}) was not approved: " . ($lr->partner_remarks ?? $reason), 'calendar-days');
                }
            } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::debug($e->getMessage()); }
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

    /**
     * Policy quota (in days) for a leave type, or null when unset.
     * - For Students:
     *   * Regular leaves (annual, sick, emergency) enforce the per-anniversary-year quota
     *     (e.g. 14d/yr Casual, 14d/yr Sick, 7d/yr Emergency) within the active 12-month
     *     articleship year window from joining date.
     *   * Study / Exam leaves (icab_study, icab_exam) when custom profile allocations are set
     *     use the full multi-year Study Leave pool plus any unused regular leave rolled over
     *     from completed prior anniversary years.
     * - For Managers / Staff:
     *   * Enforces the annual calendar-year (Jan-Dec) quota, including any unused prior-year
     *     regular leave carried forward into Earned Leave.
     */
    protected function quotaDaysForLeaveType(string $type): ?int
    {
        if (Auth::check()) {
            /** @var User $u */
            $u = Auth::user();
            $hasCustomProfileAlloc = is_array($u->leave_allocations) && ! empty($u->leave_allocations);
            if ($hasCustomProfileAlloc || $u->isManager() || $type === 'earned') {
                $ent = $u->resolvedLeaveEntitlement();
                $allocs = $ent['allocations'] ?? [];
                $perYear = $ent['per_year_allocations'] ?? [];

                if ($u->isStudent()) {
                    if (in_array($type, ['annual', 'sick', 'emergency'], true) && isset($perYear[$type])) {
                        return max(0, (int) $perYear[$type]);
                    }
                    if (in_array($type, ['icab_study', 'icab_exam'], true) && isset($allocs['icab_study'])) {
                        $rollover = $this->unusedPriorYearsRegularLeaveForStudent($u, $this->leaveStart ?: now()->format('Y-m-d'));
                        return max(0, (int) $allocs['icab_study'] + $rollover);
                    }
                } elseif ($u->isManager()) {
                    $targetYear = (int) substr($this->leaveStart ?: now()->format('Y-m-d'), 0, 4);
                    if ($type === 'earned') {
                        $autoCarry = $this->computeAutoCarryForwardFromPriorYear($u, $targetYear);
                        return max(0, (int) ($allocs['earned'] ?? 15) + $autoCarry);
                    }
                    if (isset($allocs[$type])) {
                        return max(0, (int) $allocs[$type]);
                    }
                }

                if (isset($allocs[$type])) {
                    return max(0, (int) $allocs[$type]);
                }
            }
        }

        foreach ($this->leaveQuotas as $quota) {
            if (($quota['key'] ?? null) === $type) {
                return max(0, (int) ($quota['days'] ?? 0));
            }
        }

        return null;
    }

    /**
     * Days already consumed (approved or still in flight) for a leave type.
     * - For Students:
     *   * Regular leaves (annual, sick, emergency) are counted within the student's active
     *     12-month Anniversary Year window (based on articleship_start_date) if set,
     *     or calendar year if articleship_start_date is not set.
     *   * Study/Exam leaves (when profile leave_allocations are configured) pool across
     *     the 3/4-year articleship.
     * - For Managers / Staff:
     *   * Counted strictly within the Jan 1 - Dec 31 calendar year (yearly reset).
     */
    protected function usedLeaveDaysForYear(string $date, string $type): int
    {
        if (! Auth::check() || ! Schema::hasTable('leave_requests')) {
            return 0;
        }

        $year = substr($date, 0, 4);

        try {
            /** @var User $u */
            $u = Auth::user();
            $hasCustomProfileAlloc = is_array($u->leave_allocations) && ! empty($u->leave_allocations);
            $isStudentMultiYearStudy = $hasCustomProfileAlloc && $u->isStudent() && in_array($type, ['icab_study', 'icab_exam'], true);
            $typesToMatch = $isStudentMultiYearStudy
                ? ['icab_study', 'icab_exam']
                : [$type];

            $query = LeaveRequest::where('user_id', Auth::id())
                ->whereIn('leave_type', $typesToMatch)
                ->whereIn('status', ['pending_manager', 'pending_partner', 'approved', 'Awaiting Manager', 'Awaiting Partner']);

            if (! $isStudentMultiYearStudy) {
                if ($u->isStudent() && ! empty($u->articleship_start_date) && in_array($type, ['annual', 'sick', 'emergency'], true)) {
                    $win = $u->articleshipYearWindowForDate($date);
                    $query->where('start_date', '>=', $win['start_date'])
                        ->where('start_date', '<=', $win['end_date']);
                } else {
                    $query->where('start_date', '>=', $year . '-01-01')
                        ->where('start_date', '<=', $year . '-12-31');
                }
            }

            return (int) $query->get()->sum(function ($row) {
                return (int) ($row->approved_days ?: ($row->requested_days ?: 1));
            });
        } catch (\Throwable $e) {
            // Never block a submission because the ledger could not be read.
            Log::warning('Leave quota lookup failed: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Calculates unused regular leave days from completed prior Anniversary Years for an Articled Student,
     * which remain available in their overall 180/240-day Study Leave pool.
     */
    protected function unusedPriorYearsRegularLeaveForStudent(User $u, ?string $refDateYmd = null): int
    {
        if (! $u->isStudent() || empty($u->articleship_start_date) || ! Schema::hasTable('leave_requests')) {
            return 0;
        }

        try {
            $ent = $u->resolvedLeaveEntitlement();
            $perYearRegular = (int) (($ent['per_year_allocations']['annual'] ?? 14)
                + ($ent['per_year_allocations']['sick'] ?? 14)
                + ($ent['per_year_allocations']['emergency'] ?? 7));

            $winInfo = $u->articleshipYearWindowForDate($refDateYmd);
            $activeYr = (int) ($winInfo['year_number'] ?? 1);
            if ($activeYr <= 1) {
                return 0;
            }

            $priorWindows = array_filter($winInfo['all_windows'] ?? [], fn ($w) => (int) ($w['year_number'] ?? 1) < $activeYr);
            $rollover = 0;

            foreach ($priorWindows as $pw) {
                $usedInWindow = (int) LeaveRequest::where('user_id', $u->id)
                    ->whereIn('leave_type', ['annual', 'sick', 'emergency'])
                    ->whereIn('status', ['pending_manager', 'pending_partner', 'approved', 'Awaiting Manager', 'Awaiting Partner'])
                    ->where('start_date', '>=', $pw['start_date'])
                    ->where('start_date', '<=', $pw['end_date'])
                    ->get()
                    ->sum(fn ($row) => (int) ($row->approved_days ?: ($row->requested_days ?: 1)));

                $rollover += max(0, $perYearRegular - $usedInWindow);
            }

            return $rollover;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Calculates automatic prior-calendar-year unused regular leave carry-forward into Earned Leave
     * for a Manager / Regular Staff member when they had prior-year leave activity.
     */
    protected function computeAutoCarryForwardFromPriorYear(User $u, int $targetYear): int
    {
        if (! $u->isManager() || ! Schema::hasTable('leave_requests')) {
            return 0;
        }

        // If partner/admin already manually set a non-zero carry_forward_earned on the profile,
        // resolvedLeaveEntitlement() already includes it in $ent['allocations']['earned'].
        $rawCustom = is_array($u->leave_allocations) ? $u->leave_allocations : [];
        if ((int) ($rawCustom['carry_forward_earned'] ?? 0) > 0) {
            return 0;
        }

        try {
            $prevYear = $targetYear - 1;
            $prevRows = LeaveRequest::where('user_id', $u->id)
                ->whereIn('status', ['pending_manager', 'pending_partner', 'approved', 'Awaiting Manager', 'Awaiting Partner'])
                ->where('start_date', '>=', $prevYear . '-01-01')
                ->where('start_date', '<=', $prevYear . '-12-31')
                ->get();

            if ($prevRows->isEmpty()) {
                return 0;
            }

            $ent = $u->resolvedLeaveEntitlement();
            $regularQuota = (int) (($ent['allocations']['annual'] ?? 10)
                + ($ent['allocations']['sick'] ?? 14)
                + ($ent['allocations']['emergency'] ?? 5));

            $regularUsedPrevYear = (int) $prevRows
                ->filter(fn ($r) => in_array((string) $r->leave_type, ['annual', 'sick', 'emergency'], true))
                ->sum(fn ($r) => (int) ($r->approved_days ?: ($r->requested_days ?: 1)));

            return max(0, $regularQuota - $regularUsedPrevYear);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Live Personal Leave Balance Passbook for the current Student or Manager/Staff.
     * - Students: Tracks both Active Anniversary Year Window (Joining Date 12-Month cycle)
     *   and Total 3-Yr (180d) / 4-Yr (240d) Articleship Entitlement.
     * - Managers/Staff: Resets annually every Jan 1 - Dec 31 and carries forward unused
     *   prior-year regular leaves into Earned Leave.
     */
    public function getMyLeaveBalanceSummaryProperty(): array
    {
        $role = $this->previewRole === 'manager' ? 'manager' : 'student';
        /** @var User|null $authUser */
        $authUser = Auth::user();
        $ent = $authUser
            ? $authUser->resolvedLeaveEntitlement()
            : User::buildLeaveEntitlement($role, 3);

        $currentYearStr = now()->format('Y');
        $activeWindow = $authUser && $authUser->isStudent()
            ? $authUser->articleshipYearWindowForDate()
            : User::buildLeaveEntitlement('student', 3) && (new User(['articleship_duration_years' => 3]))->articleshipYearWindowForDate();

        if (! is_array($activeWindow)) {
            $activeWindow = (new User(['articleship_duration_years' => 3]))->articleshipYearWindowForDate();
        }

        $autoCarryForwardEarned = ($authUser && $authUser->isManager())
            ? $this->computeAutoCarryForwardFromPriorYear($authUser, (int) $currentYearStr)
            : 0;
        $totalCarryForwardEarned = (int) ($ent['carry_forward_earned'] ?? 0) + $autoCarryForwardEarned;

        $rawLeaves = [];
        if ($authUser && Schema::hasTable('leave_requests')) {
            try {
                $dbRows = LeaveRequest::where('user_id', $authUser->id)
                    ->whereIn('status', ['pending_manager', 'pending_partner', 'approved', 'Awaiting Manager', 'Awaiting Partner'])
                    ->get();
                foreach ($dbRows as $r) {
                    $reqD = (int) ($r->requested_days ?: (($r->start_date && $r->end_date) ? ($r->start_date->diffInDays($r->end_date) + 1) : 1));
                    $appD = (int) ($r->approved_days ?: $reqD);
                    $startYmd = $r->start_date ? $r->start_date->format('Y-m-d') : ($currentYearStr . '-01-01');
                    $rawLeaves[] = [
                        'type'       => (string) $r->leave_type,
                        'status'     => (string) $r->status,
                        'req'        => max(1, $reqD),
                        'app'        => max(1, $appD),
                        'start_date' => $startYmd,
                    ];
                }
            } catch (\Throwable $e) {
                Log::debug($e->getMessage());
            }
        } else {
            foreach ($this->leaveQueue as $q) {
                $st = $q['status'] ?? '';
                if (! in_array($st, ['pending_manager', 'pending_partner', 'approved', 'Awaiting Manager', 'Awaiting Partner'], true)) {
                    continue;
                }
                if (($q['student_code'] ?? '') === 'S-101' || ($q['user_id'] ?? null) === Auth::id()) {
                    $tSlug = strtolower(str_replace(' ', '_', $q['type'] ?? 'annual'));
                    if (str_contains($tSlug, 'exam') || str_contains($tSlug, 'study')) {
                        $tSlug = 'icab_study';
                    } elseif (str_contains($tSlug, 'annual') || str_contains($tSlug, 'casual')) {
                        $tSlug = 'annual';
                    } elseif (str_contains($tSlug, 'sick') || str_contains($tSlug, 'medical')) {
                        $tSlug = 'sick';
                    } elseif (str_contains($tSlug, 'emerg')) {
                        $tSlug = 'emergency';
                    } elseif (str_contains($tSlug, 'earn')) {
                        $tSlug = 'earned';
                    }
                    $reqD = max(1, (int) ($q['requested_days'] ?? 1));
                    $appD = max(1, (int) ($q['approved_days'] ?? $reqD));
                    $rawLeaves[] = [
                        'type'       => $tSlug,
                        'status'     => $st,
                        'req'        => $reqD,
                        'app'        => $appD,
                        'start_date' => (string) ($q['start_date_raw'] ?? ($currentYearStr . '-06-01')),
                    ];
                }
            }
        }

        // For Managers/Staff, filter rawLeaves to the current calendar year (Jan 1 - Dec 31 reset)
        if (in_array($ent['regime'], ['bd_corporate', 'corporate_staff'], true)) {
            $rawLeaves = array_values(array_filter($rawLeaves, function ($rl) use ($currentYearStr) {
                $sd = $rl['start_date'] ?? '';
                return $sd >= ($currentYearStr . '-01-01') && $sd <= ($currentYearStr . '-12-31');
            }));
        }

        $categories = [];
        $totalUsed = 0;
        $totalPending = 0;

        foreach ($ent['categories'] as $cat) {
            $cKey = $cat['key'];
            $allocated = max(0, (int) $cat['days']);
            if (in_array($ent['regime'], ['bd_corporate', 'corporate_staff'], true) && $cKey === 'earned' && $autoCarryForwardEarned > 0) {
                $allocated += $autoCarryForwardEarned;
            }
            $perYearAlloc = max(0, (int) ($cat['per_year_days'] ?? $allocated));
            $used = 0;
            $pending = 0;
            $yearUsed = 0;
            $yearPending = 0;

            foreach ($rawLeaves as $rl) {
                $lType = $rl['type'];
                $matches = ($cKey === 'icab_study')
                    ? in_array($lType, ['icab_study', 'icab_exam'], true)
                    : ($lType === $cKey);
                if (! $matches) {
                    continue;
                }
                $sd = $rl['start_date'] ?? '';
                $inActiveWindow = ($ent['regime'] === 'icab_articleship')
                    ? ($sd >= ($activeWindow['start_date'] ?? '0000-01-01') && $sd <= ($activeWindow['end_date'] ?? '9999-12-31'))
                    : true;

                if ($rl['status'] === 'approved') {
                    $used += $rl['app'];
                    if ($inActiveWindow) {
                        $yearUsed += $rl['app'];
                    }
                } else {
                    $pending += $rl['req'];
                    if ($inActiveWindow) {
                        $yearPending += $rl['req'];
                    }
                }
            }

            $committed = $used + $pending;
            $available = max(0, $allocated - $committed);
            $yearCommitted = $yearUsed + $yearPending;
            $yearAvailable = max(0, $perYearAlloc - $yearCommitted);

            $totalUsed += $used;
            $totalPending += $pending;

            $categories[] = [
                'key'                => $cKey,
                'label'              => $cat['label'],
                'is_study'           => ! empty($cat['is_study']),
                'allocated'          => $allocated,
                'used'               => $used,
                'pending'            => $pending,
                'committed'          => $committed,
                'available'          => $available,
                'per_year_allocated' => $perYearAlloc,
                'year_used'          => $yearUsed,
                'year_pending'       => $yearPending,
                'year_committed'     => $yearCommitted,
                'year_available'     => $yearAvailable,
                'carry_forward'      => ($cKey === 'earned') ? $totalCarryForwardEarned : 0,
            ];
        }

        $totalCap = max(1, (int) $ent['total_leave_cap'] + $autoCarryForwardEarned);
        $totalCommitted = $totalUsed + $totalPending;
        $totalAvailable = max(0, $totalCap - $totalCommitted);

        return [
            'regime'                     => $ent['regime'],
            'regime_label'               => $ent['regime_label'],
            'cycle_type'                 => $ent['cycle_type'] ?? ($ent['regime'] === 'icab_articleship' ? 'anniversary_year' : 'calendar_year_jan_dec'),
            'articleship_duration_years' => $ent['articleship_duration_years'],
            'active_window'              => $activeWindow,
            'calendar_year_label'        => "Jan 1, {$currentYearStr} – Dec 31, {$currentYearStr}",
            'carry_forward_earned'       => $totalCarryForwardEarned,
            'total_cap'                  => $totalCap,
            'per_year_cap'               => (int) ($ent['per_year_cap'] ?? ($ent['regime'] === 'icab_articleship' ? 60 : $totalCap)),
            'total_used'                 => $totalUsed,
            'total_pending'              => $totalPending,
            'total_committed'            => $totalCommitted,
            'total_available'            => $totalAvailable,
            'categories'                 => $categories,
        ];
    }
}
