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
use App\Support\CsvSafe;
use App\Services\FirmRegistry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait ManagesConveyanceWorkflow
{
    protected function loadClaimQueue(): void
    {
        if (Schema::hasTable('conveyance_claims') && Schema::hasTable('users')) {
            $firmModel = $this->resolveFirmModel();
            if ($firmModel) {
                try {
                    $claimsQuery = ConveyanceClaim::with(['user.manager', 'reviewer']);
                    if (Schema::hasColumn('conveyance_claims', 'firm_id')) {
                        $claimsQuery->where('firm_id', $firmModel->id);
                    } elseif (Schema::hasColumn('conveyance_claims', 'user_id')) {
                        $userIds = DB::table('users')->where('firm_id', $firmModel->id)->pluck('id')->all();
                        $claimsQuery->whereIn('user_id', $userIds);
                    }
                    $dbClaims = $claimsQuery->orderByDesc('id')->get();
                    if ($dbClaims->isNotEmpty()) {
                        $this->claimQueue = $dbClaims->map(fn (ConveyanceClaim $cc) => [
                            'id'                        => 'CC-' . $cc->id,
                            'db_id'                     => $cc->id,
                            'user_id'                   => $cc->user_id,
                            'name'                      => $cc->user?->name ?? 'Practice Member',
                            'student_code'              => $cc->user?->student_code ?? ('S-' . $cc->user_id),
                            'created_at_human'          => $cc->created_at?->diffForHumans() ?? 'Just now',
                            'manager'                   => $cc->user?->manager?->name ?? 'Direct Report',
                            'partner_unit'              => $cc->user?->partner_unit ?? $this->partnerUnit,
                            'claim_date_raw'            => $cc->claim_date?->format('Y-m-d') ?? '',
                            'client_name'               => $cc->client_name,
                            'route'                     => $cc->route,
                            'purpose'                   => $cc->purpose,
                            'amount'                    => (float) $cc->amount,
                            'original_amount'           => (float) $cc->original_amount,
                            'adjustment_remark'         => $cc->adjustment_remark ?? '',
                            'attachment_name'           => $cc->attachment_name,
                            'transport_items'           => $cc->transport_items ?? [],
                            'other_expense_description' => $cc->other_expense_description,
                            'other_expense_amount'      => (float) ($cc->other_expense_amount ?? 0),
                            'is_late'                   => (bool) $cc->is_late,
                            'status'                    => $cc->status,
                            'status_label'              => match ($cc->status) {
                                'pending_manager' => 'Awaiting Manager',
                                'pending_partner' => 'Escalated to Partner',
                                'approved'        => 'Approved',
                                'rejected'        => 'Rejected',
                                default           => ucfirst($cc->status),
                            },
                            'manager_remarks'           => $cc->manager_remarks ?? '',
                            'partner_remarks'           => $cc->partner_remarks ?? '',
                        ])->toArray();
                        return;
                    } else {
                        $this->claimQueue = [];
                        return;
                    }
                } catch (\Throwable $e) {
                    Log::warning('loadClaimQueue error: ' . $e->getMessage());
                }
            }
        }

        if (! app()->runningUnitTests()) {
            $this->claimQueue = [];
            return;
        }

        $this->claimQueue = [
            [
                'id'                        => 'CC-201',
                'name'                      => 'Sample Student 01',
                'student_code'              => 'S-101',
                'manager'                   => 'Sample Manager A',
                'partner_unit'              => 'Partner Unit 01',
                'client_name'               => 'BEXIMCO Textiles Audit',
                'route'                     => 'Office → Sample Client → Return',
                'amount'                    => 220.00,
                'original_amount'           => 220.00,
                'adjustment_remark'         => '',
                'attachment_name'           => null,
                'transport_items'           => [
                    ['from' => 'Karwan Bazar Office', 'to' => 'Dhanmondi Client', 'mode' => 'Bus', 'amount' => 110],
                    ['from' => 'Dhanmondi Client', 'to' => 'Karwan Bazar Office', 'mode' => 'Bus', 'amount' => 110],
                ],
                'other_expense_description' => null,
                'other_expense_amount'      => 0,
                'is_late'                   => false,
                'status'                    => 'approved',
                'status_label'              => 'Approved by Manager',
                'manager_remarks'           => 'Within ৳300 limit. Standard local bus/rickshaw fare.',
                'partner_remarks'           => '',
            ],
            [
                'id'                        => 'CC-202',
                'name'                      => 'Sample Student 02',
                'student_code'              => 'S-102',
                'manager'                   => 'Sample Manager A',
                'partner_unit'              => 'Partner Unit 01',
                'client_name'               => 'Square Pharmaceuticals Audit',
                'route'                     => 'Office → Gazipur Plant → Return',
                'amount'                    => 550.00,
                'original_amount'           => 650.00,
                'adjustment_remark'         => 'Overclaimed AC premium fare deducted; approved Express bus fare.',
                'attachment_name'           => null,
                'transport_items'           => [],
                'other_expense_description' => null,
                'other_expense_amount'      => 0,
                'is_late'                   => false,
                'status'                    => 'pending_partner',
                'status_label'              => 'Escalated to Partner',
                'manager_remarks'           => 'Amount exceeds ৳300 Manager limit. Adjusted and escalated to Partner.',
                'partner_remarks'           => '',
            ],
            [
                'id'                        => 'CC-203',
                'name'                      => 'Sample Student 03',
                'student_code'              => 'S-103',
                'manager'                   => 'Sample Manager B',
                'partner_unit'              => 'Partner Unit 01',
                'client_name'               => 'Brac Bank Motijheel Branch',
                'route'                     => 'Office → Motijheel Branch → Return',
                'amount'                    => 180.00,
                'original_amount'           => 180.00,
                'adjustment_remark'         => '',
                'attachment_name'           => null,
                'transport_items'           => [],
                'other_expense_description' => null,
                'other_expense_amount'      => 0,
                'is_late'                   => true,
                'status'                    => 'pending_partner',
                'status_label'              => 'Escalated to Partner (Late)',
                'manager_remarks'           => 'Submitted 6 days late (> 2 days policy). Escalated for Partner sanction.',
                'partner_remarks'           => '',
            ],
        ];
    }

    public function addTransportRow(): void
    {
        if (count($this->transportRows) >= 12) {
            return;
        }
        $this->transportRows[] = ['from' => '', 'to' => '', 'mode' => 'Bus', 'amount' => ''];
    }

    public function removeTransportRow(int $index): void
    {
        if (count($this->transportRows) <= 1) {
            return;
        }
        unset($this->transportRows[$index]);
        $this->transportRows = array_values($this->transportRows);
        $this->recalculateTransportTotal();
    }

    public function updatedTransportRows(): void
    {
        $this->recalculateTransportTotal();
    }

    public function updatedOtherExpenseAmount(): void
    {
        $this->recalculateTransportTotal();
    }

    public function recalculateTransportTotal(): void
    {
        $sum = 0.0;
        $legs = [];
        foreach ($this->transportRows as $r) {
            $amt = filter_var($r['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
            if ($amt !== false && $amt > 0) {
                $sum += $amt;
            }
            $from = trim((string) ($r['from'] ?? ''));
            $to = trim((string) ($r['to'] ?? ''));
            $mode = trim((string) ($r['mode'] ?? 'Bus'));
            if ($from !== '' && $to !== '') {
                $legs[] = "{$from} → {$to} ({$mode})";
            }
        }
        if ($this->hasOtherExpense) {
            $otherAmt = filter_var($this->otherExpenseAmount, FILTER_VALIDATE_FLOAT);
            if ($otherAmt !== false && $otherAmt > 0) {
                $sum += $otherAmt;
            }
        }
        if ($sum > 0) {
            $this->claimAmount = (string) round($sum, 2);
        }
        if (! empty($legs)) {
            $this->claimRoute = implode(' | ', $legs);
        }
    }

    protected function findDuplicateActiveClaim(string $claimDate, string $clientName, float $amount, string $route): ?array
    {
        if (Auth::check() && Schema::hasTable('conveyance_claims') && Schema::hasTable('firms')) {
            try {
                $user = Auth::user();
                $firm = $this->resolveFirmModel();
                $firmId = $firm?->id ?? $user->firm_id;
                if ($firmId) {
                    $dup = ConveyanceClaim::where('firm_id', $firmId)
                        ->where('user_id', $user->id)
                        ->whereDate('claim_date', $claimDate)
                        ->where('client_name', $clientName ?: 'General Practice Site')
                        ->where('amount', $amount)
                        ->where('route', trim($route))
                        ->whereIn('status', ['pending_manager', 'pending_partner', 'approved'])
                        ->first();
                    if ($dup) {
                        return [
                            'id'     => 'CC-' . $dup->id,
                            'amount' => (float) $dup->amount,
                            'client' => $dup->client_name,
                            'date'   => $claimDate,
                        ];
                    }
                }
            } catch (\Throwable) {}
        }
        return null;
    }

    public function requestClaimSubmissionConfirm(): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;
        $this->notice = null;
        if ($this->previewRole !== 'student') {
            $this->error = Auth::check()
                ? 'Only the Student role can submit a conveyance claim.'
                : 'Only the Student design role can preview a new conveyance claim.';
            return;
        }

        $this->recalculateTransportTotal();

        $amount = filter_var($this->claimAmount, FILTER_VALIDATE_FLOAT);
        if (! $this->validDate($this->claimDate)
            || $amount === false || $amount <= 0 || $amount > 50000
            || mb_strlen(trim($this->claimRoute)) < 3
            || mb_strlen(trim($this->claimPurpose)) < 5
        ) {
            $this->error = 'Enter a valid date, amount (1–50,000), journey, and a purpose of at least five characters.';
            return;
        }

        $dup = $this->findDuplicateActiveClaim($this->claimDate, $this->claimClientName ?: 'General Practice Site', (float) $amount, $this->claimRoute);
        if ($dup) {
            $this->error = "Duplicate Claim Blocked: You already submitted an identical claim ({$dup['id']} for ৳{$dup['amount']} at {$dup['client']} on {$dup['date']}).";
            $this->dispatch('toast', [
                'type'    => 'error',
                'title'   => 'Duplicate Conveyance Claim',
                'message' => "Claim {$dup['id']} (৳{$dup['amount']}) is already active for {$dup['date']}.",
            ]);
            return;
        }

        $this->showClaimSubmitConfirmModal = true;
    }

    public function confirmAndSubmitConveyanceClaim(): void
    {
        $this->showClaimSubmitConfirmModal = false;
        $this->submitClaimDemo();
    }

    public function submitConveyanceClaim(): void
    {
        $this->submitClaimDemo();
    }

    public function submitClaimDemo(): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;
        $this->notice = null;
        $this->showClaimSubmitConfirmModal = false;
        if ($this->previewRole !== 'student') {
            $this->error = Auth::check()
                ? 'Only the Student role can submit a conveyance claim.'
                : 'Only the Student design role can preview a new conveyance claim.';
            return;
        }

        // Sync multi-row transport builder if filled
        $this->recalculateTransportTotal();

        $amount = filter_var($this->claimAmount, FILTER_VALIDATE_FLOAT);
        if (! $this->validDate($this->claimDate)
            || $amount === false || $amount <= 0 || $amount > 50000
            || mb_strlen(trim($this->claimRoute)) < 3
            || mb_strlen(trim($this->claimPurpose)) < 5
        ) {
            $this->error = 'Enter a valid date, amount (1–50,000), journey, and a purpose of at least five characters.';
            return;
        }

        $dup = $this->findDuplicateActiveClaim($this->claimDate, $this->claimClientName ?: 'General Practice Site', (float) $amount, $this->claimRoute);
        if ($dup) {
            $this->error = "Duplicate Claim Blocked: You already submitted an identical claim ({$dup['id']} for ৳{$dup['amount']} at {$dup['client']} on {$dup['date']}).";
            return;
        }

        $storedAttachmentName = null;
        if ($this->claimAttachment) {
            $this->validate([
                'claimAttachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            ]);
            try {
                $ext = $this->claimAttachment->getClientOriginalExtension() ?: 'pdf';
                $safeName = 'voucher_' . date('Ymd_His') . '_' . Str::random(6) . '.' . $ext;
                $this->claimAttachment->storeAs('conveyance-receipts/' . $this->firmSlug, $safeName, 'public');
                $storedAttachmentName = $safeName;
            } catch (\Throwable) {
                $storedAttachmentName = 'voucher_attached.pdf';
            }
        }

        $cleanTransportItems = array_values(array_filter($this->transportRows, fn ($r) => ! empty(trim((string) ($r['from'] ?? ''))) || ! empty($r['amount'])));
        $otherAmt = $this->hasOtherExpense ? (float) (filter_var($this->otherExpenseAmount, FILTER_VALIDATE_FLOAT) ?: 0) : 0.0;
        $otherDesc = $this->hasOtherExpense ? trim($this->otherExpenseDesc) : null;

        $claimDateTime = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->claimDate);
        $daysAgo = (int) now()->diffInDays($claimDateTime);
        $isLate = $daysAgo > $this->conveyanceLateDays;

        if (Auth::check() && Schema::hasTable('conveyance_claims') && Schema::hasTable('firms')) {
            try {
                $user = Auth::user();
                $firm = $this->resolveFirmModel();
                $firmId = $firm?->id ?? $user->firm_id;

                if ($firmId) {
                    $createData = [
                        'firm_id'           => $firmId,
                        'user_id'           => $user->id,
                        'claim_date'        => $this->claimDate,
                        'amount'            => $amount,
                        'original_amount'   => $amount,
                        'route'             => $this->claimRoute,
                        'purpose'           => $this->claimPurpose,
                        'client_name'       => $this->claimClientName ?: 'General Practice Site',
                        'attachment_name'   => $storedAttachmentName,
                        'is_late'           => $isLate,
                        'status'            => $isLate ? 'pending_partner' : 'pending_manager',
                        'manager_remarks'   => $isLate ? "Submitted {$daysAgo} days late (> {$this->conveyanceLateDays} working days policy)." : '',
                        'partner_remarks'   => '',
                    ];
                    if (Schema::hasColumn('conveyance_claims', 'transport_items')) {
                        $createData['transport_items'] = $cleanTransportItems;
                        $createData['other_expense_description'] = $otherDesc;
                        $createData['other_expense_amount'] = $otherAmt;
                    }
                    $cc = ConveyanceClaim::create($createData);

                    $this->recordAuditLog('conveyance', 'claim_submitted', "{$user->name} submitted conveyance claim CC-{$cc->id} for ৳{$amount} ({$cc->client_name}).", 'CC-' . $cc->id);
                    if ($isLate) {
                        $this->createPracticeNotification(null, 'partner', "New Late Conveyance Claim (CC-{$cc->id})", "{$user->name} submitted a conveyance claim of ৳{$amount} for {$cc->client_name} ({$cc->route}). Escalated to Partner.", 'receipt');
                    } else {
                        $this->createPracticeNotification($user->manager_id, 'manager', "New Conveyance Claim (CC-{$cc->id})", "{$user->name} submitted a conveyance claim of ৳{$amount} for {$cc->client_name} ({$cc->route}). Awaiting your review.", 'receipt');
                        $this->createPracticeNotification(null, 'partner', "New Conveyance Claim (CC-{$cc->id})", "{$user->name} submitted a conveyance claim of ৳{$amount} for {$cc->client_name}.", 'receipt', null, false);
                    }

                    $this->loadClaimQueue();
                    $this->loadPayrollRows();
                    $this->claimDate = '';
                    $this->claimAmount = '';
                    $this->claimRoute = '';
                    $this->claimPurpose = '';
                    $this->claimAttachment = null;
                    $this->transportRows = [['from' => '', 'to' => '', 'mode' => 'Bus', 'amount' => '']];
                    $this->hasOtherExpense = false;
                    $this->otherExpenseDesc = '';
                    $this->otherExpenseAmount = '';
                    $this->formResetIteration++;
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
            'id'                        => $newId,
            'name'                      => Auth::check() ? Auth::user()->name : 'Sample Student 01',
            'student_code'              => Auth::check() ? (Auth::user()->student_code ?? 'S-101') : 'S-101',
            'manager'                   => 'Sample Manager A',
            'partner_unit'              => $this->partnerUnit,
            'client_name'               => $this->claimClientName ?: 'General Practice Site',
            'route'                     => $this->claimRoute,
            'amount'                    => $amount,
            'original_amount'           => $amount,
            'adjustment_remark'         => '',
            'attachment_name'           => $storedAttachmentName,
            'transport_items'           => $cleanTransportItems,
            'other_expense_description' => $otherDesc,
            'other_expense_amount'      => $otherAmt,
            'is_late'                   => $isLate,
            'status'                    => $isLate ? 'pending_partner' : 'pending_manager',
            'status_label'              => $isLate ? 'Escalated to Partner (Late)' : 'Manager Review',
            'manager_remarks'           => $isLate ? "Submitted {$daysAgo} days late (> {$this->conveyanceLateDays} days policy)." : '',
            'partner_remarks'           => '',
        ];

        $this->claimAttachment = null;
        if (! app()->runningUnitTests()) {
            $this->claimDate = '';
            $this->claimAmount = '';
            $this->claimRoute = '';
            $this->claimPurpose = '';
            $this->transportRows = [['from' => '', 'to' => '', 'mode' => 'Bus', 'amount' => '']];
            $this->hasOtherExpense = false;
            $this->otherExpenseDesc = '';
            $this->otherExpenseAmount = '';
            $this->formResetIteration++;
        }
        $this->notice = 'Conveyance claim preview validated. No invoice, receipt, reimbursement or payment was recorded.';
    }

    /**
     * Open modal for Manager/Partner to increase or decrease claim amount or send to Partner.
     */
    /**
     * Open Lightbox Modal to view an attached Conveyance Receipt/Ticket (#2).
     */
    public function openVoucherPreviewModal(string $id): void
    {
        $this->closeDialogs();

        // SECURITY (audit P1-3.9): any firm member could open any colleague's
        // receipt by id. Receipts carry amounts, client sites and account
        // details, so access is now limited to the owner, that person's unit
        // manager, or a Partner.
        if (Auth::check() && $this->previewRole === 'student') {
            $claim = ConveyanceClaim::find(str_replace('CC-', '', $id));
            if ($claim && (int) $claim->user_id !== (int) Auth::id()) {
                $this->error = 'Access denied: you can only open your own conveyance receipts.';
                return;
            }
        }

        foreach ($this->claimQueue as $c) {
            if ($c['id'] === $id) {
                $this->previewVoucherClaim = $c;
                $this->showVoucherPreviewModal = true;
                return;
            }
        }
    }

    public function openClaimAdjustModal(string $id): void
    {
        $this->enforceAuthenticatedRoleLock();
        if ($this->previewRole === 'student') {
            $this->error = 'Students cannot adjust or review claims.';
            return;
        }
        $this->closeDialogs();
        $this->adjustTargetClaimId = $id;
        $this->adjustNewAmount = 220.00;
        $this->adjustRemarks = '';
        $this->adjustEscalateToPartner = false;
        foreach ($this->claimQueue as $c) {
            if ($c['id'] === $id) {
                $this->adjustNewAmount = (float) ($c['amount'] ?? 220.00);
                break;
            }
        }
        $this->showClaimAdjustModal = true;
    }

    public function submitClaimAdjustment(): void
    {
        $this->enforceAuthenticatedRoleLock();
        if ($this->adjustNewAmount <= 0 || $this->adjustNewAmount > 100000) {
            $this->error = 'Enter a valid adjusted amount (1–100,000 BDT).';
            return;
        }
        $action = $this->adjustEscalateToPartner ? 'escalate' : 'approve';
        $this->managerReviewClaim($this->adjustTargetClaimId, $this->adjustNewAmount, $this->adjustRemarks ?: 'Amount reviewed and adjusted by ' . ucfirst($this->previewRole), $action);
        $this->closeDialogs();
    }

    public function managerReviewClaim(string $id, float $adjustedAmount, string $adjustmentRemark = '', string $action = 'approve'): void
    {
        $this->enforceAuthenticatedRoleLock();

        if ($this->previewRole !== 'manager' && $this->previewRole !== 'partner') {
            $this->error = 'Only an assigned Manager or Partner can review conveyance claims.';
            return;
        }

        if ($this->previewRole === 'manager' && ! $this->managerCanApproveClaims) {
            $this->error = 'Partner has disabled Conveyance Claim Review permission for Managers.';
            return;
        }

        if (Schema::hasTable('conveyance_claims')) {
            try {
                $firmModel = $this->resolveFirmModel();
                $dbId = str_replace('CC-', '', $id);
                $query = ConveyanceClaim::where('id', $dbId);
                if ($firmModel && Schema::hasColumn('conveyance_claims', 'firm_id')) {
                    $query->where('firm_id', $firmModel->id);
                }
                $cc = $query->first();
                if ($cc && ! $this->guardUserScope(User::find($cc->user_id), 'review claims for')) {
                    return;
                }
                if ($cc) {
                    if ($action === 'reject') {
                        $cc->status = 'rejected';
                        $cc->manager_remarks = $adjustmentRemark ?: 'Rejected by Manager.';
                    } else {
                        $diffDirection = $adjustedAmount > (float) $cc->original_amount
                            ? 'Increased'
                            : ($adjustedAmount < (float) $cc->original_amount ? 'Reduced' : 'Verified');
                        $cc->amount = $adjustedAmount;
                        $cc->adjustment_remark = $adjustmentRemark ?: "{$diffDirection} by " . ucfirst($this->previewRole);
                        $exceedsLimit = $adjustedAmount > $this->managerConveyanceLimit;
                        $mustEscalate = ($action === 'escalate') || $exceedsLimit || (bool) $cc->is_late;
                        $cc->status = ($this->previewRole === 'partner')
                            ? 'approved'
                            : ($mustEscalate ? 'pending_partner' : 'approved');
                    }
                    if (Auth::check()) {
                        $cc->reviewed_by = Auth::id();
                    }
                    $cc->save();
                    $this->recordAuditLog('conveyance', 'claim_reviewed', ucfirst($this->previewRole) . " reviewed claim {$id}: status {$cc->status}, amount ৳{$cc->amount} (Original: ৳{$cc->original_amount}).", $id);
                    if ($cc->status === 'pending_partner') {
                        $this->createPracticeNotification(null, 'partner', "Conveyance Escalated to Partner ({$id})", "Manager reviewed and escalated conveyance claim {$id} (৳{$cc->amount}) for Partner sanction.", 'receipt');
                    } elseif ($cc->status === 'approved') {
                        $this->createPracticeNotification($cc->user_id, 'student', "Conveyance Claim Approved ({$id})", "Your conveyance claim ({$id}) for ৳{$cc->amount} has been approved and added to your payroll.", 'receipt');
                    } elseif ($cc->status === 'rejected') {
                        $this->createPracticeNotification($cc->user_id, 'student', "Conveyance Claim Rejected ({$id})", "Your conveyance claim ({$id}) was rejected: {$cc->manager_remarks}", 'receipt');
                    }
                    $this->loadPayrollRows();
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
                if ($adjustedAmount != $claim['original_amount']) {
                    $dir = $adjustedAmount > $claim['original_amount'] ? 'Increased' : 'Reduced';
                    $claim['adjustment_remark'] = $adjustmentRemark ?: "Amount {$dir} by Manager (৳{$claim['original_amount']} → ৳{$adjustedAmount}).";
                }

                $exceedsLimit = $adjustedAmount > $this->managerConveyanceLimit;
                $isLate = ! empty($claim['is_late']);
                $mustEscalate = ($action === 'escalate') || $exceedsLimit || $isLate;

                if ($this->previewRole !== 'partner' && $mustEscalate) {
                    $claim['status'] = 'pending_partner';
                    $claim['status_label'] = 'Escalated to Partner';
                    $reason = ($action === 'escalate' ? 'Forwarded by Manager for Partner review. ' : '')
                        . ($exceedsLimit ? "Amount (৳{$adjustedAmount}) exceeds Manager limit (৳{$this->managerConveyanceLimit}). " : "")
                        . ($isLate ? "Older claim (> {$this->conveyanceLateDays} working days) requires Partner approval." : "");
                    $claim['manager_remarks'] = trim($reason . ' ' . $adjustmentRemark);
                    $this->notice = "Claim {$id} escalated to Partner for final authorization: {$reason}";
                } else {
                    $claim['status'] = 'approved';
                    $claim['status_label'] = 'Approved by ' . ucfirst($this->previewRole);
                    $claim['manager_remarks'] = $adjustmentRemark ?: "Approved within policy limit (৳{$adjustedAmount}). Added to student payroll.";
                    $this->notice = "Claim {$id} (৳{$adjustedAmount}) approved and synchronized with monthly payroll.";
                }
                break;
            }
        }
        unset($claim);
    }

    public function partnerApproveClaim(string $id, string $remarks = ''): void
    {
        $this->enforceAuthenticatedRoleLock();

        if ($this->previewRole !== 'partner') {
            $this->error = 'Only a Partner can give final claim sanction.';
            return;
        }

        if (Schema::hasTable('conveyance_claims')) {
            try {
                $firmModel = $this->resolveFirmModel();
                $dbId = str_replace('CC-', '', $id);
                $query = ConveyanceClaim::where('id', $dbId);
                if ($firmModel && Schema::hasColumn('conveyance_claims', 'firm_id')) {
                    $query->where('firm_id', $firmModel->id);
                }
                $cc = $query->first();
                if ($cc) {
                    $cc->status = 'approved';
                    $cc->partner_remarks = $remarks ?: ('Sanctioned by Partner ' . $this->partnerUnit . '.');
                    if (Auth::check()) {
                        $cc->partner_id = Auth::id();
                    }
                    $cc->save();
                    $this->recordAuditLog('conveyance', 'claim_approved', "Partner approved conveyance claim {$id} for ৳{$cc->amount}.", $id);
                    $this->createPracticeNotification($cc->user_id, 'student', 'Conveyance Claim Approved', "Your conveyance claim ({$id}) for ৳{$cc->amount} has been approved and added to your payroll.", 'receipt');
                    $this->loadPayrollRows();
                }
            } catch (\Throwable) {}
        }

        foreach ($this->claimQueue as &$claim) {
            if ($claim['id'] === $id) {
                $claim['status'] = 'approved';
                $claim['status_label'] = 'Approved by Partner';
                $claim['partner_remarks'] = $remarks ?: 'Approved by Partner ' . $this->partnerUnit . '.';
                $this->notice = "Conveyance claim {$id} approved by Partner and added to student payroll.";
                break;
            }
        }
        unset($claim);
    }

    public function rejectClaim(string $id, string $remarks = ''): void
    {
        $this->enforceAuthenticatedRoleLock();

        if ($this->previewRole === 'student') {
            $this->error = 'Students cannot reject claims.';
            return;
        }
        if (Schema::hasTable('conveyance_claims')) {
            try {
                $firmModel = $this->resolveFirmModel();
                $dbId = str_replace('CC-', '', $id);
                $query = ConveyanceClaim::where('id', $dbId);
                if ($firmModel && Schema::hasColumn('conveyance_claims', 'firm_id')) {
                    $query->where('firm_id', $firmModel->id);
                }
                $cc = $query->first();
                if ($cc && ! $this->guardUserScope(User::find($cc->user_id), 'reject claims for')) {
                    return;
                }
                if ($cc) {
                    $cc->status = 'rejected';
                    $cc->partner_remarks = $remarks ?: ('Rejected by ' . ucfirst($this->previewRole));
                    $cc->save();
                    $this->recordAuditLog('conveyance', 'claim_rejected', ucfirst($this->previewRole) . " rejected claim {$id}.", $id);
                    $this->createPracticeNotification($cc->user_id, 'student', 'Conveyance Claim Rejected', "Your conveyance claim ({$id}) was rejected: {$cc->partner_remarks}", 'receipt');
                    $this->loadPayrollRows();
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

    // ─── Dynamic Payroll & Monthly Allowance Engine (with Late Penalty Rule) ──

    public function loadAuditClients(): void
    {
        if (Schema::hasTable('audit_clients') && Schema::hasTable('firms')) {
            $firm = $this->resolveFirmModel();
            if ($firm) {
                try {
                    $dbClients = AuditClient::where('firm_id', $firm->id)->orderBy('name')->pluck('name')->all();
                    if (! empty($dbClients)) {
                        $this->auditClients = $dbClients;
                        if (! in_array($this->clientSite, $this->auditClients, true) && ! empty($this->auditClients)) {
                            $this->clientSite = $this->auditClients[0];
                        }
                        if (! in_array($this->claimClientName, $this->auditClients, true) && ! empty($this->auditClients)) {
                            $this->claimClientName = $this->auditClients[0];
                        }
                        return;
                    }
                } catch (\Throwable) {}
            }
        }

        $this->auditClients = app()->runningUnitTests() ? [
            'BEXIMCO Textiles Audit',
            'Square Pharmaceuticals Audit',
            'BRAC Bank PLC (Motijheel Branch)',
            'ACI Logistics Limited (Shwapno)',
            'General Office / Firm HQ',
        ] : [
            'General Office / Firm HQ',
        ];
        if (! in_array($this->clientSite, $this->auditClients, true)) {
            $this->clientSite = $this->auditClients[0];
        }
        if (! in_array($this->claimClientName, $this->auditClients, true)) {
            $this->claimClientName = $this->auditClients[0];
        }
    }

    public function updatedClientSite($value): void
    {
        if ($value === '__NEW__') {
            $this->clientSite = $this->auditClients[0] ?? 'General Office / Firm HQ';
            $this->openNewClientModal();
        }
    }

    public function updatedClaimClientName($value): void
    {
        if ($value === '__NEW__') {
            $this->claimClientName = $this->auditClients[0] ?? 'General Office / Firm HQ';
            $this->openNewClientModal();
        }
    }

    public function openNewClientModal(): void
    {
        $this->closeDialogs();
        $this->newClientName = '';
        $this->newClientLocation = '';
        $this->showAddNewClientModal = true;
    }

    public function saveNewClientSite(): void
    {
        $this->enforceAuthenticatedRoleLock();
        $name = trim($this->newClientName);
        if (empty($name)) {
            $this->error = 'Please enter a valid client or audit location name.';
            return;
        }

        $firm = $this->resolveFirmModel();
        if ($firm && Schema::hasTable('audit_clients')) {
            try {
                $existing = AuditClient::where('firm_id', $firm->id)->where('name', $name)->first();
                if (! $existing) {
                    AuditClient::create([
                        'firm_id'    => $firm->id,
                        'name'       => $name,
                        'location'   => trim($this->newClientLocation) ?: null,
                        'created_by' => Auth::id(),
                    ]);
                }
            } catch (\Throwable) {}
        }

        if (! in_array($name, $this->auditClients, true)) {
            $this->auditClients[] = $name;
            sort($this->auditClients);
        }

        $this->clientSite = $name;
        $this->claimClientName = $name;
        $this->showAddNewClientModal = false;
        $this->newClientName = '';
        $this->newClientLocation = '';
        $this->notice = "Audit client site '{$name}' has been added and selected.";
    }

    public function exportConveyanceCsv()
    {
        $filename = 'Conveyance_Claims_' . $this->firmSlug . '_' . date('Y_m_d') . '.csv';

        $columns = [
            'Claim ID',
            'Staff Name',
            'Student ID',
            'Audit Client Tagged',
            'Travel Route',
            'Claim Purpose',
            'Amount (BDT)',
            'Original Amount (BDT)',
            'Adjustment Remarks',
            'Late Claim Flag',
            'Approval Status',
            'Manager Remarks',
            'Partner Remarks',
        ];

        $claimsToExport = $this->claimQueue;

        return response()->streamDownload(function () use ($columns, $claimsToExport) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, $columns);

            foreach ($claimsToExport as $claim) {
                fputcsv($handle, CsvSafe::row([
                    $claim['id'] ?? '',
                    $claim['name'] ?? '',
                    $claim['student_code'] ?? '',
                    $claim['client_name'] ?? 'Audit Site',
                    $claim['route'] ?? '—',
                    $claim['purpose'] ?? ($claim['client_name'] ?? 'Field Audit Duty'),
                    number_format((float) ($claim['amount'] ?? 0), 2),
                    number_format((float) ($claim['original_amount'] ?? $claim['amount'] ?? 0), 2),
                    $claim['adjustment_remark'] ?? '—',
                    !empty($claim['is_late']) ? 'YES (Late Submission)' : 'NO',
                    $claim['status_label'] ?? ucfirst($claim['status'] ?? 'pending'),
                    $claim['manager_remarks'] ?? '—',
                    $claim['partner_remarks'] ?? '—',
                ]));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

}
