<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $notification->subject ?: 'Notification Nema ERP' }}</title>
</head>
<body style="margin:0; padding:24px; background:#f7f2ea; color:#1f2933; font-family:Segoe UI, Arial, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:18px; overflow:hidden; border:1px solid #e8dccb;">
        <tr>
            <td style="padding:28px 28px 18px; background:#efe6d8;">
                <div style="font-size:12px; letter-spacing:0.08em; text-transform:uppercase; color:#6b7280;">{{ $companyName }}</div>
                <h1 style="margin:10px 0 0; font-size:24px; line-height:1.25; color:#111827;">{{ $notification->subject ?: 'Notification d approbation' }}</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:28px;">
                <p style="margin:0 0 16px; font-size:15px; line-height:1.7;">{{ $notification->message }}</p>

                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:20px 0; border-collapse:collapse;">
                    <tr>
                        <td style="padding:10px 0; border-bottom:1px solid #eee6db; color:#6b7280; width:180px;">Module</td>
                        <td style="padding:10px 0; border-bottom:1px solid #eee6db; font-weight:600;">{{ $moduleLabel ?: 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 0; border-bottom:1px solid #eee6db; color:#6b7280;">Document</td>
                        <td style="padding:10px 0; border-bottom:1px solid #eee6db; font-weight:600;">{{ $documentNumber ?: 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 0; border-bottom:1px solid #eee6db; color:#6b7280;">Etape</td>
                        <td style="padding:10px 0; border-bottom:1px solid #eee6db; font-weight:600;">{{ $stepLabel ?: 'N/A' }}</td>
                    </tr>
                </table>

                @if ($actionUrl)
                    <p style="margin:24px 0 0;">
                        <a href="{{ $actionUrl }}" style="display:inline-block; padding:12px 18px; border-radius:12px; background:#005f73; color:#ffffff; text-decoration:none; font-weight:700;">Ouvrir le document</a>
                    </p>
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
