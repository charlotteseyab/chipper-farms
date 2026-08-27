<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $storeName }}</title>
</head>
<body style="margin:0;background:#f1f5f9;font-family:Arial,sans-serif;color:#1e293b;">
    <div style="padding:32px 16px;">
        <div style="max-width:620px;margin:0 auto;overflow:hidden;border:1px solid #dbe5e1;border-radius:20px;background:#ffffff;">
            <div style="padding:24px 28px;background:#176c50;color:#ffffff;">
                <div style="font-size:20px;font-weight:700;">{{ $storeName }}</div>
            </div>
            <div style="padding:28px;font-size:15px;line-height:1.7;">
                {!! nl2br(e($body)) !!}

                @if($actionUrl && $actionLabel)
                    <div style="margin-top:26px;">
                        <a href="{{ $actionUrl }}" style="display:inline-block;border-radius:10px;background:#176c50;padding:12px 18px;color:#ffffff;text-decoration:none;font-weight:700;">{{ $actionLabel }}</a>
                    </div>
                @endif
                @if($secondaryActionUrl && $secondaryActionLabel)
                    <div style="margin-top:12px;">
                        <a href="{{ $secondaryActionUrl }}" style="display:inline-block;border:1px solid #176c50;border-radius:10px;background:#ffffff;padding:11px 17px;color:#176c50;text-decoration:none;font-weight:700;">{{ $secondaryActionLabel }}</a>
                    </div>
                    <p style="margin:12px 0 0;color:#64748b;font-size:13px;">Optional — create an account to keep this and future orders together.</p>
                @endif
            </div>
            @if($supportEmail || $supportPhone)
                <div style="border-top:1px solid #e2e8f0;padding:18px 28px;color:#64748b;font-size:13px;line-height:1.6;">
                    Need help?
                    @if($supportEmail)<span>{{ $supportEmail }}</span>@endif
                    @if($supportEmail && $supportPhone)<span> · </span>@endif
                    @if($supportPhone)<span>{{ $supportPhone }}</span>@endif
                </div>
            @endif
        </div>
    </div>
</body>
</html>
