<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDay;
use App\Models\AttendanceSession;
use App\Models\Company;
use App\Models\CompanyMembership;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationalAttendanceController extends Controller
{
    private function context(Request $request, string $companySlug): array
    {
        $company = Company::query()->where('slug', $companySlug)->where('is_active', true)->firstOrFail();
        $membership = CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->whereNotNull('workspace_id')
            ->firstOrFail();

        return [$company, $membership];
    }

    public function dashboard(Request $request, string $companySlug)
    {
        [$company, $membership] = $this->context($request, $companySlug);

        $visibleMemberIds = match ($membership->role) {
            'partner' => CompanyMembership::query()->where('company_id', $company->id)->where('workspace_id', $membership->workspace_id)->pluck('id'),
            'manager' => CompanyMembership::query()->where('company_id', $company->id)->where('workspace_id', $membership->workspace_id)
                ->where(fn ($q) => $q->where('id', $membership->id)->orWhere('manager_membership_id', $membership->id))->pluck('id'),
            default => collect([$membership->id]),
        };

        $days = AttendanceDay::query()->where('company_id', $company->id)
            ->where('workspace_id', $membership->workspace_id)->whereIn('membership_id', $visibleMemberIds)
            ->orderByDesc('work_date')->limit(30)->get();

        $openSession = $membership->role === 'student'
            ? AttendanceSession::query()->where('company_id', $company->id)->where('membership_id', $membership->id)
                ->where('is_open', true)->first()
            : null;

        return view('operational.dashboard', compact('company', 'membership', 'days', 'openSession'));
    }

    public function checkIn(Request $request, string $companySlug)
    {
        [$company, $membership] = $this->context($request, $companySlug);
        abort_unless($membership->role === 'student', 403);

        $input = $request->validate([
            'mode' => ['required', 'in:office,client'],
            'client_site' => ['nullable', 'string', 'max:200', 'required_if:mode,client'],
        ]);

        DB::transaction(function () use ($company, $membership, $input) {
            // Lock the membership row; serialize simultaneous requests across days/modes.
            CompanyMembership::query()->whereKey($membership->id)->lockForUpdate()->firstOrFail();

            if (AttendanceSession::query()->where('company_id', $company->id)->where('membership_id', $membership->id)->where('is_open', true)->exists()) {
                throw ValidationException::withMessages(['mode' => 'An attendance session is already open. Check out first.']);
            }

            $now = CarbonImmutable::now('UTC');
            $local = $now->setTimezone($company->timezone);
            $day = AttendanceDay::query()->firstOrCreate(
                ['company_id' => $company->id, 'membership_id' => $membership->id, 'work_date' => $local->toDateString()],
                ['workspace_id' => $membership->workspace_id, 'status' => 'present']
            );

            AttendanceSession::query()->create([
                'attendance_day_id' => $day->id, 'company_id' => $company->id,
                'membership_id' => $membership->id, 'mode' => $input['mode'],
                'client_site' => $input['mode'] === 'client' ? trim($input['client_site']) : null,
                'checked_in_at' => $now, 'is_open' => true,
            ]);
        }, 3);

        return redirect()->route('operational.dashboard', ['companySlug' => $companySlug])->with('status', 'Check-in recorded.');
    }

    public function checkOut(Request $request, string $companySlug)
    {
        [$company, $membership] = $this->context($request, $companySlug);
        abort_unless($membership->role === 'student', 403);

        DB::transaction(function () use ($company, $membership) {
            CompanyMembership::query()->whereKey($membership->id)->lockForUpdate()->firstOrFail();

            $session = AttendanceSession::query()->where('company_id', $company->id)
                ->where('membership_id', $membership->id)->where('is_open', true)->lockForUpdate()->first();

            if (! $session) {
                throw ValidationException::withMessages(['mode' => 'There is no active session to check out.']);
            }

            $session->update(['checked_out_at' => CarbonImmutable::now('UTC'), 'is_open' => false]);
        }, 3);

        return redirect()->route('operational.dashboard', ['companySlug' => $companySlug])->with('status', 'Check-out recorded.');
    }
}
