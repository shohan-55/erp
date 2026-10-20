<?php

namespace App\Console\Commands;

use App\Support\ReleaseEvidenceGate;
use App\Support\ReleaseRouteAudit;
use Illuminate\Console\Command;

/**
 * Read-only code/infrastructure readiness inventory. Does NOT run migrations,
 * deploy code, make snapshots or turn an evidence packet into human approval.
 */
final class FinalReleaseCandidateAudit extends Command
{
    protected $signature='erp:release:final-audit
        {--sha= : Exact reviewed development release commit SHA}
        {--evidence= : Optional private, nonsecret signed-external-evidence index}
        {--artifacts= : Optional directory with reviewed cPanel ZIPs and manifest}
        {--json : Structured, non-PII release-candidate report}';

    protected $description='Phase 24 exhaustive route audit and fail-closed human release evidence summary (no deployment).';

    public function handle():int
    {
        $sha=(string)($this->option('sha')?:'');
        $routes=ReleaseRouteAudit::assess();
        $evidence=ReleaseEvidenceGate::assess(
            $this->option('evidence')?:null,
            $sha,
            $this->option('artifacts')?:null
        );
        // A valid metadata packet is still only a review REQUEST, not a signed
        // authorization or a verified real data restore. Never turn it into GO.
        $result=[
            'phase'=>24,
            'release_candidate_code_review'=>$routes['decision'],
            'routes'=>$routes,
            'evidence_index'=>$evidence['decision'],
            'evidence_failed'=>$evidence['failed'],
            'independent_real_restore_approved'=>false,
            'human_ua_testing_approved'=>false,
            'owner_authorized_production_deployment'=>false,
            'deployment_authorized'=>false,
            'production_decision'=>'NO_GO',
            'next_action'=>'Complete an isolated real backup/private-file restore, independent DR/UAT/security reviews and explicit exact-SHA production release approval. Git pull alone is not a safe release.',
        ];
        if($this->option('json')){
            $this->line(json_encode($result,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT));
        }else{
            $this->info('DhakaWala ERP — Final Phase 24 Development Release Audit');
            $this->line('Route guard inventory: '.$routes['decision']);
            $this->line('Evidence packet: '.$evidence['decision']);
            $this->line('Production decision: NO_GO (human release approval not present)');
            foreach($routes['failed'] as $fail)$this->warn('[ROUTE FAIL] '.$fail);
            foreach($evidence['failed'] as $fail)$this->warn('[EVIDENCE MISSING] '.$fail);
            $this->warn($result['next_action']);
        }
        // Nonzero is mandatory even if source guards + untrusted JSON pass:
        // callers must not mistake CLI success for production deploy consent.
        return self::FAILURE;
    }
}
