<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
use App\Support\WorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PracticeDocumentController extends Controller
{
    private function visible(object $actor,object $doc): bool
    {
        if(WorkspaceAccess::partnerAction($actor,'documents.manage'))return true;
        if($doc->archived_at || $doc->visibility==='partner' || !$doc->subject_membership_id)return false;
        if($actor->id===$doc->subject_membership_id || (int)$actor->id===(int)$doc->subject_membership_id)return true;
        if($actor->role!=='manager' || $doc->visibility!=='team')return false;
        return DB::table('memberships')->where('company_id',$actor->company_id)
            ->where('workspace_id',$actor->workspace_id)
            ->where('id',$doc->subject_membership_id)
            ->where('manager_membership_id',$actor->id)->where('role','student')
            ->exists();
    }

    public function index(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        $q=DB::table('practice_documents as d')
            ->leftJoin('memberships as m','m.id','=','d.subject_membership_id')
            ->leftJoin('users as u','u.id','=','m.user_id')
            ->where('d.company_id',$company->id)->where('d.workspace_id',$workspace->id)
            ->where(function($x)use($company,$workspace){
                $x->whereNull('d.subject_membership_id')
                    ->orWhere(function($y)use($company,$workspace){
                        $y->where('m.company_id',$company->id)
                            ->where('m.workspace_id',$workspace->id);
                    });
            })
            ->when(!WorkspaceAccess::partnerAction($actor,'documents.manage'),fn($q)=>$q->whereNull('d.archived_at')
                ->where('d.visibility','!=','partner')
                ->where(function($z)use($actor){
                    $z->where('d.subject_membership_id',$actor->id);
                    if($actor->role==='manager'){
                        $z->orWhere(function($m)use($actor){
                            $m->where('d.visibility','team')
                                ->where('m.role','student')
                                ->where('m.manager_membership_id',$actor->id);
                        });
                    }
                }))
            ->select('d.*','u.name as subject_name')->orderByDesc('d.id')->limit(100)->get();
        $members=WorkspaceAccess::partnerAction($actor,'documents.manage')
            ? DB::table('memberships as m')->join('users as u','u.id','=','m.user_id')
                ->where('m.company_id',$company->id)->where('m.workspace_id',$workspace->id)
                ->whereIn('m.role',['student','manager'])->where('m.active',true)
                ->select('m.id','u.name','m.role')->orderBy('u.name')->get() : collect();
        return response()->view('erp.practice-documents',compact(
            'company','workspace','actor','firmSlug','q','members'
        ))->header('Cache-Control','private, no-store, max-age=0');
    }

    public function upload(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        abort_unless(WorkspaceAccess::partnerAction($actor,'documents.manage'),403);
        $input=$request->validate([
            'title'=>['required','string','min:4','max:180'],
            'category'=>['required','in:policy,audit,hr,notice,other'],
            'visibility'=>['required','in:partner,subject,team'],
            'subject_membership_id'=>['nullable','integer','min:1'],
            'document'=>['required','file','mimes:pdf,jpg,jpeg,png','max:5120'],
        ]);
        $subjectId=$input['subject_membership_id']??null;
        if(($input['visibility']==='partner' && $subjectId)
            || ($input['visibility']!=='partner' && !$subjectId)){
            throw ValidationException::withMessages(['subject_membership_id'=>
                'Choose a recipient for a personal/team document; Partner-only documents have no recipient.']);
        }
        if($subjectId){
            $member=DB::table('memberships')->where('company_id',$actor->company_id)
                ->where('workspace_id',$actor->workspace_id)->where('id',(int)$subjectId)
                ->whereIn('role',['student','manager'])->where('active',true)
                ->whereNull('platform_suspended_at')->first();
            abort_unless($member && in_array($member->role,['student','manager'],true),404);
        }
        $file=$request->file('document');
        $hash=hash_file('sha256',$file->getRealPath());
        $path=$file->store('practice-documents/'.$company->id.'/'.$workspace->id,'local');
        try{
            DB::transaction(function()use($actor,$input,$subjectId,$path,$hash,$file){
                DB::table('workspaces')->where('company_id',$actor->company_id)
                    ->where('id',$actor->workspace_id)->lockForUpdate()->first();
                if($subjectId)TenantOps::subject($actor,(int)$subjectId);
                $id=DB::table('practice_documents')->insertGetId([
                    'company_id'=>$actor->company_id,'workspace_id'=>$actor->workspace_id,
                    'uploaded_by'=>$actor->id,'subject_membership_id'=>$subjectId,
                    'title'=>trim($input['title']),'category'=>$input['category'],
                    'visibility'=>$input['visibility'],'storage_path'=>$path,
                    'original_filename'=>basename($file->getClientOriginalName()),
                    'sha256'=>$hash,'byte_size'=>$file->getSize(),
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
                TenantOps::audit($actor,'document.uploaded','practice_document',$id,[
                    'category'=>$input['category'],'visibility'=>$input['visibility'],
                    'subject_membership_id'=>$subjectId,'sha256'=>$hash,
                ]);
                if($subjectId){
                    TenantOps::notify($actor,(int)$subjectId,'New private practice document',
                        'A document was published to your private Document Center.');
                }
            },3);
        }catch(\Throwable $e){
            Storage::disk('local')->delete($path);
            throw $e;
        }
        return back()->with('success','Private document uploaded successfully.');
    }

    public function download(Request $request,string $firmSlug,int $id)
    {
        [,,$actor]=TenantOps::context($request);
        $doc=DB::table('practice_documents')->where('id',$id)
            ->where('company_id',$actor->company_id)
            ->where('workspace_id',$actor->workspace_id)->first();
        abort_unless($doc && $this->visible($actor,$doc),404);
        abort_unless(Storage::disk('local')->exists($doc->storage_path),404);
        TenantOps::audit($actor,'document.downloaded','practice_document',$id);
        return Storage::disk('local')->download($doc->storage_path,
            basename($doc->original_filename),[
                'Cache-Control'=>'private, no-store, max-age=0',
                'X-Content-Type-Options'=>'nosniff',
            ]);
    }

    public function archive(Request $request,string $firmSlug,int $id)
    {
        [,,$actor]=TenantOps::context($request);
        abort_unless(WorkspaceAccess::partnerAction($actor,'documents.manage'),403);
        DB::transaction(function()use($actor,$id){
            DB::table('workspaces')->where('id',$actor->workspace_id)
                ->where('company_id',$actor->company_id)->lockForUpdate()->first();
            $doc=DB::table('practice_documents')->where('id',$id)
                ->where('company_id',$actor->company_id)
                ->where('workspace_id',$actor->workspace_id)->lockForUpdate()->first();
            abort_unless($doc,404);
            if($doc->archived_at){
                throw ValidationException::withMessages(['document'=>'This document is already archived.']);
            }
            DB::table('practice_documents')->where('id',$id)->update([
                'archived_at'=>now(),'archived_by'=>$actor->id,'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'document.archived','practice_document',$id);
        },3);
        return back()->with('success','Document archived; original evidence is retained privately.');
    }
}
