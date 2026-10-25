<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OfficialTemplate
{
    public const KEYS=['leave_approval','employment_certificate','salary_certificate'];
    public const TOKENS=[
        'employee_name','company_name','workspace_name','designation',
        'joining_date','leave_from','leave_to','leave_type','leave_reason',
        'approval_date','approver_name','salary_amount','payroll_period',
        'purpose','reference','issue_date',
    ];

    public static function defaults(string $key):array
    {
        return match($key) {
            'employment_certificate'=>[
                'title'=>'Employment / Articleship Certificate',
                'body'=>'This is to certify that {{employee_name}} is recorded with {{company_name}}, {{workspace_name}} in the capacity of {{designation}}, with a joining date of {{joining_date}}. This certificate is issued for: {{purpose}}.',
                'footer'=>'Issued from approved staff records by an authorized Partner. Printed name is not a digital signature.',
            ],
            'salary_certificate'=>[
                'title'=>'Monthly Salary / Allowance Certificate',
                'body'=>'This is to certify that {{employee_name}} ({{designation}}) has a verified base salary / allowance of BDT {{salary_amount}} for {{payroll_period}}, based on a locked payroll record. This certificate is issued for: {{purpose}}.',
                'footer'=>'Reimbursements, other deductions and net disbursement are not represented as basic salary. This is not proof of bank transfer.',
            ],
            default=>[
                'title'=>'Approved Leave Application',
                'body'=>'{{employee_name}} has been granted approved {{leave_type}} leave from {{leave_from}} to {{leave_to}}. Application reason: {{leave_reason}}. Final Partner approval by {{approver_name}} on {{approval_date}}.',
                'footer'=>'Approved status comes only from the authenticated leave workflow. Printed signatory is not a cryptographic signature.',
            ],
        };
    }

    public static function current(object $company,object $workspace,string $key):array
    {
        abort_unless(in_array($key,self::KEYS,true),404);
        $row=DB::table('workspace_document_templates')
            ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
            ->where('template_key',$key)->orderByDesc('version')->first();
        if(!$row) return [...self::defaults($key),'version'=>0,'key'=>$key];
        return [
            'title'=>$row->document_title,'body'=>$row->body_text,
            'footer'=>$row->footer_note,'version'=>(int)$row->version,
            'key'=>$key,
        ];
    }

    public static function validateText(string $text):void
    {
        preg_match_all('/\{\{\s*([^{}]+)\s*\}\}/u',$text,$found);
        foreach($found[1] as $name){
            if(!in_array(trim($name),self::TOKENS,true)) {
                throw ValidationException::withMessages([
                    'body_text'=>'Unsupported template placeholder: '.trim($name),
                ]);
            }
        }
        if(str_contains($text,'<?php') || str_contains($text,'<script')){
            throw ValidationException::withMessages([
                'body_text'=>'Only plain-text letter templates are supported.',
            ]);
        }
    }

    public static function render(string $text,array $variables):string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/',
            fn($m)=>(string)($variables[$m[1]]??''),$text);
    }
}
