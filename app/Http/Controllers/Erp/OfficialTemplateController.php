<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\OfficialTemplate;
use App\Support\TenantOps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OfficialTemplateController extends Controller
{
    public function index(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner','manager']);
        $templates=[];
        foreach(OfficialTemplate::KEYS as $key){
            $templates[$key]=OfficialTemplate::current($company,$workspace,$key);
        }
        $history=DB::table('workspace_document_templates')
            ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
            ->orderByDesc('id')->limit(30)->get();
        return response()->view('erp.official-templates',compact(
            'company','workspace','actor','firmSlug','templates','history'
        ))->header('Cache-Control','private, no-store, max-age=0');
    }

    public function save(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $input=$request->validate([
            'template_key'=>['required','in:leave_approval,employment_certificate,salary_certificate'],
            'document_title'=>['required','string','min:5','max:180'],
            'body_text'=>['required','string','min:30','max:2500'],
            'footer_note'=>['nullable','string','max:500'],
        ]);
        OfficialTemplate::validateText($input['body_text']);
        DB::transaction(function()use($company,$workspace,$actor,$input) {
            DB::table('workspaces')->where('company_id',$company->id)
                ->where('id',$workspace->id)->lockForUpdate()->first();
            $max=DB::table('workspace_document_templates')
                ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
                ->where('template_key',$input['template_key'])->max('version');
            $version=(int)$max+1;
            if($version>65000)throw ValidationException::withMessages([
                'template_key'=>'Template version limit reached.',
            ]);
            $id=DB::table('workspace_document_templates')->insertGetId([
                'company_id'=>$company->id,'workspace_id'=>$workspace->id,
                'template_key'=>$input['template_key'],'version'=>$version,
                'document_title'=>trim($input['document_title']),
                'body_text'=>trim($input['body_text']),
                'footer_note'=>trim($input['footer_note']??'') ?: null,
                'approved_by'=>$actor->id,'created_at'=>now(),'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'official_template.version_published','workspace_document_template',$id,[
                'template_key'=>$input['template_key'],'version'=>$version,
            ]);
        },3);
        return back()->with('success','Official template version published for future documents. Previously issued records are unchanged.');
    }
}
