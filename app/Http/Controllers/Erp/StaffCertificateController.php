<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\BrandIdentity;
use App\Support\OfficialTemplate;
use App\Support\PortableDocx;
use App\Support\TenantOps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StaffCertificateController extends Controller
{
    public function index(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner','student']);
        $rows=DB::table('official_document_issues as i')
            ->join('memberships as m','m.id','=','i.source_id')
            ->join('users as u','u.id','=','m.user_id')
            ->where('i.company_id',$company->id)
            ->where('i.workspace_id',$workspace->id)
            ->where('m.company_id',$company->id)->where('m.workspace_id',$workspace->id)
            ->where('m.role','student')
            ->where(function($q){$q->where('i.document_type','like','employment_%')
                ->orWhere('i.document_type','like','salary_%');})
            ->when($actor->role==='student',fn($q)=>$q->where('i.source_id',$actor->id))
            ->select('i.*','u.name as employee_name')->orderByDesc('i.id')->limit(100)->get();
        return response()->view('erp.staff-certificates',compact(
            'company','workspace','actor','firmSlug','rows'
        ))->header('Cache-Control','private, no-store, max-age=0');
    }

    public function issue(Request $request,string $firmSlug,int $memberId)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $input=$request->validate([
            'kind'=>['required','in:employment,salary'],
            'period'=>['nullable','date_format:Y-m'],
            'purpose'=>['required','string','min:10','max:500'],
        ]);
        $month=($input['period'] ?? null) ?: now($company->timezone)->format('Y-m');
        $docType=$input['kind'].'_'.str_replace('-','',$month);
        DB::transaction(function()use($company,$workspace,$actor,$memberId,$input,$month,$docType){
            DB::table('workspaces')->where('company_id',$company->id)
                ->where('id',$workspace->id)->lockForUpdate()->first();
            $subject=TenantOps::subject($actor,$memberId);
            abort_unless($subject->role==='student',404);
            $staff=DB::table('staff_profiles')->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)->where('membership_id',$memberId)->first();
            if(!$staff || !$staff->designation || !$staff->joined_on) {
                throw ValidationException::withMessages([
                    'kind'=>'Save the employee joining date and designation before issuing an official certificate.',
                ]);
            }
            if($input['kind']==='employment' && ($staff->employment_status!=='active' || !$subject->active)) {
                throw ValidationException::withMessages([
                    'kind'=>'Active employment cannot be certified for an exited staff member.',
                ]);
            }
            $existing=DB::table('official_document_issues')
                ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
                ->where('document_type',$docType)->where('source_id',$memberId)->first();
            if($existing)throw ValidationException::withMessages([
                'kind'=>'This month already has an issued certificate of this type. Retrieve the immutable copy from Document Center.',
            ]);
            $memberName=DB::table('users as u')->join('memberships as m','m.user_id','=','u.id')
                ->where('m.id',$memberId)->where('m.company_id',$company->id)
                ->where('m.workspace_id',$workspace->id)->value('u.name');
            $salaryAmount=null;$payrollRun=null;
            if($input['kind']==='salary'){
                $payroll=DB::table('payroll_runs as r')
                    ->join('payroll_lines as l','l.payroll_run_id','=','r.id')
                    ->where('r.company_id',$company->id)->where('r.workspace_id',$workspace->id)
                    ->where('r.period_month',$month.'-01')->where('r.status','locked')
                    ->where('l.membership_id',$memberId)
                    ->select('r.id as run_id','l.base_allowance')->first();
                if(!$payroll) throw ValidationException::withMessages([
                    'period'=>'Salary certificate requires a finalized, locked payroll period for this student.',
                ]);
                $salaryAmount=number_format((float)$payroll->base_allowance,2,'.',',');
                $payrollRun=$payroll->run_id;
            }
            $brand=BrandIdentity::profile($company,$workspace);
            $templateKey=$input['kind'].'_certificate';
            $template=OfficialTemplate::current($company,$workspace,$templateKey);
            $ref=strtoupper($input['kind']==='salary'?'SAL':'EMP').'-'.$company->id.'-'.$workspace->id.'-'.$memberId.'-'.str_replace('-','',$month);
            $vars=[
                'employee_name'=>$memberName,
                'company_name'=>$brand->public_name ?: $company->name,
                'workspace_name'=>$workspace->name,
                'designation'=>$staff->designation,
                'joining_date'=>$staff->joined_on,
                'salary_amount'=>$salaryAmount??'',
                'payroll_period'=>$month,
                'purpose'=>trim($input['purpose']),
                'reference'=>$ref,
                'issue_date'=>now($company->timezone)->toDateString(),
            ];
            $snapshot=[
                'registered_company_name'=>$company->name,
                'company_name'=>$vars['company_name'],
                'address'=>$brand->office_address,'email'=>$brand->email,'phone'=>$brand->phone,
                'website'=>$brand->website,'accent_color'=>$brand->accent_color,
                'logo_asset_id'=>$brand->logo_asset_id,
                'letterhead_asset_id'=>$brand->letterhead_asset_id,
                'signatory_name'=>$brand->signatory_name,
                'signatory_title'=>$brand->signatory_title,
                'employee_name'=>$memberName,
                'designation'=>$staff->designation,
                'joining_date'=>$staff->joined_on,
                'purpose'=>trim($input['purpose']),
                'payroll_period'=>$input['kind']==='salary'?$month:null,
                'payroll_run_id'=>$payrollRun,
                'salary_base'=>$salaryAmount,
                'template_version'=>$template['version'],
                'document_title'=>$template['title'],
                'body_text'=>OfficialTemplate::render($template['body'],$vars),
                'footer_note'=>OfficialTemplate::render($template['footer']??'',$vars),
                'approver_name'=>auth()->user()->name,
                'approved_at'=>now($company->timezone)->toDateTimeString(),
            ];
            $id=DB::table('official_document_issues')->insertGetId([
                'company_id'=>$company->id,'workspace_id'=>$workspace->id,
                'document_type'=>$docType,'source_id'=>$memberId,
                'reference'=>$ref,'issued_by'=>$actor->id,
                'approved_snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR),
                'issued_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'official_document.certificate_issued','official_document_issue',$id,[
                'type'=>$input['kind'],'member_id'=>$memberId,
                'template_version'=>$template['version'],'payroll_run'=>$payrollRun,
            ]);
            TenantOps::notify($actor,$memberId,'Official certificate ready',
                'A verified '.$input['kind'].' certificate is ready for private download.');
        },3);
        return redirect()->route('erp.staff_certificates.index',['firmSlug'=>$firmSlug])
            ->with('success','Official certificate issued. Its approved wording and brand snapshot are locked.');
    }

    private function authorized(Request $request,int $id):array
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner','student']);
        $issue=DB::table('official_document_issues')
            ->where('id',$id)->where('company_id',$company->id)
            ->where('workspace_id',$workspace->id)->first();
        abort_unless($issue && (
            str_starts_with($issue->document_type,'employment_')
            || str_starts_with($issue->document_type,'salary_')
        ),404);
        if($actor->role==='student')abort_unless((int)$issue->source_id===(int)$actor->id,404);
        return [$company,$workspace,$actor,$issue,
            json_decode($issue->approved_snapshot,true,512,JSON_THROW_ON_ERROR)];
    }

    public function print(Request $request,string $firmSlug,int $id)
    {
        [$company,$workspace,$actor,$issue,$snapshot]=$this->authorized($request,$id);
        $logo=$snapshot['logo_asset_id']
            ? BrandIdentity::inlineImage(BrandIdentity::asset($actor,(int)$snapshot['logo_asset_id'])):null;
        $letterhead=$snapshot['letterhead_asset_id']
            ? BrandIdentity::inlineImage(BrandIdentity::asset($actor,(int)$snapshot['letterhead_asset_id'])):null;
        TenantOps::audit($actor,'official_document.certificate_printed','official_document_issue',$issue->id);
        return response()->view('erp.official-certificate-print',compact(
            'company','workspace','actor','firmSlug','issue','snapshot','logo','letterhead'
        ))->header('Cache-Control','private, no-store, max-age=0');
    }

    public function word(Request $request,string $firmSlug,int $id)
    {
        [$company,$workspace,$actor,$issue,$snapshot]=$this->authorized($request,$id);
        $lines=[
            ['text'=>$snapshot['company_name'],'bold'=>true],
            ['text'=>$snapshot['address']?:$snapshot['registered_company_name']],
            ['text'=>$snapshot['document_title'],'bold'=>true],
            ['text'=>'Reference: '.$issue->reference],
            ['text'=>'Date: '.$issue->issued_at],
            ['text'=>$snapshot['body_text']],
            ['text'=>'Authorized by Partner: '.$snapshot['approver_name']],
            ['text'=>'Printed signatory: '.($snapshot['signatory_name']?:$snapshot['approver_name'])
                .' / '.($snapshot['signatory_title']?:'Partner')],
            ['text'=>$snapshot['footer_note']?:'Official HR record; no cryptographic signature.'],
        ];
        $bytes=PortableDocx::make($lines, BrandIdentity::officeImages($actor,$snapshot));
        TenantOps::audit($actor,'official_document.certificate_word_downloaded','official_document_issue',$issue->id);
        return response($bytes,200,[
            'Content-Type'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition'=>'attachment; filename="'.$issue->reference.'.docx"',
            'Cache-Control'=>'private, no-store, max-age=0',
            'X-Content-Type-Options'=>'nosniff',
        ]);
    }
}
