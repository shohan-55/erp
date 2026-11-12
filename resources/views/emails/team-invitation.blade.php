<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ !empty($isPasswordReset) ? 'Updated Login Credentials — ' . $firmName : 'Login Credentials — ' . $firmName }}</title>
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

                            @if (!empty($isPasswordReset))
                                <p style="margin:0 0 18px;font-size:14px;line-height:1.6;color:#334155;">
                                    Your account password at <strong>{{ $firmName }}</strong> has been updated.
                                    You can sign in directly using your login link, username, and password below:
                                </p>
                            @else
                                <p style="margin:0 0 18px;font-size:14px;line-height:1.6;color:#334155;">
                                    Your account has been created at <strong>{{ $firmName }}</strong> ({{ $roleLabel }} · {{ $partnerUnit ?? 'Partner Unit 01' }}).
                                    Your account is active and ready to use. Please sign in directly using the login details below:
                                </p>
                            @endif

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 22px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:10px;padding:16px;">
                                <tr>
                                    <td style="padding:8px;font-size:13px;color:#64748b;width:38%;"><strong>Login Link:</strong></td>
                                    <td style="padding:8px;font-size:13px;color:#0f172a;word-break:break-all;">
                                        <a href="{{ $loginUrl }}" style="color:#0d9488;font-weight:bold;text-decoration:underline;">{{ $loginUrl }}</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:8px;font-size:13px;color:#64748b;"><strong>Username (Email):</strong></td>
                                    <td style="padding:8px;font-size:13px;color:#0f172a;font-weight:bold;">{{ $email }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px;font-size:13px;color:#64748b;"><strong>Password:</strong></td>
                                    <td style="padding:8px;font-size:14px;color:#0f172a;font-family:'Courier New',monospace;font-weight:bold;">
                                        <span style="display:inline-block;background:#fef3c7;border:1px solid #fde68a;color:#92400e;padding:3px 10px;border-radius:6px;">{{ $plainPassword ?: 'Use your set password' }}</span>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:24px 0;text-align:center;">
                                <a href="{{ $loginUrl }}"
                                   style="display:inline-block;background:#0d9488;color:#ffffff;text-decoration:none;font-weight:bold;font-size:14px;padding:13px 28px;border-radius:10px;">
                                    Sign In to Workspace
                                </a>
                            </p>

                            <p style="margin:0;font-size:12px;line-height:1.6;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:16px;">
                                You can change your password anytime after signing in from your Profile &amp; Security settings.
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
