<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PracticeNoticeController extends Controller
{
    public function index(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $notices=DB::table('practice_notices')->where('company_id',$company->id)
            ->where('workspace_id',$workspace->id)->orderByDesc('id')->limit(100)->get();
        return response()->view('erp.practice-notices',compact(
            'company','workspace','actor','firmSlug','notices'
        ))->header('Cache-Control','private, no-store, max-age=0');
    }

    public function publish(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $data=$request->validate([
            'title'=>['required','string','min:5','max:160'],
            'body'=>['required','string','min:20','max:5000'],
            'audience'=>['required','in:everyone,managers,students'],
            'confirm_publish'=>['required','accepted'],
        ]);
        DB::transaction(function()use($actor,$data){
            DB::table('workspaces')->where('company_id',$actor->company_id)
                ->where('id',$actor->workspace_id)->lockForUpdate()->first();
            $query=DB::table('memberships')->where('company_id',$actor->company_id)
                ->where('workspace_id',$actor->workspace_id)->where('active',true)
                ->whereNull('platform_suspended_at');
            if($data['audience']==='managers')$query->where('role','manager');
            if($data['audience']==='students')$query->where('role','student');
            $recipients=$query->orderBy('id')->pluck('id')->all();
            if(count($recipients)>1000){
                throw ValidationException::withMessages(['audience'=>
                    'Large broadcasts must be sent in smaller batches after a compliance review.']);
            }
            if(!$recipients){
                throw ValidationException::withMessages(['audience'=>'No active authorized recipients in this Partner workspace.']);
            }
            $id=DB::table('practice_notices')->insertGetId([
                'company_id'=>$actor->company_id,'workspace_id'=>$actor->workspace_id,
                'author_membership_id'=>$actor->id,
                'title'=>trim($data['title']),'body'=>trim($data['body']),
                'audience'=>$data['audience'],'recipients_count'=>count($recipients),
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $now=now();
            $rows=array_map(fn($membershipId)=>[
                'company_id'=>$actor->company_id,'workspace_id'=>$actor->workspace_id,
                'recipient_membership_id'=>$membershipId,
                'practice_notice_id'=>$id,'title'=>trim($data['title']),
                'message'=>trim($data['body']),'created_at'=>$now,'updated_at'=>$now,
            ],$recipients);
            DB::table('tenant_notifications')->insert($rows);
            TenantOps::audit($actor,'notice.published','practice_notice',$id,[
                'audience'=>$data['audience'],'recipient_count'=>count($recipients),
            ]);
        },3);
        return back()->with('success','Notice published to authorized workspace inboxes. No external email was sent.');
    }

    public function markAllRead(Request $request,string $firmSlug)
    {
        [,,$actor]=TenantOps::context($request);
        $count=DB::table('tenant_notifications')
            ->where('company_id',$actor->company_id)->where('workspace_id',$actor->workspace_id)
            ->where('recipient_membership_id',$actor->id)->whereNull('read_at')
            ->update(['read_at'=>now(),'updated_at'=>now()]);
        if($count){
            TenantOps::audit($actor,'notice.inbox.marked_read','membership',$actor->id,[
                'notifications_marked'=>$count,
            ]);
        }
        return back()->with('success','Your own unread notifications have been marked as read.');
    }
}
