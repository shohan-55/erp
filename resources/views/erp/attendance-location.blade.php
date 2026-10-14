@extends('layouts.erp')
@section('title','Attendance Location')
@section('content')
<section class="max-w-3xl space-y-4">
  <a href="{{ route('erp.attendance', ['firmSlug'=>$firmSlug]) }}" class="text-sm font-bold text-amber-800 underline">← Back to Attendance</a>
  <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
    <h2 class="text-xl font-extrabold">Browser-Reported Attendance Location</h2>
    <p class="mt-2 text-sm leading-relaxed text-slate-600">Only the authorized Partner or assigned Manager can see these readings. GPS coordinates come from the employee's browser and are not independently verified proof of presence. The app does not use a third-party map service.</p>
    <p class="mt-3 text-xs text-slate-500">Attendance session #{{ $session->id }} · {{ $session->work_date }}</p>
    <div class="mt-5 space-y-3">
    @foreach($readings as $reading)
      <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
        <p class="font-extrabold">{{ $reading['event'] === 'check_in' ? 'Check In' : 'Check Out' }}</p>
        <p class="mt-2 font-mono text-slate-700">Latitude: {{ $reading['latitude'] }}</p>
        <p class="font-mono text-slate-700">Longitude: {{ $reading['longitude'] }}</p>
        <p class="mt-1 text-xs text-slate-500">Browser accuracy: {{ number_format((float) $reading['accuracy_meters'], 1) }} metres · Server recorded: {{ \Carbon\Carbon::parse($reading['captured_at'],'UTC')->timezone($company->timezone)->format('d M Y h:i A') }}</p>
      </div>
    @endforeach
    </div>
    <p class="mt-5 text-xs text-slate-500">These precise readings are encrypted at rest and scheduled for deletion according to your workspace's retention setting. Backups may retain copies longer under the approved backup policy.</p>
  </div>
</section>
@endsection
