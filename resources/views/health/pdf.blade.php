<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Health Report - {{ $pig->code ?? $pig->ear_tag_number }}</title>
    <style>
        @page { margin: 30px 34px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 10px; line-height: 1.45; }
        .header { background: #1e3a8a; color: #fff; padding: 20px 22px; border-radius: 8px; margin-bottom: 16px; }
        .header h1 { margin: 0 0 5px; font-size: 22px; }
        .header p { margin: 0; color: #dbeafe; font-size: 10px; }
        .meta { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 -8px 16px; }
        .meta td { width: 25%; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; vertical-align: top; }
        .label { color: #64748b; font-size: 8px; text-transform: uppercase; letter-spacing: .5px; }
        .value { margin-top: 3px; color: #0f172a; font-size: 12px; font-weight: bold; }
        .summary { width: 100%; border-collapse: separate; border-spacing: 7px 0; margin: 0 -7px 18px; }
        .summary td { width: 25%; padding: 10px; color: #fff; border-radius: 6px; }
        .summary .total { background: #4f46e5; }
        .summary .meds { background: #2563eb; }
        .summary .active { background: #dc2626; }
        .summary .resolved { background: #059669; }
        .summary-number { font-size: 18px; font-weight: bold; }
        .summary-label { font-size: 8px; text-transform: uppercase; }
        h2 { color: #0f172a; font-size: 15px; margin: 18px 0 7px; padding-bottom: 5px; border-bottom: 2px solid #cbd5e1; }
        .current { background: #eef2ff; border-left: 4px solid #4f46e5; padding: 10px 12px; margin-bottom: 15px; }
        .current strong { color: #312e81; }
        table.records { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .records th { background: #e2e8f0; color: #334155; padding: 6px 5px; text-align: left; font-size: 8px; text-transform: uppercase; }
        .records td { border-bottom: 1px solid #e2e8f0; padding: 6px 5px; vertical-align: top; word-wrap: break-word; }
        .records tr:nth-child(even) td { background: #f8fafc; }
        .empty { padding: 18px; text-align: center; color: #64748b; border: 1px dashed #cbd5e1; }
        .badge { display: inline-block; padding: 2px 5px; border-radius: 8px; background: #e0e7ff; color: #3730a3; font-size: 8px; font-weight: bold; text-transform: uppercase; }
        .footer { margin-top: 18px; border-top: 1px solid #cbd5e1; padding-top: 7px; color: #64748b; font-size: 8px; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Pig Health & Medication Report</h1>
        <p>Complete clinical history generated {{ now()->format('F d, Y \a\t H:i') }}</p>
    </div>

    <table class="meta">
        <tr>
            <td><div class="label">Pig ID</div><div class="value">{{ $pig->code ?? 'N/A' }}</div></td>
            <td><div class="label">Current identity</div><div class="value">{{ $pig->identityAt() }}</div></td>
            <td><div class="label">Breed</div><div class="value">{{ $pig->breed ?? 'N/A' }}</div></td>
            <td><div class="label">Gender / Stock</div><div class="value">{{ $pig->gender === 'M' ? 'Male' : ($pig->gender === 'F' ? 'Female' : 'N/A') }} · {{ ucfirst($pig->stock_type ?? 'N/A') }}</div></td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td class="total"><div class="summary-number">{{ $summary['health_records'] }}</div><div class="summary-label">Health records</div></td>
            <td class="meds"><div class="summary-number">{{ $summary['medications'] }}</div><div class="summary-label">Medications</div></td>
            <td class="active"><div class="summary-number">{{ $summary['active'] }}</div><div class="summary-label">Active records</div></td>
            <td class="resolved"><div class="summary-number">{{ $summary['resolved'] }}</div><div class="summary-label">Resolved records</div></td>
        </tr>
    </table>

    <div class="current">
        <strong>Current clinical summary:</strong>
        @if($lastRecord)
            {{ $lastRecord->condition }} ({{ ucfirst($lastRecord->severity ?: 'severity not specified') }}) —
            treatment {{ ucfirst($lastRecord->treatment_status ?: 'not set') }}, outcome {{ ucfirst($lastRecord->outcome ?: 'not assessed') }}.
            @if($lastMedication) Latest medication: {{ $lastMedication->medication_name }} on {{ $lastMedication->administration_date->format('M d, Y') }}. @endif
        @else
            No health records have been entered for this pig.
        @endif
    </div>

    <h2>Health Records</h2>
    @if($healthRecords->isEmpty())
        <div class="empty">No health records available.</div>
    @else
        <table class="records">
            <thead><tr><th style="width:10%">Date</th><th style="width:15%">Condition</th><th style="width:9%">Severity</th><th style="width:17%">Diagnosis / Symptoms</th><th style="width:20%">Treatment Plan</th><th style="width:13%">Status / Outcome</th><th style="width:16%">Resolution</th></tr></thead>
            <tbody>
                @foreach($healthRecords as $record)
                    <tr>
                        <td>{{ $record->record_date?->format('Y-m-d') }}<br>{{ $pig->identityAt($record->record_date) }}</td>
                        <td><strong>{{ $record->condition }}</strong>@if($record->voided_at)<br><em>VOIDED: {{ $record->void_reason }}</em>@endif</td>
                        <td><span class="badge">{{ $record->severity ?: 'N/A' }}</span></td>
                        <td>{{ $record->diagnosis ?: $record->symptoms ?: '—' }}</td>
                        <td>{{ $record->treatment_plan ?: '—' }}</td>
                        <td>{{ ucfirst($record->treatment_status ?: 'Not set') }}<br>{{ ucfirst($record->outcome ?: 'Not assessed') }}</td>
                        <td>@if($record->resolved_at){{ $record->resolved_at->format('Y-m-d') }}<br>@endif{{ $record->resolution_notes ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Medication Records</h2>
    @if($medicationRecords->isEmpty())
        <div class="empty">No medication records available.</div>
    @else
        <table class="records">
            <thead><tr><th style="width:12%">Date</th><th style="width:21%">Medication</th><th style="width:14%">Dosage</th><th style="width:14%">Route</th><th style="width:17%">Purpose</th><th style="width:22%">Notes</th></tr></thead>
            <tbody>
                @foreach($medicationRecords as $record)
                    <tr>
                        <td>{{ $record->administration_date?->format('Y-m-d') }}<br>{{ $pig->identityAt($record->administration_date) }}</td>
                        <td><strong>{{ $record->medication_name }}</strong>@if($record->voided_at)<br><em>VOIDED: {{ $record->void_reason }}</em>@endif<br>Withdrawal: {{ $record->withdrawal_period_days === null ? 'not recorded' : $record->withdrawal_period_days.' days' }}</td>
                        <td>{{ $record->dosage }}</td>
                        <td>{{ $record->route ?: '—' }}</td>
                        <td>{{ $record->purpose ?: '—' }}</td>
                        <td>{{ $record->notes ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">Confidential animal health record · {{ $pig->code ?? $pig->ear_tag_number }}</div>
</body>
</html>
