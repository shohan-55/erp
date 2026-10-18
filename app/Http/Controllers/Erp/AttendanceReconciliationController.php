<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\AttendanceReconciliation;
use App\Support\TenantOps;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttendanceReconciliationController extends Controller
{
    public function index(Request $request, string $firmSlug)
    {
        [$company,$workspace,$actor] = TenantOps::context($request);
        TenantOps::requireRole($actor,['partner','manager','student']);

        $input = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $today = CarbonImmutable::now($company->timezone)->startOfDay();
        $date = $input['date'] ?? $today->toDateString();
        $day = CarbonImmutable::createFromFormat('!Y-m-d',$date,$company->timezone);

        if (! $day || $day->toDateString() !== $date || $day->greaterThan($today)
            || $day->lessThan($today->subDays(60))) {
            throw ValidationException::withMessages([
                'date' => 'Choose a valid attendance date within the last 60 days, up to today.',
            ]);
        }

        $result = AttendanceReconciliation::forDate($company,$workspace,$actor,$date);
        return response()->view('erp.attendance-reconciliation',[
            'company'=>$company, 'workspace'=>$workspace, 'actor'=>$actor,
            'firmSlug'=>$firmSlug, 'date'=>$date,
            'rows'=>$result['rows'], 'summary'=>$result['summary'],
        ])->header('Cache-Control','private, no-store, max-age=0');
    }
}
