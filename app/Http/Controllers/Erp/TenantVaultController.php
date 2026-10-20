<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
use App\Support\TenantVault;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class TenantVaultController extends Controller
{
    private function lockWorkspace(object $actor): void
    {
        DB::table('workspaces')->where('company_id',$actor->company_id)
            ->where('id',$actor->workspace_id)->lockForUpdate()->firstOrFail();
    }

    private function upload(object $actor,int $id,bool $lock = false): object
    {
        $q=DB::table('erp_vault_uploads')
            ->where('company_id',$actor->company_id)
            ->where('workspace_id',$actor->workspace_id)->where('id',$id)
            ->where('created_by',$actor->id);
        $found=($lock?$q->lockForUpdate():$q)->first();
        abort_unless($found,404);
        return $found;
    }

    private function fileObject(object $actor,int $id): object
    {
        $doc=DB::table('erp_vault_objects')
            ->where('company_id',$actor->company_id)
            ->where('workspace_id',$actor->workspace_id)
            ->where('id',$id)->first();
        abort_unless($doc && TenantVault::visible($actor,$doc),404);
        return $doc;
    }

    public function index(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        $quota=DB::table('erp_vault_quotas')->where('company_id',$company->id)
            ->where('workspace_id',$workspace->id)->first();
        $objects=DB::table('erp_vault_objects as o')
            ->leftJoin('memberships as m',function($q){
                $q->on('m.id','=','o.subject_membership_id')
                    ->on('m.company_id','=','o.company_id')
                    ->on('m.workspace_id','=','o.workspace_id');
            })
            ->leftJoin('users as u','u.id','=','m.user_id')
            ->where('o.company_id',$company->id)->where('o.workspace_id',$workspace->id)
            ->when($actor->role!=='partner',function($q)use($actor){
                $q->whereNull('o.archived_at')
                    ->where('o.visibility','!=','partner')
                    ->where(function($w)use($actor){
                        $w->where('o.subject_membership_id',$actor->id);
                        if($actor->role==='manager'){
                            $w->orWhere(function($team)use($actor){
                                $team->where('o.visibility','team')->where('m.role','student')
                                    ->where('m.manager_membership_id',$actor->id);
                            });
                        }
                    });
            })
            ->select('o.*','u.name as subject_name')->orderByDesc('o.id')->limit(100)->get();
        $uploads=$actor->role==='partner'
            ?DB::table('erp_vault_uploads')->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)->where('created_by',$actor->id)
                ->where('status','pending')->latest('id')->limit(20)->get()
            :collect();
        $members=$actor->role==='partner'
            ?DB::table('memberships as m')->join('users as u','u.id','=','m.user_id')
                ->where('m.company_id',$company->id)->where('m.workspace_id',$workspace->id)
                ->where('m.active',true)->whereIn('m.role',['manager','student'])
                ->select('m.id','u.name','m.role')->orderBy('u.name')->limit(300)->get()
            :collect();
        return response()->view('erp.tenant-vault',compact(
            'company','workspace','actor','firmSlug','quota','objects','uploads','members'
        ))->header('Cache-Control','private, no-store, max-age=0');
    }

    public function start(Request $request,string $firmSlug)
    {
        [,,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        TenantVault::disk();
        $input=$request->validate([
            'title'=>['required','string','min:4','max:180'],
            'filename'=>['required','string','min:5','max:180',
                'regex:/^[^\\\\\/\x00-\x1F]+\\.(pdf|png|jpg|jpeg)$/i'],
            'expected_bytes'=>['required','integer','min:1','max:'.config('erp_vault.max_file_bytes')],
            'visibility'=>['required','in:partner,subject,team'],
            'subject_membership_id'=>['nullable','integer','min:1'],
        ]);
        $subject=$input['subject_membership_id']??null;
        if(($input['visibility']==='partner' && $subject)
            ||($input['visibility']!=='partner' && !$subject)){
            throw ValidationException::withMessages(['subject_membership_id'=>
                'A personal/team file needs a recipient; Partner-only files cannot select one.']);
        }
        if($subject) {
            $member=TenantOps::subject($actor,(int)$subject);
            abort_unless(in_array($member->role,['manager','student'],true)
                && !$member->platform_suspended_at,404);
        }
        $id=DB::transaction(function()use($actor,$input,$subject){
            $this->lockWorkspace($actor);
            if($subject)TenantOps::subject($actor,(int)$subject);
            $quota=TenantVault::quota($actor);
            $size=(int)$input['expected_bytes'];
            TenantVault::capacity($quota,$size);
            $parts=(int)ceil($size/(int)config('erp_vault.chunk_bytes'));
            $id=DB::table('erp_vault_uploads')->insertGetId([
                'company_id'=>$actor->company_id,'workspace_id'=>$actor->workspace_id,
                'created_by'=>$actor->id,'title'=>trim($input['title']),
                'filename'=>trim($input['filename']),
                'expected_bytes'=>$size,'expected_parts'=>$parts,
                'visibility'=>$input['visibility'],'subject_membership_id'=>$subject,
                'status'=>'pending',
                'expires_at'=>now()->addHours((int)config('erp_vault.session_hours')),
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            DB::table('erp_vault_quotas')->where('id',$quota->id)
                ->update(['reserved_bytes'=>$quota->reserved_bytes+$size,'updated_at'=>now()]);
            TenantOps::audit($actor,'vault.upload.reserved','vault_upload',$id,[
                'bytes'=>$size,'parts'=>$parts,'visibility'=>$input['visibility'],
            ]);
            return $id;
        },3);
        return response()->json([
            'upload_id'=>$id,
            'chunk_bytes'=>(int)config('erp_vault.chunk_bytes'),
            'parts'=>(int)ceil((int)$input['expected_bytes']/(int)config('erp_vault.chunk_bytes')),
        ],201)->header('Cache-Control','no-store');
    }

    public function part(Request $request,string $firmSlug,int $uploadId,int $partNumber)
    {
        [,,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $data=$request->validate([
            'chunk'=>['required','file',
                'max:'.(int)(config('erp_vault.chunk_bytes')/1024)],
        ]);
        $upload=$this->upload($actor,$uploadId);
        abort_unless($upload->status==='pending' && \Carbon\Carbon::parse($upload->expires_at)->isFuture(),409);
        abort_unless($partNumber>=1 && $partNumber<=(int)$upload->expected_parts,404);
        $size=$data['chunk']->getSize();
        $chunkSize=(int)config('erp_vault.chunk_bytes');
        $expected=$partNumber===(int)$upload->expected_parts
            ?(int)$upload->expected_bytes - ($partNumber-1)*$chunkSize
            :$chunkSize;
        if($size!==$expected){
            throw ValidationException::withMessages(['chunk'=>'Chunk length does not match this reserved upload.']);
        }
        $digest=hash_file('sha256',$data['chunk']->getRealPath());
        $dir='vault-staging/'.$actor->company_id.'/'.$actor->workspace_id.'/'.$uploadId;
        $candidate=$data['chunk']->storeAs($dir,'chunk-'.Str::random(30),'local');
        if(!$candidate)throw ValidationException::withMessages(['chunk'=>'Unable to write private staging part.']);
        try{
            $inserted=DB::transaction(function()use($actor,$uploadId,$partNumber,$size,$digest,$candidate){
                $this->lockWorkspace($actor);
                $upload=$this->upload($actor,$uploadId,true);
                abort_unless($upload->status==='pending' && $upload->expires_at>now(),409);
                $existing=DB::table('erp_vault_parts')->where('upload_id',$uploadId)
                    ->where('part_number',$partNumber)->lockForUpdate()->first();
                if($existing){
                    if($existing->sha256!==$digest || (int)$existing->size_bytes!==$size){
                        throw ValidationException::withMessages(['chunk'=>
                            'This chunk number is already committed with different content.']);
                    }
                    return false; // Idempotent retry, never overwrite the original part.
                }
                DB::table('erp_vault_parts')->insert([
                    'upload_id'=>$uploadId,'part_number'=>$partNumber,
                    'size_bytes'=>$size,'sha256'=>$digest,
                    'private_path'=>$candidate,'created_at'=>now(),
                ]);
                return true;
            },3);
        }catch(Throwable $e){
            Storage::disk('local')->delete($candidate);
            throw $e;
        }
        if(!$inserted)Storage::disk('local')->delete($candidate);
        return response()->json(['part_number'=>$partNumber,'accepted'=>true],$inserted?201:200)
            ->header('Cache-Control','no-store');
    }

    public function complete(Request $request,string $firmSlug,int $uploadId)
    {
        [,,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $disk=TenantVault::disk();
        $objectKey=null;
        $tmp=null;
        try {
            $id=DB::transaction(function()use($actor,$uploadId,$disk,&$objectKey,&$tmp){
                $this->lockWorkspace($actor);
                $upload=$this->upload($actor,$uploadId,true);
                if($upload->status==='complete'){
                    return (int)DB::table('erp_vault_objects')
                        ->where('company_id',$actor->company_id)
                        ->where('workspace_id',$actor->workspace_id)
                        ->where('upload_id',$uploadId)->value('id');
                }
                abort_unless($upload->status==='pending' && $upload->expires_at>now(),409);
                if($upload->subject_membership_id){
                    $subject=TenantOps::subject($actor,(int)$upload->subject_membership_id);
                    abort_unless(!$subject->platform_suspended_at,404);
                }
                $parts=DB::table('erp_vault_parts')->where('upload_id',$uploadId)
                    ->orderBy('part_number')->get();
                if($parts->count()!==(int)$upload->expected_parts){
                    throw ValidationException::withMessages(['chunk'=>'Every chunk must be uploaded before finalizing.']);
                }
                $tmp=tmpfile();
                if(!$tmp)throw ValidationException::withMessages(['document'=>'Unable to stage verified upload.']);
                $hash=hash_init('sha256');
                $bytes=0;
                foreach($parts as $index=>$part){
                    if((int)$part->part_number!==$index+1){
                        throw ValidationException::withMessages(['chunk'=>'Upload sequence is incomplete.']);
                    }
                    $stream=Storage::disk('local')->readStream($part->private_path);
                    if(!is_resource($stream))throw ValidationException::withMessages(['chunk'=>'A private chunk is missing.']);
                    try {
                        $partHash=hash_init('sha256');
                        $partBytes=0;
                        while(!feof($stream)){
                            $buf=fread($stream,65536);
                            if($buf===false)throw new \RuntimeException('Private chunk read failed.');
                            if($buf==='')continue;
                            $written=fwrite($tmp,$buf);
                            if($written===false || $written!==strlen($buf)){
                                throw new \RuntimeException('Private vault temporary stream write failed.');
                            }
                            $partBytes+=$written;$bytes+=$written;
                            hash_update($hash,$buf);hash_update($partHash,$buf);
                        }
                        if($partBytes!==(int)$part->size_bytes
                            || hash_final($partHash)!==$part->sha256){
                            throw ValidationException::withMessages(['chunk'=>'Uploaded chunk integrity mismatch.']);
                        }
                    } finally {fclose($stream);}
                }
                if($bytes!==(int)$upload->expected_bytes){
                    throw ValidationException::withMessages(['document'=>'Verified file size differs from reservation.']);
                }
                $sha=hash_final($hash);
                $extension=strtolower(pathinfo($upload->filename,PATHINFO_EXTENSION));
                $actual=(new \finfo(FILEINFO_MIME_TYPE))->file(stream_get_meta_data($tmp)['uri']);
                if(!isset(TenantVault::MIME[$extension]) || TenantVault::MIME[$extension]!==$actual){
                    throw ValidationException::withMessages(['document'=>'File contents do not match the allowed extension.']);
                }
                rewind($tmp);
                $objectKey='erp-vault/'.$actor->company_id.'/'.$actor->workspace_id.'/'.Str::uuid().'.'.$extension;
                if(!Storage::disk($disk)->put($objectKey,$tmp)){
                    throw ValidationException::withMessages(['document'=>'Private storage provider rejected the upload.']);
                }
                if(Storage::disk($disk)->size($objectKey)!==$bytes){
                    throw ValidationException::withMessages(['document'=>'Stored object size verification failed.']);
                }
                $id=DB::table('erp_vault_objects')->insertGetId([
                    'company_id'=>$actor->company_id,'workspace_id'=>$actor->workspace_id,
                    'upload_id'=>$uploadId,'uploaded_by'=>$actor->id,
                    'subject_membership_id'=>$upload->subject_membership_id,
                    'title'=>$upload->title,'original_filename'=>$upload->filename,
                    'visibility'=>$upload->visibility,'disk'=>$disk,'object_key'=>$objectKey,
                    'mime'=>$actual,'byte_size'=>$bytes,'sha256'=>$sha,
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
                $quota=TenantVault::quota($actor);
                if((int)$quota->reserved_bytes<$bytes){
                    throw ValidationException::withMessages(['document'=>'Reserved quota ledger is inconsistent.']);
                }
                DB::table('erp_vault_quotas')->where('id',$quota->id)->update([
                    'reserved_bytes'=>$quota->reserved_bytes-$bytes,
                    'used_bytes'=>$quota->used_bytes+$bytes,
                    'updated_at'=>now(),
                ]);
                DB::table('erp_vault_uploads')->where('id',$uploadId)->update([
                    'status'=>'complete','completed_at'=>now(),'updated_at'=>now(),
                ]);
                TenantOps::audit($actor,'vault.object.verified','vault_object',$id,[
                    'bytes'=>$bytes,'sha256'=>$sha,'disk'=>$disk,
                    'visibility'=>$upload->visibility,
                ]);
                if($upload->subject_membership_id){
                    TenantOps::notify($actor,(int)$upload->subject_membership_id,
                        'New private vault document','An authorized document is available in your workspace vault.');
                }
                return $id;
            },3);
        }catch(Throwable $e){
            if($objectKey){
                try{Storage::disk($disk)->delete($objectKey);}catch(Throwable){}
            }
            throw $e;
        }finally{
            if(is_resource($tmp))fclose($tmp);
        }

        // Keep part metadata for integrity audits; the staging byte copies are disposable.
        $paths=DB::table('erp_vault_parts')->where('upload_id',$uploadId)->pluck('private_path')->all();
        if($paths)Storage::disk('local')->delete($paths);
        return response()->json(['object_id'=>$id,'completed'=>true],200)->header('Cache-Control','no-store');
    }

    public function abortUpload(Request $request,string $firmSlug,int $uploadId)
    {
        [,,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $paths=DB::transaction(function()use($actor,$uploadId){
            $this->lockWorkspace($actor);
            $upload=$this->upload($actor,$uploadId,true);
            abort_unless($upload->status!=='complete',409);
            if($upload->status==='pending'){
                $quota=TenantVault::quota($actor);
                if((int)$quota->reserved_bytes<(int)$upload->expected_bytes)abort(409);
                DB::table('erp_vault_quotas')->where('id',$quota->id)->update([
                    'reserved_bytes'=>$quota->reserved_bytes-$upload->expected_bytes,'updated_at'=>now(),
                ]);
                DB::table('erp_vault_uploads')->where('id',$uploadId)
                    ->update(['status'=>'cancelled','updated_at'=>now()]);
                TenantOps::audit($actor,'vault.upload.cancelled','vault_upload',$uploadId);
            }
            return DB::table('erp_vault_parts')->where('upload_id',$uploadId)->pluck('private_path')->all();
        },3);
        if($paths)Storage::disk('local')->delete($paths);
        return response()->json(['cancelled'=>true],200)->header('Cache-Control','no-store');
    }

    public function download(Request $request,string $firmSlug,int $id)
    {
        [,,$actor]=TenantOps::context($request);
        $doc=$this->fileObject($actor,$id);
        abort_unless(in_array($doc->disk,['erp_vault','s3'],true),404);
        abort_unless(Storage::disk($doc->disk)->exists($doc->object_key),404);
        TenantOps::audit($actor,'vault.object.downloaded','vault_object',$id);
        return Storage::disk($doc->disk)->download($doc->object_key,
            basename($doc->original_filename),[
                'Content-Type'=>$doc->mime,'Cache-Control'=>'private, no-store, max-age=0',
                'X-Content-Type-Options'=>'nosniff',
                'Content-Security-Policy'=>"default-src 'none'; sandbox",
            ]);
    }

    public function archive(Request $request,string $firmSlug,int $id)
    {
        [,,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        DB::transaction(function()use($actor,$id){
            $this->lockWorkspace($actor);
            $doc=DB::table('erp_vault_objects')->where('id',$id)
                ->where('company_id',$actor->company_id)
                ->where('workspace_id',$actor->workspace_id)->lockForUpdate()->first();
            abort_unless($doc,404);
            if(!$doc->archived_at){
                DB::table('erp_vault_objects')->where('id',$id)->update([
                    'archived_at'=>now(),'archived_by'=>$actor->id,'updated_at'=>now(),
                ]);
                TenantOps::audit($actor,'vault.object.archived','vault_object',$id);
            }
        },3);
        return back()->with('success','Object archived; retained storage still counts toward quota.');
    }

    public function hold(Request $request,string $firmSlug,int $id)
    {
        [,,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $input=$request->validate([
            'legal_hold'=>['required','boolean'],
            'reason'=>['required','string','min:15','max:1000'],
        ]);
        DB::transaction(function()use($actor,$input,$id){
            $this->lockWorkspace($actor);
            $doc=DB::table('erp_vault_objects')->where('id',$id)
                ->where('company_id',$actor->company_id)
                ->where('workspace_id',$actor->workspace_id)->lockForUpdate()->first();
            abort_unless($doc,404);
            DB::table('erp_vault_objects')->where('id',$id)->update([
                'legal_hold'=>(bool)$input['legal_hold'],'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'vault.object.hold_changed','vault_object',$id,[
                'previous'=>(bool)$doc->legal_hold,'new'=>(bool)$input['legal_hold'],
                'reason'=>trim($input['reason']),
            ]);
        },3);
        return back()->with('success','Retention hold changed with a tenant audit event.');
    }
}
