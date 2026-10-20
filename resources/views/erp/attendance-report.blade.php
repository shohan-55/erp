@extends('layouts.erp')
@section('title','Attendance Report')
@section('content')
<section>
    <h2 class="text-2xl font-extrabold">Monthly Attendance Report</h2>
    <p class="mt-2 max-w-3xl text-sm text-slate-500">Read-only observed attendance for your authorized team. Multiple check-ins on one date count as one attended day. No salary penalties are calculated.</p>
</section>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <form method="GET" action="{{ route('erp.attendance.report', ['firmSlug' => $firmSlug]) }}" class="flex flex-wrap items-end gap-3">
        <label class="text-xs font-bold text-slate-700">Reporting month
            <input type="month" name="month" value="{{ $month }}" required class="mt-2 block rounded-xl border border-slate-300 p-3 text-sm">
        </label>
        <button class="rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-extrabold">View Report</button>
        <a class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold" href="{{ route('erp.attendance', ['firmSlug' => $firmSlug]) }}">Session History</a>
    </form>
</section>
<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 p-5"><h3 class="text-sm font-extrabold">Observed Sessions · {{ $month }}</h3></div>
    <div class="overflow-x-auto"><table class="w-full min-w-[740px] text-left text-sm">
        <thead class="bg-slate-50 text-xs text-slate-600"><tr>
            <th class="p-4">Student</th><th class="p-4">Days with Check-in</th>
            <th class="p-4">Sessions</th><th class="p-4">Late Sessions</th>
            <th class="p-4">Incomplete Sessions</th><th class="p-4">Holiday Check-in Days</th>
        </tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($rows as $row)
                <tr><td class="p-4 font-semibold">{{ $row->member_name }}</td>
                    <td class="p-4">{{ $row->attended_days }}</td><td class="p-4">{{ $row->session_count }}</td>
                    <td class="p-4">{{ $row->late_sessions }}</td><td class="p-4">{{ $row->incomplete_sessions }}</td>
                    <td class="p-4">{{ $row->holiday_days }}</td></tr>
            @empty
                <tr><td colspan="6" class="p-8 text-center text-slate-500">No recorded sessions for this month and your authorized scope.</td></tr>
            @endforelse
        </tbody>
    </table></div>
    <div class="border-t border-slate-100 p-5">
        <p class="mb-3 text-xs text-slate-500">Page {{ $rows->currentPage() }} of {{ $rows->lastPage() }} · {{ number_format($rows->total()) }} matching staff · 100 staff per page.</p>
        {{ $rows->links() }}
    </div>
    <p class="border-t border-slate-100 p-5 text-xs leading-relaxed text-slate-500">These are historical session counts, not an absence or payable-day determination. Approved leave, missing check-ins, and exceptional workdays require review. GPS coordinates and payroll amounts are excluded.</p>
</section>
@endsection
