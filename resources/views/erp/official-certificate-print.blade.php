<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{{ $snapshot['document_title'] }} · {{ $issue->reference }}</title>
<style>
@page{size:A4;margin:16mm}
*{box-sizing:border-box}html{background:#e8edf2}body{font:13px/1.7 Arial,"Noto Sans",sans-serif;color:#17191f;margin:0}
.toolbar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;padding:13px 20px;background:#17191f;color:#fff;position:sticky;top:0}
.toolbar a,.toolbar button{font-weight:800;font-size:12px;text-decoration:none;cursor:pointer;padding:9px 13px;background:#fcdf02;color:#17191f;border:0;border-radius:9px}
.paper{max-width:210mm;min-height:267mm;background:#fff;box-shadow:0 12px 45px #0002;margin:24px auto;padding:18mm}
.letterhead{display:block;max-height:40mm;max-width:100%;object-fit:contain;margin-bottom:18px}
.head{display:flex;align-items:center;gap:15px;padding-bottom:18px;border-bottom:3px solid var(--accent,#fcdf02)}
.head img{max-width:70px;max-height:70px;object-fit:contain}
.head h1{font-size:22px;margin:0;line-height:1.25}.head p{font-size:11px;color:#64748b;margin:5px 0 0}
.caption{margin-top:36px;display:flex;justify-content:space-between;align-items:start;gap:15px}
.caption h2{font-size:18px;margin:0}.ref{font-size:10px;color:#64748b;text-align:right}
.body{font-size:14px;white-space:pre-wrap;line-height:1.9;overflow-wrap:anywhere;margin-top:35px}
.approve{background:#f8fafc;border-left:4px solid #16a34a;margin-top:30px;padding:18px}
.approve strong{color:#166534}.sign{margin-top:60px;border-top:1px solid #cbd5e1;padding-top:12px;width:58%;font-size:12px}
.footer{border-top:1px solid #cbd5e1;margin-top:45px;padding-top:12px;font-size:10px;color:#64748b}
@media(max-width:600px){.paper{margin:0;padding:22px;box-shadow:none;min-height:0}.caption{flex-direction:column}}
@media print{html,body{background:white}.toolbar{display:none}.paper{margin:0;padding:0;box-shadow:none;width:auto;min-height:0}.approve,.sign{break-inside:avoid}}
</style>
</head>
<body style="--accent:{{ $snapshot['accent_color'] }}">
<nav class="toolbar">
<strong>{{ $issue->reference }} · Partner Issued</strong>
<div>
<button type="button" onclick="window.print()">Print / Save as PDF</button>
<a href="{{ route('erp.staff_certificates.word',['firmSlug'=>$firmSlug,'id'=>$issue->id]) }}">Download Word</a>
<a href="{{ route('erp.staff_certificates.index',['firmSlug'=>$firmSlug]) }}">Back to Library</a>
</div>
</nav>
<main class="paper">
@if($letterhead)<img class="letterhead" src="{{ $letterhead }}" alt="Workspace letterhead">@endif
<header class="head">
@if($logo)<img src="{{ $logo }}" alt="Company logo">@endif
<div><h1>{{ $snapshot['company_name'] }}</h1>
<p>{{ $snapshot['registered_company_name']!==$snapshot['company_name']?$snapshot['registered_company_name'].' · ':'' }}{{ $snapshot['address'] }}</p>
<p>{{ implode(' · ',array_filter([$snapshot['phone'],$snapshot['email'],$snapshot['website']])) }}</p></div>
</header>
<div class="caption">
<h2>{{ $snapshot['document_title'] }}</h2>
<p class="ref">Reference: {{ $issue->reference }}<br>Date: {{ \Carbon\CarbonImmutable::parse($issue->issued_at)->setTimezone($company->timezone)->format('d M Y') }}<br>Template version: {{ $snapshot['template_version'] }}</p>
</div>
<p class="body">{{ $snapshot['body_text'] }}</p>
<div class="approve"><strong>APPROVED & ISSUED BY AUTHORIZED PARTNER</strong><p>{{ $snapshot['approver_name'] }}</p><p>Recorded: {{ $snapshot['approved_at'] }}</p></div>
<div class="sign"><strong>{{ $snapshot['signatory_name'] ?: $snapshot['approver_name'] }}</strong><br>
{{ $snapshot['signatory_title'] ?: 'Partner' }}<br>
<small style="color:#64748b">Printed signatory details. This does not constitute a cryptographic signature.</small></div>
<footer class="footer"><p>{{ $snapshot['footer_note'] }}</p><p>Immutable issued snapshot · {{ $issue->reference }} · Verify authenticity via authorized ERP records.</p></footer>
</main>
</body>
</html>
