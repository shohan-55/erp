<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Reviews every registered private ERP route, not a frozen subset from an
 * earlier phase. A newly added route cannot silently bypass these checks.
 * This is route-configuration evidence, NOT a penetration-test certification.
 */
final class ReleaseRouteAudit
{
    public static function assess():array
    {
        $checks=[];
        $summary=['tenant_routes'=>0,'platform_routes'=>0,'api_routes'=>0];
        $publicTenant=[
            'login','login.attempt','password.forgot','password.email',
            'password.reset.form','password.reset','activate.form','activate.accept',
        ];
        $publicPlatform=['login','login.attempt'];
        $tenantSwitch=['workspaces.index','workspaces.select'];

        foreach(Route::getRoutes() as $route) {
            $uri=ltrim($route->uri(),'/');
            $name=(string)$route->getName();
            $middleware=$route->gatherMiddleware();
            $methods=$route->methods();
            $isTenant=str_starts_with($uri,'c/{firmSlug}/app');
            $isPlatform=str_starts_with($uri,'platform/app');
            $isApi=str_starts_with($uri,'api/v1/companies/');
            if(!$isTenant && !$isPlatform && !$isApi)continue;

            // Route IDs contain no user data. Avoid serializing any route
            // action/controller or current request into security output.
            $id=preg_replace('/[^a-zA-Z0-9_.-]/','_',($name?:$uri));
            if($name===''||strlen($id)>160){
                $checks['named_and_bounded_'.count($checks)]=false;
                continue;
            }
            if($isTenant){
                $summary['tenant_routes']++;
                $prefix='tenant_'.$id.'_';
                $checks[$prefix.'verified_host']=in_array('tenant.host',$middleware,true);
                $short=str_starts_with($name,'erp.')?substr($name,4):'';
                if(in_array($short,$publicTenant,true))continue;
                $checks[$prefix.'session_auth']=in_array('auth',$middleware,true);
                if($short==='logout')continue;
                $checks[$prefix.'mfa']=in_array('mfa.verified',$middleware,true);
                if(in_array($short,$tenantSwitch,true))continue;
                $checks[$prefix.'member']=in_array('tenant.member',$middleware,true);
                $checks[$prefix.'feature_policy']=in_array('student.features',$middleware,true);
            }elseif($isPlatform){
                $summary['platform_routes']++;
                $prefix='platform_'.$id.'_';
                $checks[$prefix.'verified_host']=in_array('platform.host',$middleware,true);
                $short=str_starts_with($name,'erp.platform.')?substr($name,13):'';
                if(in_array($short,$publicPlatform,true))continue;
                $checks[$prefix.'session_auth']=in_array('auth',$middleware,true);
                $checks[$prefix.'platform_admin']=in_array('platform.admin',$middleware,true);
                if($short==='logout')continue;
                $checks[$prefix.'mfa']=in_array('mfa.verified',$middleware,true);
            }elseif($isApi){
                $summary['api_routes']++;
                $prefix='api_'.$id.'_';
                $checks[$prefix.'tenant_host']=in_array('tenant.host',$middleware,true);
                $checks[$prefix.'bearer_auth']=in_array('erp.api-token',$middleware,true);
                $checks[$prefix.'throttle']=in_array('throttle:60,1',$middleware,true);
                $checks[$prefix.'read_only']=count(array_diff($methods,['GET','HEAD']))===0
                    && in_array('GET',$methods,true);
                $checks[$prefix.'versioned_name']=str_starts_with($name,'erp.api.v1.');
            }
        }

        // Counts catch accidental removal of an entire protected route group.
        $checks['minimum_tenant_route_surface']=$summary['tenant_routes']>=100;
        $checks['minimum_platform_route_surface']=$summary['platform_routes']>=25;
        $checks['exact_readonly_api_surface']=$summary['api_routes']===4;
        $failed=array_keys(array_filter($checks,static fn($ok)=>!$ok));

        return [
            'decision'=>$failed===[]?'ROUTE_GUARDS_PASSED':'ROUTE_GUARDS_FAILED',
            'reviewed_route_counts'=>$summary,
            'checked_guard_conditions'=>count($checks),
            'failed'=>$failed,
            'note'=>'Static route registration only; runtime authorization, business approval, tenant isolation and external penetration testing require independent evidence.',
        ];
    }
}
