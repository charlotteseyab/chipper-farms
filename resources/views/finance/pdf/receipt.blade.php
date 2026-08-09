{{--
    Customer-facing receipt (Phase 7, blueprint §28 "customer-facing printable
    receipt"). dompdf: tables + inline styles.

    This is the piece of paper a buyer walks away with. It answers the three
    questions they will ask later: how much, when, and against what.
--}}
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $customer = $receipt->counterparty;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $receipt->receipt_number }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #0f172a; margin: 28px; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.lines th { background: #f1f5f9; color: #334155; font-size: 10px; text-align: left; padding: 7px 8px; border-bottom: 1px solid #cbd5e1; }
        table.lines td { padding: 7px 8px; border-bottom: 1px solid #e2e8f0; }
        .num { text-align: right; }
    </style>
</head>
<body>
    @include('finance.pdf.partials.document-header', [
        'documentTitle'  => 'Receipt',
        'documentNumber' => $receipt->receipt_number,
        'documentDate'   => \Illuminate\Support\Carbon::parse($receipt->receipt_date)->format('d M Y'),
        'documentStatus' => $receipt->paymentMethod?->name,
    ])

    <div style="font-size:10px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Received from</div>
    <div style="font-size:12px; font-weight:bold; margin-top:3px;">{{ $customer?->name ?? '—' }}</div>
    @if($customer?->phone)
        <div style="font-size:10px; color:#475569;">{{ $customer->phone }}</div>
    @endif

    <div style="margin-top:20px; padding:14px 16px; background:#ecfdf5; border:1px solid #a7f3d0;">
        <div style="font-size:10px; color:#047857; text-transform:uppercase; letter-spacing:0.5px;">Amount received</div>
        <div style="font-size:24px; font-weight:bold; color:#065f46; margin-top:4px;">
            {{ $receipt->currency_code }} {{ $money($receipt->amount) }}
        </div>
        @if($receipt->external_reference)
            <div style="font-size:10px; color:#047857; margin-top:4px;">Reference: {{ $receipt->external_reference }}</div>
        @endif
    </div>

    @if($receipt->description)
        <p style="font-size:11px; color:#334155; margin-top:14px;">{{ $receipt->description }}</p>
    @endif

    {{-- What this money was put against. A receipt that does not say which bill
         it settled leaves the customer unable to check their own account. --}}
    @if($receipt->activeAllocations->isNotEmpty())
        <div style="margin-top:18px; font-size:10px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Applied to</div>
        <table class="lines">
            <thead>
                <tr>
                    <th style="width:40%;">Invoice</th>
                    <th style="width:30%;">Invoice date</th>
                    <th class="num" style="width:30%;">Applied</th>
                </tr>
            </thead>
            <tbody>
                @foreach($receipt->activeAllocations as $allocation)
                    <tr>
                        <td>{{ $allocation->invoice?->invoice_number ?? '—' }}</td>
                        <td>
                            {{ $allocation->invoice?->invoice_date
                                ? \Illuminate\Support\Carbon::parse($allocation->invoice->invoice_date)->format('d M Y')
                                : '—' }}
                        </td>
                        <td class="num">{{ $money($allocation->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @php $unallocated = $receipt->unallocatedAmount(); @endphp
    @if(bccomp((string) $unallocated, '0', 2) > 0)
        <p style="margin-top:12px; font-size:11px; color:#334155;">
            {{ $receipt->currency_code }} {{ $money($unallocated) }} of this payment is held on your account
            as credit against future purchases.
        </p>
    @endif

    @if($receipt->status !== \App\Models\Finance\Receipt::STATUS_POSTED)
        <p style="margin-top:18px; padding:8px 10px; background:#fee2e2; color:#991b1b; font-weight:bold; font-size:12px;">
            This receipt is {{ $receipt->status }}. It is not evidence of payment.
        </p>
    @endif

    <p style="margin-top:26px; font-size:9px; color:#94a3b8;">
        {{ config('finance.statements.footer_text') }}
    </p>
</body>
</html>
