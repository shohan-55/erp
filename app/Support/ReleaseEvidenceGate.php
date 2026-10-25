<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Validates only a NONSECRET evidence index: no dumps, keys, receipts or PII.
 * A complete index never constitutes live deployment approval or proof that
 * off-site data has genuinely been restored. Human witnesses remain required.
 */
final class ReleaseEvidenceGate
{
    public const REQUIRED=[
        'encrypted_offsite_backup',
        'isolated_database_restore',
        'private_files_restore',
        'app_key_recovery',
        'tenant_isolation_negative_test',
        'smtp_delivery_test',
        'queue_scheduler_observation',
        'financial_policy_signoff',
        'partner_user_acceptance',
        'rollback_rehearsal',
        'independent_security_review',
    ];

    private static function digest(mixed $v):bool
    {
        return is_string($v) && preg_match('/\A[a-f0-9]{64}\z/D',$v)===1;
    }

    private static function observed(mixed $value,CarbonImmutable $min,CarbonImmutable $now):bool
    {
        if(!is_string($value) || !preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/D',$value)) return false;
        try {
            $date=CarbonImmutable::parse($value,'UTC');
            return $date >= $min && $date <= $now && $date->format('Y-m-d\TH:i:s\Z') === $value;
        }catch(Throwable){return false;}
    }

    public static function assess(?string $file,string $sha,?string $artifactDir=null):array
    {
        $checks=[
            'reviewed_sha_format'=>preg_match('/\A[a-f0-9]{40}\z/D',$sha)===1,
            'private_evidence_location'=>false,
            'bounded_json_manifest'=>false,
            'staging_only'=>false,
            'manifest_matches_reviewed_sha'=>false,
            'distinct_operator_and_reviewer'=>false,
            'fresh_utc_snapshot_and_restore'=>false,
            'encrypted_backup_checksum_transferred'=>false,
            'release_artifacts_present_and_hashed'=>false,
            'release_manifest_source_sha_matches'=>false,
            'release_manifest_cannot_authorize_deploy'=>false,
        ];
        foreach(self::REQUIRED as $gate)$checks['witness_'.$gate]=false;

        $root=realpath(storage_path('app/private/release-evidence'));
        $path=is_string($file)?realpath($file):false;
        $public=realpath(public_path());
        $checks['private_evidence_location']=$root!==false && $path!==false
            && str_starts_with($path,$root.DIRECTORY_SEPARATOR)
            && ($public===false || !str_starts_with($path,$public.DIRECTORY_SEPARATOR))
            && !is_link((string)$file);
        $doc=null;
        if($checks['private_evidence_location'] && is_file($path)
            && filesize($path)>0 && filesize($path)<=65536){
            $input=file_get_contents($path);
            try{$doc=json_decode($input,true,32,JSON_THROW_ON_ERROR);}catch(Throwable){$doc=null;}
            $checks['bounded_json_manifest']=is_array($doc)&&array_is_list($doc)===false
                && ($doc['schema_version']??null)===1;
        }

        $now=CarbonImmutable::now('UTC');
        if($checks['bounded_json_manifest']){
            $checks['staging_only']=($doc['target_environment']??null)==='isolated-staging';
            $checks['manifest_matches_reviewed_sha']=$checks['reviewed_sha_format']
                && hash_equals($sha,(string)($doc['reviewed_sha']??''));
            $operator=$doc['operator_alias']??null;
            $reviewer=$doc['independent_reviewer_alias']??null;
            $checks['distinct_operator_and_reviewer']=is_string($operator)
                && is_string($reviewer)
                && preg_match('/\A[a-z0-9_-]{4,48}\z/D',$operator)===1
                && preg_match('/\A[a-z0-9_-]{4,48}\z/D',$reviewer)===1
                && !hash_equals($operator,$reviewer);
            $snapshot=$doc['snapshot_utc']??null;$restored=$doc['restored_utc']??null;
            $earliest=$now->subDays(30);
            $checks['fresh_utc_snapshot_and_restore']=self::observed($snapshot,$earliest,$now)
                && self::observed($restored,CarbonImmutable::parse($snapshot,'UTC'),$now);
            $checks['encrypted_backup_checksum_transferred']=self::digest($doc['encrypted_backup_sha256']??null)
                && self::digest($doc['staging_received_sha256']??null)
                && hash_equals($doc['encrypted_backup_sha256'],$doc['staging_received_sha256']);
            $witnesses=is_array($doc['witnesses']??null)?$doc['witnesses']:[];
            foreach(self::REQUIRED as $gate){
                $v=$witnesses[$gate]??null;
                $checks['witness_'.$gate]=$checks['fresh_utc_snapshot_and_restore']
                    && is_array($v)
                    && ($v['status']??null)==='passed'
                    && self::digest($v['evidence_sha256']??null)
                    && self::observed($v['observed_utc']??null,
                        CarbonImmutable::parse($snapshot,'UTC'),$now);
            }
        }

        // Verify REVIEW archives byte-by-byte when provided. Content check
        // and private-file exclusions remain the separate CI archive verifier.
        $dir=is_string($artifactDir)?realpath($artifactDir):false;
        if($dir!==false && is_dir($dir)
            && ($public===false || !str_starts_with($dir,$public.DIRECTORY_SEPARATOR))){
            $manifestPath=$dir.DIRECTORY_SEPARATOR.'release-manifest.json';
            $release=null;
            if(is_file($manifestPath) && filesize($manifestPath)<=32768){
                try{$release=json_decode(file_get_contents($manifestPath),true,16,JSON_THROW_ON_ERROR);}
                catch(Throwable){$release=null;}
            }
            if(is_array($release)){
                $checks['release_manifest_source_sha_matches']=$checks['reviewed_sha_format']
                    && ($release['source_commit_sha']??null)===$sha;
                $checks['release_manifest_cannot_authorize_deploy']
                    =($release['deployment_authorized']??null)===false;
                $names=['erp-cpanel-application.zip','erp-cpanel-split-webroot.zip'];
                $valid=true;
                foreach($names as $name){
                    $zip=$dir.DIRECTORY_SEPARATOR.$name;
                    $expected=$release['artifacts_sha256'][$name]??null;
                    if(!is_file($zip)||is_link($zip)||filesize($zip)===0
                        || !self::digest($expected)
                        || !hash_equals($expected,hash_file('sha256',$zip))){
                        $valid=false;break;
                    }
                }
                $checks['release_artifacts_present_and_hashed']=$valid;
            }
        }
        $failed=array_keys(array_filter($checks,fn($pass)=>!$pass));
        return [
            'decision'=>$failed===[]?'PACKET_READY_FOR_INDEPENDENT_REVIEW':'NO_GO',
            'deployment_authorized'=>false,
            'checks'=>$checks,
            'failed'=>$failed,
            'note'=>'Nonsecret evidence index and archive hash checks only. They do NOT prove actual backup integrity, signed human approval, production safety or 200GB restore. Live deploy always requires separate explicit owner authorization.',
        ];
    }
}
