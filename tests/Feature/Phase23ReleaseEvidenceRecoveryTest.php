<?php

namespace Tests\Feature;

use App\Support\ReleaseEvidenceGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class Phase23ReleaseEvidenceRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private array $temporary=[];

    protected function tearDown():void
    {
        foreach(array_reverse($this->temporary) as $path){
            if(is_dir($path))File::deleteDirectory($path);
            elseif(file_exists($path)||is_link($path))@unlink($path);
        }
        parent::tearDown();
    }

    private function sha():string
    {
        return str_repeat('a',40);
    }

    private function workspace():array
    {
        $parent=storage_path('app/private/release-evidence');
        File::ensureDirectoryExists($parent,0700);
        $target=$parent.'/phase23-test-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($target,0700);
        $this->temporary[]=$target;
        return [$target,$target.'/witnesses.json'];
    }

    private function document(string $sha):array
    {
        $stamp=now('UTC')->subMinutes(12)->format('Y-m-d\TH:i:s\Z');
        $end=now('UTC')->subMinutes(2)->format('Y-m-d\TH:i:s\Z');
        $witnesses=[];
        foreach(ReleaseEvidenceGate::REQUIRED as $name){
            $witnesses[$name]=[
                'status'=>'passed',
                'evidence_sha256'=>hash('sha256','test-evidence-'.$name),
                'observed_utc'=>$end,
            ];
        }
        return [
            'schema_version'=>1,'target_environment'=>'isolated-staging',
            'reviewed_sha'=>$sha,
            'operator_alias'=>'release_operator_01',
            'independent_reviewer_alias'=>'independent_reviewer_02',
            'snapshot_utc'=>$stamp,'restored_utc'=>$end,
            'encrypted_backup_sha256'=>hash('sha256','fake-ci-encrypted-backup'),
            'staging_received_sha256'=>hash('sha256','fake-ci-encrypted-backup'),
            'witnesses'=>$witnesses,
        ];
    }

    private function releaseArtifacts(string $dir,string $sha):void
    {
        foreach(['erp-cpanel-application.zip','erp-cpanel-split-webroot.zip'] as $file){
            // Separate dummy archives for hash-binding tests, never production packages.
            File::put($dir.'/'.$file,"SYNTHETIC RELEASE ARTIFACT PLACEHOLDER / $file");
        }
        $checksums=[];
        foreach(['erp-cpanel-application.zip','erp-cpanel-split-webroot.zip'] as $file){
            $checksums[$file]=hash_file('sha256',$dir.'/'.$file);
        }
        File::put($dir.'/release-manifest.json',json_encode([
            'project'=>'DhakaWala ERP',
            'source_commit_sha'=>$sha,
            'artifacts_sha256'=>$checksums,
            'deployment_authorized'=>false,
        ],JSON_THROW_ON_ERROR));
    }

    public function test_missing_evidence_is_no_go_and_reports_only_safe_check_names():void
    {
        $this->assertSame(1,Artisan::call('erp:release:evidence',[
            '--sha'=>$this->sha(),'--json'=>true,
        ]));
        $result=json_decode(Artisan::output(),true,512,JSON_THROW_ON_ERROR);
        $this->assertSame('NO_GO',$result['decision']);
        $this->assertFalse($result['deployment_authorized']);
        $this->assertContains('private_evidence_location',$result['failed']);
        $this->assertContains('release_artifacts_present_and_hashed',$result['failed']);
    }

    public function test_complete_private_packet_and_integrity_are_reviewable_but_never_deployable():void
    {
        [$dir,$file]=$this->workspace();
        $document=$this->document($this->sha());
        File::put($file,json_encode($document,JSON_THROW_ON_ERROR));
        $this->releaseArtifacts($dir,$this->sha());
        $before=DB::table('companies')->count();
        $this->assertSame(0,Artisan::call('erp:release:evidence',[
            '--sha'=>$this->sha(),'--evidence'=>$file,
            '--artifacts'=>$dir,'--json'=>true,
        ]));
        $report=json_decode(Artisan::output(),true,512,JSON_THROW_ON_ERROR);
        $this->assertSame('PACKET_READY_FOR_INDEPENDENT_REVIEW',$report['decision']);
        $this->assertFalse($report['deployment_authorized']);
        $this->assertSame([],$report['failed']);
        $this->assertSame($before,DB::table('companies')->count());
        $this->assertStringNotContainsString($dir,Artisan::output());
        $this->assertStringNotContainsString('release_operator_01',Artisan::output());
        $this->assertStringNotContainsString($document['encrypted_backup_sha256'],Artisan::output());
    }

    public function test_archive_tampering_and_source_sha_mismatch_block_review():void
    {
        [$dir,$file]=$this->workspace();
        File::put($file,json_encode($this->document($this->sha()),JSON_THROW_ON_ERROR));
        $this->releaseArtifacts($dir,$this->sha());
        File::append($dir.'/erp-cpanel-application.zip','TAMPERED');
        $bad=ReleaseEvidenceGate::assess($file,$this->sha(),$dir);
        $this->assertSame('NO_GO',$bad['decision']);
        $this->assertFalse($bad['checks']['release_artifacts_present_and_hashed']);
        $this->releaseArtifacts($dir,str_repeat('b',40));
        $wrong=ReleaseEvidenceGate::assess($file,$this->sha(),$dir);
        $this->assertFalse($wrong['checks']['release_manifest_source_sha_matches']);
        $this->assertSame('NO_GO',$wrong['decision']);
    }

    public function test_unreviewed_missing_stale_and_self_approved_evidence_fail_closed():void
    {
        [$dir,$file]=$this->workspace();
        $this->releaseArtifacts($dir,$this->sha());
        $doc=$this->document($this->sha());
        $doc['independent_reviewer_alias']=$doc['operator_alias'];
        $doc['witnesses']['partner_user_acceptance']['status']='pending';
        $doc['snapshot_utc']=now('UTC')->subDays(45)->format('Y-m-d\TH:i:s\Z');
        $doc['target_environment']='production';
        File::put($file,json_encode($doc,JSON_THROW_ON_ERROR));
        $bad=ReleaseEvidenceGate::assess($file,$this->sha(),$dir);
        foreach(['distinct_operator_and_reviewer','staging_only',
            'fresh_utc_snapshot_and_restore','witness_partner_user_acceptance'
        ] as $check)$this->assertFalse($bad['checks'][$check],$check);
        $this->assertFalse($bad['deployment_authorized']);
        $this->assertSame('NO_GO',$bad['decision']);
    }

    public function test_public_or_oversize_manifests_are_rejected_without_parsing_contents():void
    {
        [$dir,$file]=$this->workspace();
        File::put($file,str_repeat('x',65537));
        $large=ReleaseEvidenceGate::assess($file,$this->sha(),$dir);
        $this->assertFalse($large['checks']['bounded_json_manifest']);
        $this->assertSame('NO_GO',$large['decision']);
        $public=public_path('phase23-review-test-'.bin2hex(random_bytes(8)).'.json');
        File::put($public,json_encode($this->document($this->sha())));
        $this->temporary[]=$public;
        $result=ReleaseEvidenceGate::assess($public,$this->sha(),$dir);
        $this->assertFalse($result['checks']['private_evidence_location']);
        $this->assertFalse($result['deployment_authorized']);
    }

    public function test_release_requires_explicit_exact_sha_not_branch_name_or_placeholder():void
    {
        [$dir,$file]=$this->workspace();
        File::put($file,json_encode($this->document($this->sha()),JSON_THROW_ON_ERROR));
        $this->releaseArtifacts($dir,$this->sha());
        foreach(['security/production-readiness-gates','unverified-local-build',
            str_repeat('g',40)] as $badSha){
            $decision=ReleaseEvidenceGate::assess($file,$badSha,$dir);
            $this->assertSame('NO_GO',$decision['decision']);
            $this->assertFalse($decision['checks']['reviewed_sha_format']);
        }
    }

    public function test_synthetic_restore_script_blocks_non_test_or_unconfirmed_invocation():void
    {
        $process=new \Symfony\Component\Process\Process([PHP_BINARY,base_path('scripts/phase23-synthetic-restore.php')],
            base_path(),[
                'APP_ENV'=>'production',
                'PHASE23_SYNTHETIC_RESTORE'=>'',
            ]);
        $process->run();
        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString('requires explicit test-only environment',$process->getErrorOutput());
    }
}
