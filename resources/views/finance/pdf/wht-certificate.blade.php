<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>WHT Certificate {{ $cert->certificate_number }}</title>
    <style>
        @page { margin: 18mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 14px; margin: 0 0 4px 0; text-align: center; letter-spacing: 1px; }
        h2 { font-size: 11px; margin: 0; text-align: center; color: #4b5563; font-weight: normal; }

        .header-block { border-bottom: 2px solid #111827; padding-bottom: 8px; margin-bottom: 14px; }

        .cert-meta { width: 100%; margin: 12px 0; }
        .cert-meta td { padding: 3px 6px; vertical-align: top; }
        .cert-meta .label { font-weight: bold; color: #4b5563; width: 28%; text-transform: uppercase; font-size: 9px; }

        .party-block { width: 100%; margin: 8px 0 14px; border-collapse: collapse; }
        .party-block td { vertical-align: top; padding: 8px; width: 50%; border: 1px solid #e5e7eb; }
        .party-block .heading { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; font-weight: bold; padding-bottom: 4px; }
        .party-block .name { font-weight: bold; font-size: 11px; color: #111827; }
        .party-block .tin  { font-family: monospace; font-size: 10px; margin-top: 4px; }
        .party-block .addr { color: #4b5563; font-size: 9px; margin-top: 2px; }

        .alloc-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .alloc-table th, .alloc-table td { padding: 5px 6px; border: 1px solid #d1d5db; text-align: left; vertical-align: top; font-size: 9px; }
        .alloc-table thead th { background: #f3f4f6; font-weight: bold; font-size: 9px; text-transform: uppercase; }
        .alloc-table .num { text-align: right; font-family: monospace; }
        .alloc-table tfoot td { font-weight: bold; background: #f9fafb; }

        .totals-row td { font-size: 10px; }

        .footer { margin-top: 28px; border-top: 1px solid #d1d5db; padding-top: 10px; font-size: 8px; color: #6b7280; }

        .signature-block { margin-top: 30px; width: 100%; border-collapse: collapse; }
        .signature-block td { width: 50%; padding: 0 8px; }
        .signature-block .sig-line { border-top: 1px solid #111827; padding-top: 4px; font-size: 9px; color: #4b5563; }
        .signature-block .sig-label { font-size: 8px; color: #6b7280; text-transform: uppercase; }

        .void-watermark {
            position: fixed;
            top: 40%;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 80px;
            color: #ef4444;
            opacity: 0.15;
            transform: rotate(-30deg);
            font-weight: bold;
            letter-spacing: 8px;
        }
    </style>
</head>
<body>

@if($cert->isVoid())
    <div class="void-watermark">VOIDED</div>
@endif

<div class="header-block">
    <h1>CERTIFICATE OF TAX WITHHELD AT SOURCE</h1>
    <h2>Ghana Revenue Authority — Withholding Tax Filing Reference</h2>
</div>

<table class="cert-meta">
    <tr>
        <td class="label">Certificate No.</td>
        <td><strong>{{ $cert->certificate_number ?: 'DRAFT' }}</strong></td>
        <td class="label">Period</td>
        <td><strong>{{ $cert->periodLabel() }}</strong></td>
    </tr>
    <tr>
        <td class="label">Period Start</td>
        <td>{{ $cert->period_from->format('d M Y') }}</td>
        <td class="label">Period End</td>
        <td>{{ $cert->period_to->format('d M Y') }}</td>
    </tr>
    <tr>
        <td class="label">Issue Date</td>
        <td>{{ optional($cert->issued_at)->format('d M Y') ?: '—' }}</td>
        <td class="label">Currency</td>
        <td>{{ $cert->currency_code }}</td>
    </tr>
</table>

<table class="party-block">
    <tr>
        <td>
            <div class="heading">Withholding Agent (Issuer)</div>
            <div class="name">{{ $cert->issuer_name_snapshot ?: config('finance.statements.company_name', config('app.name')) }}</div>
            <div class="tin">TIN: {{ $cert->issuer_tin_snapshot ?: (config('finance.statements.company_tin') ?: '—') }}</div>
            @if(config('finance.statements.company_address'))
                <div class="addr">{{ config('finance.statements.company_address') }}</div>
            @endif
        </td>
        <td>
            <div class="heading">Recipient (Supplier)</div>
            <div class="name">{{ $cert->supplier_name_snapshot ?: optional($cert->counterparty)->name }}</div>
            <div class="tin">TIN: {{ $cert->supplier_tin_snapshot ?: (optional($cert->counterparty)->tax_id ?: '—') }}</div>
            @if($cert->supplier_address_snapshot)
                <div class="addr">{{ $cert->supplier_address_snapshot }}</div>
            @endif
        </td>
    </tr>
</table>

<p style="margin: 10px 0 4px; font-size: 10px;">
    The withholding agent above hereby certifies that the following amounts of tax have
    been withheld at source from payments made to the recipient during the period stated:
</p>

<table class="alloc-table">
    <thead>
        <tr>
            <th>Payment Date</th>
            <th>Payment Ref</th>
            <th>Invoice Ref</th>
            <th class="num">Gross Paid</th>
            <th class="num">Tax Withheld</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $r)
            <tr>
                <td>{{ $r['payment_date'] ?? '—' }}</td>
                <td>{{ $r['payment_number'] ?? ('#' . ($r['payment_id'] ?? '')) }}</td>
                <td>
                    {{ $r['supplier_invoice_number'] ?? ($r['vendor_invoice_number'] ?? '—') }}
                    @if(! empty($r['supplier_invoice_number']) && ! empty($r['vendor_invoice_number']))
                        <br><span style="color:#9ca3af;font-size:8px;">({{ $r['vendor_invoice_number'] }})</span>
                    @endif
                </td>
                <td class="num">{{ number_format((float) ($r['amount'] ?? 0), 2) }}</td>
                <td class="num">{{ number_format((float) ($r['withholding_amount'] ?? 0), 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5" style="text-align: center; padding: 16px; color: #9ca3af;">No contributing allocations.</td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr class="totals-row">
            <td colspan="3" style="text-align: right;">TOTAL ({{ $cert->currency_code }}):</td>
            <td class="num">{{ number_format((float) $cert->total_amount_paid, 2) }}</td>
            <td class="num">{{ number_format((float) $cert->total_withheld, 2) }}</td>
        </tr>
    </tfoot>
</table>

<p style="margin-top: 14px; font-size: 9px; color: #4b5563;">
    The total tax withheld on the above payments has been remitted to the Ghana Revenue
    Authority in accordance with the Income Tax Act, 2015 (Act 896) and applicable
    regulations. The recipient may use this certificate as evidence of tax already
    paid at source when filing their annual return.
</p>

<table class="signature-block">
    <tr>
        <td>
            <div class="sig-line">&nbsp;</div>
            <div class="sig-label">Authorised Signatory (Issuer)</div>
        </td>
        <td>
            <div class="sig-line">&nbsp;</div>
            <div class="sig-label">Date &amp; Stamp</div>
        </td>
    </tr>
</table>

<div class="footer">
    Certificate {{ $cert->certificate_number ?: '—' }} ·
    Generated {{ now()->format('d M Y H:i') }} ·
    Allocations: {{ $cert->allocation_count }} across {{ $cert->payment_count }} payment(s)
    @if($cert->isVoid())
        · <strong style="color:#b91c1c">VOIDED {{ optional($cert->voided_at)->format('d M Y') }}</strong>
        @if($cert->void_reason): {{ $cert->void_reason }} @endif
    @endif
</div>

</body>
</html>
