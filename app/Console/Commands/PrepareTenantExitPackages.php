<?php

namespace App\Console\Commands;

use App\Support\TenantExitPackage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PrepareTenantExitPackages extends Command
{
    protected $signature='erp:exit:prepare {--case= : Process one approved case ID} {--dry-run : Show approved queue only}';
    protected $description='Prepare a bounded encrypted company exit package after unanimous Partner approval; never purge tenant data.';

    public function handle(): int
    {
        $query=DB::table('tenant_exit_cases')->where('state','approved')->orderBy('id');
        if($this->option('case')){
            $query->where('id',(int)$this->option('case'));
        }
        $ids=$query->limit(3)->pluck('id');
        if($this->option('dry-run')){
            $this->info('Approved exit cases queued: '.implode(', ',$ids->all()));
            return self::SUCCESS;
        }
        $errors=0;
        foreach($ids as $id) {
            try{
                TenantExitPackage::prepare((int)$id);
                $this->info('Encrypted export staged for case #'.$id.'; manual integrity and independent restore still required.');
            }catch(Throwable $e){
                $errors++;
                $this->error('Case #'.$id.' blocked: '.$e->getMessage());
            }
        }
        return $errors?self::FAILURE:self::SUCCESS;
    }
}
