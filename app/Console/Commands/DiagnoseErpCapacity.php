<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DiagnoseErpCapacity extends Command
{
    protected $signature='erp:scale:diagnose
        {--company= : Scope customer metrics to one company ID (recommended)}
        {--json : Return machine-readable, non-PII metrics}';

    protected $description='Read-only ERP workload, queue and bounded-export readiness diagnostics; never assert 200GB capability.';

    public function handle(): int
    {
        $companyId=$this->option('company');
        if($companyId!==null && (!ctype_digit((string)$companyId) || (int)$companyId<1)){
            $this->error('A positive numeric --company ID is required.');
            return self::FAILURE;
        }
        if($companyId!==null &&
            !DB::table('companies')->where('id',(int)$companyId)->exists()){
            $this->error('Company not found.');
            return self::FAILURE;
        }
        $scope=static function(string $table)use($companyId) {
            $q=DB::table($table);
            return $companyId!==null?$q->where('company_id',(int)$companyId):$q;
        };
        $queued=Schema::hasTable('jobs')?(int)DB::table('jobs')->count():null;
        $failed=Schema::hasTable('failed_jobs')?(int)DB::table('failed_jobs')->count():null;
        $quota=Schema::hasTable('erp_vault_quotas')
            ?$scope('erp_vault_quotas')->selectRaw(
                'COUNT(*) as workspaces, COALESCE(SUM(used_bytes),0) as used_bytes, '.
                'COALESCE(SUM(reserved_bytes),0) as reserved_bytes, '.
                'COALESCE(SUM(limit_bytes),0) as quota_bytes'
            )->first():null;
        $pending=Schema::hasTable('erp_vault_uploads')?(int)$scope('erp_vault_uploads')
            ->where('status','pending')->count():null;
        $approvedExit=Schema::hasTable('tenant_exit_cases')?(int)$scope('tenant_exit_cases')
            ->where('state','approved')->count():null;
        $renewals=Schema::hasTable('saas_subscriptions')?(int)$scope('saas_subscriptions')
            ->where('auto_invoice',true)->count():null;
        $privateDisk=config('filesystems.disks.erp_vault.driver')==='local'
            && config('erp_vault.disk')==='erp_vault';
        $report=[
            'scope'=>['company_id'=>$companyId===null?null:(int)$companyId],
            'queue'=>[
                'driver'=>config('queue.default'),
                'pending_jobs'=>$queued,
                'failed_jobs'=>$failed,
                'worker_heartbeat_verified'=>false,
            ],
            'vault'=>[
                'workspace_quotas'=>(int)($quota?->workspaces??0),
                'used_bytes'=>(int)($quota?->used_bytes??0),
                'reserved_bytes'=>(int)($quota?->reserved_bytes??0),
                'configured_quota_bytes'=>(int)($quota?->quota_bytes??0),
                'pending_uploads'=>$pending,
                'private_local_disk_mode'=>$privateDisk,
                'covers_all_legacy_customer_files'=>false,
                'physical_free_space_verified'=>false,
            ],
            'workload'=>[
                'opt_in_auto_invoice_subscriptions'=>$renewals,
                'approved_company_exit_packages'=>$approvedExit,
            ],
            'large_tenant_export'=>[
                'pilot_plaintext_limit_bytes'=>(int)config('erp_exit.max_plaintext_bytes'),
                'verified_200_gb_end_to_end_export'=>false,
                'independent_restore_drill_verified'=>false,
                'durable_offsite_object_storage_verified'=>false,
                'full_database_snapshot_consistency_verified'=>false,
            ],
            'safe_for_customer_deletion'=>false,
            'note'=>'Read-only indicative counters. Real 200GB performance, consistency, offsite DR and physical capacity require staging evidence.',
        ];
        if($this->option('json')) {
            $this->line(json_encode($report,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        }else{
            $this->table(['Signal','Reading'],[
                ['Queue driver',$report['queue']['driver']],
                ['Pending jobs',$queued??'table unavailable'],
                ['Failed jobs',$failed??'table unavailable'],
                ['Private vault accounted bytes',$report['vault']['used_bytes']],
                ['Private vault reserved bytes',$report['vault']['reserved_bytes']],
                ['Pending uploads',$pending??'table unavailable'],
                ['Approved exit jobs',$approvedExit??'table unavailable'],
                ['16MiB pilot export max',$report['large_tenant_export']['pilot_plaintext_limit_bytes']],
                ['200GB independently verified','NO'],
                ['Safe for hard delete','NO'],
            ]);
            $this->warn($report['note']);
        }
        return self::SUCCESS;
    }
}
