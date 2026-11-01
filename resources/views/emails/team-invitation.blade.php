<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Activate Your {{ $firmName }} ERP Account</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0;">
                    <tr>
                        <td style="background:#0d9488;padding:22px 28px;color:#ffffff;">
                            <div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;opacity:.85;">DhakaWala ERP</div>
                            <div style="font-size:19px;font-weight:bold;margin-top:4px;">{{ $firmName }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 14px;font-size:15px;">Dear <strong>{{ $memberName }}</strong>,</p>
                            <p style="margin:0 0 18px;font-size:14px;line-height:1.6;color:#334155;">
                                Your account has been opened at <strong>{{ $firmName }}</strong> as
                                <strong>{{ $roleLabel }}</strong> ({{ $partnerUnit ?? 'Partner Unit 01' }}).
                                Use the secure, single-use link below to activate your account and create your own password.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 22px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:10px;padding:16px;">
                                <tr>
                                    <td style="padding:6px 8px;font-size:13px;color:#64748b;width:38%;"><strong>Practice Sign-in:</strong></td>
                                    <td style="padding:6px 8px;font-size:13px;color:#0f172a;word-break:break-all;"><a href="{{ $activationUrl }}" style="color:#0d9488;font-weight:bold;text-decoration:underline;">{{ $activationUrl }}</a></td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 8px;font-size:13px;color:#64748b;"><strong>Username (Email):</strong></td>
                                    <td style="padding:6px 8px;font-size:13px;color:#0f172a;font-weight:bold;">{{ $email }}</td>
                                </tr>
                                @if (!empty($userId))
                                    <tr>
                                        <td style="padding:6px 8px;font-size:13px;color:#64748b;"><strong>User ID / Code:</strong></td>
                                        <td style="padding:6px 8px;font-size:13px;color:#0f172a;font-family:'Courier New',monospace;font-weight:bold;">{{ $userId }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding:6px 8px;font-size:13px;color:#64748b;"><strong>Role &amp; Unit:</strong></td>
                                    <td style="padding:6px 8px;font-size:13px;color:#0f172a;">{{ $roleLabel }} · {{ $partnerUnit ?? 'Partner Unit 01' }}</td>
                                </tr>
                            </table>

                            <p style="margin:24px 0;text-align:center;">
                                <a href="{{ $activationUrl }}" style="display:inline-block;background:#0d9488;color:#ffffff;text-decoration:none;font-weight:bold;font-size:14px;padding:13px 28px;border-radius:10px;">
                                    Activate Account &amp; Set Password
                                </a>
                            </p>

                            <p style="margin:0;font-size:12px;line-height:1.6;color:#64748b;border-top:1px solid #e2e8f0;padding-top:16px;">
                                This link expires after seven days and works once. If you did not expect this invitation, ignore the email. Your password will never be sent by email.
                            </p>
                        </td>
                    </tr>
                </table>
                <p style="margin:18px 0 0;font-size:11px;color:#94a3b8;">Sent by {{ $firmName }} via DhakaWala ERP SaaS.</p>
            </td>
        </tr>
    </table>
</body>
</html>
