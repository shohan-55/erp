<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>You are invited to {{ $firmName }}</title>
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
                            <p style="margin:0 0 14px;font-size:15px;">Dear {{ $memberName }},</p>

                            <p style="margin:0 0 14px;font-size:14px;line-height:1.6;color:#334155;">
                                You have been enrolled at <strong>{{ $firmName }}</strong> as
                                <strong>{{ $roleLabel }}</strong>. To finish setting up your account,
                                choose your own password using the button below.
                            </p>

                            <p style="margin:26px 0;text-align:center;">
                                <a href="{{ $activationUrl }}"
                                   style="display:inline-block;background:#0d9488;color:#ffffff;text-decoration:none;font-weight:bold;font-size:14px;padding:13px 26px;border-radius:10px;">
                                    Activate My Account
                                </a>
                            </p>

                            @if ($plainPassword)
                                <p style="margin:0 0 14px;font-size:13px;line-height:1.6;color:#334155;">
                                    If the button does not work, sign in at the practice workspace with the
                                    temporary password below and change it immediately:
                                </p>
                                <p style="margin:0 0 18px;font-family:'Courier New',monospace;font-size:15px;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px;padding:12px;text-align:center;letter-spacing:1px;">
                                    {{ $plainPassword }}
                                </p>
                            @endif

                            <p style="margin:0 0 18px;font-size:13px;line-height:1.6;color:#64748b;">
                                Sign-in email: <strong>{{ $email }}</strong><br>
                                This invitation link expires in {{ $expiresInDays }} days and can be used once.
                            </p>

                            <p style="margin:0;font-size:12px;line-height:1.6;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:16px;">
                                If you were not expecting this invitation, you can ignore this email —
                                no account access is possible without activating the link.
                            </p>
                        </td>
                    </tr>
                </table>

                <p style="margin:18px 0 0;font-size:11px;color:#94a3b8;">
                    Sent by {{ $firmName }} via DhakaWala ERP SaaS.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
