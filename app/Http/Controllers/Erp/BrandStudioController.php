<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\BrandIdentity;
use App\Support\TenantOps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class BrandStudioController extends Controller
{
    public function index(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner','manager']);
        $profile=BrandIdentity::profile($company,$workspace);
        $assets=DB::table('workspace_brand_assets')
            ->where('company_id',$company->id)
            ->where('workspace_id',$workspace->id)
            ->orderByDesc('id')->limit(35)->get();
        return response()->view('erp.brand-studio',compact(
            'company','workspace','actor','firmSlug','profile','assets'
        ))->header('Cache-Control','private, no-store, max-age=0');
    }

    public function save(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);

        $fields=$request->validate([
            'public_name'=>['required','string','min:3','max:160'],
            'office_address'=>['nullable','string','max:400'],
            'email'=>['nullable','email','max:180'],
            'phone'=>['nullable','string','max:60'],
            'website'=>['nullable','url','max:200','regex:/^https:\/\//i'],
            'accent_color'=>['required','regex:/^#[0-9a-fA-F]{6}$/'],
            'sidebar_color'=>['required','regex:/^#[0-9a-fA-F]{6}$/'],
            'signatory_name'=>['nullable','string','max:160'],
            'signatory_title'=>['nullable','string','max:160'],
        ]);

        if(BrandIdentity::contrast($fields['sidebar_color'],'#FFFFFF')<7) {
            throw ValidationException::withMessages([
                'sidebar_color'=>'Sidebar must have strong contrast against white navigation text.',
            ]);
        }
        $fields['accent_color']=strtoupper($fields['accent_color']);
        $fields['sidebar_color']=strtoupper($fields['sidebar_color']);
        foreach(['office_address','email','phone','website','signatory_name','signatory_title'] as $name) {
            $fields[$name]=trim($fields[$name]??'') ?: null;
        }
        $fields['public_name']=trim($fields['public_name']);
        DB::transaction(function()use($company,$workspace,$actor,$fields) {
            $row=DB::table('workspaces')->where('company_id',$company->id)
                ->where('id',$workspace->id)->lockForUpdate()->first();
            abort_unless($row,404);
            $old=DB::table('workspace_brand_profiles')->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)->first();
            DB::table('workspace_brand_profiles')->updateOrInsert([
                'company_id'=>$company->id,'workspace_id'=>$workspace->id,
            ],[
                ...$fields,'updated_by'=>$actor->id,
                'created_at'=>$old?->created_at ?? now(),'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'brand.profile.updated','workspace',$workspace->id,[
                'changed_fields'=>array_keys($fields),
                'old_accent'=>$old?->accent_color,
                'new_accent'=>$fields['accent_color'],
            ]);
        },3);
        return back()->with('success','Workspace brand identity saved. Firm legal name and company-wide governance were not altered.');
    }

    public function upload(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $input=$request->validate([
            'kind'=>['required','in:logo,letterhead,seal'],
            'file'=>['required','file','max:4096'],
        ]);
        $file=$request->file('file');
        // Real MIME verification, not just the client-provided filename.
        $mime=$file->getMimeType();
        $allowed=$input['kind']==='letterhead'
            ? ['application/pdf','image/png','image/jpeg',
               'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
            : ['image/png','image/jpeg'];
        if(!in_array($mime,$allowed,true)){
            throw ValidationException::withMessages(['file'=>'Unsupported file type for this brand asset. Use PNG/JPEG for logo/seal or PDF/DOCX/image for letterhead.']);
        }
        $original=basename($file->getClientOriginalName());
        // Active content is never served as HTML or SVG.
        $hash=hash_file('sha256',$file->getRealPath());
        $path=$file->store('workspace-branding/'.$company->id.'/'.$workspace->id,'local');
        try {
            DB::transaction(function()use($company,$workspace,$actor,$input,$file,$original,$hash,$path,$mime) {
                DB::table('workspaces')->where('company_id',$company->id)
                    ->where('id',$workspace->id)->lockForUpdate()->first();
                $id=DB::table('workspace_brand_assets')->insertGetId([
                    'company_id'=>$company->id,'workspace_id'=>$workspace->id,
                    'uploaded_by'=>$actor->id,'kind'=>$input['kind'],
                    'storage_path'=>$path,'original_name'=>substr($original,0,180),
                    'mime'=>$mime,'sha256'=>$hash,'byte_size'=>$file->getSize(),
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
                $old=DB::table('workspace_brand_profiles')
                    ->where('company_id',$company->id)->where('workspace_id',$workspace->id)->first();
                DB::table('workspace_brand_profiles')->updateOrInsert([
                    'company_id'=>$company->id,'workspace_id'=>$workspace->id,
                ],[
                    $input['kind'].'_asset_id'=>$id,'updated_by'=>$actor->id,
                    'created_at'=>$old?->created_at ?? now(),'updated_at'=>now(),
                ]);
                TenantOps::audit($actor,'brand.asset.uploaded','workspace_brand_asset',$id,[
                    'kind'=>$input['kind'],'sha256'=>$hash,
                ]);
            },3);
        } catch(\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
        return back()->with('success',ucfirst($input['kind']).' stored privately and activated for this workspace.');
    }

    public function asset(Request $request,string $firmSlug,int $id)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        $asset=BrandIdentity::asset($actor,$id);
        abort_unless($asset,404);
        $profile=BrandIdentity::profile($company,$workspace);
        $currentId=$profile->{$asset->kind.'_asset_id'} ?? null;
        // Ordinary users can view only the active corporate logo; raw
        // letterhead, seal and historical evidence remain Partner-only.
        if($actor->role!=='partner'){
            abort_unless($asset->kind==='logo' && (int)$currentId === (int)$asset->id,404);
        }
        $disk=Storage::disk('local');
        abort_unless($disk->exists($asset->storage_path),404);
        TenantOps::audit($actor,'brand.asset.downloaded','workspace_brand_asset',$id);
        return response()->streamDownload(function()use($disk,$asset){
            $stream=$disk->readStream($asset->storage_path);
            if($stream){fpassthru($stream);fclose($stream);}
        },$asset->original_name,[
            'Content-Type'=>$asset->mime,
            'Cache-Control'=>'private, no-store, max-age=0',
            'X-Content-Type-Options'=>'nosniff',
            'Content-Security-Policy'=>"default-src 'none'; sandbox",
        ],'inline');
    }
}
