{{--
    Shared header for customer-facing finance documents (Phase 7).

    dompdf idiom: tables and inline styles. No Tailwind — the utility classes
    are never expanded in this rendering path, so a class-based layout would
    print as unstyled text.
--}}
<table style="width:100%; border-collapse:collapse; margin-bottom:18px;">
    <tr>
        <td style="vertical-align:top; width:60%;">
            <div style="font-size:17px; font-weight:bold; color:#0f172a;">{{ $issuer['name'] }}</div>
            @if($issuer['address'])
                <div style="font-size:10px; color:#475569; margin-top:3px;">{!! nl2br(e($issuer['address'])) !!}</div>
            @endif
            <div style="font-size:10px; color:#475569; margin-top:3px;">
                @if($issuer['phone']){{ $issuer['phone'] }}@endif
                @if($issuer['phone'] && $issuer['email']) · @endif
                @if($issuer['email']){{ $issuer['email'] }}@endif
            </div>
            @if($issuer['tin'])
                <div style="font-size:10px; color:#475569; margin-top:3px;">TIN: {{ $issuer['tin'] }}</div>
            @endif
        </td>
        <td style="vertical-align:top; text-align:right; width:40%;">
            <div style="font-size:20px; font-weight:bold; color:#0f172a; letter-spacing:1px;">{{ strtoupper($documentTitle) }}</div>
            <div style="font-size:12px; color:#334155; margin-top:4px;">{{ $documentNumber }}</div>
            <div style="font-size:10px; color:#64748b; margin-top:2px;">{{ $documentDate }}</div>
            @isset($documentStatus)
                <div style="font-size:10px; color:#64748b; margin-top:2px;">{{ $documentStatus }}</div>
            @endisset
        </td>
    </tr>
</table>
