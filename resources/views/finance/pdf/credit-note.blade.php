{{--
    Customer-facing credit note (Phase 7, blueprint §29 "customer-facing
    document — needed for refunds/returns"). dompdf: tables + inline styles.

    A credit note without a document the customer can hold is a number in a
    ledger they have to take on trust. This is that document.
--}}
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $customer = $creditNote->customer;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $creditNote->credit_note_number }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #0f172a; margin: 28px; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.lines th { background: #f1f5f9; color: #334155; font-size: 10px; text-align: left; padding: 7px 8px; border-bottom: 1px solid #cbd5e1; }
        table.lines td { padding: 7px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .num { text-align: right; }
        .totals td { padding: 4px 8px; }
    </style>
</head>
<body>
    @include('finance.pdf.partials.document-header', [
        'documentTitle'  => 'Credit Note',
        'documentNumber' => $creditNote->credit_note_number,
        'documentDate'   => \Illuminate\Support\Carbon::parse($creditNote->issue_date)->format('d M Y'),
        'documentStatus' => $creditNote->originalInvoice
            ? 'Against invoice ' . $creditNote->originalInvoice->invoice_number
            : null,
    ])

    <div style="font-size:10px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Credited to</div>
    <div style="font-size:12px; font-weight:bold; margin-top:3px;">{{ $customer?->name ?? '—' }}</div>
    @if($customer?->phone)
        <div style="font-size:10px; color:#475569;">{{ $customer->phone }}</div>
    @endif

    @if($creditNote->reason)
        <p style="font-size:11px; color:#334155; margin-top:14px;">
            <span style="color:#64748b;">Reason:</span> {{ $creditNote->reason }}
        </p>
    @endif

    <table class="lines">
        <thead>
            <tr>
                <th style="width:52%;">Description</th>
                <th class="num" style="width:12%;">Qty</th>
                <th class="num" style="width:18%;">Unit price</th>
                <th class="num" style="width:18%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($creditNote->lines as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $line->quantity, 4, '.', ''), '0'), '.') }}</td>
                    <td class="num">{{ $money($line->unit_price) }}</td>
                    <td class="num">{{ $money($line->line_total) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="color:#94a3b8;">No lines on this credit note.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table style="width:100%; border-collapse:collapse; margin-top:14px;">
        <tr>
            <td style="width:58%;"></td>
            <td style="width:42%;">
                <table class="totals" style="width:100%; border-collapse:collapse;">
                    <tr>
                        <td style="color:#475569;">Subtotal</td>
                        <td class="num">{{ $creditNote->currency_code }} {{ $money($creditNote->subtotal) }}</td>
                    </tr>
                    <tr>
                        <td style="color:#475569;">Tax</td>
                        <td class="num">{{ $money($creditNote->tax_total) }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight:bold; border-top:1px solid #cbd5e1; padding-top:6px;">Total credited</td>
                        <td class="num" style="font-weight:bold; border-top:1px solid #cbd5e1; padding-top:6px;">
                            {{ $creditNote->currency_code }} {{ $money($creditNote->total) }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if(in_array($creditNote->status, [
        \App\Models\Finance\CreditNote::STATUS_CANCELLED,
        \App\Models\Finance\CreditNote::STATUS_REVERSED,
    ], true))
        <p style="margin-top:18px; padding:8px 10px; background:#fee2e2; color:#991b1b; font-weight:bold; font-size:12px;">
            This credit note has been {{ $creditNote->status }}. It no longer holds value.
        </p>
    @elseif($creditNote->status === \App\Models\Finance\CreditNote::STATUS_DRAFT)
        <p style="margin-top:18px; padding:8px 10px; background:#fef3c7; color:#92400e; font-weight:bold; font-size:12px;">
            Draft — not yet issued.
        </p>
    @endif

    <p style="margin-top:26px; font-size:9px; color:#94a3b8;">
        {{ config('finance.statements.footer_text') }}
    </p>
</body>
</html>
