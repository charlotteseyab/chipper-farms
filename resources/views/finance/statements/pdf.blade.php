<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Statement — {{ $statement->customer->name }}</title>
    <style>
        @page { margin: 1.5cm; }
        body { font-family: sans-serif; font-size: 10pt; color: #333; line-height: 1.4; }
        .header { display: table; width: 100%; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #2c5282; }
        .header-left, .header-right { display: table-cell; vertical-align: top; }
        .header-right { text-align: right; }
        .company-name { font-size: 16pt; font-weight: bold; color: #2c5282; margin: 0; }
        .doc-title { font-size: 14pt; font-weight: bold; color: #2c5282; margin: 0; }
        .doc-subtitle { font-size: 10pt; color: #666; margin-top: 2px; }
        .small { font-size: 9pt; color: #666; }
        .meta { margin-top: 4px; }
        .addresses { display: table; width: 100%; margin-bottom: 16px; }
        .addr-block { display: table-cell; vertical-align: top; width: 50%; padding-right: 8px; }
        .addr-label { font-size: 8pt; color: #888; text-transform: uppercase; letter-spacing: 0.04em; font-weight: bold; }
        .addr-value { font-weight: bold; margin-top: 2px; }
        table.activity { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.activity th { background: #f1f5f9; padding: 6px 8px; text-align: left; font-size: 8pt; text-transform: uppercase; color: #475569; border-bottom: 1px solid #cbd5e1; }
        table.activity td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        table.activity tr.opening { background: #f8fafc; font-style: italic; color: #64748b; }
        table.activity tr.totals { background: #f1f5f9; font-weight: bold; }
        .right { text-align: right; }
        .summary { display: table; width: 100%; margin-bottom: 16px; }
        .summary-cell { display: table-cell; padding: 12px; text-align: center; border: 1px solid #cbd5e1; width: 33%; }
        .summary-cell-closing { background: #fef2f2; border-color: #fca5a5; color: #b91c1c; }
        .summary-cell.paid { background: #f0fdf4; border-color: #86efac; color: #166534; }
        .summary-label { font-size: 8pt; text-transform: uppercase; color: #64748b; letter-spacing: 0.04em; }
        .summary-value { font-size: 14pt; font-weight: bold; margin-top: 2px; }
        .open-invoices { background: #fffbeb; border: 1px solid #fde68a; padding: 8px; margin-top: 8px; }
        .open-invoices h3 { font-size: 10pt; color: #92400e; margin: 0 0 6px 0; }
        .open-invoices table { width: 100%; border-collapse: collapse; }
        .open-invoices th { background: #fde68a; padding: 4px 6px; font-size: 8pt; }
        .open-invoices td { padding: 4px 6px; border-bottom: 1px solid #fde68a; font-size: 9pt; }
        .footer { margin-top: 24px; padding-top: 8px; border-top: 1px solid #cbd5e1; font-size: 8pt; color: #64748b; line-height: 1.4; }
        .badge { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 8pt; }
        .badge-invoice { background: #fef3c7; color: #92400e; }
        .badge-receipt { background: #d1fae5; color: #065f46; }
    </style>
</head>
<body>

<div class="header">
    <div class="header-left">
        <p class="company-name">{{ $company['name'] ?? config('app.name') }}</p>
        @if(!empty($company['address']))<p class="small">{{ $company['address'] }}</p>@endif
        @if(!empty($company['phone']) || !empty($company['email']))
            <p class="small">
                @if(!empty($company['phone'])){{ $company['phone'] }}@endif
                @if(!empty($company['phone']) && !empty($company['email'])) · @endif
                @if(!empty($company['email'])){{ $company['email'] }}@endif
            </p>
        @endif
        @if(!empty($company['tin']))<p class="small">TIN: {{ $company['tin'] }}</p>@endif
    </div>
    <div class="header-right">
        <p class="doc-title">Customer Statement</p>
        <p class="doc-subtitle">{{ $statement->startDate->format('Y-m-d') }} to {{ $statement->endDate->format('Y-m-d') }}</p>
        <p class="small meta">Generated {{ $statement->generatedAt->format('Y-m-d H:i') }}</p>
    </div>
</div>

<div class="addresses">
    <div class="addr-block">
        <span class="addr-label">To</span>
        <p class="addr-value">{{ $statement->customer->name }}</p>
        <p class="small">{{ $statement->customer->code }}</p>
        @if($statement->customer->billing_address_line1)
            <p class="small">{{ $statement->customer->billing_address_line1 }}</p>
            @if($statement->customer->billing_city)<p class="small">{{ $statement->customer->billing_city }}</p>@endif
        @endif
    </div>
    <div class="addr-block">
        <span class="addr-label">Reference</span>
        <p class="addr-value">Account: {{ $statement->customer->code }}</p>
        @if($statement->customer->credit_limit)
            <p class="small">Credit Limit: {{ $statement->customer->currency_code ?? 'GHS' }} {{ number_format((float) $statement->customer->credit_limit, 2) }}</p>
        @endif
    </div>
</div>

<div class="summary">
    <div class="summary-cell">
        <p class="summary-label">Opening Balance</p>
        <p class="summary-value">{{ number_format((float) $statement->openingBalance, 2) }}</p>
    </div>
    <div class="summary-cell">
        <p class="summary-label">Period Activity</p>
        <p class="summary-value">{{ $statement->invoicesCount }} / {{ $statement->receiptsCount }}</p>
        <p class="small">invoices / receipts</p>
    </div>
    <div class="summary-cell {{ (float) $statement->closingBalance > 0.01 ? 'summary-cell-closing' : 'paid' }}">
        <p class="summary-label">Closing Balance</p>
        <p class="summary-value">{{ number_format((float) $statement->closingBalance, 2) }}</p>
    </div>
</div>

@if(!empty($statement->entries))
    <table class="activity">
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Reference</th>
                <th>Description</th>
                <th class="right">Debit</th>
                <th class="right">Credit</th>
                <th class="right">Balance</th>
            </tr>
        </thead>
        <tbody>
            <tr class="opening">
                <td colspan="6">Opening balance</td>
                <td class="right">{{ number_format((float) $statement->openingBalance, 2) }}</td>
            </tr>
            @foreach($statement->entries as $entry)
                <tr>
                    <td>{{ $entry->date->format('Y-m-d') }}</td>
                    <td>
                        <span class="badge {{ $entry->type === 'invoice' ? 'badge-invoice' : 'badge-receipt' }}">{{ ucfirst($entry->type) }}</span>
                    </td>
                    <td>{{ $entry->reference }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($entry->description, 50) }}</td>
                    <td class="right">@if(bccomp($entry->debit, '0', 2) > 0){{ number_format((float) $entry->debit, 2) }}@endif</td>
                    <td class="right">@if(bccomp($entry->credit, '0', 2) > 0){{ number_format((float) $entry->credit, 2) }}@endif</td>
                    <td class="right">{{ number_format((float) $entry->runningBalance(), 2) }}</td>
                </tr>
            @endforeach
            <tr class="totals">
                <td colspan="4">Period totals</td>
                <td class="right">{{ number_format((float) $statement->invoicesTotal, 2) }}</td>
                <td class="right">{{ number_format((float) $statement->receiptsTotal, 2) }}</td>
                <td class="right">{{ number_format((float) $statement->closingBalance, 2) }}</td>
            </tr>
        </tbody>
    </table>
@endif

@if($statement->openInvoices->isNotEmpty())
    <div class="open-invoices">
        <h3>Outstanding Invoices ({{ $statement->openInvoices->count() }})</h3>
        <table>
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Date</th>
                    <th>Due</th>
                    <th class="right">Total</th>
                    <th class="right">Paid</th>
                    <th class="right">Balance</th>
                </tr>
            </thead>
            <tbody>
                @foreach($statement->openInvoices as $inv)
                    <tr>
                        <td>{{ $inv->invoice_number }}</td>
                        <td>{{ $inv->invoice_date?->format('Y-m-d') }}</td>
                        <td>{{ $inv->due_date?->format('Y-m-d') ?? '—' }}</td>
                        <td class="right">{{ number_format((float) $inv->total, 2) }}</td>
                        <td class="right">{{ number_format((float) $inv->amount_paid, 2) }}</td>
                        <td class="right"><strong>{{ number_format((float) $inv->balance_due, 2) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="footer">
    {{ $footerText ?? '' }}
</div>

</body>
</html>
