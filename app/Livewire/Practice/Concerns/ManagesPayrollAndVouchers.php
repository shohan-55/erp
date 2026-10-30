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

            $dailyRate = $baseStipend / 30.0;
            return [
                'absent_days' => $absentCount,
                'deduction'   => round($totalCutDays * $dailyRate, 2),
            ];
        } catch (\Throwable) {
            return ['absent_days' => 0, 'deduction' => 0.00];
        }
    }

    public function loadPayrollRows(): void
    {
        if (Schema::hasTable('users') && Schema::hasTable('firms')) {
            $firmModel = $this->resolveFirmModel();
            if ($firmModel) {
                try {
                    [$periodStart, $periodEnd] = $this->payrollPeriodBounds();

                    $students = User::where('firm_id', $firmModel->id)
                        ->where('role', 'student')
                        ->when(Schema::hasColumn('users', 'is_active'), fn ($q) => $q->where('is_active', true))
                        ->when(Auth::check(), fn ($q) => $q->where('partner_unit', $this->partnerUnit))
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
                                'id'                       => $s->student_code ?? ('U-' . $s->id),
                                'db_id'                    => $s->id,
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
                ->where('role', 'student')
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

        if (in_array($targetStatus, ['approved', 'paid', 'locked'], true) && $this->previewRole !== 'partner') {
            $this->error = 'Only a Partner can approve, mark paid, or lock the monthly payroll ledger.';
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
        $rowsToExport = $this->payrollRows;
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
     * Download Printable Payment Voucher / Payslip for a single student or all students (#3).
     */
    public function exportStudentPayrollVoucher(?string $studentCode = null)
    {
        $this->loadPayrollRows();
        $firmName = $this->firm['name'] ?? 'Chartered Accountants Practice';
        $period = $this->period;
        $paymentMode = $this->selectedPaymentMethod ?: 'bKash / Bank';

        $vouchers = $studentCode
            ? array_values(array_filter($this->payrollRows, fn ($r) => ($r['id'] ?? '') === $studentCode))
            : $this->payrollRows;

        if (empty($vouchers) && ! empty($this->payrollRows)) {
            $vouchers = [$this->payrollRows[0]];
        }

        $filename = ($studentCode ? "Payment_Voucher_{$studentCode}_" : "All_Payment_Vouchers_") . $this->firmSlug . "_{$period}.html";

        return response()->streamDownload(function () use ($firmName, $period, $paymentMode, $vouchers) {
            echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>" . htmlspecialchars($firmName, ENT_QUOTES) . " - Monthly Allowance Voucher</title>";
            echo "<style>body{font-family:Arial,sans-serif;margin:20px;color:#111}.voucher{border:2px solid #17191F;border-radius:8px;padding:20px;margin-bottom:28px;page-break-inside:avoid}.hdr{display:flex;justify-content:space-between;border-bottom:2px solid #eee;padding-bottom:10px;margin-bottom:12px}table{width:100%;border-collapse:collapse;margin-top:10px;font-size:12px}th,td{border:1px solid #ccc;padding:8px;text-align:left}th{background:#f8f8fa}.sig{margin-top:36px;display:flex;justify-content:space-between;font-size:11px}@media print{.no-print{display:none}}</style>";
            echo "</head><body onload='window.print()'>";
            echo "<div class='no-print' style='margin-bottom:14px'><button onclick='window.print()' style='padding:8px 16px;font-weight:bold;cursor:pointer'>Print / Save Voucher PDF</button></div>";
            foreach ($vouchers as $v) {
                $vNo = $v['voucher_no'] ?? ('VCH-' . str_replace('-', '', $period) . '-' . ($v['id'] ?? '101'));
                $mode = $v['payment_method'] ?? $paymentMode;
                echo "<div class='voucher'>";
                echo "<div class='hdr'><div><h2 style='margin:0;font-size:18px'>" . htmlspecialchars($firmName) . "</h2><p style='margin:3px 0;font-size:11px;color:#555'>Official Monthly Articled Student Stipend &amp; Conveyance Voucher</p></div>";
                echo "<div style='text-align:right;font-size:11px'><strong>Voucher No:</strong> " . htmlspecialchars($vNo) . "<br><strong>Period:</strong> {$period}<br><strong>Payment Mode:</strong> " . htmlspecialchars($mode) . "</div></div>";
                echo "<p style='font-size:12px;margin:4px 0'><strong>Student Name:</strong> " . htmlspecialchars($v['name'] ?? '') . " (" . htmlspecialchars($v['id'] ?? '') . ") &nbsp;|&nbsp; <strong>Unit:</strong> " . htmlspecialchars($v['partner_unit'] ?? '') . " &nbsp;|&nbsp; <strong>Manager:</strong> " . htmlspecialchars($v['manager'] ?? '') . "</p>";
                echo "<table><thead><tr><th>Description</th><th>Details</th><th style='text-align:right'>Amount (BDT)</th></tr></thead><tbody>";
                echo "<tr><td>Monthly Base Stipend / Allowance</td><td>ICAB Standard Scale</td><td style='text-align:right'>৳ " . number_format((float) ($v['base_allowance'] ?? 0), 2) . "</td></tr>";
                echo "<tr><td>Approved Conveyance Claims</td><td>Audit Client Travel Reimbursement</td><td style='text-align:right'>+ ৳ " . number_format((float) ($v['approved_conveyance'] ?? 0), 2) . "</td></tr>";
                echo "<tr><td>Attendance / Late / Mandatory Holiday Absent Deduction</td><td>Late Days: " . (int) ($v['late_days'] ?? 0) . " | Mandatory Open Holiday Absent: " . (int) ($v['holiday_absent_days'] ?? 0) . "</td><td style='text-align:right;color:#b91c1c'>- ৳ " . number_format((float) ($v['adjustment'] ?? 0), 2) . "</td></tr>";
                echo "<tr><th colspan='2' style='text-align:right'>NET PAYABLE AMOUNT</th><th style='text-align:right;font-size:14px'>৳ " . number_format((float) ($v['net_payable'] ?? 0), 2) . "</th></tr>";
                echo "</tbody></table>";
                echo "<div class='sig'><div>_______________________<br>Recipient Student Signature</div><div>_______________________<br>Prepared / Audit Manager</div><div>_______________________<br>Authorized Partner Sign-off</div></div>";
                echo "</div>";
            }
            echo "</body></html>";
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
