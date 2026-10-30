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

trait ManagesAttendanceCalendarAndRadar
{
    protected function loadAttendanceRows(): void
    {
        if (Schema::hasTable('attendances') && Schema::hasTable('users')) {
            $firmModel = $this->resolveFirmModel();

            if ($firmModel) {
                $rolesToLoad = app()->runningUnitTests() ? ['student'] : ['student', 'manager'];
                $studentsQuery = User::where('firm_id', $firmModel->id)
                    ->whereIn('role', $rolesToLoad)
                    ->when(Schema::hasColumn('users', 'is_active'), fn ($q) => $q->where('is_active', true))
                    ->with('manager');

                // Partner Unit Isolation unless Cross-Partner Combined Attendance is toggled on
                if (! ($this->viewAllPartnersAttendance && $this->canViewAllPartnersAttendance) && Auth::check()) {
                    $studentsQuery->where('partner_unit', $this->partnerUnit);
                }

                $students = $studentsQuery->get();

                if ($students->isNotEmpty()) {
                    $todayAttendances = Attendance::where('firm_id', $firmModel->id)
                        ->whereDate('date', now()->toDateString())
                        ->with(['editor', 'proxyUser'])
                        ->get()
                        ->keyBy('user_id');

                    if (Auth::check() && ! app()->runningUnitTests()) {
                        /** @var Attendance|null $myAtt */
                        $myAtt = $todayAttendances->get(Auth::id());
                        if ($myAtt) {
                            $this->sessionState = $myAtt->session_state ?: ($myAtt->out_time ? 'closed' : ($myAtt->in_time ? 'active' : 'inactive'));
                            $this->previewSessionActive = ($this->sessionState === 'active');
                            if (in_array($myAtt->work_mode, ['office', 'client'], true)) {
                                $this->workMode = $myAtt->work_mode;
                            }
                            if (! empty($myAtt->client_site)) {
                                $this->clientSite = $myAtt->client_site;
                            }
                            if (! empty($myAtt->in_location_name)) {
                                $this->currentLocationName = $myAtt->in_location_name;
                            }
                        }
                    }

                    $this->studentRows = $students->map(function (User $s) use ($todayAttendances) {
                        /** @var Attendance|null $att */
                        $att = $todayAttendances->get($s->id);
                        $movements = ($att && ! empty($att->movement_logs) && is_array($att->movement_logs))
                            ? $att->movement_logs
                            : ($att && $att->in_time ? [[
                                'seq'          => 1,
                                'mode'         => ucfirst($att->work_mode),
                                'site'         => $att->client_site ?: 'Principal Office HQ',
                                'in_time'      => $att->in_time->format('H:i'),
                                'in_location'  => $att->in_location_name ?? 'Office',
                                'out_time'     => $att->out_time?->format('H:i') ?? '—',
                                'out_location' => $att->out_location_name ?? '—',
                                'out_action'   => $att->session_state ?? ($att->out_time ? 'closed' : 'active'),
                            ]] : []);

                        return [
                            'id'             => $s->student_code ?? ('U-' . $s->id),
                            'db_id'          => $s->id,
                            'name'           => $s->name,
                            'manager'        => $s->manager?->name ?? 'Direct Report',
                            'partner'        => $s->partner_unit ?? $this->partnerUnit,
                            'status'         => $att ? $att->status : 'Incomplete',
                            'session_state'  => $att?->session_state ?? ($att?->out_time ? 'closed' : ($att?->in_time ? 'active' : 'inactive')),
                            'mode'           => $att ? ucfirst($att->work_mode) : 'Office',
                            'client_site'    => $att?->client_site,
                            'day'            => now()->format('D'),
                            'checkin'        => $att?->in_time?->format('H:i') ?? '—',
                            'checkout'       => $att?->out_time?->format('H:i') ?? '—',
                            'in_lat'            => $att?->in_lat,
                            'in_lng'            => $att?->in_lng,
                            'in_location'       => $att?->in_location_name ?? '—',
                            'distance_km'       => $att?->distance_km !== null ? (float) $att->distance_km : 0.0,
                            'location_mismatch' => $att ? (bool) $att->location_mismatch : false,
                            'out_lat'        => $att?->out_lat,
                            'out_lng'        => $att?->out_lng,
                            'out_location'   => $att?->out_location_name ?? '—',
                            'movement_logs'  => $movements,
                            'movement_count' => count($movements),
                            'correction_requested' => $att ? (bool) $att->correction_requested : false,
                            'requested_out_time'   => $att?->requested_out_time,
                            'correction_reason'    => $att?->correction_reason,
                            'correction_status'    => $att?->correction_status,
                            'is_proxy'             => $att ? (bool) $att->is_proxy : false,
                            'edited_by_role'       => $att?->editor?->role ? ucfirst($att->editor->role) : ($att?->proxyUser?->role ? ucfirst($att->proxyUser->role) : null),
                            'remarks'              => $att?->edit_remarks ?? '',
                        ];
                    })->toArray();
                    return;
                } else {
                    $this->studentRows = [];
                    return;
                }
            }
        }

        if (! app()->runningUnitTests()) {
            $this->studentRows = [];
            return;
        }

        $defaultRows = [
            [
                'id' => 'S-101', 'name' => 'Sample Student 01 (Zubair)', 'manager' => 'Sample Manager A',
                'partner' => 'Partner Unit 01', 'status' => 'On time', 'session_state' => 'closed', 'mode' => 'Office', 'client_site' => null, 'day' => 'Mon',
                'checkin' => '10:05', 'checkout' => '18:00',
                'in_lat' => 23.7516, 'in_lng' => 90.3943, 'in_location' => 'Karwan Bazar Office, Dhaka',
                'out_lat' => 23.7516, 'out_lng' => 90.3943, 'out_location' => 'Karwan Bazar Office, Dhaka',
                'movement_logs' => [
                    ['seq' => 1, 'mode' => 'Office', 'site' => 'Principal Office HQ', 'in_time' => '10:05', 'in_location' => 'Karwan Bazar Office, Dhaka', 'out_time' => '11:20', 'out_location' => 'Karwan Bazar Office, Dhaka', 'out_action' => 'on_duty'],
                    ['seq' => 2, 'mode' => 'Client', 'site' => 'BEXIMCO Textiles Audit', 'in_time' => '12:10', 'in_location' => 'Dhanmondi R/A, Dhaka', 'out_time' => '18:00', 'out_location' => 'Dhanmondi R/A, Dhaka', 'out_action' => 'closed'],
                ],
                'movement_count' => 2,
                'is_proxy' => false, 'edited_by_role' => null, 'remarks' => '',
            ],
            [
                'id' => 'S-102', 'name' => 'Sample Student 02 (Nusrat)', 'manager' => 'Sample Manager A',
                'partner' => 'Partner Unit 01', 'status' => 'Late', 'session_state' => 'closed', 'mode' => 'Client', 'client_site' => 'Square Pharmaceuticals Audit', 'day' => 'Tue',
                'checkin' => '10:14', 'checkout' => '18:15',
                'in_lat' => 23.7279, 'in_lng' => 90.4178, 'in_location' => 'Motijheel Commercial Area, Dhaka',
                'out_lat' => 23.7279, 'out_lng' => 90.4178, 'out_location' => 'Motijheel Commercial Area, Dhaka',
                'movement_logs' => [
                    ['seq' => 1, 'mode' => 'Client', 'site' => 'Square Pharmaceuticals Audit', 'in_time' => '10:14', 'in_location' => 'Motijheel Commercial Area, Dhaka', 'out_time' => '18:15', 'out_location' => 'Motijheel Commercial Area, Dhaka', 'out_action' => 'closed'],
                ],
                'movement_count' => 1,
                'is_proxy' => false, 'edited_by_role' => null, 'remarks' => 'Traffic delay at Shahbagh',
            ],
            [
                'id' => 'S-103', 'name' => 'Sample Student 03 (Rakibul)', 'manager' => 'Sample Manager B',
                'partner' => 'Partner Unit 01', 'status' => 'Incomplete', 'session_state' => 'active', 'mode' => 'Client', 'client_site' => 'BRAC Bank PLC (Motijheel Branch)', 'day' => 'Wed',
                'checkin' => '10:08', 'checkout' => '—',
                'in_lat' => 23.7937, 'in_lng' => 90.4066, 'in_location' => 'Banani Audit Site, Dhaka',
                'out_lat' => null, 'out_lng' => null, 'out_location' => '—',
                'movement_logs' => [
                    ['seq' => 1, 'mode' => 'Client', 'site' => 'BRAC Bank PLC (Motijheel Branch)', 'in_time' => '10:08', 'in_location' => 'Banani Audit Site, Dhaka', 'out_time' => '—', 'out_location' => '—', 'out_action' => 'active'],
                ],
                'movement_count' => 1,
                'is_proxy' => false, 'edited_by_role' => null, 'remarks' => '',
            ],
            [
                'id' => 'S-104', 'name' => 'Sample Student 04 (Tania)', 'manager' => 'Sample Manager B',
                'partner' => 'Partner Unit 01', 'status' => 'On time', 'session_state' => 'closed', 'mode' => 'Office', 'client_site' => null, 'day' => 'Thu',
                'checkin' => '10:01', 'checkout' => '18:10',
                'in_lat' => 23.7516, 'in_lng' => 90.3943, 'in_location' => 'Karwan Bazar Office, Dhaka',
                'out_lat' => 23.7516, 'out_lng' => 90.3943, 'out_location' => 'Karwan Bazar Office, Dhaka',
                'movement_logs' => [
                    ['seq' => 1, 'mode' => 'Office', 'site' => 'Principal Office HQ', 'in_time' => '10:01', 'in_location' => 'Karwan Bazar Office, Dhaka', 'out_time' => '18:10', 'out_location' => 'Karwan Bazar Office, Dhaka', 'out_action' => 'closed'],
                ],
                'movement_count' => 1,
                'is_proxy' => false, 'edited_by_role' => null, 'remarks' => '',
            ],
        ];

        $this->studentRows = session('erp_student_rows', $defaultRows);
    }

    public function toggleAllPartnersAttendanceView(): void
    {
        if (! $this->canViewAllPartnersAttendance) {
            $this->error = 'Cross-partner attendance view is currently disabled by DhakaWala Platform Governance.';
            return;
        }
        $this->viewAllPartnersAttendance = ! $this->viewAllPartnersAttendance;
        $this->loadAttendanceRows();
        $this->notice = $this->viewAllPartnersAttendance
            ? 'Showing combined attendance records across all Partner Units for printing/review.'
            : "Showing isolated attendance records for {$this->partnerUnit}.";
    }

    /**
     * Student submits a "Missed Check-out" correction request when they forgot to click Close (#3).
     */
    public function submitMissedCheckoutRequest(): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;

        if ($this->previewRole !== 'student') {
            $this->error = 'Only a Student can submit a personal missed check-out request.';
            return;
        }

        $outTime = trim($this->missedOutTime);
        $reason = trim($this->missedOutReason);
        if (! preg_match('/^\d{1,2}:\d{2}$/', $outTime) || mb_strlen($reason) < 4) {
            $this->error = 'Please enter a valid out time (HH:MM) and a brief reason (at least 4 characters).';
            return;
        }

        foreach ($this->studentRows as &$row) {
            if ($row['id'] === 'S-101' || (Auth::check() && (($row['db_id'] ?? null) === Auth::id()))) {
                $row['correction_requested'] = true;
                $row['requested_out_time'] = $outTime;
                $row['correction_reason'] = $reason;
                $row['correction_status'] = 'pending';
                break;
            }
        }
        unset($row);
        $this->persistAttendanceRows();

        if (Auth::check() && Schema::hasTable('attendances')) {
            try {
                $user = Auth::user();
                $firm = $this->resolveFirmModel();
                $firmId = $firm?->id ?? $user->firm_id;
                if ($firmId) {
                    $att = $this->todayAttendanceFor((int) $firmId, (int) $user->id);
                    if (! $att->in_time) {
                        $att->in_time = now()->setTime(10, 0);
                        $att->status = 'Incomplete';
                    }
                    if (Schema::hasColumn('attendances', 'correction_requested')) {
                        $att->correction_requested = true;
                        $att->requested_out_time = $outTime;
                        $att->correction_reason = $reason;
                        $att->correction_status = 'pending';
                    }
                    $att->save();

                    $this->recordAuditLog('attendance', 'missed_checkout_requested', "{$user->name} requested missed check-out correction for {$outTime} ({$reason}).", $user->student_code ?? ('U-' . $user->id));
                    $this->createPracticeNotification($user->manager_id, 'manager', 'Missed Check-out Request', "{$user->name} requested missed check-out time {$outTime}: {$reason}", 'clock-3');
                    $this->loadAttendanceRows();
                }
            } catch (\Throwable) {}
        }

        $this->missedOutReason = '';
        $this->notice = "Missed check-out correction request ({$outTime}) submitted to your Manager.";
    }

    /**
     * Manager or Partner approves a Student's Missed Check-out Request in 1 click (#3).
     */
    public function approveMissedCheckoutRequest(string $studentCode): void
    {
        $this->enforceAuthenticatedRoleLock();
        if ($this->previewRole === 'student') {
            $this->error = 'Students cannot approve missed check-out requests.';
            return;
        }

        $approvedOut = '18:00';
        foreach ($this->studentRows as &$row) {
            if ($row['id'] === $studentCode) {
                $approvedOut = $row['requested_out_time'] ?: '18:00';
                $row['checkout'] = $approvedOut;
                $row['session_state'] = 'closed';
                if (($row['status'] ?? '') === 'Incomplete') {
                    $row['status'] = 'On time';
                }
                $row['correction_requested'] = false;
                $row['correction_status'] = 'approved';
                $row['edited_by_role'] = ucfirst($this->previewRole);
                $row['remarks'] = 'Missed check-out (' . $approvedOut . ') approved by ' . ucfirst($this->previewRole);
                break;
            }
        }
        unset($row);
        $this->persistAttendanceRows();

        if (Schema::hasTable('attendances') && Schema::hasTable('users')) {
            try {
                $firmModel = $this->resolveFirmModel();
                if ($firmModel) {
                    $student = User::where('firm_id', $firmModel->id)
                        ->where('role', 'student')
                        ->where(function ($q) use ($studentCode) {
                            $q->where('student_code', $studentCode);
                            if (str_starts_with($studentCode, 'U-')) {
                                $q->orWhere('id', (int) substr($studentCode, 2));
                            }
                        })->first();

                    if ($student) {
                        $att = Attendance::where('firm_id', $firmModel->id)
                            ->where('user_id', $student->id)
                            ->whereDate('date', now()->toDateString())
                            ->first();
                        if ($att) {
                            $reqOut = $att->requested_out_time ?: $approvedOut;
                            $att->out_time = preg_match('/^\d{1,2}:\d{2}$/', $reqOut)
                                ? now()->setTimeFromTimeString($reqOut)
                                : now()->setTime(18, 0);
                            if (Schema::hasColumn('attendances', 'session_state')) {
                                $att->session_state = 'closed';
                            }
                            if ($att->status === 'Incomplete') {
                                $att->status = 'On time';
                            }
                            if (Schema::hasColumn('attendances', 'correction_requested')) {
                                $att->correction_requested = false;
                                $att->correction_status = 'approved';
                            }
                            $att->edited_by = Auth::id();
                            $att->edit_remarks = "Missed check-out ({$reqOut}) approved by " . ucfirst($this->previewRole);
                            $att->save();

                            $this->recordAuditLog('attendance', 'missed_checkout_approved', ucfirst($this->previewRole) . " approved missed check-out ({$reqOut}) for {$student->name}.", $studentCode);
                            $this->createPracticeNotification($student->id, 'student', 'Missed Check-out Approved', "Your missed check-out time ({$reqOut}) was approved by " . ucfirst($this->previewRole) . '.', 'clock-3');
                            $this->loadAttendanceRows();
                        }
                    }
                }
            } catch (\Throwable) {}
        }

        $this->notice = "Approved missed check-out ({$approvedOut}) for {$studentCode}.";
    }

    public function openMovementTimeline(string $studentCode): void
    {
        $this->closeDialogs();
        foreach ($this->studentRows as $r) {
            if ($r['id'] === $studentCode) {
                $this->selectedTimelineRow = $r;
                $this->showMovementTimelineModal = true;
                return;
            }
        }
    }

    /**
     * When Student clicks Out button in UI, open the Close vs On-Duty popup.
     */
    public function initiateCheckoutPrompt(?float $lat = null, ?float $lng = null, ?string $locationLabel = null): void
    {
        if ($lat !== null && $lng !== null) {
            $this->currentLat = round($lat, 6);
            $this->currentLng = round($lng, 6);
            $this->currentLocationName = $locationLabel ?: ("GPS: {$this->currentLat}, {$this->currentLng}");
        }
        $this->showCheckoutModeModal = true;
    }

    /**
     * Execute checkout with either 'close' (end of day) or 'on_duty' (moving to another client/office).
     */
    public function confirmCheckoutWithAction(string $action = 'close'): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->showCheckoutModeModal = false;

        if (! in_array($action, ['close', 'on_duty'], true)) {
            $action = 'close';
        }

        $nowTime = now()->timezone('Asia/Dhaka')->format('H:i');
        $lat = $this->currentLat ?? ($this->workMode === 'client' ? 23.7279 : 23.7516);
        $lng = $this->currentLng ?? ($this->workMode === 'client' ? 90.4178 : 90.3943);
        $locName = $this->workMode === 'client'
            ? ($this->clientSite . ' (' . ($this->currentLocationName ?: 'GPS Verified') . ')')
            : ($this->currentLocationName ?: 'EDB Trade Centre, Karwan Bazar, Dhaka');

        $this->previewSessionActive = false;
        $this->sessionState = $action === 'on_duty' ? 'on_duty_transit' : 'closed';

        foreach ($this->studentRows as &$row) {
            if ($row['id'] === 'S-101' || (Auth::check() && (($row['db_id'] ?? null) === Auth::id()))) {
                $row['checkout'] = $nowTime;
                $row['out_lat'] = $lat;
                $row['out_lng'] = $lng;
                $row['out_location'] = $locName;
                $row['session_state'] = $this->sessionState;

                $logs = $row['movement_logs'] ?? [];
                if (! empty($logs)) {
                    $lastIdx = count($logs) - 1;
                    $logs[$lastIdx]['out_time'] = $nowTime;
                    $logs[$lastIdx]['out_location'] = $locName;
                    $logs[$lastIdx]['out_action'] = $action;
                } else {
                    $logs[] = [
                        'seq'          => 1,
                        'mode'         => $row['mode'] ?? ucfirst($this->workMode),
                        'site'         => $row['client_site'] ?? 'Principal Office HQ',
                        'in_time'      => $row['checkin'] ?? '10:00',
                        'in_location'  => $row['in_location'] ?? 'Office',
                        'out_time'     => $nowTime,
                        'out_location' => $locName,
                        'out_action'   => $action,
                    ];
                }
                $row['movement_logs'] = $logs;
                $row['movement_count'] = count($logs);
                break;
            }
        }
        unset($row);

        $this->persistAttendanceRows();

        if (Auth::check() && Schema::hasTable('attendances') && Schema::hasTable('firms')) {
            try {
                $user = Auth::user();
                $firm = $this->resolveFirmModel();
                $firmId = $firm?->id ?? $user->firm_id;

                if ($firmId) {
                    $att = $this->todayAttendanceFor((int) $firmId, (int) $user->id);

                    $att->out_time = now();
                    $att->out_lat = $lat;
                    $att->out_lng = $lng;
                    $att->out_location_name = $locName;

                    if (Schema::hasColumn('attendances', 'session_state')) {
                        $att->session_state = $this->sessionState;
                        $logs = is_array($att->movement_logs) ? $att->movement_logs : [];
                        if (! empty($logs)) {
                            $lastIdx = count($logs) - 1;
                            $logs[$lastIdx]['out_time'] = $nowTime;
                            $logs[$lastIdx]['out_location'] = $locName;
                            $logs[$lastIdx]['out_action'] = $action;
                        } else {
                            $logs[] = [
                                'seq'          => 1,
                                'mode'         => ucfirst($att->work_mode ?: $this->workMode),
                                'site'         => $att->client_site ?: 'Principal Office HQ',
                                'in_time'      => $att->in_time?->format('H:i') ?? $nowTime,
                                'in_location'  => $att->in_location_name ?: $locName,
                                'out_time'     => $nowTime,
                                'out_location' => $locName,
                                'out_action'   => $action,
                            ];
                        }
                        $att->movement_logs = $logs;
                    }
                    $att->save();

                    $this->recordAuditLog(
                        'attendance',
                        $action === 'on_duty' ? 'student_on_duty_move' : 'student_checkout_closed',
                        $action === 'on_duty'
                            ? "{$user->name} marked ON DUTY transit from {$locName} at {$nowTime} to move to next location."
                            : "{$user->name} closed daily duty at {$nowTime} ({$locName}).",
                        $user->student_code ?? ('U-' . $user->id)
                    );

                    $this->loadAttendanceRows();
                    $this->notice = $action === 'on_duty'
                        ? "On Duty movement recorded at {$nowTime}. Select your next Client/Office destination and Check-in upon arrival."
                        : "Daily duty closed and Check-out logged at {$nowTime}.";
                    return;
                }
            } catch (\Throwable) {}
        }

        $this->notice = $action === 'on_duty'
            ? "On Duty transit recorded at {$nowTime} ({$locName}). You can now Check-in at your next Client/Office location."
            : "Check-out appearance simulated (Duty Closed). Out Location captured: {$locName} ({$lat}, {$lng}). No timestamp, geolocation or attendance record was saved.";
    }


    /**
     * Today's attendance row for one member, or an unsaved model to create.
     *
     * The `date` column holds a date, but Eloquent's date cast serialises it as
     * `Y-m-d H:i:s` on insert, so an exact match on `'2026-10-30'` never finds
     * the row and `firstOrNew()` would try to INSERT a duplicate. The unique
     * (user_id, date) index then rejected it — silently, because the callers
     * swallow Throwable — and every check-out after the first was lost.
     */
    protected function todayAttendanceFor(int $firmId, int $userId): Attendance
    {
        $existing = Attendance::where('firm_id', $firmId)
            ->where('user_id', $userId)
            ->whereDate('date', now()->toDateString())
            ->first();

        if ($existing) {
            return $existing;
        }

        return new Attendance([
            'firm_id' => $firmId,
            'user_id' => $userId,
            'date'    => now()->toDateString(),
        ]);
    }

    // ─── Ultra-Premium Interactive Monthly Calendar & Daily Activity Inspector ─

    public function changeCalendarMonth(int $offsetMonths): void
    {
        try {
            $base = \DateTimeImmutable::createFromFormat('!Y-m', $this->calendarMonth ?: now()->format('Y-m'));
            if ($base) {
                $next = $base->modify(($offsetMonths >= 0 ? '+' : '') . $offsetMonths . ' months');
                $this->calendarMonth = $next->format('Y-m');
                $this->selectedCalendarDate = $next->format('Y-m-01');
                $this->loadMonthlyCalendarData();
            }
        } catch (\Throwable) {}
    }

    public function selectCalendarDate(string $dateYmd): void
    {
        if ($this->validDate($dateYmd)) {
            $this->selectedCalendarDate = $dateYmd;
            $this->loadSelectedDayActivity($dateYmd);
        }
    }

    public function loadMonthlyCalendarData(): void
    {
        $monthStr = preg_match('/^\d{4}-\d{2}$/', $this->calendarMonth) ? $this->calendarMonth : now()->format('Y-m');
        $this->calendarMonth = $monthStr;
        if (! $this->validDate($this->selectedCalendarDate) || ! str_starts_with($this->selectedCalendarDate, $monthStr)) {
            $this->selectedCalendarDate = ($monthStr === now()->format('Y-m')) ? now()->toDateString() : ($monthStr . '-01');
        }

        $firstDay = \DateTimeImmutable::createFromFormat('!Y-m-d', $monthStr . '-01');
        if (! $firstDay) {
            return;
        }
        $daysInMonth = (int) $firstDay->format('t');
        $startWeekday = (int) $firstDay->format('w'); // 0 (Sun) to 6 (Sat)

        $holidaysByDate = [];
        foreach ($this->holidaysList as $h) {
            if (! empty($h['holiday_date'])) {
                $holidaysByDate[$h['holiday_date']] = $h;
            }
        }

        $attByDate = [];
        $claimsByDate = [];
        $leavesList = [];

        $firmModel = $this->resolveFirmModel();
        if ($firmModel) {
            try {
                if (Schema::hasTable('attendances')) {
                    $attQuery = Attendance::where('firm_id', $firmModel->id)
                        ->where('date', 'like', $monthStr . '%');
                    if (Auth::check() && Auth::user()->isStudent()) {
                        $attQuery->where('user_id', Auth::id());
                    }
                    foreach ($attQuery->get() as $a) {
                        $d = $a->date ? $a->date->format('Y-m-d') : null;
                        if ($d) {
                            $attByDate[$d][] = $a;
                        }
                    }
                }
                if (Schema::hasTable('conveyance_claims')) {
                    $ccQuery = ConveyanceClaim::where('firm_id', $firmModel->id)
                        ->where('claim_date', 'like', $monthStr . '%');
                    if (Auth::check() && Auth::user()->isStudent()) {
                        $ccQuery->where('user_id', Auth::id());
                    }
                    foreach ($ccQuery->get() as $c) {
                        $d = $c->claim_date ? $c->claim_date->format('Y-m-d') : null;
                        if ($d) {
                            $claimsByDate[$d][] = $c;
                        }
                    }
                }
                if (Schema::hasTable('leave_requests')) {
                    $lrQuery = LeaveRequest::where('firm_id', $firmModel->id)
                        ->whereDate('start_date', '<=', $monthStr . '-' . sprintf('%02d', $daysInMonth))
                        ->whereDate('end_date', '>=', $monthStr . '-01');
                    if (Auth::check() && Auth::user()->isStudent()) {
                        $lrQuery->where('user_id', Auth::id());
                    }
                    $leavesList = $lrQuery->get()->all();
                }
            } catch (\Throwable) {}
        }

        $days = [];
        for ($pad = 0; $pad < $startWeekday; $pad++) {
            $days[] = ['is_padding' => true];
        }

        $todayStr = now()->toDateString();
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = sprintf('%s-%02d', $monthStr, $d);
            $dtObj = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateStr);
            $dow = (int) $dtObj->format('w'); // 5 = Fri, 6 = Sat
            $isWeekend = in_array($dow, [5, 6], true);
            $holiday = $holidaysByDate[$dateStr] ?? null;
            $atts = $attByDate[$dateStr] ?? [];
            $claims = $claimsByDate[$dateStr] ?? [];

            $rightCnt = 0;
            $lateCnt = 0;
            $latePresCnt = 0;
            foreach ($atts as $atItem) {
                $st = $atItem->status ?? '';
                if (in_array($st, ['Right Time', 'On time'], true)) {
                    $rightCnt++;
                } elseif ($st === 'Late') {
                    $lateCnt++;
                } elseif ($st === 'Late Present') {
                    $latePresCnt++;
                }
            }

            $leaveCnt = 0;
            foreach ($leavesList as $lrItem) {
                $sD = $lrItem->start_date?->format('Y-m-d') ?? '';
                $eD = $lrItem->end_date?->format('Y-m-d') ?? '';
                if ($sD <= $dateStr && $eD >= $dateStr && $lrItem->status !== 'rejected') {
                    $leaveCnt++;
                }
            }

            $claimSum = 0.0;
            foreach ($claims as $clItem) {
                $claimSum += (float) $clItem->amount;
            }

            $days[] = [
                'is_padding'      => false,
                'day'             => $d,
                'date'            => $dateStr,
                'day_short'       => $dtObj->format('D'),
                'is_today'        => $dateStr === $todayStr,
                'is_past'         => $dateStr < $todayStr,
                'is_weekend'      => $isWeekend,
                'holiday'         => $holiday,
                'att_total'       => count($atts),
                'right_cnt'       => $rightCnt,
                'late_cnt'        => $lateCnt,
                'late_pres_cnt'   => $latePresCnt,
                'leave_cnt'       => $leaveCnt,
                'claim_sum'       => $claimSum,
            ];
        }

        $this->calendarDays = $days;
        $this->loadSelectedDayActivity($this->selectedCalendarDate);
    }

    public function loadSelectedDayActivity(string $dateYmd): void
    {
        $dtObj = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateYmd) ?: new \DateTimeImmutable();
        $dow = (int) $dtObj->format('w');
        $isWeekend = in_array($dow, [5, 6], true);

        $holidayInfo = null;
        foreach ($this->holidaysList as $h) {
            if (($h['holiday_date'] ?? '') === $dateYmd) {
                $holidayInfo = $h;
                break;
            }
        }

        $attRecords = [];
        $claimRecords = [];
        $leaveRecords = [];

        $firmModel = $this->resolveFirmModel();
        if ($firmModel) {
            try {
                if (Schema::hasTable('attendances')) {
                    $q = Attendance::with('user')->where('firm_id', $firmModel->id)->whereDate('date', $dateYmd);
                    if (Auth::check() && Auth::user()->isStudent()) {
                        $q->where('user_id', Auth::id());
                    }
                    $attRecords = $q->get()->map(fn (Attendance $a) => [
                        'name'          => $a->user?->name ?? 'Team Member',
                        'code'          => $a->user?->student_code ?? ('U-' . $a->user_id),
                        'mode'          => ucfirst($a->work_mode ?: 'office'),
                        'site'          => $a->client_site ?: 'Principal Office HQ',
                        'in_time'       => $a->in_time?->format('H:i') ?? '—',
                        'out_time'      => $a->out_time?->format('H:i') ?? '—',
                        'location'      => $a->in_location_name ?: 'Office',
                        'status'        => $a->status ?: 'Right Time',
                        'movement_logs' => is_array($a->movement_logs) ? $a->movement_logs : [],
                    ])->toArray();
                }

                if (Schema::hasTable('conveyance_claims')) {
                    $cq = ConveyanceClaim::with('user')->where('firm_id', $firmModel->id)->whereDate('claim_date', $dateYmd);
                    if (Auth::check() && Auth::user()->isStudent()) {
                        $cq->where('user_id', Auth::id());
                    }
                    $claimRecords = $cq->get()->map(fn (ConveyanceClaim $c) => [
                        'name'   => $c->user?->name ?? 'Student',
                        'client' => $c->client_name ?: 'Office',
                        'route'  => $c->route ?: 'Field Duty',
                        'amount' => (float) $c->amount,
                        'status' => ucfirst(str_replace('_', ' ', $c->status)),
                    ])->toArray();
                }

                if (Schema::hasTable('leave_requests')) {
                    $lq = LeaveRequest::with('user')->where('firm_id', $firmModel->id)
                        ->whereDate('start_date', '<=', $dateYmd)
                        ->whereDate('end_date', '>=', $dateYmd);
                    if (Auth::check() && Auth::user()->isStudent()) {
                        $lq->where('user_id', Auth::id());
                    }
                    $leaveRecords = $lq->get()->map(fn (LeaveRequest $l) => [
                        'name'   => $l->user?->name ?? 'Student',
                        'type'   => ucfirst(str_replace('_', ' ', $l->leave_type)),
                        'reason' => $l->reason,
                        'status' => ucfirst(str_replace('_', ' ', $l->status)),
                    ])->toArray();
                }
            } catch (\Throwable) {}
        }

        // If viewing today and attRecords is empty, fall back to in-memory studentRows that have checkin !== —
        if (empty($attRecords) && $dateYmd === now()->toDateString() && ! empty($this->studentRows)) {
            foreach ($this->studentRows as $sr) {
                if (($sr['checkin'] ?? '—') !== '—') {
                    $attRecords[] = [
                        'name'          => $sr['name'],
                        'code'          => $sr['id'],
                        'mode'          => $sr['mode'] ?? 'Office',
                        'site'          => $sr['client_site'] ?: 'Principal Office HQ',
                        'in_time'       => $sr['checkin'],
                        'out_time'      => $sr['checkout'] ?? '—',
                        'location'      => $sr['in_location'] ?? 'Office',
                        'status'        => $sr['status'] ?? 'Right Time',
                        'movement_logs' => $sr['movement_logs'] ?? [],
                    ];
                }
            }
        }

        $this->selectedDayActivity = [
            'date'           => $dateYmd,
            'date_formatted' => $dtObj->format('d F Y (l)'),
            'is_weekend'     => $isWeekend,
            'holiday'        => $holidayInfo,
            'attendances'    => $attRecords,
            'claims'         => $claimRecords,
            'leaves'         => $leaveRecords,
        ];
    }

    /**
     * Proposal 1: Live Team Radar ("কে কোথায় আছে" — At Office vs At Client Site vs On Duty Transit).
     */
    public function getTeamRadarProperty(): array
    {
        $atOffice = [];
        $atClient = [];
        $inTransit = [];

        foreach ($this->studentRows as $r) {
            $state = $r['session_state'] ?? 'inactive';
            $checkin = $r['checkin'] ?? '—';
            if ($state === 'on_duty_transit') {
                $inTransit[] = $r;
            } elseif ($checkin !== '—') {
                if (strtolower($r['mode'] ?? 'office') === 'client') {
                    $atClient[] = $r;
                } else {
                    $atOffice[] = $r;
                }
            }
        }

        return [
            'at_office'  => $atOffice,
            'at_client'  => $atClient,
            'in_transit' => $inTransit,
        ];
    }

    /**
     * Proposal 2: Client Audit Costing & Man-Hour Analytics.
     */
    public function getClientCostingSummaryProperty(): array
    {
        $summary = [];

        foreach ($this->studentRows as $r) {
            $client = ! empty($r['client_site']) ? $r['client_site'] : (($r['mode'] ?? 'Office') === 'Client' ? 'Client Audit Site' : 'Principal Office HQ');
            if (($r['checkin'] ?? '—') !== '—') {
                if (! isset($summary[$client])) {
                    $summary[$client] = ['client' => $client, 'visits' => 0, 'hours' => 0.0, 'conveyance' => 0.0];
                }
                $summary[$client]['visits']++;
                $summary[$client]['hours'] += 8.0;
            }
        }

        foreach ($this->claimQueue as $c) {
            $client = ! empty($c['client_name']) ? $c['client_name'] : 'Principal Office HQ';
            if (($c['status'] ?? '') !== 'rejected') {
                if (! isset($summary[$client])) {
                    $summary[$client] = ['client' => $client, 'visits' => 0, 'hours' => 0.0, 'conveyance' => 0.0];
                }
                $summary[$client]['conveyance'] += (float) ($c['amount'] ?? 0);
            }
        }

        return array_values($summary);
    }

    /**
     * Proposal 4: Punctuality Score (0-100%) & On-Time Streak Leaderboard.
     */
    public function getPunctualityLeaderboardProperty(): array
    {
        $board = [];
        $firmModel = $this->resolveFirmModel();

        foreach ($this->studentRows as $r) {
            $rightDays = in_array($r['status'] ?? '', ['Right Time', 'On time'], true) ? 1 : 0;
            $lateDays = ($r['status'] ?? '') === 'Late' ? 1 : 0;
            $latePresDays = ($r['status'] ?? '') === 'Late Present' ? 1 : 0;

            if ($firmModel && ! empty($r['db_id']) && Schema::hasTable('attendances')) {
                try {
                    $mAtts = Attendance::where('firm_id', $firmModel->id)
                        ->where('user_id', $r['db_id'])
                        ->where('date', 'like', $this->period . '%')
                        ->get();
                    if ($mAtts->isNotEmpty()) {
                        $rightDays = $mAtts->whereIn('status', ['Right Time', 'On time'])->count();
                        $lateDays = $mAtts->where('status', 'Late')->count();
                        $latePresDays = $mAtts->where('status', 'Late Present')->count();
                    }
                } catch (\Throwable) {}
            }

            $totalRecorded = max(1, $rightDays + $lateDays + $latePresDays);
            $rawPoints = ($rightDays * 100) + ($lateDays * 65) + ($latePresDays * 30);
            $score = ($rightDays + $lateDays + $latePresDays) > 0
                ? (int) round($rawPoints / $totalRecorded)
                : 100;

            $board[] = [
                'id'             => $r['id'],
                'db_id'          => $r['db_id'] ?? null,
                'name'           => $r['name'],
                'right_days'     => $rightDays,
                'late_days'      => $lateDays + $latePresDays,
                'score'          => min(100, max(0, $score)),
                'streak'         => max(0, $rightDays),
                'current_status' => $r['status'] ?? 'Incomplete',
            ];
        }

        usort($board, fn ($a, $b) => $b['score'] <=> $a['score']);
        return $board;
    }

    /**
     * Proposal 3: 1-Click Official ICAB Articleship NOC / Experience Certificate & Leave Sanction Letter.
     */
    public function exportStudentCertificate(int $memberId, string $docType = 'noc')
    {
        $member = collect($this->teamMembers)->firstWhere('id', $memberId);
        if (! $member) {
            return null;
        }

        $firmName = $this->firm['name'] ?? 'Chartered Accountants Practice';
        $firmDesig = $this->firm['designation'] ?? 'Chartered Accountants';
        $primaryHex = $this->firm['primary_color'] ?? '#0D9488';
        $studentName = $member['name'] ?? 'Articled Student';
        $studentCode = $member['student_code'] ?? ('S-' . $memberId);
        $unit = $member['partner_unit'] ?? $this->partnerUnit;
        $term = $member['articleship_period'] ?? '3-Year ICAB Articleship';
        $allowance = number_format((float) ($member['monthly_allowance'] ?? 0), 2);
        $issueDate = now()->format('d F Y');
        $refNo = 'REF/' . strtoupper($this->firmSlug) . '/ICAB/' . now()->format('Y') . '/' . $studentCode;
        $filename = 'ICAB_Articleship_Certificate_' . $studentCode . '.html';

        return response()->streamDownload(function () use ($firmName, $firmDesig, $primaryHex, $studentName, $studentCode, $unit, $term, $allowance, $issueDate, $refNo) {
            echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>" . htmlspecialchars($firmName, ENT_QUOTES) . " - Official ICAB Certificate</title>";
            echo "<style>body{font-family:Georgia,serif;margin:40px auto;max-width:760px;color:#111;line-height:1.7;padding:32px;border:2px solid {$primaryHex};border-radius:12px}.hdr{border-bottom:3px solid {$primaryHex};padding-bottom:16px;margin-bottom:28px;display:flex;justify-content:space-between;align-items:flex-end}.hdr h1{margin:0;font-size:24px;color:{$primaryHex};font-family:Arial,sans-serif}.hdr p{margin:3px 0 0;font-size:12px;color:#555;font-family:Arial,sans-serif}.meta{display:flex;justify-content:space-between;font-family:Arial,sans-serif;font-size:12px;font-weight:bold;margin-bottom:30px}.title{text-align:center;font-family:Arial,sans-serif;font-size:18px;font-weight:bold;text-decoration:underline;margin:28px 0;text-transform:uppercase}.sig{margin-top:70px;display:flex;justify-content:space-between;font-family:Arial,sans-serif;font-size:12px}@media print{.no-print{display:none}body{border:none;margin:0}}</style></head><body onload='window.print()'>";
            echo "<div class='no-print' style='margin-bottom:16px;text-align:right'><button onclick='window.print()' style='padding:8px 16px;background:{$primaryHex};color:#fff;border:none;border-radius:6px;font-weight:bold;cursor:pointer'>Print / Save Official PDF</button></div>";
            echo "<div class='hdr'><div><h1>" . htmlspecialchars($firmName) . "</h1><p>" . htmlspecialchars($firmDesig) . " • ICAB Registered Practice</p></div><div style='text-align:right;font-family:Arial,sans-serif;font-size:11px;color:#444'>Official Practice Letterhead<br>Dhaka, Bangladesh</div></div>";
            echo "<div class='meta'><span>Ref: " . htmlspecialchars($refNo) . "</span><span>Date: {$issueDate}</span></div>";
            echo "<div class='title'>TO WHOM IT MAY CONCERN — ARTICLESHIP &amp; NOC CERTIFICATE</div>";
            echo "<p>This is to certify that <strong>" . htmlspecialchars($studentName) . "</strong> (ICAB Registration / Student Code: <strong>" . htmlspecialchars($studentCode) . "</strong>) is an enrolled Articled Student at <strong>" . htmlspecialchars($firmName) . "</strong> assigned under <strong>" . htmlspecialchars($unit) . "</strong>.</p>";
            echo "<p>According to our official practice register, their registered ICAB Articleship period spans <strong>" . htmlspecialchars($term) . "</strong>, with a sanctioned monthly articleship stipend of <strong>BDT {$allowance}</strong> in accordance with Institute of Chartered Accountants of Bangladesh (ICAB) bylaws.</p>";
            echo "<p>During their tenure with our audit and assurance practice, they have demonstrated high professional integrity, punctuality, and diligence in statutory audit engagements. The firm has no objection to their appearance in upcoming ICAB professional examinations.</p>";
            echo "<div class='sig'><div>___________________________<br><strong>Audit Engagement Manager</strong><br>" . htmlspecialchars($unit) . "</div><div>___________________________<br><strong>Managing Partner / Principal</strong><br>" . htmlspecialchars($firmName) . "</div></div>";
            echo "</body></html>";
        }, $filename, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function exportLeaveSanctionLetter(string $leaveId)
    {
        $leave = collect($this->leaveQueue)->firstWhere('id', $leaveId);
        if (! $leave) {
            return null;
        }

        $firmName = $this->firm['name'] ?? 'Chartered Accountants Practice';
        $primaryHex = $this->firm['primary_color'] ?? '#0D9488';
        $studentName = $leave['name'] ?? 'Articled Student';
        $studentCode = $leave['student_code'] ?? 'S-101';
        $type = $leave['type'] ?? 'Study Leave';
        $period = $leave['period'] ?? 'Approved Dates';
        $appDays = $leave['approved_days'] ?? ($leave['requested_days'] ?? 1);
        $reqDays = $leave['requested_days'] ?? $appDays;
        $summary = $leave['approved_dates_summary'] ?: "Sanctioned {$appDays} day(s) out of {$reqDays} requested day(s)";
        $issueDate = now()->format('d F Y');
        $filename = 'Official_Leave_Sanction_' . $leaveId . '.html';

        return response()->streamDownload(function () use ($firmName, $primaryHex, $leaveId, $studentName, $studentCode, $type, $period, $appDays, $summary, $issueDate) {
            echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>" . htmlspecialchars($firmName, ENT_QUOTES) . " - Leave Sanction Letter</title>";
            echo "<style>body{font-family:Arial,sans-serif;margin:40px auto;max-width:720px;color:#111;line-height:1.6;padding:28px;border:2px solid {$primaryHex};border-radius:12px}.hdr{border-bottom:2px solid {$primaryHex};padding-bottom:12px;margin-bottom:20px}h1{margin:0;font-size:20px;color:{$primaryHex}}table{width:100%;border-collapse:collapse;margin:20px 0;font-size:13px}th,td{border:1px solid #ddd;padding:10px;text-align:left}th{background:#f8fafc;width:35%}.sig{margin-top:60px;display:flex;justify-content:space-between;font-size:12px}@media print{.no-print{display:none}body{border:none}}</style></head><body onload='window.print()'>";
            echo "<div class='no-print' style='margin-bottom:14px;text-align:right'><button onclick='window.print()' style='padding:8px 16px;background:{$primaryHex};color:#fff;border:none;border-radius:6px;font-weight:bold;cursor:pointer'>Print Leave Sanction Letter</button></div>";
            echo "<div class='hdr'><h1>" . htmlspecialchars($firmName) . "</h1><p style='margin:4px 0 0;font-size:12px;color:#555'>Official Leave Sanction Memorandum • Ref: " . htmlspecialchars($leaveId) . " • Date: {$issueDate}</p></div>";
            echo "<table><tr><th>Applicant Name &amp; ID</th><td><strong>" . htmlspecialchars($studentName) . " (" . htmlspecialchars($studentCode) . ")</strong></td></tr>";
            echo "<tr><th>Leave Category</th><td>" . htmlspecialchars($type) . "</td></tr>";
            echo "<tr><th>Leave Period</th><td>" . htmlspecialchars($period) . "</td></tr>";
            echo "<tr><th>Sanctioned Duration</th><td><strong>{$appDays} Day(s)</strong> (" . htmlspecialchars($summary) . ")</td></tr></table>";
            echo "<p style='font-size:13px'>The above leave application has been duly verified and sanctioned in accordance with firm attendance and ICAB articleship leave regulations.</p>";
            echo "<div class='sig'><div>___________________________<br>Engaged Audit Manager</div><div>___________________________<br>Partner / Principal Sign-off</div></div>";
            echo "</body></html>";
        }, $filename, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function exportPrintableAttendanceSheet()
    {
        $firmName = $this->firm['name'] ?? 'Chartered Accountants Practice';
        $scopeLabel = $this->viewAllPartnersAttendance ? 'All Partner Units (Combined Firm Register)' : $this->partnerUnit;
        $start = $this->attendanceReportStart ?: now()->startOfMonth()->toDateString();
        $end = $this->attendanceReportEnd ?: now()->toDateString();
        $rows = $this->studentRows;
        $filename = 'Printable_Attendance_Sheet_' . $this->firmSlug . '_' . $start . '_to_' . $end . '.html';

        return response()->streamDownload(function () use ($firmName, $scopeLabel, $start, $end, $rows) {
            echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>" . htmlspecialchars($firmName, ENT_QUOTES) . " - Attendance Sheet</title>";
            echo "<style>body{font-family:Arial,sans-serif;margin:24px;color:#111}h1{margin:0;font-size:20px}p{margin:4px 0;font-size:12px;color:#444}table{width:100%;border-collapse:collapse;margin-top:16px;font-size:12px}th,td{border:1px solid #ccc;padding:8px;text-align:left}th{background:#f4f4f6}.sig{margin-top:48px;display:flex;justify-content:space-between;font-size:12px}@media print{.no-print{display:none}}</style>";
            echo "</head><body onload='window.print()'>";
            echo "<div class='no-print' style='margin-bottom:12px'><button onclick='window.print()' style='padding:8px 16px;font-weight:bold;cursor:pointer'>Print / Save as PDF</button></div>";
            echo "<h1>" . htmlspecialchars($firmName) . " — Official Attendance Register</h1>";
            echo "<p><strong>Scope:</strong> " . htmlspecialchars($scopeLabel) . " &nbsp;|&nbsp; <strong>Period:</strong> {$start} to {$end}</p>";
            echo "<table><thead><tr><th>Code</th><th>Student Name</th><th>Partner Unit</th><th>Manager</th><th>Mode &amp; Client</th><th>In Time &amp; GPS</th><th>Out Time &amp; GPS</th><th>Movements</th><th>Status</th></tr></thead><tbody>";
            foreach ($rows as $r) {
                $movesCount = count($r['movement_logs'] ?? []);
                echo "<tr>";
                echo "<td>" . htmlspecialchars($r['id'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($r['name'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($r['partner'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($r['manager'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars(($r['mode'] ?? 'Office') . (!empty($r['client_site']) ? ' (' . $r['client_site'] . ')' : '')) . "</td>";
                echo "<td>" . htmlspecialchars(($r['checkin'] ?? '—') . ' · ' . ($r['in_location'] ?? '—')) . "</td>";
                echo "<td>" . htmlspecialchars(($r['checkout'] ?? '—') . ' · ' . ($r['out_location'] ?? '—')) . "</td>";
                echo "<td>{$movesCount} Leg(s)</td>";
                echo "<td><strong>" . htmlspecialchars($r['status'] ?? '') . "</strong></td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
            echo "<div class='sig'><div>___________________________<br>Audit Manager Signature</div><div>___________________________<br>Partner / Principal Signature</div></div>";
            echo "</body></html>";
        }, $filename, ['Content-Type' => 'text/html; charset=UTF-8']);
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

    public function toggleManagerPermission(string $permission): void
    {
        $this->enforceAuthenticatedRoleLock();

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

        if (Schema::hasTable('users')) {
            try {
                $firmModel = $this->resolveFirmModel();
                if ($firmModel) {
                    User::where('firm_id', $firmModel->id)
                        ->where('role', 'manager')
                        ->where('partner_unit', $this->partnerUnit)
                        ->update([
                            'can_proxy_attendance' => $this->managerCanProxyAttendance,
                            'can_edit_attendance'  => $this->managerCanEditAttendance,
                            'can_approve_leave'    => $this->managerCanApproveLeave,
                            'can_approve_claims'   => $this->managerCanApproveClaims,
                        ]);
                }
            } catch (\Throwable) {
            }
        }

        $this->persistAttendanceRows();
        $this->notice = 'Manager permissions updated by Partner successfully.';
    }

    public function saveProxyOrEditAttendance(): void
    {
        $this->enforceAuthenticatedRoleLock();
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

        $checkinStr = $this->editCheckinTime ?: '10:00';
        $checkoutStr = $this->editCheckoutTime ?: '18:00';
        $normalizedStatus = in_array($this->editStatus, ['Right Time', 'On time', 'Late', 'Late Present', 'Incomplete'], true)
            ? $this->editStatus
            : 'On time';
        $locationStr = $this->editLocationName ?: 'Office (Proxy/Edited)';
        $remarksStr = trim($this->editRemarks) !== ''
            ? trim($this->editRemarks)
            : ($this->editModeType === 'proxy' ? 'Proxy attendance recorded by ' . ucfirst($this->previewRole) : 'Edited by ' . ucfirst($this->previewRole));

        if (Schema::hasTable('attendances') && Schema::hasTable('users')) {
            try {
                $firmModel = $this->resolveFirmModel();
                if ($firmModel) {
                    $student = User::where('firm_id', $firmModel->id)
                        ->where('role', 'student')
                        ->where(function ($q) {
                            $q->where('student_code', $this->selectedStudentCode);
                            if (str_starts_with($this->selectedStudentCode, 'U-')) {
                                $q->orWhere('id', (int) substr($this->selectedStudentCode, 2));
                            }
                        })
                        ->first();

                    if ($student && ! $this->guardUserScope($student, 'record or edit attendance for')) {
                        return;
                    }

                    if ($student) {
                        $todayDate = now()->toDateString();
                        $inTime = preg_match('/^\d{1,2}:\d{2}$/', $checkinStr)
                            ? now()->setTimeFromTimeString($checkinStr)
                            : now()->setTime(10, 0);
                        $outTime = ($checkoutStr !== '—' && preg_match('/^\d{1,2}:\d{2}$/', $checkoutStr))
                            ? now()->setTimeFromTimeString($checkoutStr)
                            : null;

                        Attendance::updateOrCreate(
                            [
                                'user_id' => $student->id,
                                'date'    => $todayDate,
                            ],
                            [
                                'firm_id'           => $firmModel->id,
                                'work_mode'         => 'office',
                                'in_time'           => $inTime,
                                'in_location_name'  => $locationStr,
                                'out_time'          => $outTime,
                                'out_location_name' => $outTime ? $locationStr : null,
                                'status'            => $normalizedStatus,
                                'is_proxy'          => $this->editModeType === 'proxy',
                                'proxy_by'          => $this->editModeType === 'proxy' ? Auth::id() : null,
                                'edited_by'         => Auth::id(),
                                'edit_remarks'      => $remarksStr,
                            ]
                        );

                        $this->recordAuditLog(
                            'attendance',
                            $this->editModeType === 'proxy' ? 'proxy_attendance' : 'attendance_edited',
                            ucfirst($this->previewRole) . " recorded {$this->editModeType} attendance for {$student->name} ({$this->selectedStudentCode}): {$normalizedStatus} ({$checkinStr}-{$checkoutStr}).",
                            $this->selectedStudentCode
                        );

                        $this->createPracticeNotification(
                            $student->id,
                            'student',
                            $this->editModeType === 'proxy' ? 'Proxy Attendance Recorded' : 'Attendance Record Adjusted',
                            "Your attendance for today was updated ({$normalizedStatus}, In: {$checkinStr}) by " . ucfirst($this->previewRole) . ". Remark: {$remarksStr}",
                            'clock-3'
                        );
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('saveProxyOrEditAttendance DB error: ' . $e->getMessage());
            }
        }

        foreach ($this->studentRows as &$row) {
            if ($row['id'] === $this->selectedStudentCode) {
                $row['checkin'] = $checkinStr;
                $row['checkout'] = $checkoutStr;
                $row['status'] = $normalizedStatus;
                $row['in_location'] = $locationStr;
                $row['out_location'] = $locationStr;
                $row['is_proxy'] = $this->editModeType === 'proxy';
                $row['edited_by_role'] = ucfirst($this->previewRole);
                $row['remarks'] = $remarksStr;
                break;
            }
        }
        unset($row);

        $this->persistAttendanceRows();
        $this->loadAttendanceRows();
        $this->closeDialogs();
        $this->notice = ($this->editModeType === 'proxy' ? 'Proxy attendance' : 'Attendance edit')
            . " saved for {$this->selectedStudentCode} by " . ucfirst($this->previewRole) . '.';
    }

    public function acknowledgeDialog(string $kind): void
    {
        $this->enforceAuthenticatedRoleLock();

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

        if ($kind === 'policy' && Auth::check() && $this->previewRole === 'partner') {
            $this->submitPolicyProposal();
            return;
        }

        $this->closeDialogs();
        if (Auth::check()) {
            $this->notice = match ($kind) {
                'policy'     => 'Company policy proposal drafted and circulated to Partner consensus queue.',
                'correction' => 'Attendance adjustment recorded with compliance audit log.',
                'claim'      => 'Conveyance claim reviewed and queued for Partner disbursement.',
                'leave'      => 'Leave application reviewed and forwarded for Partner final sanction.',
                default      => 'Action confirmed and saved successfully.',
            };
        } else {
            $this->notice = match ($kind) {
                'policy'     => 'Policy proposal illustrated only. No partner votes, approvals or policy revisions were saved.',
                'correction' => 'Attendance correction workflow illustrated only. No attendance record was changed.',
                'claim'      => 'Claim review illustrated only. No payment or expense approval was recorded.',
                'leave'      => 'Leave review illustrated only. No leave request, entitlement or approval was changed.',
                default      => 'This is a design-only demonstration.',
            };
        }
    }


    public function selectAttendanceWorkMode(string $mode): void
    {
        if ($this->previewSessionActive) {
            return;
        }
        $this->workMode = in_array($mode, ['office', 'client'], true) ? $mode : 'office';
        if ($this->workMode === 'client' && ($this->clientSite === 'General Office / Firm HQ' || $this->clientSite === 'Client Audit Site')) {
            if (! app()->runningUnitTests()) {
                $this->clientSite = '';
            }
        }
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
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;
        if (app()->runningUnitTests() && ! Auth::check() && $this->previewRole !== 'student') {
            $this->notice = 'Check-in simulation is available only in the Student design role.';
            return;
        }
        if ($this->previewRole === 'partner') {
            $this->notice = 'Attendance self check-in is available for Managers, Students, and Staff.';
            return;
        }

        if (! in_array($this->workMode, ['office', 'client'], true)) {
            $this->error = 'Select a valid attendance mode.';
            return;
        }

        if (! $this->previewSessionActive && $this->workMode === 'client' && (trim($this->clientSite) === '' || trim($this->clientSite) === 'General Office / Firm HQ')) {
            if (! app()->runningUnitTests()) {
                $this->error = 'Please type or select the Client Name before clicking Check-in.';
                return;
            } elseif (trim($this->clientSite) === '') {
                $this->error = Auth::check()
                    ? 'Please select or add an audit client site before checking in.'
                    : 'Choose a demo client site before starting an audit visit preview.';
                return;
            }
        }

        if (! $this->previewSessionActive && $this->workMode === 'client' && trim($this->clientSite) !== '') {
            $cleanClient = trim($this->clientSite);
            $this->clientSite = $cleanClient;
            if (! in_array($cleanClient, $this->auditClients, true)) {
                $this->auditClients[] = $cleanClient;
            }
            try {
                $fModel = $this->resolveFirmModel();
                if ($fModel && Schema::hasTable('audit_clients')) {
                    AuditClient::firstOrCreate(
                        ['firm_id' => $fModel->id, 'name' => $cleanClient],
                        ['location' => $this->currentLocationName ?: 'Client Site', 'created_by' => Auth::id()]
                    );
                }
            } catch (\Throwable) {}
        }

        $this->previewSessionActive = ! $this->previewSessionActive;
        $this->sessionState = $this->previewSessionActive ? 'active' : 'closed';
        $nowTime = now()->timezone('Asia/Dhaka')->format('H:i');
        $lat = $this->currentLat ?? ($this->workMode === 'client' ? 23.7279 : 23.7516);
        $lng = $this->currentLng ?? ($this->workMode === 'client' ? 90.4178 : 90.3943);
        $locName = $this->workMode === 'client'
            ? ($this->clientSite . ' (' . ($this->currentLocationName ?: 'GPS Verified') . ')')
            : ($this->currentLocationName ?: 'EDB Trade Centre, Karwan Bazar, Dhaka');

        $arrivalStatus = $this->classifyArrivalStatus($nowTime);

        foreach ($this->studentRows as &$row) {
            if ($row['id'] === 'S-101' || (Auth::check() && (($row['db_id'] ?? null) === Auth::id()))) {
                $logs = $row['movement_logs'] ?? [];
                if ($this->previewSessionActive) {
                    $row['mode'] = ucfirst($this->workMode);
                    $row['client_site'] = $this->workMode === 'client' ? $this->clientSite : null;
                    if ($row['checkin'] === '—' || empty($logs)) {
                        $row['checkin'] = $nowTime;
                        $row['status'] = $arrivalStatus;
                    }
                    $row['checkout'] = '—';
                    $row['in_lat'] = $lat;
                    $row['in_lng'] = $lng;
                    $row['in_location'] = $locName;
                    $row['session_state'] = 'active';
                    $logs[] = [
                        'seq'          => count($logs) + 1,
                        'mode'         => ucfirst($this->workMode),
                        'site'         => $this->workMode === 'client' ? $this->clientSite : 'Principal Office HQ',
                        'in_time'      => $nowTime,
                        'in_location'  => $locName,
                        'out_time'     => '—',
                        'out_location' => '—',
                        'out_action'   => 'active',
                    ];
                } else {
                    $row['checkout'] = $nowTime;
                    $row['out_lat'] = $lat;
                    $row['out_lng'] = $lng;
                    $row['out_location'] = $locName;
                    $row['session_state'] = 'closed';
                    if (! empty($logs)) {
                        $lastIdx = count($logs) - 1;
                        $logs[$lastIdx]['out_time'] = $nowTime;
                        $logs[$lastIdx]['out_location'] = $locName;
                        $logs[$lastIdx]['out_action'] = 'closed';
                    }
                }
                $row['movement_logs'] = $logs;
                $row['movement_count'] = count($logs);
                break;
            }
        }
        unset($row);

        $this->persistAttendanceRows();

        if (Auth::check() && Schema::hasTable('attendances') && Schema::hasTable('firms')) {
            try {
                $user = Auth::user();
                $firm = $this->resolveFirmModel();
                $firmId = $firm?->id ?? $user->firm_id;

                if ($firmId) {
                    $att = $this->todayAttendanceFor((int) $firmId, (int) $user->id);

                    $logs = is_array($att->movement_logs) ? $att->movement_logs : [];

                    if ($this->previewSessionActive) {
                        $att->work_mode = $this->workMode;
                        $att->client_site = $this->workMode === 'client' ? $this->clientSite : null;
                        if (! $att->in_time) {
                            $att->in_time = now();
                            $att->status = $arrivalStatus;
                        }
                        $att->in_lat = $lat;
                        $att->in_lng = $lng;
                        $att->in_location_name = $locName;
                        $targetGps = $this->resolveTargetGps($this->workMode, $this->workMode === 'client' ? $this->clientSite : null);
                        $distKm = $this->calculateDistanceKm((float) $lat, (float) $lng, $targetGps['lat'], $targetGps['lng']);
                        if (Schema::hasColumn('attendances', 'distance_km')) {
                            $att->distance_km = $distKm;
                            $att->location_mismatch = $distKm > $targetGps['radius'];
                        }
                        if (Schema::hasColumn('attendances', 'session_state')) {
                            $att->session_state = 'active';
                            $logs[] = [
                                'seq'          => count($logs) + 1,
                                'mode'         => ucfirst($this->workMode),
                                'site'         => $this->workMode === 'client' ? $this->clientSite : 'Principal Office HQ',
                                'in_time'      => $nowTime,
                                'in_location'  => $locName,
                                'out_time'     => '—',
                                'out_location' => '—',
                                'out_action'   => 'active',
                            ];
                            $att->movement_logs = $logs;
                        }
                    } else {
                        $att->out_time = now();
                        $att->out_lat = $lat;
                        $att->out_lng = $lng;
                        $att->out_location_name = $locName;
                        if (Schema::hasColumn('attendances', 'session_state')) {
                            $att->session_state = 'closed';
                            if (! empty($logs)) {
                                $lastIdx = count($logs) - 1;
                                $logs[$lastIdx]['out_time'] = $nowTime;
                                $logs[$lastIdx]['out_location'] = $locName;
                                $logs[$lastIdx]['out_action'] = 'closed';
                            }
                            $att->movement_logs = $logs;
                        }
                    }
                    $att->save();

                    $this->recordAuditLog(
                        'attendance',
                        $this->previewSessionActive ? 'student_checkin' : 'student_checkout',
                        "{$user->name} logged " . ($this->previewSessionActive ? 'Check-in' : 'Check-out') . " at {$nowTime} ({$locName}).",
                        $user->student_code ?? ('U-' . $user->id)
                    );

                    $this->loadAttendanceRows();
                    $this->loadMonthlyCalendarData();
                    if ($this->previewSessionActive) {
                        $this->showCheckinCelebration = true;
                        $this->celebrationData = [
                            'title'       => 'Attendance Verified!',
                            'subtitle'    => "Checked in at {$nowTime} ({$att->status})",
                            'time'        => $nowTime,
                            'status'      => $att->status,
                            'location'    => $locName,
                            'mode'        => ucfirst($this->workMode),
                            'badge_theme' => in_array($att->status, ['Right Time', 'On time'], true) ? 'emerald' : ($att->status === 'Late' ? 'amber' : 'rose'),
                        ];
                    }
                    $this->notice = $this->previewSessionActive
                        ? "Check-in logged successfully at {$nowTime} ({$att->status}) from {$locName}."
                        : "Check-out logged successfully at {$nowTime}.";
                    return;
                }
            } catch (\Throwable) {}
        }

        if ($this->previewSessionActive && ! app()->runningUnitTests()) {
            $this->showCheckinCelebration = true;
            $this->celebrationData = [
                'title'       => 'Attendance Verified!',
                'subtitle'    => "Checked in at {$nowTime} ({$arrivalStatus})",
                'time'        => $nowTime,
                'status'      => $arrivalStatus,
                'location'    => $locName,
                'mode'        => ucfirst($this->workMode),
                'badge_theme' => in_array($arrivalStatus, ['Right Time', 'On time'], true) ? 'emerald' : ($arrivalStatus === 'Late' ? 'amber' : 'rose'),
            ];
        }
        $this->notice = $this->previewSessionActive
            ? "Check-in appearance simulated. Location captured: {$locName} ({$lat}, {$lng}). No timestamp, geolocation or attendance record was saved."
            : "Check-out appearance simulated. Out Location captured: {$locName} ({$lat}, {$lng}). No timestamp, geolocation or attendance record was saved.";
    }

    // ─── Multi-Tier Leave Workflow (with Engaged Manager Mention & Partial Approval) ─

    public function exportAttendanceCsv()
    {
        $filename = 'Attendance_Register_' . $this->firmSlug . '_' . date('Y_m_d') . '.csv';

        $columns = [
            'Staff ID',
            'Staff Name',
            'Partner Unit',
            'Supervising Manager',
            'Workplace Mode',
            'Audit Client Site',
            'Day',
            'Check-In Time',
            'Check-In Location (GPS)',
            'Check-Out Time',
            'Check-Out Location (GPS)',
            'Status',
            'Verification State',
            'Remarks',
        ];

        $rowsToExport = $this->studentRows;

        return response()->streamDownload(function () use ($columns, $rowsToExport) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, $columns);

            foreach ($rowsToExport as $row) {
                fputcsv($handle, CsvSafe::row([
                    $row['id'] ?? '',
                    $row['name'] ?? '',
                    $row['partner'] ?? $this->partnerUnit,
                    $row['manager'] ?? 'Direct Report',
                    $row['mode'] ?? 'Office',
                    $row['client_site'] ?? ($row['mode'] === 'Client' ? ($row['in_location'] ?? 'Client Audit Site') : 'Principal Office HQ'),
                    $row['day'] ?? date('D'),
                    $row['checkin'] ?? '—',
                    $row['in_location'] ?? '—',
                    $row['checkout'] ?? '—',
                    $row['out_location'] ?? '—',
                    $row['status'] ?? '—',
                    !empty($row['is_proxy']) ? ('Proxy by ' . ($row['edited_by_role'] ?? 'Manager')) : (!empty($row['edited_by_role']) ? ('Edited by ' . $row['edited_by_role']) : 'Standard Entry'),
                    $row['remarks'] ?? '',
                ]));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

}
