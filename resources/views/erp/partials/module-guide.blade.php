@php
$guide = match($module ?? '') {
    'attendance' => [
        'title'=>'Attendance without guesswork',
        'steps'=>[
            'Start your office or client session using the confirmed server-recorded check-in. Check out when work finishes.',
            'If you forget a punch or need client-duty approval, open Attendance & Client Duty Requests. Requests are reviewed, not silently applied.',
            'Grace, office hours, half-day warnings and holiday calendars come from approved Partner policy; no automatic salary penalty is applied.',
        ],
    ],
    'leave'=>[
        'title'=>'Leave from request to decision',
        'steps'=>[
            'Select your leave type, dates, day portion and reason. Review your available entitlement before submitting.',
            'Managers review assigned student requests. Partners authorize final decisions; pending requests may be withdrawn.',
            'Approved leave can affect your work calendar. Future cancellation and reviewed payroll periods follow approval safeguards.',
        ],
    ],
    'claims'=>[
        'title'=>'Conveyance with a clear audit trail',
        'steps'=>[
            'Describe the eligible trip or expense, select its category, add the amount and upload the required receipt.',
            'Your Manager may review the claim before the Partner authorizes the final approved reimbursement amount.',
            'A claim becomes paid only through the audited payroll process; submitting a claim does not transfer money.',
        ],
    ],
    'payroll'=>[
        'title'=>'Five-step, controlled payroll',
        'steps'=>[
            'Partners prepare the monthly draft from employee terms and eligible approved claims. Review attendance and leave before proceeding.',
            'The audited workflow is Draft → Review → Approve → Record Paid → Lock. Mark Paid only after verifying actual disbursement evidence.',
            'Locked periods and payslips are historical records. Salary deductions are not inferred from calendar color or unconfirmed absence.',
        ],
    ],
    'notifications'=>[
        'title'=>'Your private ERP inbox',
        'steps'=>[
            'Notices, application decisions and workflow updates appear within your authorized Partner workspace.',
            'Open relevant updates, mark them read and follow the linked module to take action.',
            'Notifications do not mean the underlying request has been approved unless the workflow status confirms it.',
        ],
    ],
    'calendar'=>[
        'title'=>'A calendar you can trust',
        'steps'=>[
            'Explore previous or upcoming months and click a date for approved holiday, attendance and leave details.',
            'Missing punch means review required, not automatic absence. Approved shifts and specific day exceptions take priority.',
            'Partners approve annual holiday imports; the CSV export contains only information permitted for your role.',
        ],
    ],
    'policies'=>[
        'title'=>'Policy control with effective dates',
        'steps'=>[
            'Create future-dated workplace rules. Office start, end, grace and holidays are configurable per authorized workspace.',
            'Managers may request changes; direct manager permissions are controlled by Partners and sensitive rules always require approval.',
            'Prior attendance and locked payroll are not recalculated merely because a new policy was approved.',
        ],
    ],
    default => null,
};
@endphp
@if($guide)
<details class="erp-module-guide">
    <summary><span class="erp-module-guide__icon"><i data-lucide="circle-help" aria-hidden="true"></i></span><span><strong>{{ $guide['title'] }}</strong><small>Quick guide · what to do next · approval safeguards</small></span><i data-lucide="chevron-down" class="erp-module-guide__arrow" aria-hidden="true"></i></summary>
    <ol>
        @foreach($guide['steps'] as $step)
            <li><span>{{ $loop->iteration }}</span><p>{{ $step }}</p></li>
        @endforeach
    </ol>
</details>
@endif
