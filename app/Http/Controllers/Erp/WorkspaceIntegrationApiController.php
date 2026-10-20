<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class WorkspaceIntegrationApiController extends Controller
{
    private function actor(Request $request): object
    {
        $actor=$request->attributes->get('integration.actor');
        abort_unless($actor,401);
        return $actor;
    }

    private function dates(Request $request): array
    {
        $d=$request->validate([
            'from'=>['sometimes','date_format:Y-m-d'],
            'to'=>['sometimes','date_format:Y-m-d','after_or_equal:from'],
            'limit'=>['sometimes','integer','between:1,50'],
        ]);
        $from=$d['from']??now()->subDays(30)->toDateString();
        $to=$d['to']??now()->toDateString();
        if(\Carbon\CarbonImmutable::parse($from)->diffInDays($to)>90) {
            return abort(422,'Maximum allowed report window is 90 days.');
        }
        return [$from,$to,(int)($d['limit']??25)];
    }

    public function me(Request $request,string $firmSlug): JsonResponse
    {
        $actor=$this->actor($request);
        return response()->json([
            'data'=>[
                'company_slug'=>$actor->company_slug,
                'workspace_id'=>(int)$actor->workspace_id,
                'membership_id'=>(int)$actor->membership_id,
                'role'=>$actor->role,
                'available_scopes'=>json_decode($actor->scopes,true),
            ],
            'api_version'=>'v1',
        ]);
    }

    public function attendance(Request $request,string $firmSlug): JsonResponse
    {
        $actor=$this->actor($request);
        [$from,$to,$limit]=$this->dates($request);
        $rows=DB::table('attendance_sessions')
            ->where('company_id',$actor->company_id)
            ->where('workspace_id',$actor->workspace_id)
            ->where('membership_id',$actor->membership_id)
            ->whereBetween('work_date',[$from,$to])->orderByDesc('work_date')
            ->orderByDesc('id')->limit($limit)
            ->get(['id','work_date','work_mode','started_at','ended_at','late','holiday']);
        return response()->json(['data'=>$rows,'count'=>$rows->count(),'api_version'=>'v1']);
    }

    public function leaves(Request $request,string $firmSlug): JsonResponse
    {
        $actor=$this->actor($request);
        [$from,$to,$limit]=$this->dates($request);
        $rows=DB::table('leave_requests')
            ->where('company_id',$actor->company_id)
            ->where('workspace_id',$actor->workspace_id)
            ->where('membership_id',$actor->membership_id)
            ->where('date_from','<=',$to)->where('date_to','>=',$from)
            ->orderByDesc('id')->limit($limit)
            ->get(['id','leave_type','date_from','date_to','status','created_at']);
        return response()->json(['data'=>$rows,'count'=>$rows->count(),'api_version'=>'v1']);
    }

    public function notifications(Request $request,string $firmSlug): JsonResponse
    {
        $actor=$this->actor($request);
        $d=$request->validate(['limit'=>['sometimes','integer','between:1,50']]);
        $rows=DB::table('tenant_notifications')
            ->where('company_id',$actor->company_id)
            ->where('workspace_id',$actor->workspace_id)
            ->where('recipient_membership_id',$actor->membership_id)
            ->orderByDesc('id')->limit((int)($d['limit']??25))
            ->get(['id','title','message','read_at','created_at']);
        return response()->json(['data'=>$rows,'count'=>$rows->count(),'api_version'=>'v1']);
    }
}
