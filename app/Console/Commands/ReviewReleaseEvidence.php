<?php

namespace App\Console\Commands;

use App\Support\ReleaseEvidenceGate;
use Illuminate\Console\Command;

final class ReviewReleaseEvidence extends Command
{
    protected $signature='erp:release:evidence
        {--sha= : Exact reviewed 40-character Git commit SHA}
        {--evidence= : Private nonsecret staging evidence JSON under storage/app/private/release-evidence}
        {--artifacts= : Directory containing the verified cPanel review release ZIP files and release-manifest.json}
        {--json : Print a privacy-safe machine-readable decision}';
    protected $description='Fail-closed staging evidence and SHA integrity review; never authorize or execute production deployment.';

    public function handle():int
    {
        $result=ReleaseEvidenceGate::assess(
            $this->option('evidence') ?: null,
            (string)($this->option('sha')?:''),
            $this->option('artifacts') ?: null
        );
        if($this->option('json')){
            $this->line(json_encode($result,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT));
        }else{
            $this->info('DhakaWala ERP — Phase 23 Independent Release Evidence Review');
            foreach($result['checks'] as $key=>$passed){
                $this->line(($passed?'[PASS] ':'[FAIL] ').$key);
            }
            $this->warn('Decision: '.$result['decision'].' — DEPLOYMENT NOT AUTHORIZED');
            $this->warn($result['note']);
        }
        // Success here means the NONSECRET EVIDENCE INDEX is complete for
        // independent human review only. It is NEVER a deploy permission.
        return $result['failed']===[]?self::SUCCESS:self::FAILURE;
    }
}
