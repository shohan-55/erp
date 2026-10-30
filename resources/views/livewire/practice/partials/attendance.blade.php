<section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
    <div>
        <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-amber-800">ATTENDANCE MANAGEMENT</p>
        <h2 class="mt-1 text-2xl font-extrabold tracking-tight">Office &amp; Client Multi-Movement Attendance</h2>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">Auto-GPS location capture on Check-in, On-Duty client-to-client movement tracking, and Close Duty sign-off.</p>
    </div>
    @if($previewRole !== 'student')
        <div class="flex flex-wrap items-center gap-2">
            @if($canViewAllPartnersAttendance)
                <button wire:click="toggleAllPartnersAttendanceView" type="button" class="inline-flex items-center justify-center gap-2 rounded-xl border px-3.5 py-2.5 text-xs font-extrabold transition {{ $viewAllPartnersAttendance ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-slate-300 bg-white text-slate-800 hover:bg-slate-50' }}">
                    <i data-lucide="users" class="h-4 w-4"></i>
                    <span>{{ $viewAllPartnersAttendance ? 'All Partners Combined (Active)' : 'All Partners Combined View' }}</span>
                </button>
            @endif
            @if($previewRole === 'partner' || $managerCanProxyAttendance)
                <button wire:click="openDialog('correction', 'S-101', 'proxy')" type="button" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-extrabold text-slate-800 hover:bg-slate-50">
                    <i data-lucide="user-check" class="h-4 w-4"></i> Give Proxy Attendance
                </button>
            @endif
            @if($previewRole === 'partner' || $managerCanEditAttendance)
                <button wire:click="openDialog('correction', 'S-102', 'edit')" type="button" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-2.5 text-xs font-extrabold hover:bg-[#F3D604]">
                    <i data-lucide="pencil-line" class="h-4 w-4"></i> Correction Review
                </button>
            @endif
        </div>
    @endif
</section>

@if($previewRole === 'partner')
    <section class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4 sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[11px] font-extrabold uppercase tracking-wider text-amber-900">PARTNER DELEGATION &amp; MONTHLY PRINT REGISTER</p>
                <h3 class="text-sm font-extrabold text-slate-900">Manager Permissions &amp; Printable Attendance Sheet</h3>
                <p class="text-xs text-slate-600">Select date range to download a print-ready PDF/HTML attendance sheet for filing, or toggle Manager edit rights.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="toggleManagerPermission('proxy')" class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-bold transition {{ $managerCanProxyAttendance ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-700' }}">
                    Manager Proxy: {{ $managerCanProxyAttendance ? 'ALLOWED' : 'DISABLED' }}
                </button>
                <button type="button" wire:click="toggleManagerPermission('edit')" class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-bold transition {{ $managerCanEditAttendance ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-700' }}">
                    Manager Edit: {{ $managerCanEditAttendance ? 'ALLOWED' : 'DISABLED' }}
                </button>
            </div>
        </div>
    </section>
@endif

@if($previewRole !== 'partner')
    <div class="grid gap-5 lg:grid-cols-[minmax(0,1.25fr)_minmax(270px,1fr)]"
         x-data="{
            locText: @entangle('currentLocationName'),
            lat: @entangle('currentLat'),
            lng: @entangle('currentLng'),
            gettingGps: false,
            captureGpsForCheckin() {
                if (!navigator.geolocation) {
                    $wire.togglePreviewSession();
                    return;
                }
                this.gettingGps = true;
                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        this.gettingGps = false;
                        const crd = pos.coords;
                        $wire.togglePreviewSessionWithGps(crd.latitude, crd.longitude, 'Live GPS (' + crd.latitude.toFixed(4) + ', ' + crd.longitude.toFixed(4) + ')');
                    },
                    () => {
                        this.gettingGps = false;
                        $wire.togglePreviewSession();
                    },
                    { enableHighAccuracy: true, timeout: 6000 }
                );
            },
            captureGpsForCheckoutPrompt() {
                if (!navigator.geolocation) {
                    $wire.initiateCheckoutPrompt();
                    return;
                }
                this.gettingGps = true;
                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        this.gettingGps = false;
                        const crd = pos.coords;
                        $wire.initiateCheckoutPrompt(crd.latitude, crd.longitude, 'Live GPS (' + crd.latitude.toFixed(4) + ', ' + crd.longitude.toFixed(4) + ')');
                    },
                    () => {
                        this.gettingGps = false;
                        $wire.initiateCheckoutPrompt();
                    },
                    { enableHighAccuracy: true, timeout: 6000 }
                );
            }
         }">
        <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm sm:p-7">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="text-xs font-extrabold uppercase tracking-wider text-amber-800">PERSONAL ATTENDANCE (LIVE GPS)</p>
                    <h3 class="mt-1 text-lg font-extrabold">Today's Check-in &amp; Duty Movement</h3>
                </div>
                <span class="rounded-full px-3 py-1 text-[11px] font-bold {{ $previewSessionActive ? 'bg-emerald-50 text-emerald-700' : ($sessionState === 'on_duty_transit' ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-600') }}">
                    {{ $previewSessionActive ? 'Active Session' : ($sessionState === 'on_duty_transit' ? 'On Duty Transit (Moving)' : 'Inactive / Closed') }}
                </span>
            </div>

            @if($sessionState === 'on_duty_transit' && !$previewSessionActive)
                <div class="mt-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-xs font-semibold text-amber-950">
                    You are currently marked <strong>On Duty (In Transit)</strong>. Select your next destination (Office or Client Site) below and click Check-in when you arrive.
                </div>
            @endif

            <p class="mt-3 text-xs leading-relaxed text-slate-500">Choose Office or Client Site mode. When leaving a location, click <strong>Out</strong> to choose between <strong>a) Close (End Duty)</strong> or <strong>b) On Duty (Move to Next Client/Office)</strong>.</p>

            <div class="mt-5 grid grid-cols-2 gap-3" aria-label="Attendance mode">
                @foreach(['office'=>'Office','client'=>'Client Site'] as $mode => $label)
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border p-4 transition {{ $workMode === $mode ? 'border-amber-400 bg-amber-50' : 'border-slate-200 bg-white' }}">
                        <input type="radio" wire:model.change="workMode" value="{{ $mode }}" name="attendance_mode" @disabled($previewSessionActive) class="accent-amber-600">
                        <span class="text-xs font-bold text-slate-800">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            @if($workMode === 'client')
                <div class="mt-4 space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold text-slate-700">Client / Company Name (ক্লায়েন্টের নাম লিখুন বা সিলেক্ট করুন) <span class="text-rose-500">*</span></label>
                    </div>
                    <input type="text"
                           wire:model="clientSite"
                           list="attendance-audit-clients-list"
                           @disabled($previewSessionActive)
                           placeholder="Type Client Name (e.g. Square Pharma, BRAC Bank Audit)..."
                           class="w-full rounded-xl border border-slate-300 bg-slate-50 p-3 text-xs font-bold text-slate-900 outline-none focus:border-amber-400 focus:bg-white">
                    <datalist id="attendance-audit-clients-list">
                        @foreach($auditClients as $c)
                            @if($c !== 'General Office / Firm HQ')
                                <option value="{{ $c }}"></option>
                            @endif
                        @endforeach
                    </datalist>
                </div>
            @endif
            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50/60 p-3 text-xs text-emerald-950 flex items-center justify-between gap-2">
                <div>
                    <span class="font-bold">Current Location Tag:</span>
                    <span x-text="locText || 'Karwan Bazar Office, Dhaka'"></span>
                </div>
                <span class="rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">GPS Auto-Detect</span>
            </div>

            @if(!$previewSessionActive)
                <button type="button" @click="captureGpsForCheckin()" class="mt-5 flex w-full items-center justify-center gap-2 rounded-xl bg-[#FCDF02] px-5 py-3.5 text-sm font-extrabold text-[#17191F] transition hover:bg-[#F3D604]">
                    <i data-lucide="log-in" class="h-5 w-5"></i>
                    <span x-show="!gettingGps">{{ $sessionState === 'on_duty_transit' ? 'Check-in at Next Location (Record In GPS)' : 'Check-in (Record In GPS)' }}</span>
                    <span x-show="gettingGps" x-cloak>Capturing GPS Coordinates...</span>
                </button>
            @else
                <button type="button" @click="captureGpsForCheckoutPrompt()" class="mt-5 flex w-full items-center justify-center gap-2 rounded-xl bg-[#17191F] px-5 py-3.5 text-sm font-extrabold text-white transition hover:bg-slate-800">
                    <i data-lucide="log-out" class="h-5 w-5 text-[#FCDF02]"></i>
                    <span x-show="!gettingGps">Out (Choose: Close Duty or On Duty Move)</span>
                    <span x-show="gettingGps" x-cloak>Capturing Out GPS Coordinates...</span>
                </button>
            @endif
                        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs leading-relaxed text-slate-600">All daily Office and Client Site check-in/out segments are saved with GPS coordinates and visible to your Manager and Partner.</div>

            <!-- Student Missed Check-out Correction Request Form (#3) -->
            <form wire:submit.prevent="submitMissedCheckoutRequest" class="mt-4 rounded-xl border border-amber-200 bg-amber-50/60 p-4 space-y-2.5 text-xs">
                <p class="font-extrabold text-amber-950">Forgot to Click Close / Check-out? (চেক-আউট দিতে ভুলে গেলে রিকোয়েস্ট পাঠান)</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <label class="block font-bold text-slate-700">Actual Out Time
                        <input type="text" wire:model="missedOutTime" placeholder="18:15" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2 text-xs font-bold">
                    </label>
                    <label class="block font-bold text-slate-700 sm:col-span-2">Reason for Missed Out
                        <input type="text" wire:model="missedOutReason" placeholder="e.g. Rushed out from client audit site at 6:15 PM" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2 text-xs">
                    </label>
                </div>
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-[#17191F] px-3.5 py-2 text-xs font-extrabold text-[#FCDF02] hover:bg-slate-800">
                    Send Missed Check-out Request to Manager
                </button>
            </form>
        </section>

        <section class="rounded-2xl border border-[#E8EAF0] bg-[#17191F] p-6 text-white shadow-sm">
            <div class="flex items-center justify-between"><h3 class="text-sm font-extrabold">Firm Attendance &amp; Late Policy</h3><i data-lucide="clock" class="h-5 w-5 text-[#FCDF02]"></i></div>
            <div class="mt-6 space-y-4">
                @foreach([
                    ['label'=>'Start time','value'=>$officeStart . ' AM'],
                    ['label'=>'End time','value'=>$officeEnd . ' PM'],
                    ['label'=>'Grace (No Penalty)','value'=>$graceMinutes . ' minutes'],
                    ['label'=>'Late Deduction Rule','value'=>$latePenaltyEnabled ? ($lateDaysPerDeduction . ' Late Days = ' . $deductionDaysCount . ' Day Cut') : 'Flag Only (No Deduction)'],
                    ['label'=>'Weekly Holiday','value'=>'Fri & Sat'],
                ] as $item)
                    <div class="flex items-center justify-between gap-2 border-b border-white/10 pb-3 text-xs"><span class="text-slate-400">{{ $item['label'] }}</span><strong>{{ $item['value'] }}</strong></div>
                @endforeach
            </div>
            <p class="mt-5 text-xs leading-relaxed text-amber-200">Arrivals after {{ $officeStart }} + {{ $graceMinutes }} min grace are marked Late. Use "On Duty" when moving between client audit sites during the day.</p>
        </section>
    </div>
@endif

<section class="min-w-0 overflow-hidden rounded-2xl border border-[#E8EAF0] bg-white shadow-sm">
    <div class="flex flex-col gap-3 border-b border-slate-100 p-5 xl:flex-row xl:items-center xl:justify-between">
        <div>
            <h3 class="text-sm font-extrabold">{{ $previewRole === 'student' ? 'My Attendance & Movement Log' : ($viewAllPartnersAttendance ? 'All Partners Combined Attendance Register' : 'Attendance Records & Live GPS Locations (' . $partnerUnit . ')') }}</h3>
            <p class="mt-1 text-xs text-slate-500">Click on any student's movement badge to inspect all Office &amp; Client site check-in/out segments for the day.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($previewRole !== 'student')
                <div class="flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs">
                    <span class="font-bold text-slate-600">Period:</span>
                    <input type="date" wire:model="attendanceReportStart" class="rounded bg-white px-1.5 py-1 text-[11px] border border-slate-200">
                    <span class="text-slate-400">to</span>
                    <input type="date" wire:model="attendanceReportEnd" class="rounded bg-white px-1.5 py-1 text-[11px] border border-slate-200">
                </div>
                <button type="button" wire:click="exportPrintableAttendanceSheet" title="Download Printable PDF/HTML Attendance Sheet" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#17191F] px-3.5 py-2.5 text-xs font-extrabold text-[#FCDF02] hover:bg-slate-800 transition shadow-sm">
                    <i data-lucide="printer" class="h-4 w-4"></i>
                    <span>Print / PDF Sheet</span>
                </button>
            @endif
            <label class="relative block"><span class="sr-only">Search attendance</span><i data-lucide="search" class="pointer-events-none absolute left-3 top-3 h-4 w-4 text-slate-400"></i><input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name or code" class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-3 text-xs outline-none focus:border-amber-400 sm:w-44"></label>
            <select wire:model.change="filter" aria-label="Filter attendance status" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs outline-none focus:border-amber-400"><option value="all">All statuses</option><option value="on-time">On time</option><option value="late">Late</option><option value="incomplete">Incomplete</option></select>
            <button type="button" wire:click="exportAttendanceCsv" title="Export ICAB Attendance Register to CSV" class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-sm">
                <i data-lucide="download" class="h-4 w-4 text-emerald-600"></i>
                <span>Export CSV</span>
            </button>
        </div>
    </div>
    <div class="hidden overflow-x-auto md:block">
        <table class="w-full min-w-[820px] text-left text-sm">
            <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-5 py-3">Student</th>
                    <th class="px-4 py-3">Current Mode &amp; Site</th>
                    <th class="px-4 py-3">First In &amp; GPS</th>
                    <th class="px-4 py-3">Last Out &amp; State</th>
                    <th class="px-4 py-3">Daily Movements</th>
                    <th class="px-4 py-3">Status</th>
                    @if($previewRole !== 'student')
                        <th class="px-4 py-3 text-right">Manager / Partner Action</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($rows as $item)
                    <tr wire:key="attendance-{{ $item['id'] }}" class="hover:bg-slate-50">
                        <td class="px-5 py-4">
                            <div class="font-bold text-slate-800">{{ $item['name'] }}</div>
                            <div class="mt-1 text-xs text-slate-400">
                                {{ $item['id'] }} · {{ $item['partner'] ?? $partnerUnit }} · {{ $item['day'] }}
                                @if(!empty($item['is_proxy']))
                                    <span class="ml-1 rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700">Proxy by {{ $item['edited_by_role'] ?? 'Manager' }}</span>
                                @elseif(!empty($item['edited_by_role']))
                                    <span class="ml-1 rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-800">Edited by {{ $item['edited_by_role'] }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-4 text-slate-600">
                            <div class="font-medium text-slate-800">{{ $item['mode'] }}</div>
                            @if(!empty($item['client_site']))
                                <div class="mt-0.5 inline-flex items-center gap-1 text-[11px] font-semibold text-amber-800">
                                    <i data-lucide="building-2" class="h-3 w-3 inline text-amber-600"></i>
                                    <span>{{ $item['client_site'] }}</span>
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-4">
                            <div class="font-semibold text-slate-800">{{ $item['checkin'] }}</div>
                            <div class="mt-0.5 text-[11px] text-slate-500">
                                {{ $item['in_location'] ?? 'Karwan Bazar, Dhaka' }}
                                @if(!empty($item['in_lat']) && !empty($item['in_lng']))
                                    <a href="https://www.google.com/maps?q={{ $item['in_lat'] }},{{ $item['in_lng'] }}" target="_blank" class="ml-1 font-bold text-amber-800 underline">Map ↗</a>
                                @endif
                                @if(!empty($item['location_mismatch']))
                                    <span class="mt-1 inline-block rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-extrabold text-rose-800">⚠️ Location Mismatch ({{ number_format($item['distance_km'] ?? 0, 1) }} km away)</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <div class="font-semibold text-slate-800">{{ $item['checkout'] }}</div>
                            <div class="mt-0.5 text-[11px] text-slate-500">
                                {{ $item['out_location'] ?? ($item['checkout'] !== '—' ? 'Karwan Bazar, Dhaka' : '—') }}
                                @if(($item['session_state'] ?? '') === 'on_duty_transit')
                                    <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-900">On Duty Move</span>
                                @elseif(($item['session_state'] ?? '') === 'closed')
                                    <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600">Duty Closed</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <button type="button" wire:click="openMovementTimeline('{{ $item['id'] }}')" class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-900 hover:bg-amber-100">
                                <i data-lucide="route" class="h-3.5 w-3.5"></i>
                                <span>{{ count($item['movement_logs'] ?? []) ?: 1 }} Leg(s) · View</span>
                            </button>
                        </td>
                        <td class="px-4 py-4">
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ in_array($item['status'], ['Right Time', 'On time'], true) ? 'bg-emerald-50 text-emerald-700' : ($item['status'] === 'Late' ? 'bg-amber-50 text-amber-800' : ($item['status'] === 'Late Present' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-600')) }}">{{ $item['status'] }}</span>
                            @if(!empty($item['correction_requested']))
                                <div class="mt-1.5 rounded-lg border border-amber-300 bg-amber-50 p-1.5 text-[10px] text-amber-950">
                                    <strong>⏳ Missed Out Request: {{ $item['requested_out_time'] }}</strong><br>
                                    <span>"{{ $item['correction_reason'] }}"</span>
                                    @if($previewRole !== 'student')
                                        <button type="button" wire:click="approveMissedCheckoutRequest('{{ $item['id'] }}')" class="mt-1 block rounded bg-emerald-600 px-2 py-0.5 text-[10px] font-extrabold text-white hover:bg-emerald-700">
                                            ✓ Approve {{ $item['requested_out_time'] }}
                                        </button>
                                    @endif
                                </div>
                            @endif
                            @if(!empty($item['remarks']))
                                <div class="mt-1 text-[10px] text-slate-400">{{ $item['remarks'] }}</div>
                            @endif
                        </td>
                        @if($previewRole !== 'student')
                            <td class="px-4 py-4 text-right space-x-1">
                                @if($previewRole === 'partner' || $managerCanEditAttendance)
                                    <button type="button" wire:click="openDialog('correction', '{{ $item['id'] }}', 'edit')" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-bold text-slate-700 hover:bg-amber-50">Edit Time/Late</button>
                                @endif
                                @if($previewRole === 'partner' || $managerCanProxyAttendance)
                                    <button type="button" wire:click="openDialog('correction', '{{ $item['id'] }}', 'proxy')" class="rounded-lg bg-[#17191F] px-2.5 py-1.5 text-xs font-bold text-[#FCDF02] hover:bg-slate-800">Proxy</button>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center text-sm text-slate-500">No attendance records found matching filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="divide-y divide-slate-100 md:hidden">
        @forelse($rows as $item)
            <div wire:key="mobile-att-{{ $item['id'] }}" class="p-4">
                <div class="flex items-center justify-between gap-3"><p class="text-sm font-bold">{{ $item['name'] }}</p><span class="rounded-full bg-amber-50 px-2 py-1 text-[10px] font-bold text-amber-800">{{ $item['status'] }}</span></div>
                <p class="mt-2 text-xs text-slate-500">
                    {{ $item['id'] }} · {{ $item['mode'] }}
                    @if(!empty($item['client_site']))
                        · <span class="font-bold text-amber-800">{{ $item['client_site'] }}</span>
                    @endif
                    · {{ $item['day'] }}
                </p>
                <div class="mt-3 grid grid-cols-2 gap-2 rounded-xl bg-slate-50 p-3 text-xs">
                    <div>
                        <span>In: <strong>{{ $item['checkin'] }}</strong></span>
                        <div class="text-[10px] text-slate-500 mt-0.5">{{ $item['in_location'] ?? 'Office' }}</div>
                    </div>
                    <div>
                        <span>Out: <strong>{{ $item['checkout'] }}</strong></span>
                        <div class="text-[10px] text-slate-500 mt-0.5">{{ $item['out_location'] ?? '—' }}</div>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" wire:click="openMovementTimeline('{{ $item['id'] }}')" class="flex-1 rounded-lg border border-amber-200 bg-amber-50 py-1.5 text-xs font-bold text-amber-900">
                        View Movements ({{ count($item['movement_logs'] ?? []) ?: 1 }})
                    </button>
                    @if($previewRole !== 'student')
                        @if($previewRole === 'partner' || $managerCanEditAttendance)
                            <button type="button" wire:click="openDialog('correction', '{{ $item['id'] }}', 'edit')" class="flex-1 rounded-lg border border-slate-200 py-1.5 text-xs font-bold">Edit Late/Time</button>
                        @endif
                        @if($previewRole === 'partner' || $managerCanProxyAttendance)
                            <button type="button" wire:click="openDialog('correction', '{{ $item['id'] }}', 'proxy')" class="flex-1 rounded-lg bg-[#17191F] py-1.5 text-xs font-bold text-[#FCDF02]">Give Proxy</button>
                        @endif
                    @endif
                </div>
            </div>
        @empty
            <p class="p-10 text-center text-xs text-slate-500">No attendance records found for today.</p>
        @endforelse
    </div>
</section>
