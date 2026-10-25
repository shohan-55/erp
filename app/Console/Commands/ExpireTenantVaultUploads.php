<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class ExpireTenantVaultUploads extends Command
{
    protected $signature = 'erp:vault:expire-staging
        {--dry-run : Display eligible sessions without releasing reservations}
        {--max=200 : Maximum expired sessions to reclaim during one bounded execution}';
    protected $description = 'Reclaim only expired temporary upload chunks, never customer object files or audit metadata.';

    public function handle(): int
    {
        $expired=0;
        $max=filter_var($this->option('max'),FILTER_VALIDATE_INT,[
            'options'=>['min_range'=>1,'max_range'=>1000]
        ]);
        if($max===false){
            $this->error('--max must be between 1 and 1000.');
            return self::FAILURE;
        }
        // Maximum per-run processing is bounded; the next scheduled run
        // resumes remaining expired IDs without rewinding completed objects.
        $sessions=DB::table('erp_vault_uploads')->where('status','pending')
            ->where('expires_at','<=',now())->orderBy('id')
            ->limit($max)->get(['id','company_id','workspace_id']);
        foreach($sessions as $session){
                    if($this->option('dry-run')){$expired++;continue;}
                    $paths=DB::transaction(function()use($session,&$expired){
                        DB::table('workspaces')->where('company_id',$session->company_id)
                            ->where('id',$session->workspace_id)->lockForUpdate()->firstOrFail();
                        $upload=DB::table('erp_vault_uploads')->where('id',$session->id)
                            ->where('company_id',$session->company_id)
                            ->where('workspace_id',$session->workspace_id)
                            ->lockForUpdate()->first();
                        if(!$upload || $upload->status!=='pending' || \Carbon\Carbon::parse($upload->expires_at)->isFuture())return [];
                        $quota=DB::table('erp_vault_quotas')->where('company_id',$session->company_id)
                            ->where('workspace_id',$session->workspace_id)->lockForUpdate()->firstOrFail();
                        // A ledger discrepancy is a stop condition, never underflow quota.
                        if((int)$quota->reserved_bytes<(int)$upload->expected_bytes){
                            throw new \RuntimeException('Vault quota reservation inconsistent for upload #'.$session->id);
                        }
                        DB::table('erp_vault_quotas')->where('id',$quota->id)->update([
                            'reserved_bytes'=>$quota->reserved_bytes-$upload->expected_bytes,
                            'updated_at'=>now(),
                        ]);
                        DB::table('erp_vault_uploads')->where('id',$session->id)
                            ->update(['status'=>'expired','updated_at'=>now()]);
                        $expired++;
                        return DB::table('erp_vault_parts')->where('upload_id',$session->id)
                            ->pluck('private_path')->all();
                    },3);
                    if($paths)Storage::disk('local')->delete($paths);
        }
        $this->info($this->option('dry-run')
            ?'Expired pending sessions eligible for cleanup: '.$expired
            :'Expired upload reservations reclaimed: '.$expired);
        return self::SUCCESS;
    }
}
