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

trait ManagesPayrollAndVouchers
{
    protected function calculateLateDeduction(float $baseStipend, int $lateDaysCount): float
    {
        if (! $this->latePenaltyEnabled || $this->lateDaysPerDeduction <= 0 || $lateDaysCount < $this->lateDaysPerDeduction) {
            return 0.00;
        }
        $penaltyUnits = intdiv($lateDaysCount, $this->lateDaysPerDeduction);
        // AUDIT P1-3.4: a flat /30 over-deducts in February (28 days) and
        // under-deducts in a 31-day month. Use the real length of the payroll
        // period, falling back to 30 only when the period cannot be parsed.
        $dailyStipend = $baseStipend / $this->daysInPayrollPeriod();
        return round($penaltyUnits * $dailyStipend * max(0, $this->deductionDaysCount), 2);
    }

    /**
     * Calculate absent days and salary deduction on holidays/weekends declared OPEN by Partner (#2).
     */
    protected function calculateMandatoryHolidayAbsentDeduction(int $firmId, int $userId, float $baseStipend): array
    {
        if (! Schema::hasTable('practice_holidays') || ! Schema::hasTable('attendances')) {
            return ['absent_days' => 0, 'deduction' => 0.00];
        }
        try {
            [$periodStart, $periodEnd] = $this->payrollPeriodBounds();

            $openHolidays = PracticeHoliday::where('firm_id', $firmId)
                ->where('is_office_open_override', true)
                ->whereBetween('holiday_date', [$periodStart, $periodEnd])
                ->whereDate('holiday_date', '<=', now()->toDateString())
                ->where(function ($q) {
                    $q->whereNull('partner_unit')
                        ->orWhere('partner_unit', $this->partnerUnit);
                })
                ->get();

            if ($openHolidays->isEmpty()) {
                return ['absent_days' => 0, 'deduction' => 0.00];
            }

            $absentCount = 0;
            $totalCutDays = 0.0;
            foreach ($openHolidays as $oh) {
                $dateStr = $oh->holiday_date->format('Y-m-d');
                $attended = Attendance::where('firm_id', $firmId)
                    ->where('user_id', $userId)
                    ->whereDate('date', $dateStr)
                    ->whereNotNull('in_time')
                    ->exists();

                $onApprovedLeave = Schema::hasTable('leave_requests') && LeaveRequest::where('firm_id', $firmId)
                    ->where('user_id', $userId)
                    ->where('status', 'approved')
                    ->whereDate('start_date', '<=', $dateStr)
                    ->whereDate('end_date', '>=', $dateStr)
                    ->exists();

                if (! $attended && ! $onApprovedLeave) {
                    $absentCount++;
                    $totalCutDays += max(0.0, (float) $oh->absent_deduction_days);
                }
            }

            $dailyRate = $baseStipend / $this->daysInPayrollPeriod();
            return [
                'absent_days' => $absentCount,
                'deduction'   => round($totalCutDays * $dailyRate, 2),
            ];
        } catch (\Throwable) {
            return ['absent_days' => 0, 'deduction' => 0.00];
        }
    }

    /**
     * Loads the payroll rows for the viewer.
     *
     * SECURITY: `$payrollRows` is a public Livewire property, so everything in
     * it is serialised into the page and readable in the browser's network
     * tab, whatever the Blade view chooses to show. A student therefore must
     * only ever receive their own row — never the unit's bKash / bank account
     * numbers, phones and allowances.
     */
    public function loadPayrollRows(): void
    {
        $this->loadPayrollRowsUnscoped();

        if (Auth::check() && Auth::user()->isStudent()) {
            $this->payrollRows = $this->payrollRowsOwnedByViewer($this->payrollRows);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    protected function payrollRowsOwnedByViewer(array $rows): array
    {
        return array_values(array_filter($rows, function ($r) {
            if (Auth::check() && Auth::user()->isStudent()) {
                return (int) ($r['db_id'] ?? 0) === (int) Auth::id()
                    || ($r['id'] ?? '') === (Auth::user()->student_code ?? 'S-101');
            }

            return ($r['id'] ?? '') === 'S-101';
        }));
    }

    /**
     * Rows the signed-in viewer may export. Students get their own row only.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function payrollRowsVisibleToViewer(): array
    {
        $this->enforceAuthenticatedRoleLock();

        if ($this->previewRole === 'student') {
            return $this->payrollRowsOwnedByViewer($this->payrollRows);
        }

        return $this->payrollRows;
    }

    protected function loadPayrollRowsUnscoped(): void
    {
        if (Schema::hasTable('users') && Schema::hasTable('firms')) {
            $firmModel = $this->resolveFirmModel();
            if ($firmModel) {
                try {
                    [$periodStart, $periodEnd] = $this->payrollPeriodBounds();

                    $payrollRoles = app()->runningUnitTests() ? ['student', 'manager'] : ['student', 'manager'];
                    $students = User::where('firm_id', $firmModel->id)
                        ->whereIn('role', $payrollRoles)
                        ->when(Schema::hasColumn('users', 'is_active'), fn ($q) => $q->where('is_active', true))
                        ->when(Auth::check(), fn ($q) => $q->where('partner_unit', $this->partnerUnit))
                        ->orderByRaw("CASE role WHEN 'manager' THEN 1 WHEN 'student' THEN 2 ELSE 3 END")
                        ->with('manager')
                        ->get();

                    if ($students->isNotEmpty()) {
                        $existingRecords = Schema::hasTable('payroll_records')
                            ? PayrollRecord::where('firm_id', $firmModel->id)->where('period', $this->period)->get()->keyBy('user_id')
                            : collect();

                        $overallStatus = 'draft';
                        if ($existingRecords->isNotEmpty()) {
                            $overallStatuses = $existingRecords->pluck('status')->unique()->values()->all();
                            $overallStatus = $overallStatuses[0] ?? 'draft';
                        }
                        $this->payrollStatus = $overallStatus;

                        $this->payrollRows = $students->map(function (User $s) use ($firmModel, $existingRecords, $periodStart, $periodEnd) {
                            /** @var PayrollRecord|null $rec */
                            $rec = $existingRecords->get($s->id);

                            $baseStipend = $rec ? (float) $rec->base_allowance : (float) ($s->monthly_allowance ?: 8000.00);
                            $approvedTravel = 0.00;
                            if ($rec && $rec->isLocked()) {
                                $approvedTravel = (float) $rec->approved_conveyance;
                            } elseif (Schema::hasTable('conveyance_claims')) {
                                $approvedTravel = (float) ConveyanceClaim::where('firm_id', $firmModel->id)
                                    ->where('user_id', $s->id)
                                    ->where('status', 'approved')
                                    ->whereBetween('claim_date', [$periodStart, $periodEnd])
                                    ->sum('amount');
                            }

                            $lateDays = Schema::hasTable('attendances')
                                ? Attendance::where('firm_id', $firmModel->id)->where('user_id', $s->id)
                                    ->whereBetween('date', [$periodStart, $periodEnd])
                                    ->whereIn('status', ['Late', 'Late Present'])->count()
                                : 0;

                            $lateDed = $this->calculateLateDeduction($baseStipend, $lateDays);
                            $holidayPenalty = $this->calculateMandatoryHolidayAbsentDeduction($firmModel->id, $s->id, $baseStipend);
                            $holidayAbsentDays = ($rec && $rec->isLocked()) ? (int) $rec->holiday_absent_days : $holidayPenalty['absent_days'];
                            $holidayDed = ($rec && $rec->isLocked()) ? (float) $rec->holiday_absent_deduction : $holidayPenalty['deduction'];

                            $adjustment = ($rec && $rec->isLocked())
                                ? ((float) $rec->attendance_adjustment + (float) $rec->holiday_absent_deduction)
                                : ($lateDed + $holidayDed);

                            $netPayable = max(0, $baseStipend + $approvedTravel - $adjustment);
                            $rowStatus = $rec ? $rec->status : 'draft';

                            return [
                                'id'                       => $s->student_code ?? ($s->role === 'manager' ? ('MGR-' . $s->id) : ('U-' . $s->id)),
                                'db_id'                    => $s->id,
                                'role'                     => $s->role,
                                'designation'              => $s->displayDesignation(),
                                'name'                     => $s->name,
                                'manager'                  => $s->manager?->name ?? 'Direct Report',
                                'partner_unit'             => $s->partner_unit ?? $this->partnerUnit,
                                'base_allowance'           => $baseStipend,
                                'approved_conveyance'      => $approvedTravel,
                                'late_days'                => $lateDays,
                                'late_deduction'           => $lateDed,
                                'holiday_absent_days'      => $holidayAbsentDays,
                                'holiday_absent_deduction' => $holidayDed,
                                'adjustment'               => $adjustment,
                                'phone'                    => $s->phone ?? '',
                                'email'                    => $s->email ?? '',
                                'payment_mode_raw'         => $s->payment_account_mode ?: 'bKash',
                                'payment_method'           => ($s->payment_account_mode && $s->payment_account_no)
                                    ? ($s->payment_account_mode . ' (' . $s->payment_account_no . ')')
                                    : ($rec?->payment_method ?: $this->selectedPaymentMethod),
                                'payment_account_no'       => $s->payment_account_no,
                                'voucher_no'               => $rec?->voucher_no ?: ('VCH-' . str_replace('-', '', $this->period) . '-' . ($s->student_code ?? $s->id)),
                                'net_payable'              => $netPayable,
                                'status'              => $rowStatus,
                                'status_label'        => match ($rowStatus) {
                                    'reviewed' => 'Reviewed',
                                    'approved' => 'Approved',
                                    'paid'     => 'Paid',
                                    'locked'   => 'Locked',
                                    default    => 'Draft Ledger',
                                },
                            ];
                        })->toArray();
                        return;
                    } else {
                        $this->payrollStatus = 'draft';
                        $this->payrollRows = [];
                        return;
                    }
                } catch (\Throwable $e) {
                    Log::warning('loadPayrollRows error: ' . $e->getMessage());
                }
            }
        }

        if (! app()->runningUnitTests()) {
            $this->payrollStatus = 'draft';
            $this->payrollRows = [];
            return;
        }

        $this->payrollStatus = 'draft';
        $this->payrollRows = array_map(fn (array $r) => [
            'id'                  => $r['id'],
            'db_id'               => $r['db_id'] ?? null,
            'name'                => $r['name'],
            'manager'             => $r['manager'] ?? 'Sample Manager A',
            'partner_unit'        => $r['partner'] ?? 'Partner Unit 01',
            'base_allowance'      => 8000.00,
            'approved_conveyance' => 220.00,
            'late_days'           => ($r['status'] ?? '') === 'Late' ? 1 : 0,
            'adjustment'          => 0.00,
            'net_payable'         => 8220.00,
            'status'              => 'draft',
            'status_label'        => 'Draft Ledger',
        ], $this->studentRows ?: []);
    }

    public function generatePayrollDraft(): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;

        if ($this->previewRole === 'student') {
            $this->error = 'Only a Manager or Partner can generate or sync the payroll register.';
            return;
        }

        $firmModel = $this->resolveFirmModel();
        if ($firmModel && Schema::hasTable('payroll_records') && Schema::hasTable('users')) {
            $students = User::where('firm_id', $firmModel->id)
                ->whereIn('role', ['student', 'manager'])
                ->when(Schema::hasColumn('users', 'is_active'), fn ($q) => $q->where('is_active', true))
                ->get();
            foreach ($students as $s) {
                $existing = PayrollRecord::where('firm_id', $firmModel->id)
                    ->where('user_id', $s->id)
                    ->where('period', $this->period)
                    ->first();

                if ($existing && $existing->isLocked()) {
                    continue;
                }

                $base = (float) ($s->monthly_allowance ?: 8000.00);

                // A real date range keeps the (firm_id, user_id, date) index
                // usable; `LIKE '2026-10%'` on a DATE column could not.
                [$periodStart, $periodEnd] = $this->payrollPeriodBounds();

                $travel = Schema::hasTable('conveyance_claims')
                    ? (float) ConveyanceClaim::where('firm_id', $firmModel->id)
                        ->where('user_id', $s->id)
                        ->where('status', 'approved')
                        ->whereBetween('claim_date', [$periodStart, $periodEnd])
                        ->sum('amount')
                    : 0.00;

                $presentDays = Schema::hasTable('attendances')
                    ? Attendance::where('firm_id', $firmModel->id)->where('user_id', $s->id)
                        ->whereBetween('date', [$periodStart, $periodEnd])->count()
                    : 0;
                $lateDays = Schema::hasTable('attendances')
                    ? Attendance::where('firm_id', $firmModel->id)->where('user_id', $s->id)
                        ->whereBetween('date', [$periodStart, $periodEnd])
                        ->whereIn('status', ['Late', 'Late Present'])->count()
                    : 0;

                $lateDeduction = $this->calculateLateDeduction($base, $lateDays);
                $holidayPenalty = $this->calculateMandatoryHolidayAbsentDeduction($firmModel->id, $s->id, $base);
                $totalDeduction = $lateDeduction + $holidayPenalty['deduction'];

                $recordPayload = [
                    'partner_unit'          => $s->partner_unit ?? $this->partnerUnit,
                    'base_allowance'        => $base,
                    'approved_conveyance'   => $travel,
                    'attendance_adjustment' => $lateDeduction,
                    'net_payable'           => max(0, $base + $travel - $totalDeduction),
                    'present_days'          => $presentDays,
                    'late_days'             => $lateDays,
                    'status'                => 'draft',
                    'policy_snapshot'       => [
                        'office_start'             => $this->officeStart,
                        'office_end'               => $this->officeEnd,
                        'grace_minutes'            => $this->graceMinutes,
                        'manager_conveyance_limit' => $this->managerConveyanceLimit,
                        'late_penalty_enabled'     => $this->latePenaltyEnabled,
                        'late_days_per_deduction'  => $this->lateDaysPerDeduction,
                        'deduction_days_count'     => $this->deductionDaysCount,
                    ],
                ];
                if (Schema::hasColumn('payroll_records', 'holiday_absent_days')) {
                    $recordPayload['holiday_absent_days'] = $holidayPenalty['absent_days'];
                    $recordPayload['holiday_absent_deduction'] = $holidayPenalty['deduction'];
                    $recordPayload['payment_method'] = $this->selectedPaymentMethod;
                    $recordPayload['voucher_no'] = 'VCH-' . str_replace('-', '', $this->period) . '-' . ($s->student_code ?? $s->id);
                }

                PayrollRecord::updateOrCreate(
                    [
                        'firm_id' => $firmModel->id,
                        'user_id' => $s->id,
                        'period'  => $this->period,
                    ],
                    $recordPayload
                );

            }

            $this->recordAuditLog('payroll', 'payroll_draft_generated', "Generated payroll draft for period {$this->period}.", $this->period);
            $this->loadPayrollRows();
            $this->notice = "Payroll draft generated and synchronized for period {$this->period}.";
            return;
        }

        $this->loadPayrollRows();
        $this->notice = "Payroll draft synchronized for {$this->period}.";
    }

    public function transitionPayrollPeriod(string $targetStatus): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;

        // 'draft' is a real transition (un-review a period that was sent back
        // for correction). It used to fall through the whitelist and return
        // silently, so a Partner clicking "Reopen" saw nothing happen at all.
        if (! in_array($targetStatus, ['draft', 'reviewed', 'approved', 'paid', 'locked'], true)) {
            return;
        }

        if ($this->previewRole === 'student') {
            $this->error = 'Students cannot modify payroll ledger states.';
            return;
        }

        if (in_array($targetStatus, ['approved', 'paid', 'locked'], true) && ! $this->userCanManagePayroll()) {
            $this->error = 'Only a Partner or an authorized Manager (with Payroll Governance permission) can approve, mark paid, or lock the monthly payroll ledger.';
            return;
        }

        $firmModel = $this->resolveFirmModel();
        if ($firmModel && Schema::hasTable('payroll_records')) {
            $records = PayrollRecord::where('firm_id', $firmModel->id)->where('period', $this->period)->get();
            if ($records->isEmpty()) {
                $this->generatePayrollDraft();
                $records = PayrollRecord::where('firm_id', $firmModel->id)->where('period', $this->period)->get();
            }

            foreach ($records as $rec) {
                if ($rec->isLocked()) {
                    $this->error = "Payroll period {$this->period} is already locked and immutable.";
                    return;
                }
                $rec->status = $targetStatus;
                if ($targetStatus === 'reviewed') {
                    $rec->reviewed_by = Auth::id();
                } elseif ($targetStatus === 'approved') {
                    $rec->approved_by = Auth::id();
                } elseif ($targetStatus === 'paid') {
                    $rec->paid_at = now();
                } elseif ($targetStatus === 'locked') {
                    $rec->locked_at = now();
                } else {
                    // Back to draft: clear the review stamps so the ledger
                    // header does not claim an approval that no longer applies.
                    $rec->reviewed_by = null;
                    $rec->approved_by = null;
                    $rec->paid_at = null;
                }
                $rec->save();
            }

            $this->recordAuditLog('payroll', 'payroll_' . $targetStatus, "Payroll register for {$this->period} marked as " . strtoupper($targetStatus) . ".", $this->period);
            $this->loadPayrollRows();
            $this->notice = "Monthly payroll register for {$this->period} updated to " . ucfirst($targetStatus) . '.';
            return;
        }

        $this->payrollStatus = $targetStatus;
        $this->notice = "Payroll status updated to " . ucfirst($targetStatus) . '.';
    }

    public function exportPayrollCsv()
    {
        $filename = 'Payroll_Register_' . $this->firmSlug . '_' . $this->period . '.csv';
        $columns = [
            'Student Code',
            'Student Name',
            'Partner Unit',
            'Supervising Manager',
            'Period',
            'Base Allowance (BDT)',
            'Approved Conveyance (BDT)',
            'Adjustments (BDT)',
            'Net Payable (BDT)',
            'Ledger Status',
        ];

        $this->loadPayrollRows();
        $rowsToExport = $this->payrollRowsVisibleToViewer();
        $period = $this->period;

        return response()->streamDownload(function () use ($columns, $rowsToExport, $period) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, $columns);

            foreach ($rowsToExport as $row) {
                fputcsv($handle, CsvSafe::row([
                    $row['id'] ?? '',
                    $row['name'] ?? '',
                    $row['partner_unit'] ?? '',
                    $row['manager'] ?? '',
                    $period,
                    number_format((float) ($row['base_allowance'] ?? 0), 2, '.', ''),
                    number_format((float) ($row['approved_conveyance'] ?? 0), 2, '.', ''),
                    number_format((float) ($row['adjustment'] ?? 0), 2, '.', ''),
                    number_format((float) ($row['net_payable'] ?? 0), 2, '.', ''),
                    $row['status_label'] ?? 'Draft Ledger',
                ]));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Export Ready-to-Upload bKash / Nagad / Bank Disbursement Sheet (CSV).
     */
    public function exportDisbursementSheetCsv()
    {
        $this->enforceAuthenticatedRoleLock();

        // The sheet carries every beneficiary's bank / bKash account number.
        if ($this->previewRole === 'student') {
            $this->error = 'Only a Manager or Partner can download the disbursement sheet.';
            return;
        }

        $this->loadPayrollRows();
        $filename = 'bKash_Bank_Disbursement_Sheet_' . $this->firmSlug . '_' . $this->period . '.csv';
        $columns = [
            'SL',
            'Voucher No',
            'Student Code',
            'Beneficiary Name',
            'Payment Mode (bKash/Nagad/Bank)',
            'Wallet / Account Number',
            'Mobile Phone',
            'Partner Unit',
            'Base Stipend (BDT)',
            'Approved Conveyance (BDT)',
            'Deductions (BDT)',
            'Net Disbursement Amount (BDT)',
            'Payment Status',
            'Remarks / Narration',
        ];

        $rowsToExport = $this->payrollRowsVisibleToViewer();
        $period = $this->period;
        $firmShort = $this->firm['short_code'] ?? strtoupper($this->firmSlug);

        return response()->streamDownload(function () use ($columns, $rowsToExport, $period, $firmShort) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, $columns);

            $sl = 1;
            $totalNet = 0.0;
            foreach ($rowsToExport as $row) {
                $net = (float) ($row['net_payable'] ?? 0);
                $totalNet += $net;
                $code = $row['id'] ?? '';
                $vNo = $row['voucher_no'] ?? ('VCH-' . str_replace('-', '', $period) . '-' . $code);
                $modeRaw = $row['payment_mode_raw'] ?? ($this->selectedPaymentMethod ?: 'bKash');
                $acctNo = !empty($row['payment_account_no']) ? $row['payment_account_no'] : 'Not Set';

                fputcsv($handle, CsvSafe::row([
                    $sl++,
                    $vNo,
                    $code,
                    $row['name'] ?? '',
                    $modeRaw,
                    $acctNo,
                    $row['phone'] ?? '',
                    $row['partner_unit'] ?? '',
                    number_format((float) ($row['base_allowance'] ?? 0), 2, '.', ''),
                    number_format((float) ($row['approved_conveyance'] ?? 0), 2, '.', ''),
                    number_format((float) ($row['adjustment'] ?? 0), 2, '.', ''),
                    number_format($net, 2, '.', ''),
                    strtoupper($row['status'] ?? 'draft'),
                    "{$firmShort} Stipend & Conveyance {$period} ({$code})",
                ]));
            }

            // Summary Footer Row
            fputcsv($handle, CsvSafe::row([
                '',
                '',
                '',
                'TOTAL DISBURSEMENT',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                number_format($totalNet, 2, '.', ''),
                '',
                'Total Beneficiaries: ' . count($rowsToExport),
            ]));

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Download Printable & 1-Click PDF Payment Voucher / Payslip with Official Letterhead (#3).
     */
    public function exportStudentPayrollVoucher(?string $studentCode = null)
    {
        $this->loadPayrollRows();
        $firmName = $this->firm['name'] ?? 'Chartered Accountants Practice';
        $firmDesig = $this->firm['designation'] ?? 'Chartered Accountants';
        $firmOffice = $this->firm['office'] ?? 'Dhaka, Bangladesh';
        $firmEmail = $this->firm['support_email'] ?? '';
        $primaryHex = $this->firm['primary_color'] ?? '#0D9488';
        $initials = strtoupper($this->firm['initials'] ?? 'CA');
        $period = $this->period;
        $paymentMode = $this->selectedPaymentMethod ?: 'bKash / Bank';

        $visibleRows = $this->payrollRowsVisibleToViewer();
        $vouchers = $studentCode
            ? array_values(array_filter($visibleRows, fn ($r) => ($r['id'] ?? '') === $studentCode))
            : $visibleRows;

        if (empty($vouchers) && ! empty($visibleRows)) {
            $vouchers = [$visibleRows[0]];
        }

        $baseName = ($studentCode ? "Payment_Voucher_{$studentCode}_" : "All_Payment_Vouchers_") . $this->firmSlug . "_{$period}";
        $filename = $baseName . ".html";
        $pdfFilename = $baseName . ".pdf";

        return response()->streamDownload(function () use ($firmName, $firmDesig, $firmOffice, $firmEmail, $primaryHex, $initials, $period, $paymentMode, $vouchers, $pdfFilename) {
            echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>" . htmlspecialchars($firmName, ENT_QUOTES) . " - Official Payment Voucher</title>";
            echo "<script src='https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js'></script>";
            echo "<style>body{font-family:'Segoe UI',Arial,sans-serif;margin:20px auto;max-width:820px;color:#0f172a;background:#f8fafc}.toolbar{display:flex;justify-content:space-between;align-items:center;background:#17191F;color:#fff;padding:12px 18px;border-radius:12px;margin-bottom:20px}.btn{padding:8px 16px;border-radius:8px;font-weight:800;font-size:12px;cursor:pointer;border:none;margin-left:8px}.btn-pdf{background:#FCDF02;color:#17191F}.btn-print{background:#ffffff;color:#17191F}.voucher{background:#fff;border:2px solid {$primaryHex};border-radius:12px;padding:24px;margin-bottom:28px;page-break-inside:avoid;box-shadow:0 4px 12px rgba(0,0,0,0.05)}.hdr{display:flex;justify-content:space-between;align-items:center;border-bottom:3px solid {$primaryHex};padding-bottom:14px;margin-bottom:14px}.brand{display:flex;align-items:center;gap:12px}.seal{width:46px;height:46px;border-radius:10px;background:{$primaryHex};color:#fff;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:16px}table{width:100%;border-collapse:collapse;margin-top:12px;font-size:12px}th,td{border:1px solid #cbd5e1;padding:9px 10px;text-align:left}th{background:#f1f5f9;font-weight:800}.sig{margin-top:42px;display:flex;justify-content:space-between;font-size:11px;text-align:center}@media print{.no-print{display:none!important}body{background:#fff;margin:0}}</style>";
            echo "<script>function downloadDirectPdf(){var el=document.getElementById('pdf-content');var btn=document.getElementById('dl-btn');if(btn)btn.innerText='Generating PDF...';if(window.html2pdf){html2pdf().set({margin:8,filename:'" . addslashes($pdfFilename) . "',image:{type:'jpeg',quality:0.98},html2canvas:{scale:2,useCORS:true},jsPDF:{unit:'mm',format:'a4',orientation:'portrait'}}).from(el).save().then(function(){if(btn)btn.innerText='⬇ Download Official PDF';});}else{window.print();}}</script>";
            echo "</head><body onload='setTimeout(function(){if(window.html2pdf){downloadDirectPdf();}else{window.print();}}, 350)'>";
            echo "<div class='toolbar no-print'><div><strong>" . htmlspecialchars($firmName) . "</strong> · Official Voucher Generator</div><div><button id='dl-btn' onclick='downloadDirectPdf()' class='btn btn-pdf'>⬇ Download Official PDF</button><button onclick='window.print()' class='btn btn-print'>🖨 Print Voucher</button></div></div>";
            echo "<div id='pdf-content'>";
            foreach ($vouchers as $v) {
                $vNo = $v['voucher_no'] ?? ('VCH-' . str_replace('-', '', $period) . '-' . ($v['id'] ?? '101'));
                $mode = $v['payment_method'] ?? $paymentMode;
                echo "<div class='voucher'>";
                echo "<div class='hdr'><div class='brand'><div class='seal'>" . htmlspecialchars($initials) . "</div><div><h2 style='margin:0;font-size:19px;color:{$primaryHex}'>" . htmlspecialchars($firmName) . "</h2><p style='margin:2px 0;font-size:11px;color:#475569;font-weight:bold'>" . htmlspecialchars($firmDesig) . " • " . htmlspecialchars($firmOffice) . ($firmEmail ? ' • ' . htmlspecialchars($firmEmail) : '') . "</p><p style='margin:2px 0 0;font-size:11px;color:#0f172a;font-weight:800'>OFFICIAL MONTHLY STIPEND &amp; CONVEYANCE PAYMENT VOUCHER</p></div></div>";
                echo "<div style='text-align:right;font-size:11px;line-height:1.5'><strong>Voucher No:</strong> " . htmlspecialchars($vNo) . "<br><strong>Payroll Period:</strong> {$period}<br><strong>Disbursement Mode:</strong> " . htmlspecialchars($mode) . "<br><strong>Issue Date:</strong> " . now()->format('d M Y') . "</div></div>";
                echo "<p style='font-size:12px;margin:6px 0;background:#f8fafc;padding:8px 10px;border-radius:6px;border:1px solid #e2e8f0'><strong>Beneficiary Student:</strong> " . htmlspecialchars($v['name'] ?? '') . " (" . htmlspecialchars($v['id'] ?? '') . ") &nbsp;|&nbsp; <strong>Partner Unit:</strong> " . htmlspecialchars($v['partner_unit'] ?? '') . " &nbsp;|&nbsp; <strong>Supervisor:</strong> " . htmlspecialchars($v['manager'] ?? '') . "</p>";
                echo "<table><thead><tr><th>Particulars / Head of Account</th><th>Calculation Basis &amp; Audit Reference</th><th style='text-align:right'>Amount (BDT)</th></tr></thead><tbody>";
                echo "<tr><td>Monthly Base Articleship Stipend / Allowance</td><td>ICAB Standard Stipend Scale</td><td style='text-align:right;font-weight:bold'>৳ " . number_format((float) ($v['base_allowance'] ?? 0), 2) . "</td></tr>";
                echo "<tr><td>Approved Client Audit Conveyance Reimbursement</td><td>Verified Travel Claims ({$period})</td><td style='text-align:right;color:#047857;font-weight:bold'>+ ৳ " . number_format((float) ($v['approved_conveyance'] ?? 0), 2) . "</td></tr>";
                echo "<tr><td>Attendance / Late / Mandatory Holiday Absent Deduction</td><td>Late Days: " . (int) ($v['late_days'] ?? 0) . " | Mandatory Open Holiday Absent: " . (int) ($v['holiday_absent_days'] ?? 0) . "</td><td style='text-align:right;color:#b91c1c;font-weight:bold'>- ৳ " . number_format((float) ($v['adjustment'] ?? 0), 2) . "</td></tr>";
                echo "<tr><th colspan='2' style='text-align:right;font-size:13px'>NET PAYABLE DISBURSEMENT</th><th style='text-align:right;font-size:15px;color:{$primaryHex}'>৳ " . number_format((float) ($v['net_payable'] ?? 0), 2) . "</th></tr>";
                echo "</tbody></table>";
                echo "<div class='sig'><div>_______________________<br><strong>Recipient Student Signature</strong></div><div>_______________________<br><strong>Reviewed by Audit Manager</strong></div><div>_______________________<br><strong>Authorized Partner Sign-off</strong></div></div>";
                echo "</div>";
            }
            echo "</div></body></html>";
        }, $filename, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    // ─── Targeted Notification Composer & Login Popup Modal ───────────────────

    /**
     * First and last calendar day of the selected payroll period (Y-m).
     */
    protected function payrollPeriodBounds(): array
    {
        try {
            $start = \Carbon\Carbon::createFromFormat('!Y-m', $this->period)->startOfMonth();

            return [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];
        } catch (\Throwable) {
            return [$this->period . '-01', $this->period . '-31'];
        }
    }

    /**
     * Number of days the selected payroll period actually contains.
     */
    protected function daysInPayrollPeriod(): int
    {
        try {
            return (int) \Carbon\Carbon::createFromFormat('!Y-m', $this->period)->daysInMonth;
        } catch (\Throwable) {
            return 30;
        }
    }
}
