<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Approved Leave · {{ $issue->reference }}</title>
<style>
@page{size:A4;margin:16mm}
*{box-sizing:border-box}html{background:#e8edf2}body{font-family:Arial,"Noto Sans",sans-serif;color:#17191f;font-size:13px;line-height:1.65;margin:0}
.toolbar{position:sticky;top:0;z-index:3;background:#17191f;color:#fff;padding:13px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.toolbar span{font-size:12px;font-weight:bold}.toolbar button,.toolbar a{padding:9px 15px;border-radius:9px;border:1px solid #c9b35b;text-decoration:none;cursor:pointer;font-weight:800;font-size:12px;background:#fcdf02;color:#17191f}
.paper{width:min(210mm,100%);min-height:267mm;margin:25px auto;background:white;padding:18mm;box-shadow:0 20px 45px rgba(15,23,42,.15)}
.letterhead{width:100%;max-height:43mm;object-fit:contain;object-position:left center;margin-bottom:18px}
.brand{display:flex;align-items:center;gap:17px;padding-bottom:18px;border-bottom:3px solid var(--letter-accent,#e8c82c)}
.brand img{height:63px;width:63px;object-fit:contain}
.company{margin:0;font-size:23px;line-height:1.2;font-weight:900}.company-detail{color:#5b6574;font-size:11px;margin:5px 0 0}
.doc-heading{display:flex;justify-content:space-between;gap:12px;align-items:start;margin-top:35px}
.doc-heading h2{font-size:17px;margin:0}.ref{color:#64748b;font-size:10px;text-align:right;line-height:1.6}
.meta{margin-top:28px;display:grid;grid-template-columns:1fr 1fr;gap:13px}
.field{border-bottom:1px solid #e2e8f0;padding-bottom:9px}.field dt{font-size:10px;letter-spacing:.04em;color:#64748b;font-weight:800;text-transform:uppercase}.field dd{margin:3px 0 0;font-size:13px;font-weight:700;overflow-wrap:anywhere}
.application{margin-top:25px;border:1px solid #e2e8f0;border-radius:10px;padding:17px}.application p{white-space:pre-wrap;overflow-wrap:anywhere}
.approval{margin-top:26px;background:#f8fafc;border-left:4px solid #16a34a;padding:15px}.approval strong{color:#14532d}
.signatures{margin-top:39px;display:grid;grid-template-columns:1fr 1fr;gap:35px;font-size:11px}.signatures .rule{border-top:1px solid #94a3b8;padding-top:9px;margin-top:22px}
.footer{border-top:1px solid #cbd5e1;margin-top:30px;padding-top:10px;font-size:10px;color:#64748b;text-align:center}
@media(max-width:600px){.paper{margin:0;padding:22px;min-height:0;box-shadow:none}.doc-heading{flex-wrap:wrap}.meta{grid-template-columns:1fr}.signatures{gap:10px}.company{font-size:20px}}
@media print{html,body{background:#fff!important}.toolbar{display:none!important}.paper{margin:0;width:auto;min-height:auto;padding:0;box-shadow:none}.application,.approval{break-inside:avoid}}
</style>
</head>
<body style="--letter-accent:{{ $snapshot['accent_color'] }}">
<nav class="toolbar" aria-label="Official document actions">
<span>Official Document · {{ $issue->reference }} · Approved Snapshot</span>
<div>
<button type="button" onclick="window.print()">Print / Save as PDF</button>
<a href="{{ route('erp.official.leave.word',['firmSlug'=>$firmSlug,'id'=>$issue->source_id]) }}">Download Word (.docx)</a>
<a href="{{ route('erp.leaves',['firmSlug'=>$firmSlug]) }}">Back</a>
</div>
</nav>
<main class="paper">
@if($letterhead)<img class="letterhead" src="{{ $letterhead }}" alt="Approved workspace letterhead image">@endif
<header class="brand">
@if($brandLogo)<img src="{{ $brandLogo }}" alt="Company logo">@endif
<div>
<h1 class="company">{{ $snapshot['display_name'] }}</h1>
<p class="company-detail">{{ $snapshot['company_name'] !== $snapshot['display_name'] ? $snapshot['company_name'].' · ' : '' }}{{ $snapshot['office_address'] }}</p>
<p class="company-detail">{{ implode(' · ',array_filter([$snapshot['phone'],$snapshot['email'],$snapshot['website']])) }}</p>
</div>
</header>
<div class="doc-heading">
<h2>{{ $snapshot['template_title'] ?? 'Approved Leave Application' }}</h2>
<div class="ref"><strong>{{ $issue->reference }}</strong><div>Issue date: {{ \Carbon\CarbonImmutable::parse($issue->issued_at)->setTimezone($company->timezone)->format('d M Y') }}</div></div>
</div>
<dl class="meta">
<div class="field"><dt>Student / Employee</dt><dd>{{ $snapshot['employee_name'] }}</dd></div>
<div class="field"><dt>Leave Type</dt><dd>{{ ucfirst($snapshot['leave_type']) }}</dd></div>
<div class="field"><dt>Leave Period</dt><dd>{{ $snapshot['date_from'] }} to {{ $snapshot['date_to'] }}</dd></div>
<div class="field"><dt>Portion</dt><dd>{{ ucfirst($snapshot['day_portion']) }}</dd></div>
<div class="field"><dt>Approved Working Days</dt><dd>{{ $snapshot['charge_days'] }}</dd></div>
<div class="field"><dt>Final Approval</dt><dd>{{ \Carbon\CarbonImmutable::parse($snapshot['approved_at'])->setTimezone($company->timezone)->format('d M Y, h:i A') }}</dd></div>
</dl>
@if(!empty($snapshot['template_body']))
<section class="application">
  <strong>Approved Official Letter Wording</strong>
  <p>{{ $snapshot['template_body'] }}</p>
</section>
@endif
<section class="application">
<strong>Application / Leave Reason</strong>
<p>{{ $snapshot['reason'] }}</p>
</section>
<section class="approval">
<strong>FINAL STATUS: APPROVED BY PARTNER</strong>
<p>Manager Review: {{ $snapshot['manager_reviewer'] ?: 'Not separately recorded' }}</p>
<p>Approved by: <strong>{{ $snapshot['partner_approver'] }}</strong></p>
@if($snapshot['decision_note'])<p>Decision Note: {{ $snapshot['decision_note'] }}</p>@endif
</section>
<section class="signatures">
<div><div class="rule">Employee / Student Signature (manual)</div></div>
<div><div class="rule"><strong>{{ $snapshot['signatory_name'] ?: $snapshot['partner_approver'] }}</strong><br>{{ $snapshot['signatory_title'] ?: 'Partner' }}<br><span style="color:#64748b">Printed signatory details — not a digital signature</span></div></div>
</section>
<footer class="footer">
Reference {{ $issue->reference }} · Officially approved in the ERP on {{ $snapshot['approved_at'] }}.<br>
{{ $snapshot['template_footer'] ?? 'This page reflects the immutable issued snapshot. Verify the application against the authenticated ERP records.' }}
</footer>
</main>
</body>
</html>
