<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ReportErpSecurityEvents extends Command
{
    protected $signature='erp:security:report {--company= : Company numeric ID; omit for platform-wide summary} {--days=7 : 1–30 day lookback} {--json : Print counts without any personal identifier}';
    protected $description='Read-only aggregate security activity for authorized server operators; no tokens, IPs or account details.';

    public function handle():int
    {
        $days=filter_var($this->option('days'),FILTER_VALIDATE_INT,[
            'options'=>['min_range'=>1,'max_range'=>30],
        ]);
        $company=$this->option('company');
        if($days===false||($company!==null&&(!ctype_digit((string)$company)||(int)$company<1))){
            $this->error('Provide --days between 1 and 30 and a positive --company numeric ID.');
            return self::FAILURE;
        }
        if($company!==null&&!DB::table('companies')->where('id',(int)$company)->exists()){
            $this->error('Company not found.');
            return self::FAILURE;
        }
        $query=DB::table('erp_security_events')
            ->where('created_at','>=',now()->subDays($days))
            ->when($company!==null,fn($q)=>$q->where('company_id',(int)$company))
            ->selectRaw('kind, severity, COUNT(*) as event_count')
            ->groupBy('kind','severity')
            ->orderBy('kind');
        $rows=$query->get()->map(fn($v)=>[
            'kind'=>$v->kind,'severity'=>$v->severity,
            'count'=>(int)$v->event_count,
        ])->all();
        if($this->option('json')) {
            $this->line(json_encode([
                'company_id'=>$company===null?null:(int)$company,
                'lookback_days'=>$days,'counts'=>$rows,
                'note'=>'Metadata only; not tamper-proof SIEM or attack-blocking',
            ],JSON_THROW_ON_ERROR));
        }else{
            $this->table(['Event type','Severity','Count'],
                array_map(fn($r)=>array_values($r),$rows));
        }
        return self::SUCCESS;
    }
}
