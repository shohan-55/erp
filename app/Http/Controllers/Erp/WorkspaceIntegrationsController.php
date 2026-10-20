<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
use App\Support\ErpSecurityEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class WorkspaceIntegrationsController extends Controller
{
    public function index(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        $tokens=DB::table('erp_integration_tokens')
            ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
            ->where('membership_id',$actor->id)->where('user_id',$request->user()->id)
            ->orderByDesc('id')->limit(20)
            ->get(['id','name','scopes','expires_at','revoked_at','last_used_at','created_at']);
        return response()->view('erp.integrations',compact('company','workspace','actor','firmSlug','tokens'))
            ->header('Cache-Control','private, no-store, max-age=0')
            ->header('Referrer-Policy','no-referrer')
            ->header('Content-Security-Policy',"frame-ancestors 'none'");
    }

    public function create(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        $d=$request->validate([
            'name'=>['required','string','min:4','max:80'],
            'password'=>['required','string','current_password'],
            'days'=>['required','integer','between:1,30'],
            'scopes'=>['required','array','min:1','max:3'],
            'scopes.*'=>['required','distinct','in:attendance:read,leave:read,notifications:read'],
        ]);
        $plain='dw20_'.bin2hex(random_bytes(32));
        DB::transaction(function()use($company,$workspace,$actor,$request,$d,$plain){
            // Concurrent token creation per person is serialized.
            DB::table('memberships')->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)
                ->where('id',$actor->id)->lockForUpdate()->firstOrFail();
            $active=DB::table('erp_integration_tokens')
                ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
                ->where('membership_id',$actor->id)->where('user_id',$request->user()->id)
                ->whereNull('revoked_at')->where('expires_at','>',now())->count();
            if($active>=5)throw ValidationException::withMessages([
                'name'=>'Revoke an existing API token before creating another (maximum 5).',
            ]);
            $id=DB::table('erp_integration_tokens')->insertGetId([
                'company_id'=>$company->id,'workspace_id'=>$workspace->id,
                'membership_id'=>$actor->id,'user_id'=>$request->user()->id,
                'name'=>trim($d['name']),
                'token_hash'=>hash('sha256',$plain),
                'scopes'=>json_encode(array_values($d['scopes']),JSON_THROW_ON_ERROR),
                'expires_at'=>now()->addDays((int)$d['days']),
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            ErpSecurityEvents::record('integration.token.created',$request,$company->id,$request->user()->id);
            TenantOps::audit($actor,'integration.token.created','erp_integration_token',$id,[
                'scopes'=>$d['scopes'],'days'=>(int)$d['days'],
            ]);
        },3);
        // Secret is shown once. No plaintext tokens in DB, logs, URLs or audit.
        return redirect()->route('erp.integrations.index',['firmSlug'=>$firmSlug])
            ->with('integration_token_once',$plain)
            ->with('success','Copy this token now. It will not be shown again.');
    }

    public function revoke(Request $request,string $firmSlug,int $id)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        $request->validate(['password'=>['required','current_password']]);
        DB::transaction(function()use($company,$workspace,$actor,$request,$id){
            $token=DB::table('erp_integration_tokens')
                ->where('id',$id)->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)
                ->where('membership_id',$actor->id)
                ->where('user_id',$request->user()->id)
                ->lockForUpdate()->first();
            abort_unless($token,404);
            if($token->revoked_at===null) {
                DB::table('erp_integration_tokens')->where('id',$id)->update([
                    'revoked_at'=>now(),'updated_at'=>now(),
                ]);
                ErpSecurityEvents::record('integration.token.revoked',$request,$company->id,$request->user()->id);
                TenantOps::audit($actor,'integration.token.revoked','erp_integration_token',$id);
            }
        },3);
        return back()->with('success','API token revoked. Subsequent requests are rejected.');
    }

    public function locale(Request $request,string $firmSlug)
    {
        TenantOps::context($request);
        $d=$request->validate(['language'=>['required','in:en,bn']]);
        $request->session()->put('erp.ui_locale',$d['language']);
        return back()->with('success','Workspace display language preference updated.');
    }

    public function manifest(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        $start=route('erp.dashboard',['firmSlug'=>$firmSlug],false);
        $manifest=[
            'name'=>'DhakaWala ERP · '.$company->name,
            'short_name'=>'DhakaWala ERP',
            'description'=>'Private ERP access. Offline data is never stored on this device.',
            'id'=>$start,'start_url'=>$start,'scope'=>"/c/$firmSlug/app/",
            'display'=>'standalone','background_color'=>'#F6F7F9',
            'theme_color'=>'#17191F',
            'icons'=>[['src'=>'/images/rac_logo.png','sizes'=>'any','type'=>'image/png','purpose'=>'any']],
        ];
        return response()->json($manifest,200,[
            'Content-Type'=>'application/manifest+json',
            'Cache-Control'=>'private, no-store, max-age=0',
            'X-Content-Type-Options'=>'nosniff',
        ]);
    }
}
