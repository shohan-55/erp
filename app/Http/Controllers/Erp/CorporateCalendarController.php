<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\DashboardMonthCalendar;
use App\Support\StudentFeatureAccess;
use App\Support\TenantOps;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class CorporateCalendarController extends Controller
{
    private function month(Request $request, object $company): CarbonImmutable
    {
        $input=$request->validate(['month'=>['nullable','date_format:Y-m']]);
        $today=CarbonImmutable::now($company->timezone)->startOfMonth();
        if (empty($input['month'])) return $today;
        $selected=CarbonImmutable::createFromFormat('!Y-m', $input['month'], $company->timezone);
        if (! $selected || $selected->format('Y-m') !== $input['month']) {
            throw ValidationException::withMessages(['month'=>'Choose a valid calendar month.']);
        }
        // Bounded query range prevents unbounded historical/report scraping.
        if ($selected->lessThan($today->subMonthsNoOverflow(24))
            || $selected->greaterThan($today->addMonthsNoOverflow(24))) {
            throw ValidationException::withMessages([
                'month'=>'Choose a month within two years of the current month.',
            ]);
        }
        return $selected;
    }

    private function snapshot(Request $request): array
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        $selected=$this->month($request,$company);
        $personal=$actor->role !== 'student'
            || (StudentFeatureAccess::enabledMap($actor)['attendance'] ?? false);
        $calendar=DashboardMonthCalendar::forActor($company,$workspace,$actor,$personal,$selected);
        return [$company,$workspace,$actor,$calendar];
    }

    public function index(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor,$monthCalendar]=$this->snapshot($request);
        return response()->view('erp.calendar',compact(
            'company','workspace','actor','firmSlug','monthCalendar'
        ))->header('Cache-Control','private, no-store, max-age=0');
    }

    public function export(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor,$calendar]=$this->snapshot($request);
        $filename='workspace-calendar-'.$calendar['month_key'].'.csv';
        $safe=function ($value): string {
            $text=(string)$value;
            // Keep spreadsheet exports safe from formula injection, including
            // leading whitespace, Unicode control characters, and separators.
            if (preg_match('/^[\s\x00-\x1f]*[=+\-@]/u', $text)) return "'".$text;
            return $text;
        };

        return response()->streamDownload(function () use ($calendar,$company,$workspace,$actor,$safe) {
            $stream=fopen('php://output','w');
            fputcsv($stream,['Company','Partner Workspace','Month','Role']);
            fputcsv($stream,[
                $safe($company->brand_name ?: $company->name),$safe($workspace->name),
                $calendar['month'],$actor->role,
            ]);
            fputcsv($stream,[]);
            fputcsv($stream,['Date','Day','Status','Details','Team Check-ins']);
            foreach ($calendar['days'] as $day) {
                $weekday=CarbonImmutable::parse($day['date'],$company->timezone)->format('l');
                fputcsv($stream,[
                    $day['date'],$weekday,$safe($day['label']),$safe($day['details']),
                    $actor->role === 'student' ? '' : $day['team_count'],
                ]);
            }
            fclose($stream);
        },$filename,[
            'Content-Type'=>'text/csv; charset=utf-8',
            'Cache-Control'=>'private, no-store, max-age=0',
            'X-Content-Type-Options'=>'nosniff',
        ]);
    }
}
