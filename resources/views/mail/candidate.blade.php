{{-- Candidate email (D94). A co-brand area: the company leads (D22). Inline styles for mail clients. --}}
<!doctype html>
<html lang="{{ $recipientLocale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:0;background:#F6F8FC;font-family:'IBM Plex Sans Arabic','IBM Plex Sans',Arial,sans-serif;color:#182238;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F6F8FC;padding:24px 12px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;">
            <tr><td style="padding:20px 28px;border-bottom:3px solid #4570C0;font-size:18px;font-weight:600;text-align:{{ $rtl ? 'right' : 'left' }};">{{ $company }}</td></tr>
            <tr><td style="padding:28px 28px 8px;text-align:{{ $rtl ? 'right' : 'left' }};">
                <h1 style="margin:0 0 16px;font-size:20px;line-height:1.4;">{{ $subjectLine }}</h1>
                <p style="margin:0 0 16px;font-size:15px;line-height:1.7;">{!! nl2br(e($bodyText)) !!}</p>
            </td></tr>
            <tr><td style="padding:8px 28px 28px;text-align:{{ $rtl ? 'right' : 'left' }};">
                @foreach ($buttons as $button)
                    <a href="{{ $button['url'] }}" style="display:inline-block;margin:4px;padding:12px 20px;border-radius:8px;background:#4570C0;color:#ffffff;font-weight:600;text-decoration:none;">{{ $button['label'] }}</a>
                @endforeach
            </td></tr>
        </table>
        <p style="font-size:12px;color:#5E6A82;margin:16px 0 0;">
            {{ __('You applied for a job with :company.', ['company' => $company]) }}
            <a href="{{ $privacyUrl }}" style="color:#5E6A82;">{{ __('Delete my details') }}</a>
        </p>
    </td></tr>
</table>
</body>
</html>
