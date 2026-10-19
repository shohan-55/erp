<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
use App\Support\WorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class WorkspaceAccessController extends Controller
{
    public function index(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        abort_unless(WorkspaceAccess::canManage($actor),403);
        $members=DB::table('memberships as m')->join('users as u','u.id','=','m.user_id')
            ->where('m.company_id',$company->id)->where('m.workspace_id',$workspace->id)
            ->where('m.role','manager')->where('m.active',true)->whereNull('m.platform_suspended_at')
            ->select('m.id','u.name','u.email')->orderBy('u.name')->get();
        $grants=DB::table('workspace_access_grants as g')
            ->join('memberships as m','m.id','=','g.membership_id')
            ->join('users as u','u.id','=','m.user_id')
            ->where('g.company_id',$company->id)->where('g.workspace_id',$workspace->id)
            ->where('m.company_id',$company->id)->where('m.workspace_id',$workspace->id)
            ->where('m.role','manager')
            ->whereNull('g.revoked_at')->where('m.active',true)
            ->whereNull('m.platform_suspended_at')
            ->select('g.*','u.name','u.email')->orderBy('u.name')->get();
        return response()->view('erp.workspace-access',compact('company','workspace','actor','firmSlug','members','grants'))
            ->header('Cache-Control','private, no-store');
    }

    public function save(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        abort_unless(WorkspaceAccess::canManage($actor),403);
        $input=$request->validate([
            'membership_id'=>['required','integer','min:1'],
            'access_role'=>['required',Rule::in(['company_admin','moderator'])],
            'permissions'=>['sometimes','array','max:30'],
            'permissions.*'=>['string',Rule::in(array_keys(WorkspaceAccess::PERMISSIONS))],
        ]);
        $permissions=array_values(array_unique($input['permissions']??[]));
        DB::transaction(function () use ($company,$workspace,$actor,$input,$permissions) {
            DB::table('workspaces')->where('company_id',$company->id)
                ->where('id',$workspace->id)->lockForUpdate()->first();
            // Both grantor and subject must still be active in this precise workspace.
            $current=DB::table('memberships')->where('id',$actor->id)
                ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
                ->where('active',true)->whereNull('platform_suspended_at')->first();
            abort_unless($current && WorkspaceAccess::canManage($current),403);
            $target=DB::table('memberships')->where('id',$input['membership_id'])
                ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
                ->where('role','manager')->where('active',true)->whereNull('platform_suspended_at')->first();
            abort_unless($target && $target->id!==$actor->id,404);
            if ($actor->role!=='partner') {
                // Delegated staff cannot grant a privilege they themselves lack,
                // nor mint Company Admin titles without explicit authorization.
                foreach($permissions as $permission) {
                    abort_unless(WorkspaceAccess::allows($current,$permission),403);
                }
                if ($input['access_role']==='company_admin') {
                    abort_unless(WorkspaceAccess::allows($current,'access.delegate_admins'),403);
                }
                $actingGrant=WorkspaceAccess::grant($current);
                abort_unless($actingGrant && $actingGrant->access_role==='company_admin',403);
            }
            $previous=DB::table('workspace_access_grants')
                ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
                ->where('membership_id',$target->id)->first();
            $values=['access_role'=>$input['access_role'],
                'permissions'=>json_encode($permissions,JSON_THROW_ON_ERROR),
                'granted_by'=>$actor->id,'revoked_by'=>null,'revoked_at'=>null,
                'updated_at'=>now()];
            if ($previous) {
                DB::table('workspace_access_grants')->where('id',$previous->id)->update($values);
                $id=$previous->id;
            } else {
                $id=DB::table('workspace_access_grants')->insertGetId([
                    'company_id'=>$company->id,'workspace_id'=>$workspace->id,
                    'membership_id'=>$target->id,...$values,'created_at'=>now(),
                ]);
            }
            TenantOps::audit($actor,'workspace_access.granted','workspace_access_grant',$id,[
                'target_membership_id'=>$target->id,'role'=>$input['access_role'],
                'permissions'=>$permissions,'previous_role'=>$previous?->access_role,
            ]);
            TenantOps::notify($actor,$target->id,'Workspace access updated',
                'Your delegated '.$input['access_role'].' access was updated.');
        },3);
        return back()->with('success','Workspace access saved with an audit trail.');
    }

    public function revoke(Request $request,string $firmSlug,int $id)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        abort_unless(WorkspaceAccess::canManage($actor),403);
        $request->validate(['reason'=>['required','string','min:10','max:500']]);
        DB::transaction(function () use ($company,$workspace,$actor,$id,$request) {
            DB::table('workspaces')->where('company_id',$company->id)
                ->where('id',$workspace->id)->lockForUpdate()->first();
            $current=DB::table('memberships')->where('id',$actor->id)
                ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
                ->where('active',true)->whereNull('platform_suspended_at')->first();
            abort_unless($current && WorkspaceAccess::canManage($current),403);
            $grant=DB::table('workspace_access_grants')
                ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
                ->where('id',$id)->whereNull('revoked_at')->first();
            abort_unless($grant && $grant->membership_id!==$actor->id,404);
            if ($actor->role!=='partner') {
                $actingGrant=WorkspaceAccess::grant($current);
                abort_unless($actingGrant && $actingGrant->access_role==='company_admin',403);
                foreach(json_decode($grant->permissions,true)?:[] as $permission) {
                    abort_unless(WorkspaceAccess::allows($current,$permission),403);
                }
                if ($grant->access_role==='company_admin') {
                    abort_unless(WorkspaceAccess::allows($current,'access.delegate_admins'),403);
                }
            }
            DB::table('workspace_access_grants')->where('id',$id)->update([
                'revoked_at'=>now(),'revoked_by'=>$actor->id,'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'workspace_access.revoked','workspace_access_grant',$id,[
                'target_membership_id'=>$grant->membership_id,'reason'=>trim($request->input('reason')),
            ]);
            TenantOps::notify($actor,$grant->membership_id,'Workspace access revoked',
                'Your delegated workspace access has been revoked.');
        },3);
        return back()->with('success','Delegated access revoked.');
    }
}
