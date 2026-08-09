{{-- Customer-facing invoice (Phase 7, blueprint §28). dompdf: tables + inline styles. --}}
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $customer = $invoice->counterparty;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
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
        'documentTitle'  => 'Invoice',
        'documentNumber' => $invoice->invoice_number,
        'documentDate'   => \Illuminate\Support\Carbon::parse($invoice->invoice_date)->format('d M Y'),
        'documentStatus' => $invoice->due_date
            ? 'Due ' . \Illuminate\Support\Carbon::parse($invoice->due_date)->format('d M Y')
            : null,
    ])

    <table style="width:100%; border-collapse:collapse; margin-bottom:10px;">
        <tr>
            <td style="vertical-align:top;">
                <div style="font-size:10px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Billed to</div>
                <div style="font-size:12px; font-weight:bold; margin-top:3px;">{{ $customer?->name ?? '—' }}</div>
                @if($customer?->address_line_1)
                    <div style="font-size:10px; color:#475569;">{{ $customer->address_line_1 }}</div>
                @endif
                @if($customer?->city)
                    <div style="font-size:10px; color:#475569;">{{ $customer->city }}{{ $customer->region ? ', ' . $customer->region : '' }}</div>
                @endif
                @if($customer?->phone)
                    <div style="font-size:10px; color:#475569;">{{ $customer->phone }}</div>
                @endif
                @if($customer?->tax_id)
                    <div style="font-size:10px; color:#475569;">TIN: {{ $customer->tax_id }}</div>
                @endif
            </td>
        </tr>
    </table>

    @if($invoice->description)
        <p style="font-size:11px; color:#334155; margin:0 0 8px;">{{ $invoice->description }}</p>
    @endif

    <table class="lines">
        <thead>
            <tr>
                <th style="width:46%;">Description</th>
                <th class="num" style="width:12%;">Qty</th>
                <th class="num" style="width:14%;">Unit price</th>
                <th class="num" style="width:14%;">Tax</th>
                <th class="num" style="width:14%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoice->lines as $line)
                <tr>
                    <td>
                        {{ $line->description }}
                        @if($line->item_code)
                            <div style="font-size:9px; color:#94a3b8;">{{ $line->item_code }}</div>
                        @endif
                    </td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $line->quantity, 4, '.', ''), '0'), '.') }}{{ $line->unit_of_measure ? ' ' . $line->unit_of_measure : '' }}</td>
                    <td class="num">{{ $money($line->unit_price) }}</td>
                    <td class="num">{{ $money($line->line_tax_amount) }}</td>
                    <td class="num">{{ $money($line->line_total) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="color:#94a3b8;">No lines on this invoice.</td></tr>
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
                        <td class="num">{{ $invoice->currency_code }} {{ $money($invoice->subtotal) }}</td>
                    </tr>
                    @if(bccomp((string) $invoice->discount_total, '0', 2) > 0)
                        <tr>
                            <td style="color:#475569;">Discount</td>
                            <td class="num">− {{ $money($invoice->discount_total) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td style="color:#475569;">Tax</td>
                        <td class="num">{{ $money($invoice->tax_total) }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight:bold; border-top:1px solid #cbd5e1; padding-top:6px;">Total</td>
                        <td class="num" style="font-weight:bold; border-top:1px solid #cbd5e1; padding-top:6px;">
                            {{ $invoice->currency_code }} {{ $money($invoice->total) }}
                        </td>
                    </tr>
                    @if(bccomp((string) $invoice->amount_paid, '0', 2) > 0)
                        <tr>
                            <td style="color:#475569;">Paid</td>
                            <td class="num">− {{ $money($invoice->amount_paid) }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:bold;">Still owed</td>
                            <td class="num" style="font-weight:bold;">{{ $invoice->currency_code }} {{ $money($invoice->balance_due) }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    @if($invoice->notes)
        <p style="font-size:10px; color:#475569; margin-top:18px;">{{ $invoice->notes }}</p>
    @endif

    {{-- A cancelled or reversed invoice must say so on its face. Handing over a
         document that looks payable when it is not is worse than handing over
         nothing. --}}
    @if(in_array($invoice->status, ['cancelled', 'reversed'], true))
        <p style="margin-top:18px; padding:8px 10px; background:#fee2e2; color:#991b1b; font-weight:bold; font-size:12px;">
            This invoice has been {{ $invoice->status }}. It is not payable.
        </p>
    @endif

    <p style="margin-top:26px; font-size:9px; color:#94a3b8;">
        {{ config('finance.statements.footer_text') }}
    </p>
</body>
</html>
