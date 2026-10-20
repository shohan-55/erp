<?php

namespace App\Http\Middleware;

use App\Support\ErpFeatures;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateWorkspaceApi
{
    private const SCOPES = [
        'erp.api.v1.me' => null,
        'erp.api.v1.attendance' => ['attendance:read','attendance'],
        'erp.api.v1.leaves' => ['leave:read','leave'],
        'erp.api.v1.notifications' => ['notifications:read','notifications'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // API tokens never substitute for browser MFA, grants or proxy approval.
        // The API is read-only and does not consume browser login sessions.
        if($request->method()!=='GET') return response()->json(['message'=>'Read-only API.'],405);
        $token=(string)$request->bearerToken();
        if(!preg_match('/^dw20_[a-f0-9]{64}$/D',$token)) {
            return response()->json(['message'=>'Missing or invalid integration token.'],401);
        }
        $entry=DB::table('erp_integration_tokens as t')
            ->join('companies as c','c.id','=','t.company_id')
            ->join('workspaces as w',function($q){
                $q->on('w.id','=','t.workspace_id')->on('w.company_id','=','t.company_id');
            })
            ->join('memberships as m',function($q){
                $q->on('m.id','=','t.membership_id')
                    ->on('m.company_id','=','t.company_id')
                    ->on('m.workspace_id','=','t.workspace_id')
                    ->on('m.user_id','=','t.user_id');
            })
            ->join('users as u','u.id','=','t.user_id')
            ->where('t.token_hash',hash('sha256',$token))
            ->whereNull('t.revoked_at')
            ->where('t.expires_at','>',now())
            ->where('c.slug',(string)$request->route('firmSlug'))
            ->where('c.active',true)->whereNull('c.archived_at')->whereNull('c.deleted_at')
            ->where('w.active',true)
            ->where('m.active',true)->whereNull('m.platform_suspended_at')
            ->where('u.is_platform_admin',false)
            ->select('t.id as token_id','t.scopes',
                'c.id as company_id','c.slug as company_slug','w.id as workspace_id',
                'm.id as membership_id','m.role','m.manager_membership_id','m.user_id')
            ->first();
        if(!$entry) return response()->json(['message'=>'Token expired, revoked, or access unavailable.'],401);
        $scope=self::SCOPES[$request->route()?->getName()]??false;
        if($scope===false)return response()->json(['message'=>'API endpoint unavailable.'],404);
        $allowed=json_decode($entry->scopes,true);
        if(!is_array($allowed))return response()->json(['message'=>'Invalid token scopes.'],403);
        if($scope!==null) {
            [$permission,$feature]=$scope;
            if(!in_array($permission,$allowed,true))return response()->json(['message'=>'Scope denied.'],403);
            $actor=(object)[
                'id'=>$entry->membership_id,'company_id'=>$entry->company_id,
                'workspace_id'=>$entry->workspace_id,'role'=>$entry->role,
                'manager_membership_id'=>$entry->manager_membership_id,
            ];
            if(!ErpFeatures::enabled($actor,$feature))return response()->json(['message'=>'Module disabled.'],403);
        }
        // Keep all authorization server-side and re-evaluate membership on every request.
        $request->attributes->set('integration.actor',$entry);
        DB::table('erp_integration_tokens')->where('id',$entry->token_id)
            ->update(['last_used_at'=>now()]);
        $response=$next($request);
        $response->headers->set('Cache-Control','private, no-store, max-age=0');
        $response->headers->set('Pragma','no-cache');
        $response->headers->set('X-Content-Type-Options','nosniff');
        return $response;
    }
}
