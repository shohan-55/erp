<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
use App\Support\WorkspaceAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnnualHolidayController extends Controller
{
    public function index(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner','manager']);

        $currentYear = (int) CarbonImmutable::now($company->timezone)->year;
        $data = $request->validate(['year' => ['nullable','integer','between:2000,2100']]);
        $year = (int) ($data['year'] ?? $currentYear);

        $plans = DB::table('annual_holiday_plans as p')
            ->join('memberships as m','m.id','=','p.submitted_by')
            ->join('users as u','u.id','=','m.user_id')
            ->where('p.company_id',$company->id)->where('p.workspace_id',$workspace->id)
            ->where('m.company_id',$company->id)->where('m.workspace_id',$workspace->id)
            ->where('p.calendar_year',$year)
            ->select('p.*','u.name as submitter_name')
            ->orderByDesc('p.id')->limit(20)->get();

        $latest = $plans->first(fn ($p) => $p->status === 'approved');
        $approvedDays = $latest ? json_decode($latest->holiday_dates,true) : [];
        return response()->view('erp.annual-holidays',compact(
            'company','workspace','actor','firmSlug','year','currentYear',
            'plans','approvedDays'
        ))->header('Cache-Control','private, no-store, max-age=0');
    }

    public function submit(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner','manager']);

        $input = $request->validate([
            'year' => ['required','integer','between:2000,2100'],
            'holidays_csv' => ['required','string','max:60000'],
            'source_note' => ['required','string','min:12','max:1200'],
        ]);
        $today = CarbonImmutable::now($company->timezone)->toDateString();
        $year = (int) $input['year'];
        $items = $this->parseRows($input['holidays_csv'], $company, $year, $today);

        $result = DB::transaction(function () use ($company,$workspace,$actor,$year,$items,$input) {
            DB::table('workspaces')->where('company_id',$company->id)
                ->where('id',$workspace->id)->lockForUpdate()->first();

            if (DB::table('annual_holiday_plans')
                ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
                ->where('calendar_year',$year)->where('status','pending')->exists()) {
                throw ValidationException::withMessages([
                    'year'=>'A pending annual holiday plan already needs Partner review.',
                ]);
            }
            $this->checkFrozenMonths($company, $workspace, array_keys($items));

            $approved = $actor->role === 'partner';
            $id = DB::table('annual_holiday_plans')->insertGetId([
                'company_id'=>$company->id, 'workspace_id'=>$workspace->id,
                'calendar_year'=>$year, 'submitted_by'=>$actor->id,
                'reviewed_by'=>$approved ? $actor->id : null,
                'status'=>$approved ? 'approved' : 'pending',
                'holiday_dates'=>json_encode($items, JSON_THROW_ON_ERROR),
                'source_note'=>trim($input['source_note']),
                'reviewed_at'=>$approved ? now() : null,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'calendar.annual_'.($approved ? 'approved' : 'submitted'),
                'annual_holiday_plan',$id,[
                    'year'=>$year,'dates'=>array_keys($items),'source_note'=>$input['source_note'],
                ]);
            if (! $approved) {
                $partnerIds = DB::table('memberships')
                    ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
                    ->where('role','partner')->where('active',true)->pluck('id');
                foreach ($partnerIds as $partnerId) {
                    TenantOps::notify($actor,(int)$partnerId,
                        'Annual holiday plan pending approval',
                        $year.' calendar with '.count($items).' dated holidays awaits approval.');
                }
            }
            return $approved;
        }, 3);

        return redirect()->route('erp.annual_holidays.index',['firmSlug'=>$firmSlug,'year'=>$year])
            ->with('success',$result
                ? 'Annual holiday plan approved and saved. Only approved future dates affect the calendar.'
                : 'Annual holiday plan submitted for Partner approval. No schedule changed yet.');
    }

    public function decide(Request $request, string $firmSlug, int $planId)
    {
        [$company,$workspace,$actor] = TenantOps::context($request);
        abort_unless(WorkspaceAccess::partnerAction($actor,'holidays.manage'),403);
        $data = $request->validate(['decision'=>['required','in:approve,reject']]);

        DB::transaction(function () use ($company,$workspace,$actor,$planId,$data) {
            DB::table('workspaces')->where('company_id',$company->id)
                ->where('id',$workspace->id)->lockForUpdate()->first();
            $plan = DB::table('annual_holiday_plans')->where('id',$planId)
                ->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)->lockForUpdate()->first();
            abort_unless($plan,404);
            if ((int)$plan->submitted_by===(int)$actor->id) {
                throw ValidationException::withMessages(['decision'=>'Submitted and final-reviewed holiday plans require different people.']);
            }
            if ($plan->status !== 'pending') {
                throw ValidationException::withMessages(['decision'=>'This annual plan has already been reviewed.']);
            }
            if ($data['decision'] === 'approve') {
                $dates = json_decode($plan->holiday_dates,true,512,JSON_THROW_ON_ERROR);
                $today = CarbonImmutable::now($company->timezone)->toDateString();
                foreach (array_keys($dates) as $date) {
                    if ($date <= $today) {
                        throw ValidationException::withMessages([
                            'decision'=>'Plan has a date that is no longer in the future. Re-submit updated dates.',
                        ]);
                    }
                }
                $this->checkFrozenMonths($company,$workspace,array_keys($dates));
            }
            $status = $data['decision'] === 'approve' ? 'approved' : 'rejected';
            DB::table('annual_holiday_plans')->where('id',$plan->id)->update([
                'status'=>$status,'reviewed_by'=>$actor->id,
                'reviewed_at'=>now(),'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'calendar.annual_'.$status,'annual_holiday_plan',$plan->id,[
                'year'=>$plan->calendar_year,
            ]);
            if ((int)$plan->submitted_by !== (int)$actor->id) {
                TenantOps::notify($actor,(int)$plan->submitted_by,'Annual holiday plan '.$status,
                    'Your '.$plan->calendar_year.' calendar was '.$status.'.');
            }
        }, 3);

        return back()->with('success','Annual calendar review saved. No historical attendance or payroll was changed.');
    }

    public function branding(Request $request, string $firmSlug)
    {
        [$company,$workspace,$actor] = TenantOps::context($request);
        abort_unless(WorkspaceAccess::partnerAction($actor,'holidays.manage'),403);
        $input = $request->validate([
            'mode'=>['required','in:off,name,logo'],
            'opacity'=>['required','integer','between:3,12'],
        ]);
        if ($input['mode'] === 'logo' && ! $company->brand_logo_asset) {
            throw ValidationException::withMessages(['mode'=>'Upload a company brand logo before selecting logo watermark.']);
        }

        DB::transaction(function () use ($company,$workspace,$actor,$input) {
            $existing = DB::table('workspaces')
                ->where('company_id',$company->id)->where('id',$workspace->id)
                ->lockForUpdate()->first();
            abort_unless($existing,404);
            DB::table('workspaces')->where('id',$workspace->id)->update([
                'calendar_watermark_mode'=>$input['mode'],
                'calendar_watermark_opacity'=>(int)$input['opacity'],
                'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'calendar.watermark_updated','workspace',$workspace->id,[
                'old_mode'=>$existing->calendar_watermark_mode,
                'new_mode'=>$input['mode'],'opacity'=>(int)$input['opacity'],
            ]);
        },3);
        return back()->with('success','Calendar watermark preference saved for this Partner workspace.');
    }

    private function parseRows(string $csv, object $company, int $year, string $today): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csv));
        if (count($lines) > 366) {
            throw ValidationException::withMessages(['holidays_csv'=>'A year may contain at most 366 holiday dates.']);
        }
        $dates = [];
        foreach ($lines as $position => $line) {
            if (trim($line) === '') continue;
            $fields = str_getcsv($line);
            if (count($fields) !== 3) {
                throw ValidationException::withMessages(['holidays_csv'=>'Line '.($position+1).' must contain date,title,category.']);
            }
            [$date,$title,$category] = array_map('trim',$fields);
            if ($position === 0 && strtolower($date) === 'date') continue;
            $valid = CarbonImmutable::createFromFormat('!Y-m-d',$date,$company->timezone);
            if (! $valid || $valid->toDateString() !== $date
                || (int)substr($date,0,4) !== $year || $date <= $today) {
                throw ValidationException::withMessages(['holidays_csv'=>'Line '.($position+1).' has a past or invalid date/year.']);
            }
            if (mb_strlen($title) < 2 || mb_strlen($title) > 140
                || ! in_array($category,['government','optional','partner_decision'],true)
                || isset($dates[$date])) {
                throw ValidationException::withMessages([
                    'holidays_csv'=>'Line '.($position+1).' has an invalid title, category or duplicate date.',
                ]);
            }
            $dates[$date] = ['title'=>$title,'category'=>$category];
        }
        if (! count($dates)) {
            throw ValidationException::withMessages(['holidays_csv'=>'Include at least one future holiday date.']);
        }
        ksort($dates);
        return $dates;
    }

    private function checkFrozenMonths(object $company, object $workspace, array $dates): void
    {
        $months = array_unique(array_map(fn ($d)=>substr($d,0,7).'-01',$dates));
        if (DB::table('payroll_runs')
            ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
            ->whereIn('period_month',$months)
            ->whereIn('status',['reviewed','approved','paid','locked'])->exists()) {
            throw ValidationException::withMessages([
                'holidays_csv'=>'One or more affected months are frozen by payroll review.',
            ]);
        }
    }
}
