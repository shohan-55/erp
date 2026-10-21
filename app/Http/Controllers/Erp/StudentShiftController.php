<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentShiftController extends Controller
{
    public function store(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner', 'manager']);

        $data = $request->validate([
            'scope' => ['required', 'in:student,team'],
            'student_membership_id' => ['nullable', 'required_if:scope,student', 'integer', 'min:1'],
            'manager_membership_id' => ['nullable', 'required_if:scope,team', 'integer', 'min:1'],
            'work_date' => ['required', 'date_format:Y-m-d'],
            'day_type' => ['required', 'in:working,holiday'],
            'office_start' => ['nullable', 'required_with:office_end', 'date_format:H:i'],
            'office_end' => ['nullable', 'required_with:office_start', 'date_format:H:i', 'after:office_start'],
            'grace_minutes' => ['nullable', 'integer', 'between:0,60'],
            'reason' => ['required', 'string', 'min:15', 'max:1000'],
        ]);
        if ($data['day_type'] === 'holiday'
            && (filled($data['office_start'] ?? null) || filled($data['office_end'] ?? null)
                || isset($data['grace_minutes']))) {
            throw ValidationException::withMessages(['office_start' => 'A holiday cannot contain office hours.']);
        }
        if (isset($data['grace_minutes']) && (! filled($data['office_start'] ?? null)
            || ! filled($data['office_end'] ?? null))) {
            throw ValidationException::withMessages(['grace_minutes' => 'Specify both working hours for a special grace period.']);
        }
        $this->assertEditable($company, $workspace, $data['work_date']);

        $count = DB::transaction(function () use ($company, $workspace, $actor, $data) {
            DB::table('workspaces')->where('company_id', $company->id)
                ->where('id', $workspace->id)->lockForUpdate()->first();
            $this->assertEditable($company, $workspace, $data['work_date']);

            if ($data['scope'] === 'student') {
                $target = TenantOps::subject($actor, (int) $data['student_membership_id']);
                abort_unless($target->role === 'student', 404);
                $students = collect([$target]);
            } else {
                $managerId = (int) $data['manager_membership_id'];
                if ($actor->role === 'manager' && $managerId !== (int) $actor->id) {
                    abort(403, 'Managers can only propose schedules for their own team.');
                }
                $manager = DB::table('memberships')
                    ->where('id', $managerId)
                    ->where('company_id', $company->id)
                    ->where('workspace_id', $workspace->id)
                    ->where('role', 'manager')->where('active', true)->first();
                abort_unless($manager, 404);

                $students = DB::table('memberships')
                    ->where('company_id', $company->id)
                    ->where('workspace_id', $workspace->id)
                    ->where('manager_membership_id', $managerId)
                    ->where('role', 'student')->where('active', true)
                    ->orderBy('id')->get();
                if ($students->isEmpty()) {
                    throw ValidationException::withMessages(['scope' => 'This manager has no assigned active students.']);
                }
                if ($students->count() > 200) {
                    throw ValidationException::withMessages(['scope' => 'Schedule up to 200 students in a single batch.']);
                }
            }

            $ids = $students->pluck('id')->all();
            $pending = DB::table('student_calendar_shifts')
                ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                ->where('work_date', $data['work_date'])
                ->whereIn('student_membership_id', $ids)
                ->where('status', 'pending')->exists();
            if ($pending) {
                throw ValidationException::withMessages(['work_date' => 'An existing shift request requires Partner review first.']);
            }

            $approved = $actor->role === 'partner';
            $batch = (string) Str::uuid();
            $time = now();
            foreach ($ids as $studentId) {
                $id = DB::table('student_calendar_shifts')->insertGetId([
                    'company_id' => $company->id, 'workspace_id' => $workspace->id,
                    'student_membership_id' => $studentId, 'batch_ref' => $batch,
                    'work_date' => $data['work_date'], 'day_type' => $data['day_type'],
                    'office_start' => filled($data['office_start'] ?? null) ? $data['office_start'].':00' : null,
                    'office_end' => filled($data['office_end'] ?? null) ? $data['office_end'].':00' : null,
                    'grace_minutes' => $data['grace_minutes'] ?? null,
                    'reason' => trim($data['reason']),
                    'status' => $approved ? 'approved' : 'pending',
                    'proposed_by' => $actor->id,
                    'approved_by' => $approved ? $actor->id : null,
                    'decided_at' => $approved ? $time : null,
                    'created_at' => $time, 'updated_at' => $time,
                ]);
                TenantOps::audit($actor, $approved ? 'shift.authorized' : 'shift.proposed',
                    'student_calendar_shift', $id, [
                        'student_membership_id' => $studentId, 'work_date' => $data['work_date'],
                        'batch_ref' => $batch,
                    ]);
            }

            if (! $approved) {
                $partners = DB::table('memberships')
                    ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                    ->where('role', 'partner')->where('active', true)->pluck('id');
                foreach ($partners as $partnerId) {
                    TenantOps::notify($actor, (int) $partnerId, 'Student shift approval requested',
                        count($ids).' student schedule change(s) for '.$data['work_date'].' need Partner review.');
                }
            }
            return count($ids);
        }, 3);

        return back()->with('success', $count.' student schedule(s) '
            .($actor->role === 'partner' ? 'authorized.' : 'submitted for Partner review.'));
    }

    public function decide(Request $request, string $firmSlug, int $shiftId)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner']);
        $data = $request->validate(['decision' => ['required', 'in:approve,reject']]);

        DB::transaction(function () use ($company, $workspace, $actor, $shiftId, $data) {
            DB::table('workspaces')->where('company_id', $company->id)
                ->where('id', $workspace->id)->lockForUpdate()->first();

            $shift = DB::table('student_calendar_shifts')
                ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                ->where('id', $shiftId)->first();
            abort_unless($shift, 404);
            if ($shift->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'Shift request was already decided.']);
            }
            $this->assertEditable($company, $workspace, $shift->work_date);

            // Approve/reject all entries that were submitted as one Manager
            // team request atomically; never approve a partial hidden team.
            $pending = DB::table('student_calendar_shifts')
                ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                ->where('batch_ref', $shift->batch_ref)->where('status', 'pending')
                ->get();
            $status = $data['decision'] === 'approve' ? 'approved' : 'rejected';
            foreach ($pending as $row) {
                DB::table('student_calendar_shifts')->where('id', $row->id)->update([
                    'status' => $status,
                    'approved_by' => $status === 'approved' ? $actor->id : null,
                    'decided_at' => now(), 'updated_at' => now(),
                ]);
                TenantOps::audit($actor, 'shift.'.$status,
                    'student_calendar_shift', $row->id,
                    ['batch_ref' => $row->batch_ref, 'student_membership_id' => $row->student_membership_id]);
            }
            if ($shift->proposed_by !== $actor->id) {
                TenantOps::notify($actor, (int) $shift->proposed_by,
                    'Student shift request '.$status,
                    count($pending).' schedule change(s) were '.$status.'.');
            }
        }, 3);

        return back()->with('success', 'Student shift decision saved for the entire request batch.');
    }

    private function assertEditable(object $company, object $workspace, string $date): void
    {
        $d = CarbonImmutable::createFromFormat('!Y-m-d', $date, $company->timezone);
        if (! $d || $d->toDateString() !== $date
            || $date <= CarbonImmutable::now($company->timezone)->toDateString()) {
            throw ValidationException::withMessages(['work_date' => 'Only future dates may be changed.']);
        }
        if (DB::table('payroll_runs')->where('company_id', $company->id)
            ->where('workspace_id', $workspace->id)
            ->where('period_month', substr($date, 0, 7).'-01')
            ->whereIn('status', ['reviewed','approved','paid','locked'])->exists()) {
            throw ValidationException::withMessages(['work_date' => 'Payroll is already frozen for this month.']);
        }
    }
}
