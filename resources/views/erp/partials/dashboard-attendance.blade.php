{{-- Attendance is always submitted to the existing server-authoritative, tenant-scoped endpoints. --}}
@php($onDuty = $activeAttendance !== null)
<section class="attendance-spotlight" data-attendance-experience aria-label="Quick attendance">
    <div class="attendance-spotlight__orb attendance-spotlight__orb--one" aria-hidden="true"></div>
    <div class="attendance-spotlight__orb attendance-spotlight__orb--two" aria-hidden="true"></div>
    <div class="attendance-spotlight__content">
        <div class="attendance-spotlight__intro">
            <div class="attendance-spotlight__eyebrow"><span class="attendance-spotlight__sparkle" aria-hidden="true">✦</span> QUICK ATTENDANCE <span class="attendance-spotlight__eyebrow-line" aria-hidden="true"></span> YOUR WORKDAY</div>
            <h3 class="attendance-spotlight__heading">{{ $onDuty ? 'A productive day is underway.' : 'Ready to make today count?' }}</h3>
            <p class="attendance-spotlight__copy">{{ $onDuty ? 'Your session is live. Finish your day with one secure check-out.' : 'One tap to begin. Your attendance is recorded using the server clock.' }}</p>
            <div class="attendance-spotlight__details">
                <span><i data-lucide="clock-3" aria-hidden="true"></i> {{ substr($effectivePolicy['office_start'], 0, 5) }} – {{ substr($effectivePolicy['office_end'], 0, 5) }}</span>
                <span><i data-lucide="timer" aria-hidden="true"></i> {{ $effectivePolicy['grace_minutes'] }} min grace</span>
                <span class="attendance-spotlight__status {{ $onDuty ? 'attendance-spotlight__status--active' : '' }}"><span class="attendance-spotlight__status-dot" aria-hidden="true"></span>{{ $onDuty ? 'Session active' : 'Not checked in' }}</span>
            </div>
            @if($onDuty)
                <p class="attendance-spotlight__started">Checked in at {{ \Carbon\Carbon::parse($activeAttendance->started_at, 'UTC')->timezone($company->timezone)->format('h:i A') }} · {{ ucfirst($activeAttendance->work_mode) }}{{ $activeAttendance->client_site ? ' · '.$activeAttendance->client_site : '' }}</p>
            @endif
        </div>

        <div class="attendance-spotlight__control">
            <form method="POST" action="{{ route($onDuty ? 'erp.attendance.check_out' : 'erp.attendance.check_in', ['firmSlug' => $firmSlug]) }}" data-attendance-confirm-form data-attendance-kind="{{ $onDuty ? 'out' : 'in' }}" @if($workspace->attendance_location_enabled) data-erp-attendance-form @endif>
                @csrf
                @unless($onDuty)
                    <div class="attendance-spotlight__fields">
                        <label>Work location
                            <select name="work_mode" data-attendance-mode>
                                <option value="office">Office</option>
                                <option value="client">Client Site</option>
                            </select>
                        </label>
                        <label>Client name <span class="attendance-spotlight__optional">(for client visits)</span>
                            <input type="text" name="client_site" maxlength="180" placeholder="Client / engagement location" data-attendance-client>
                        </label>
                    </div>
                @endunless
                @if($workspace->attendance_location_enabled)
                    <label class="attendance-spotlight__consent">
                        <input type="checkbox" data-erp-location-consent>
                        <span>I agree to share my approximate browser GPS for this attendance event. GPS is optional and may require manual verification.</span>
                    </label>
                    <p data-erp-location-status role="status" aria-live="polite" class="attendance-spotlight__location-status"></p>
                @endif
                <button type="submit" class="attendance-spotlight__action {{ $onDuty ? 'attendance-spotlight__action--out' : '' }}" data-attendance-primary>
                    <span class="attendance-spotlight__action-icon"><i data-lucide="{{ $onDuty ? 'log-out' : 'fingerprint' }}" aria-hidden="true"></i></span>
                    <span>{{ $onDuty ? 'Check Out Now' : 'Check In Now' }}</span>
                    <i data-lucide="arrow-up-right" aria-hidden="true"></i>
                </button>
            </form>
            <a class="attendance-spotlight__history" href="{{ route('erp.attendance', ['firmSlug' => $firmSlug]) }}">View attendance history <span aria-hidden="true">↗</span></a>
        </div>
    </div>
    <p class="attendance-spotlight__footnote"><i data-lucide="shield-check" aria-hidden="true"></i> Securely recorded · {{ $company->timezone }} · No automatic payroll late penalty</p>
</section>

<div class="attendance-dialog" data-attendance-dialog hidden>
    <div class="attendance-dialog__backdrop" data-attendance-cancel></div>
    <section class="attendance-dialog__panel" role="dialog" aria-modal="true" aria-labelledby="attendance-dialog-title" aria-describedby="attendance-dialog-description" tabindex="-1">
        <div class="attendance-dialog__icon"><i data-lucide="fingerprint" aria-hidden="true"></i></div>
        <p class="attendance-dialog__eyebrow">ONE MORE STEP</p>
        <h3 id="attendance-dialog-title" data-attendance-dialog-title>Confirm attendance</h3>
        <p id="attendance-dialog-description" data-attendance-dialog-description>Please confirm before your attendance is recorded.</p>
        <div class="attendance-dialog__buttons">
            <button type="button" class="attendance-dialog__cancel" data-attendance-cancel>Cancel</button>
            <button type="button" class="attendance-dialog__confirm" data-attendance-confirm>Yes, confirm <i data-lucide="arrow-right" aria-hidden="true"></i></button>
        </div>
    </section>
</div>

@if(session('attendance_result'))
    @php($attendanceResult = session('attendance_result'))
    @php($attendanceLate = ($attendanceResult['kind'] ?? '') === 'in' && ($attendanceResult['late'] ?? false))
    <div class="attendance-celebration {{ $attendanceLate ? 'attendance-celebration--late' : '' }}" data-attendance-result>
        <div class="attendance-celebration__backdrop" aria-hidden="true"></div>
        <section class="attendance-celebration__panel" role="alertdialog" aria-modal="true" aria-labelledby="attendance-result-title" aria-describedby="attendance-result-description" tabindex="-1">
            <div class="attendance-celebration__confetti" aria-hidden="true"><span>✦</span><span>✧</span><span>✦</span><span>✧</span><span>✦</span></div>
            <div class="attendance-celebration__halo"><i data-lucide="{{ $attendanceLate ? 'clock-alert' : (($attendanceResult['kind'] ?? '') === 'out' ? 'flag-triangle-right' : 'check') }}" aria-hidden="true"></i></div>
            <p class="attendance-celebration__eyebrow">{{ $attendanceLate ? 'LATE ARRIVAL RECORDED' : 'ATTENDANCE CONFIRMED' }}</p>
            <h3 id="attendance-result-title">{{ $attendanceLate ? 'You are checked in — a little late.' : (($attendanceResult['kind'] ?? '') === 'out' ? 'Beautiful work today!' : 'You are officially checked in!') }}</h3>
            <p id="attendance-result-description">{{ $attendanceLate ? 'Your arrival was recorded after the approved grace period. Your attendance is saved; no automatic pay deduction is applied.' : (($attendanceResult['kind'] ?? '') === 'out' ? 'Your check-out has been safely recorded. See you next time!' : (($attendanceResult['holiday'] ?? false) ? 'Attendance saved for your scheduled day off.' : 'Great start! Your work session has been saved securely.')) }}</p>
            <p class="attendance-celebration__time"><i data-lucide="clock-3" aria-hidden="true"></i> Recorded {{ $attendanceResult['time'] ?? '' }} · {{ $company->timezone }}</p>
            <button type="button" class="attendance-celebration__done" data-attendance-done>Continue to dashboard <i data-lucide="arrow-right" aria-hidden="true"></i></button>
        </section>
    </div>
@endif