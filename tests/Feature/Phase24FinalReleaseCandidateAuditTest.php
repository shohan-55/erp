<?php

namespace Tests\Feature;

use App\Support\ReleaseRouteAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class Phase24FinalReleaseCandidateAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_registered_sensitive_route_has_required_security_boundary():void
    {
        $result=ReleaseRouteAudit::assess();
        $this->assertSame('ROUTE_GUARDS_PASSED',$result['decision'],json_encode($result['failed']));
        $this->assertSame([],$result['failed']);
        $this->assertGreaterThanOrEqual(100,$result['reviewed_route_counts']['tenant_routes']);
        $this->assertGreaterThanOrEqual(25,$result['reviewed_route_counts']['platform_routes']);
        $this->assertSame(4,$result['reviewed_route_counts']['api_routes']);
        $this->assertGreaterThan(350,$result['checked_guard_conditions']);

        foreach([
            'erp.integrations.create'=>['tenant.host','auth','mfa.verified','tenant.member','student.features'],
            'erp.security.mfa.disable'=>['tenant.host','auth','mfa.verified','tenant.member'],
            'erp.vault.download'=>['tenant.host','auth','mfa.verified','tenant.member'],
            'erp.exit.download'=>['tenant.host','auth','mfa.verified','tenant.member'],
            'erp.platform.billing.storage-allocate'=>['platform.host','auth','platform.admin','mfa.verified'],
            'erp.platform.billing.payment.decide'=>['platform.host','auth','platform.admin','mfa.verified'],
            'erp.api.v1.attendance'=>['tenant.host','erp.api-token','throttle:60,1'],
        ] as $name=>$guards){
            $route=Route::getRoutes()->getByName($name);
            $this->assertNotNull($route,$name);
            foreach($guards as $guard)$this->assertContains($guard,$route->gatherMiddleware(),$name);
        }
    }

    public function test_new_unprotected_tenant_route_is_detected_without_editing_real_routes():void
    {
        Route::get('/c/{firmSlug}/app/unsafe-p24-probe',static fn()=>response('not-used'))
            ->name('erp.unsafe.p24.probe');
        $result=ReleaseRouteAudit::assess();
        $this->assertSame('ROUTE_GUARDS_FAILED',$result['decision']);
        $this->assertContains('tenant_erp.unsafe.p24.probe_verified_host',$result['failed']);
        $this->assertContains('tenant_erp.unsafe.p24.probe_session_auth',$result['failed']);
        $this->assertContains('tenant_erp.unsafe.p24.probe_member',$result['failed']);
    }

    public function test_mutating_api_route_is_detected_even_with_correct_bearer_guard():void
    {
        Route::post('/api/v1/companies/{firmSlug}/write-probe',static fn()=>response('not-used'))
            ->middleware(['tenant.host','erp.api-token','throttle:60,1'])
            ->name('erp.api.v1.write.probe');
        $result=ReleaseRouteAudit::assess();
        $this->assertSame('ROUTE_GUARDS_FAILED',$result['decision']);
        $this->assertContains('api_erp.api.v1.write.probe_read_only',$result['failed']);
        $this->assertContains('exact_readonly_api_surface',$result['failed']);
    }

    public function test_final_audit_cannot_be_tricked_into_deploy_go_without_evidence():void
    {
        $before=DB::table('companies')->count();
        $this->assertSame(1,Artisan::call('erp:release:final-audit',[
            '--sha'=>str_repeat('a',40),'--json'=>true,
        ]));
        $response=json_decode(Artisan::output(),true,512,JSON_THROW_ON_ERROR);
        $this->assertSame(24,$response['phase']);
        $this->assertSame('ROUTE_GUARDS_PASSED',$response['release_candidate_code_review']);
        $this->assertSame('NO_GO',$response['production_decision']);
        $this->assertSame('NO_GO',$response['evidence_index']);
        $this->assertFalse($response['deployment_authorized']);
        $this->assertFalse($response['owner_authorized_production_deployment']);
        $this->assertFalse($response['independent_real_restore_approved']);
        $this->assertFalse($response['human_ua_testing_approved']);
        $this->assertSame($before,DB::table('companies')->count());
        $this->assertStringNotContainsString('DB_PASSWORD',Artisan::output());
    }

    public function test_invalid_sha_and_missing_external_artifacts_always_fail_closed():void
    {
        $this->assertSame(1,Artisan::call('erp:release:final-audit',[
            '--sha'=>'development','--json'=>true,
        ]));
        $report=json_decode(Artisan::output(),true,512,JSON_THROW_ON_ERROR);
        $this->assertContains('reviewed_sha_format',$report['evidence_failed']);
        $this->assertFalse($report['deployment_authorized']);
        $this->assertSame('NO_GO',$report['production_decision']);
    }
}
