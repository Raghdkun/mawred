{{-- Notification email (ADR-0016). Inline styles because mail clients drop stylesheets; colours mirror the Mawred tokens. --}}
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
            <tr><td><img src="{{ asset('brand/'.$header) }}" alt="{{ config('app.name') }}" width="560" style="display:block;width:100%;height:auto;border:0;"></td></tr>
            <tr><td style="padding:28px 28px 8px;text-align:{{ $rtl ? 'right' : 'left' }};">
                <h1 style="margin:0 0 16px;font-size:20px;line-height:1.4;">{{ $subjectLine }}</h1>
                @if ($bodyText !== '')
                    <p style="margin:0 0 16px;font-size:15px;line-height:1.7;">{!! nl2br(e($bodyText)) !!}</p>
                @endif
                @foreach ($items as $item)
                    <div style="padding:12px 0;border-top:1px solid #DCE3EE;">
                        @if ($item['url'])
                            <a href="{{ $item['url'] }}" style="color:#4570C0;font-weight:600;text-decoration:none;">{{ $item['title'] }}</a>
                        @else
                            <strong>{{ $item['title'] }}</strong>
                        @endif
                        @if ($item['body'])
                            <div style="font-size:14px;color:#5E6A82;">{{ $item['body'] }}</div>
                        @endif
                    </div>
                @endforeach
            </td></tr>
            <tr><td style="padding:8px 28px 28px;text-align:{{ $rtl ? 'right' : 'left' }};">
                @if (isset($actions['approved']))
                    <a href="{{ $actions['approved'] }}" style="display:inline-block;margin:4px;padding:12px 20px;border-radius:8px;background:#4570C0;color:#ffffff;font-weight:600;text-decoration:none;">{{ __('Approve') }}</a>
                @endif
                @if (isset($actions['rejected']))
                    <a href="{{ $actions['rejected'] }}" style="display:inline-block;margin:4px;padding:12px 20px;border-radius:8px;background:#ffffff;border:1px solid #C3CDDC;color:#182238;font-weight:600;text-decoration:none;">{{ __('Reject') }}</a>
                @endif
                @if ($url)
                    <a href="{{ $url }}" style="display:inline-block;margin:4px;padding:12px 20px;color:#4570C0;font-weight:600;">{{ __('Open in Mawred') }}</a>
                @endif
            </td></tr>
        </table>
        <p style="font-size:12px;color:#5E6A82;margin:16px 0 0;">{{ __('You can change which emails you get in your notification settings.') }}</p>
    </td></tr>
</table>
</body>
</html>
