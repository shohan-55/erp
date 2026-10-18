<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\BrandIdentity;
use App\Support\PortableDocx;
use App\Support\OfficialTemplate;
use App\Support\TenantOps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class OfficialDocumentController extends Controller
{
    private function leaveSource(object $company,object $workspace,object $actor,int $id):object
    {
        $leave=DB::table('leave_requests as r')
            ->join('memberships as m','m.id','=','r.membership_id')
            ->join('users as u','u.id','=','m.user_id')
            ->where('r.id',$id)
            ->where('r.company_id',$company->id)->where('r.workspace_id',$workspace->id)
            ->where('m.company_id',$company->id)->where('m.workspace_id',$workspace->id)
            ->select('r.*','u.name as member_name','m.manager_membership_id')
            ->first();
        abort_unless($leave,404);
        abort_unless($actor->role==='partner' || (int)$actor->id === (int)$leave->membership_id
            || ($actor->role==='manager' && (int)$actor->id === (int)$leave->manager_membership_id),404);
        abort_unless($leave->status==='approved',404,'An approved application is required.');
        return $leave;
    }

    private function issued(object $company,object $workspace,object $actor,int $id):array
    {
        $leave=$this->leaveSource($company,$workspace,$actor,$id);
        $row=DB::transaction(function()use($company,$workspace,$actor,$id,$leave) {
            DB::table('workspaces')->where('company_id',$company->id)
                ->where('id',$workspace->id)->lockForUpdate()->first();
            $existing=DB::table('official_document_issues')
                ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
                ->where('document_type','leave_approval')->where('source_id',$id)
                ->lockForUpdate()->first();
            if($existing) return $existing;
            // Recheck the authoritative status inside the transaction.
            $source=DB::table('leave_requests')->where('id',$id)
                ->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)->lockForUpdate()->first();
            abort_unless($source && $source->status==='approved',404);
            $profile=BrandIdentity::profile($company,$workspace);
            $partner=DB::table('memberships as m')->join('users as u','u.id','=','m.user_id')
                ->where('m.id',$source->reviewed_by)
                ->where('m.company_id',$company->id)
                ->where('m.workspace_id',$workspace->id)
                ->where('m.role','partner')->value('u.name');
            abort_unless($partner,409,'Final Partner authorization must be recorded.');
            $manager=DB::table('tenant_audit_events as e')
                ->join('memberships as m','m.id','=','e.actor_membership_id')
                ->join('users as u','u.id','=','m.user_id')
                ->where('e.company_id',$company->id)->where('e.workspace_id',$workspace->id)
                ->where('e.entity_type','leave_request')->where('e.entity_id',$source->id)
                ->where('e.action','leave.manager_reviewed')
                ->where('m.role','manager')
                ->orderByDesc('e.id')->value('u.name');
            $template=OfficialTemplate::current($company,$workspace,'leave_approval');
            $variables=[
                'employee_name'=>$leave->member_name,
                'company_name'=>$profile->public_name ?: $company->name,
                'workspace_name'=>$workspace->name,
                'leave_from'=>$source->date_from,'leave_to'=>$source->date_to,
                'leave_type'=>$source->leave_type,'leave_reason'=>$source->reason,
                'approval_date'=>$source->reviewed_at,'approver_name'=>$partner,
                'reference'=>sprintf('LEAVE-%d-%d-%06d',$company->id,$workspace->id,$source->id),
                'issue_date'=>now($company->timezone)->toDateString(),
            ];
            $snapshot=[
                'company_name'=>$company->name,
                'display_name'=>$profile->public_name ?: ($company->brand_name ?: $company->name),
                'office_address'=>$profile->office_address,
                'email'=>$profile->email,'phone'=>$profile->phone,'website'=>$profile->website,
                'accent_color'=>$profile->accent_color,
                'logo_asset_id'=>$profile->logo_asset_id,
                'letterhead_asset_id'=>$profile->letterhead_asset_id,
                'signatory_name'=>$profile->signatory_name,
                'signatory_title'=>$profile->signatory_title,
                'employee_name'=>$leave->member_name,
                'leave_type'=>$source->leave_type,
                'day_portion'=>$source->day_portion,
                'date_from'=>$source->date_from,'date_to'=>$source->date_to,
                'reason'=>$source->reason,'charge_days'=>$source->charge_days,
                'manager_reviewer'=>$manager,'partner_approver'=>$partner,
                'approved_at'=>$source->reviewed_at,
                'decision_note'=>$source->decision_note,
                'template_title'=>$template['title'],
                'template_body'=>OfficialTemplate::render($template['body'],$variables),
                'template_footer'=>OfficialTemplate::render($template['footer']??'',$variables),
                'template_version'=>$template['version'],
            ];
            $ref=sprintf('LEAVE-%d-%d-%06d',$company->id,$workspace->id,$source->id);
            $issueId=DB::table('official_document_issues')->insertGetId([
                'company_id'=>$company->id,'workspace_id'=>$workspace->id,
                'document_type'=>'leave_approval','source_id'=>$id,
                'reference'=>$ref,'issued_by'=>$source->reviewed_by,
                'approved_snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR),
                'issued_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'official_document.issued','official_document_issue',$issueId,[
                'type'=>'leave_approval','source_id'=>$id,'reference'=>$ref,
            ]);
            return DB::table('official_document_issues')->find($issueId);
        },3);
        $snapshot=json_decode($row->approved_snapshot,true,512,JSON_THROW_ON_ERROR);
        return [$row,$snapshot];
    }

    private function lines(object $issue,array $snapshot):array
    {
        $text=[
            ['text'=>$snapshot['display_name'], 'bold'=>true],
            ['text'=>$snapshot['office_address'] ?: $snapshot['company_name']],
            ['text'=>trim(implode(' · ',array_filter([$snapshot['phone'],$snapshot['email'],$snapshot['website']])))],
            ['text'=>'OFFICIAL APPROVED LEAVE APPLICATION', 'bold'=>true],
            ['text'=>'Reference: '.$issue->reference],
            ['text'=>'Issued on: '.$issue->issued_at],
            ['text'=>'Employee: '.$snapshot['employee_name']],
            ['text'=>'Leave: '.ucfirst($snapshot['leave_type']).' · '.ucfirst($snapshot['day_portion'])],
            ['text'=>'Period: '.$snapshot['date_from'].' through '.$snapshot['date_to']],
            ['text'=>'Charged working days: '.$snapshot['charge_days']],
            ['text'=>'Application / reason: '.$snapshot['reason']],
            ['text'=>$snapshot['template_body']??''],
            ['text'=>'Manager review: '.($snapshot['manager_reviewer'] ?: 'Not separately recorded')],
            ['text'=>'Final approval by Partner: '.$snapshot['partner_approver']],
            ['text'=>'Approved at: '.$snapshot['approved_at']],
        ];
        if($snapshot['decision_note']) $text[]=['text'=>'Decision note: '.$snapshot['decision_note']];
        $text[]=['text'=>'Authorized signatory designation (printed): '
            .($snapshot['signatory_name'] ?: $snapshot['partner_approver'])
            .' · '.($snapshot['signatory_title'] ?: 'Partner')];
        $text[]=['text'=>'Generated from an approved ERP record. Printed name does not represent a cryptographic signature.'];
        $text[]=['text'=>'The original authorized application is retained with its immutable reference.'];
        return $text;
    }

    public function print(Request $request,string $firmSlug,int $id)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        [$issue,$snapshot]=$this->issued($company,$workspace,$actor,$id);
        $brandLogo=$snapshot['logo_asset_id']
            ? BrandIdentity::inlineImage(BrandIdentity::asset($actor,(int)$snapshot['logo_asset_id'])) : null;
        $letterhead= $snapshot['letterhead_asset_id']
            ? BrandIdentity::inlineImage(BrandIdentity::asset($actor,(int)$snapshot['letterhead_asset_id'])) : null;

        TenantOps::audit($actor,'official_document.print_viewed','official_document_issue',$issue->id);
        return response()->view('erp.official-leave-print',compact(
            'company','workspace','actor','firmSlug','issue','snapshot','brandLogo','letterhead'
        ))->header('Cache-Control','private, no-store, max-age=0')
          ->header('X-Content-Type-Options','nosniff');
    }

    public function word(Request $request,string $firmSlug,int $id)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        [$issue,$snapshot]=$this->issued($company,$workspace,$actor,$id);
        $docx=PortableDocx::make($this->lines($issue,$snapshot));
        TenantOps::audit($actor,'official_document.word_downloaded','official_document_issue',$issue->id);
        return response($docx,200,[
            'Content-Type'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition'=>'attachment; filename="'.$issue->reference.'.docx"',
            'Content-Length'=>strlen($docx),
            'Cache-Control'=>'private, no-store, max-age=0',
            'X-Content-Type-Options'=>'nosniff',
        ]);
    }

    public function register(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $rows=DB::table('official_document_issues')
            ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
            ->orderByDesc('id')->limit(2000)
            ->get(['reference','document_type','source_id','issued_at','issued_by']);
        TenantOps::audit($actor,'official_document.register_exported','workspace',$workspace->id,[
            'count'=>$rows->count(),
        ]);
        $safe=static function($v) {
            $value=(string)$v;
            return preg_match('/^[\s\x00-\x1f]*[=+\-@]/u',$value) ? "'".$value : $value;
        };
        return response()->streamDownload(function()use($rows,$safe){
            $out=fopen('php://output','w');
            fputcsv($out,['Reference','Document Type','Source ID','Issued At','Approver Membership']);
            foreach($rows as $r) fputcsv($out,array_map($safe,[
                $r->reference,$r->document_type,$r->source_id,$r->issued_at,$r->issued_by,
            ]));
            fclose($out);
        },'official-document-register.csv',[
            'Content-Type'=>'text/csv; charset=utf-8',
            'Cache-Control'=>'private, no-store, max-age=0',
            'X-Content-Type-Options'=>'nosniff',
        ]);
    }
}
