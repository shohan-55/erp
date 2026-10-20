<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Bounded, encrypted and company-scoped offboarding pilot.
 * Export != backup certification and NEVER authorizes a destructive purge.
 */
final class TenantExitPackage
{
    private const FILE_SOURCES = [
        'erp_vault_objects' => ['object_key', 'disk', 'erp-vault/'],
        'practice_documents' => ['storage_path', 'local', 'practice-documents/'],
        'staff_documents' => ['storage_path', 'local', 'staff-documents/'],
        'conveyance_claims' => ['receipt_path', 'local', 'claim-evidence/'],
        'payroll_runs' => ['payment_evidence_path', 'local', 'private-payroll-evidence/'],
        'workspace_brand_assets' => ['storage_path', 'local', 'workspace-branding/'],
    ];

    public static function tables(): array
    {
        $tables=[];
        foreach (Schema::getTableListing() as $table) {
            if (!preg_match('/^[a-z][a-z0-9_]*$/', $table)) continue;
            if (preg_match('/^(tenant_exit_|platform_control_|password_|oauth_|identity_|personal_access_|sessions$|cache|jobs$|failed_jobs$)/', $table)) continue;
            if (Schema::hasColumn($table,'company_id') && Schema::hasColumn($table,'id')) {
                $tables[]=$table;
            }
        }
        sort($tables);
        return $tables;
    }

    public static function inventory(int $companyId): array
    {
        $counts=[];
        foreach(self::tables() as $table) {
            $counts[$table]=DB::table($table)->where('company_id',$companyId)->count();
        }
        $files=[];
        foreach(self::FILE_SOURCES as $table=>$source) {
            if(!Schema::hasTable($table)) continue;
            $rows=DB::table($table)->where('company_id',$companyId)
                ->whereNotNull($source[0])->count();
            $files[$table]=$rows;
        }
        return ['tables'=>$counts,'file_references'=>$files];
    }

    private static function disk(): string
    {
        $name=(string)config('erp_exit.disk','erp_exit');
        if($name!=='erp_exit' || config('filesystems.disks.erp_exit.driver')!=='local'
            || config('filesystems.disks.erp_exit.serve', true)!==false
            || !config('filesystems.disks.erp_exit.throw',false)) {
            throw new RuntimeException('A dedicated non-public, fail-closed export disk is required.');
        }
        return $name;
    }

    private static function addArtifact(object $case,string $kind,string $source,?int $sourceId,
        int $part,string $plaintext,bool $binary=false): void
    {
        $key='company-exit/'.$case->company_id.'/'.$case->id.'/'.Str::uuid().'.enc';
        $encoded=Crypt::encryptString($binary ? base64_encode($plaintext) : $plaintext);
        if(!Storage::disk(self::disk())->put($key,$encoded)){
            throw new RuntimeException('Private encrypted export write failed.');
        }
        try {
            DB::table('tenant_exit_artifacts')->insert([
                'case_id'=>$case->id,'company_id'=>$case->company_id,
                'kind'=>$kind,'source_name'=>$source,'source_id'=>$sourceId,
                'part_number'=>$part,'storage_key'=>$key,
                'byte_size'=>strlen($plaintext),'sha256'=>hash('sha256',$plaintext),
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        } catch(\Throwable $e){
            Storage::disk(self::disk())->delete($key);
            throw $e;
        }
    }

    private static function maxBytes(): int
    {
        return (int)config('erp_exit.max_plaintext_bytes', 16777216);
    }

    /** Redact server credentials even when attached to tenant-scoped operational tables. */
    private static function sanitized(object $record): array
    {
        return array_filter((array)$record,static function($key){
            return !preg_match('/password|secret|token|credential|api_key|private_key|recovery_code|mfa_secret/i',(string)$key);
        },ARRAY_FILTER_USE_KEY);
    }

    private static function writeTables(object $case,int &$total,array &$counts): void
    {
        $limit=(int)config('erp_exit.segment_bytes',262144);
        foreach(self::tables() as $table){
            $buffer='';$part=1;$count=0;
            $query=DB::table($table)->where('company_id',$case->company_id)->orderBy('id');
            foreach($query->cursor() as $r) {
                $line=json_encode(self::sanitized($r),JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE)."\n";
                if(strlen($line)>$limit){
                    throw new RuntimeException('Single database row exceeds approved pilot segment size: '.$table);
                }
                self::reserve($total,strlen($line));
                if($buffer!=='' && strlen($buffer)+strlen($line)>$limit) {
                    self::addArtifact($case,'table',$table,null,$part++,$buffer);
                    $buffer='';
                }
                $buffer.=$line;$count++;
            }
            if($buffer!=='') self::addArtifact($case,'table',$table,null,$part,$buffer);
            $counts[$table]=$count;
        }

        // Users are global identities. Export only safe fields of users that
        // explicitly belong to this company; never passwords/MFA/session keys.
        $buffer='';$part=1;$count=0;
        $query=DB::table('memberships as m')
            ->join('users as u','u.id','=','m.user_id')
            ->where('m.company_id',$case->company_id)
            ->select('u.id','u.name','u.email','u.created_at')
            ->distinct()->orderBy('u.id');
        foreach($query->cursor() as $row){
            $line=json_encode((array)$row,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE)."\n";
            self::reserve($total,strlen($line));
            if($buffer!=='' && strlen($buffer)+strlen($line)>$limit){
                self::addArtifact($case,'table','users_safe',null,$part++,$buffer);$buffer='';
            }
            $buffer.=$line;$count++;
        }
        if($buffer!=='')self::addArtifact($case,'table','users_safe',null,$part,$buffer);
        $counts['users_safe']=$count;

        // Payroll lines have no company_id; resolve through an owned run and
        // prevent any foreign-company row from entering this export.
        $buffer='';$part=1;$count=0;
        $query=DB::table('payroll_lines as l')
            ->join('payroll_runs as r','r.id','=','l.payroll_run_id')
            ->where('r.company_id',$case->company_id)->select('l.*')->orderBy('l.id');
        foreach($query->cursor() as $row){
            $line=json_encode(self::sanitized($row),JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE)."\n";
            self::reserve($total,strlen($line));
            if($buffer!=='' && strlen($buffer)+strlen($line)>$limit){
                self::addArtifact($case,'table','payroll_lines',null,$part++,$buffer);$buffer='';
            }
            $buffer.=$line;$count++;
        }
        if($buffer!=='') self::addArtifact($case,'table','payroll_lines',null,$part,$buffer);
        $counts['payroll_lines']=$count;
    }

    private static function reserve(int &$total,int $incoming): void
    {
        if($incoming<0 || $total>self::maxBytes()-$incoming){
            throw new RuntimeException('Tenant export exceeds the bounded pilot capacity. Configure verified external object-storage export before proceeding.');
        }
        $total+=$incoming;
    }

    private static function writeFiles(object $case,int &$total,array &$fileCounts): void
    {
        $segment=(int)config('erp_exit.segment_bytes',262144);
        foreach(self::FILE_SOURCES as $table=>$source) {
            if(!Schema::hasTable($table))continue;
            [$column,$diskConfig,$prefix]=$source;
            $n=0;
            foreach(DB::table($table)->where('company_id',$case->company_id)
                ->whereNotNull($column)->orderBy('id')->cursor() as $row) {
                $key=(string)$row->{$column};
                if($key==='') continue;
                $disk=$diskConfig==='disk'?(string)$row->disk:$diskConfig;
                // Database metadata cannot grant a private path from another
                // company. All recognized storage layouts start with company_id.
                $ownedPrefix=$prefix.$case->company_id.'/';
                if(!in_array($disk,['local','erp_vault'],true)
                    || !str_starts_with($key,$ownedPrefix)
                    || str_contains($key,'..')
                    || str_starts_with($key,'/')) {
                    throw new RuntimeException('Unsafe or unsupported private file reference in '.$table.' #'.$row->id);
                }
                if(!Storage::disk($disk)->exists($key)){
                    throw new RuntimeException('Required private file is missing: '.$table.' #'.$row->id);
                }
                $stream=Storage::disk($disk)->readStream($key);
                if(!is_resource($stream))throw new RuntimeException('Cannot read customer file '.$table.' #'.$row->id);
                $digest=hash_init('sha256');$bytes=0;$part=1;
                try{
                    while(!feof($stream)){
                        $data=fread($stream,$segment);
                        if($data===false)throw new RuntimeException('Private file read error.');
                        if($data==='')continue;
                        self::reserve($total,strlen($data));
                        hash_update($digest,$data);
                        self::addArtifact($case,'file',$table,(int)$row->id,$part++,$data,true);
                        $bytes+=strlen($data);
                    }
                }finally{fclose($stream);}
                if(isset($row->byte_size) && (int)$row->byte_size!==$bytes){
                    throw new RuntimeException('Source document size mismatch for '.$table.' #'.$row->id);
                }
                if(isset($row->sha256) && !hash_equals(strtolower((string)$row->sha256),hash_final($digest))){
                    throw new RuntimeException('Source document SHA-256 mismatch for '.$table.' #'.$row->id);
                }
                $n++;
            }
            $fileCounts[$table]=$n;
        }
    }

    public static function prepare(int $caseId): void
    {
        $case=DB::transaction(function()use($caseId){
            $case=DB::table('tenant_exit_cases')->where('id',$caseId)->lockForUpdate()->firstOrFail();
            if($case->state!=='approved')return null;
            $company=DB::table('companies')->where('id',$case->company_id)->lockForUpdate()->firstOrFail();
            if($company->deleted_at || $company->archived_at){
                throw new RuntimeException('Restore company access before preparing a customer-authorized export.');
            }
            $all=DB::table('tenant_exit_votes')->where('case_id',$caseId)->get();
            if($all->isEmpty() || $all->contains(fn($v)=>$v->decision!=='approve')){
                throw new RuntimeException('Every original Partner must explicitly approve.');
            }
            $voters=$all->pluck('membership_id')->map(fn($id)=>(int)$id)->sort()->values()->all();
            $currentPartners=DB::table('memberships')->where('company_id',$case->company_id)
                ->where('role','partner')->where('active',true)
                ->whereNull('platform_suspended_at')->pluck('id')
                ->map(fn($id)=>(int)$id)->sort()->values()->all();
            if($voters!==$currentPartners) {
                DB::table('tenant_exit_cases')->where('id',$caseId)->update([
                    'state'=>'blocked',
                    'block_reason'=>'Partner roster changed; open a new case with fresh unanimous consent.',
                    'updated_at'=>now(),
                ]);
                return null;
            }
            DB::table('tenant_exit_cases')->where('id',$caseId)->update([
                'state'=>'preparing','updated_at'=>now(),
            ]);
            return $case;
        },3);
        if(!$case)return;
        try{
            $initial=self::inventory((int)$case->company_id);
            $total=0;$counts=[];$fileCounts=[];
            self::writeTables($case,$total,$counts);
            self::writeFiles($case,$total,$fileCounts);
            // Detect insert/delete drift during the archive process. This is
            // not an independent consistent DB snapshot or restore check.
            $after=self::inventory((int)$case->company_id);
            if($initial!==$after){
                throw new RuntimeException('Tenant source inventory changed while export ran; safe retry requires a new case.');
            }
            foreach($initial['tables'] as $name=>$count) {
                if(($counts[$name]??null)!==$count){
                    throw new RuntimeException('Source table count mismatch for '.$name);
                }
            }
            $entries=DB::table('tenant_exit_artifacts')->where('case_id',$caseId)
                ->orderBy('id')->get(['id','kind','source_name','source_id','part_number','byte_size','sha256'])
                ->map(fn($r)=>(array)$r)->all();
            $manifest=json_encode([
                'format'=>'dhakawala-tenant-exit-pilot-v1',
                'company_id'=>(int)$case->company_id,
                'case_id'=>(int)$caseId,
                'created_at'=>now()->toIso8601String(),
                'inventory'=>$initial,
                'table_export_counts'=>$counts,
                'file_export_counts'=>$fileCounts,
                'artifacts'=>$entries,
                'exclusions'=>[
                    'global authentication credentials, password/MFA secrets, token tables',
                    'platform control events, infrastructure backups, provider object versions',
                ],
                'consistency'=>'Row/file validation and count reconciliation only; independent restore required',
            ],JSON_THROW_ON_ERROR);
            self::reserve($total,strlen($manifest));
            self::addArtifact($case,'manifest','manifest',null,1,$manifest);
            DB::table('tenant_exit_cases')->where('id',$caseId)
                ->where('state','preparing')->update([
                    'state'=>'prepared','prepared_at'=>now(),
                    'manifest_sha256'=>hash('sha256',$manifest),
                    'block_reason'=>null,'updated_at'=>now(),
                ]);
        }catch(\Throwable $e){
            // Discard this case's own partial export data only, never originals.
            $artifacts=DB::table('tenant_exit_artifacts')->where('case_id',$caseId)->get();
            foreach($artifacts as $part){
                try{Storage::disk(self::disk())->delete($part->storage_key);}catch(\Throwable){}
            }
            DB::table('tenant_exit_artifacts')->where('case_id',$caseId)->delete();
            DB::table('tenant_exit_cases')->where('id',$caseId)
                ->where('state','preparing')->update([
                    'state'=>'blocked','block_reason'=>substr($e->getMessage(),0,300),
                    'updated_at'=>now(),
                ]);
            throw $e;
        }
    }

    public static function content(object $artifact): string
    {
        $key=(string)$artifact->storage_key;
        $expected='company-exit/'.$artifact->company_id.'/'.$artifact->case_id.'/';
        if(!str_starts_with($key,$expected) || str_contains($key,'..')){
            throw new RuntimeException('Export path scope mismatch.');
        }
        $encrypted=Storage::disk(self::disk())->get($key);
        $body=Crypt::decryptString($encrypted);
        $plain=$artifact->kind==='file'?base64_decode($body,true):$body;
        if($plain===false || !hash_equals($artifact->sha256,hash('sha256',$plain))){
            throw new RuntimeException('Export artifact checksum mismatch.');
        }
        return $plain;
    }

    public static function verify(int $caseId): bool
    {
        $case=DB::table('tenant_exit_cases')->where('id',$caseId)->first();
        if(!$case || !in_array($case->state,['prepared','integrity_verified','acknowledged','restore_attested'],true))return false;
        $artifacts=DB::table('tenant_exit_artifacts')->where('case_id',$caseId)
            ->where('company_id',$case->company_id)->orderBy('id')->get();
        if(!$artifacts->contains('kind','manifest')) return false;
        foreach($artifacts as $part){
            try {
                $plain=self::content($part);
                if($part->kind==='manifest' && !hash_equals($case->manifest_sha256,hash('sha256',$plain)))return false;
            }catch(\Throwable){return false;}
        }
        return true;
    }
}
