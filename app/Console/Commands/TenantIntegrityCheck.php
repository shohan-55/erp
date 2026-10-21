<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Cross-module relational privacy audit. Aggregate counters only; never
 * emit tenant/member/client names, GPS, salary, documents or DB errors.
 * Zero anomaly count means these invariants hold, NOT a security certification.
 */
final class TenantIntegrityCheck extends Command
{
    protected $signature='erp:tenant:check {--json : Print non-identifying JSON counts}';
    protected $description='Read-only detection of cross-company and cross-Partner record links';

    public function handle():int
    {
        $checks=[
            'memberships_linked_to_foreign_workspaces'=>0,
            'attendance_linked_to_foreign_memberships'=>0,
            'leave_linked_to_foreign_memberships'=>0,
            'claims_linked_to_foreign_memberships'=>0,
            'staff_profiles_linked_to_foreign_memberships'=>0,
            'payroll_lines_linked_to_foreign_memberships'=>0,
            'payroll_adjustments_linked_to_foreign_memberships'=>0,
            'engagements_linked_to_foreign_clients'=>0,
            'engagement_assignments_linked_to_foreign_staff'=>0,
            'timesheets_linked_to_foreign_staff_or_engagements'=>0,
            'documents_linked_to_foreign_subject_memberships'=>0,
        ];
        $ready=true;
        try {
            foreach ([
                'memberships','workspaces','attendance_sessions','leave_requests',
                'conveyance_claims','staff_profiles','payroll_lines','payroll_runs',
                'payroll_line_adjustments','audit_clients','audit_engagements',
                'audit_engagement_assignments','audit_timesheets','practice_documents',
            ] as $table) {
                if(!Schema::hasTable($table)){$ready=false;break;}
            }
            if($ready){
                $checks['memberships_linked_to_foreign_workspaces']=
                    DB::table('memberships as m')->join('workspaces as w','w.id','=','m.workspace_id')
                        ->whereColumn('m.company_id','!=','w.company_id')->count();
                foreach([
                    'attendance_sessions'=>'attendance_linked_to_foreign_memberships',
                    'leave_requests'=>'leave_linked_to_foreign_memberships',
                    'conveyance_claims'=>'claims_linked_to_foreign_memberships',
                    'staff_profiles'=>'staff_profiles_linked_to_foreign_memberships',
                ] as $table=>$key){
                    $checks[$key]=DB::table($table.' as item')
                        ->join('memberships as m','m.id','=','item.membership_id')
                        ->where(function($q){
                            $q->whereColumn('item.company_id','!=','m.company_id')
                                ->orWhereColumn('item.workspace_id','!=','m.workspace_id');
                        })->count();
                }
                $checks['payroll_lines_linked_to_foreign_memberships']=
                    DB::table('payroll_lines as l')
                        ->join('payroll_runs as r','r.id','=','l.payroll_run_id')
                        ->join('memberships as m','m.id','=','l.membership_id')
                        ->where(function($q){
                            $q->whereColumn('r.company_id','!=','m.company_id')
                                ->orWhereColumn('r.workspace_id','!=','m.workspace_id');
                        })->count();
                $checks['payroll_adjustments_linked_to_foreign_memberships']=
                    DB::table('payroll_line_adjustments as a')
                        ->join('payroll_runs as r','r.id','=','a.payroll_run_id')
                        ->join('memberships as m','m.id','=','a.membership_id')
                        ->join('memberships as actor','actor.id','=','a.authorized_by')
                        ->where(function($q){
                            $q->whereColumn('a.company_id','!=','m.company_id')
                                ->orWhereColumn('a.workspace_id','!=','m.workspace_id')
                                ->orWhereColumn('a.company_id','!=','r.company_id')
                                ->orWhereColumn('a.workspace_id','!=','r.workspace_id')
                                ->orWhereColumn('a.company_id','!=','actor.company_id')
                                ->orWhereColumn('a.workspace_id','!=','actor.workspace_id');
                        })->count();
                $checks['engagements_linked_to_foreign_clients']=
                    DB::table('audit_engagements as e')
                        ->join('audit_clients as c','c.id','=','e.audit_client_id')
                        ->where(function($q){
                            $q->whereColumn('e.company_id','!=','c.company_id')
                                ->orWhereColumn('e.workspace_id','!=','c.workspace_id');
                        })->count();
                $checks['engagement_assignments_linked_to_foreign_staff']=
                    DB::table('audit_engagement_assignments as a')
                        ->join('audit_engagements as e','e.id','=','a.engagement_id')
                        ->join('memberships as m','m.id','=','a.membership_id')
                        ->where(function($q){
                            $q->whereColumn('a.company_id','!=','e.company_id')
                                ->orWhereColumn('a.workspace_id','!=','e.workspace_id')
                                ->orWhereColumn('a.company_id','!=','m.company_id')
                                ->orWhereColumn('a.workspace_id','!=','m.workspace_id');
                        })->count();
                $checks['timesheets_linked_to_foreign_staff_or_engagements']=
                    DB::table('audit_timesheets as t')
                        ->join('audit_engagements as e','e.id','=','t.engagement_id')
                        ->join('memberships as m','m.id','=','t.membership_id')
                        ->where(function($q){
                            $q->whereColumn('t.company_id','!=','e.company_id')
                                ->orWhereColumn('t.workspace_id','!=','e.workspace_id')
                                ->orWhereColumn('t.company_id','!=','m.company_id')
                                ->orWhereColumn('t.workspace_id','!=','m.workspace_id');
                        })->count();
                $checks['documents_linked_to_foreign_subject_memberships']=
                    DB::table('practice_documents as d')
                        ->join('memberships as m','m.id','=','d.subject_membership_id')
                        ->where(function($q){
                            $q->whereColumn('d.company_id','!=','m.company_id')
                                ->orWhereColumn('d.workspace_id','!=','m.workspace_id');
                        })->count();
            }
        }catch(Throwable $exception){$ready=false;}
        $decision=!$ready?'SCHEMA_OR_QUERY_NOT_READY':
            (array_sum($checks)>0?'REVIEW_REQUIRED':'NO_ANOMALIES_DETECTED');
        $result=[
            'decision'=>$decision,'schema_ready'=>$ready,'anomalies'=>$checks,
            'note'=>'Aggregate read-only relationship check. It does not replace tenant permission tests, backup restoration or independent review.',
        ];
        if($this->option('json'))$this->line(json_encode($result,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT));
        else {
            $this->line('DhakaWala ERP — Cross-Partner Data Integrity Precheck');
            foreach($checks as $key=>$count)$this->line($key.': '.$count);
            $this->line('Decision: '.$decision);
            $this->warn($result['note']);
        }
        return $decision==='NO_ANOMALIES_DETECTED'?self::SUCCESS:self::FAILURE;
    }
}
