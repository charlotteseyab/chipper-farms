<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Farm performance report</title>
    <style>
        @page { margin: 30px 34px 42px; }
        body { color:#172033; font-family:DejaVu Sans, sans-serif; font-size:10px; line-height:1.45; }
        h1,h2,p { margin:0; } h1 { font-size:22px; } h2 { font-size:13px; margin-bottom:9px; }
        .muted { color:#64748b; } .green { color:#087f5b; } .red { color:#be123c; }
        .header { border-bottom:2px solid #087f5b; padding-bottom:14px; margin-bottom:18px; }
        .header-table,.kpis,.two-col,.data { width:100%; border-collapse:collapse; }
        .header-table td { vertical-align:bottom; } .right { text-align:right; }
        .kpis td { border:1px solid #dfe5eb; padding:11px; width:20%; vertical-align:top; }
        .label { color:#64748b; font-size:8px; text-transform:uppercase; letter-spacing:.5px; }
        .value { font-size:15px; font-weight:bold; margin-top:4px; }
        .section { margin-top:18px; page-break-inside:avoid; }
        .two-col td { width:50%; vertical-align:top; } .two-col td:first-child { padding-right:7px; } .two-col td:last-child { padding-left:7px; }
        .panel { border:1px solid #dfe5eb; border-radius:6px; padding:12px; }
        .data th { background:#f4f7f9; color:#64748b; font-size:8px; text-transform:uppercase; text-align:left; padding:7px; }
        .data td { border-bottom:1px solid #edf0f3; padding:7px; }
        .badge { color:#087f5b; background:#e8f7f0; padding:3px 6px; }
        .footer { position:fixed; bottom:-28px; left:0; right:0; border-top:1px solid #dfe5eb; padding-top:7px; color:#94a3b8; text-align:center; font-size:8px; }
        .page-break { page-break-before:always; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table"><tr><td><div class="label green">Management reporting</div><h1>Farm performance report</h1><p class="muted">{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</p></td><td class="right muted">Generated {{ now()->format('d M Y, g:i A') }}</td></tr></table>
    </div>

    <table class="kpis"><tr>
        <td><div class="label">Closing herd</div><div class="value">{{ number_format($current['closing_herd']) }}</div></td>
        <td><div class="label">Sales revenue</div><div class="value">GHS {{ number_format($current['revenue'], 2) }}</div></td>
        <td><div class="label">Average sale</div><div class="value">GHS {{ number_format($current['average_sale'], 2) }}</div></td>
        <td><div class="label">Mortality</div><div class="value">{{ $current['mortality_rate'] === null ? 'N/A' : number_format($current['mortality_rate'], 2).'%' }}</div></td>
        <td><div class="label">Average / kg</div><div class="value">GHS {{ number_format($current['average_price_per_kg'], 2) }}</div></td>
    </tr></table>

    <div class="section panel">
        <h2>Herd movement reconciliation</h2>
        <table class="data"><thead><tr>@foreach(['Opening','Manually added','Purchased','Sold','Deaths','Closing','Variance'] as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead><tbody><tr>@foreach(['opening','manually_added','purchased','sold','deaths','closing','variance'] as $key)<td class="{{ in_array($key, ['sold','deaths']) ? 'red' : ($key === 'closing' ? 'green' : '') }}">{{ number_format($herdMovement[$key]) }}</td>@endforeach</tr></tbody></table>
        <p class="muted" style="margin-top:8px">Opening herd + additions − recorded sales − recorded deaths = closing herd.</p>
    </div>

    <table class="two-col section"><tr>
        <td><div class="panel"><h2>Breeding performance</h2><table class="data"><tbody>
            <tr><td>Matings</td><td class="right">{{ number_format($breeding['matings']) }}</td></tr>
            <tr><td>Farrowings</td><td class="right">{{ number_format($breeding['farrowings']) }}</td></tr>
            <tr><td>Weanings</td><td class="right">{{ number_format($breeding['weanings']) }}</td></tr>
            <tr><td>Average litter size</td><td class="right">{{ number_format($breeding['average_litter_size'], 1) }}</td></tr>
            <tr><td>Pregnancy rate</td><td class="right">{{ $breeding['pregnancy_rate'] === null ? 'N/A' : $breeding['pregnancy_rate'].'%' }}</td></tr>
        </tbody></table></div></td>
        <td><div class="panel"><h2>Growth and health</h2><table class="data"><tbody>
            <tr><td>Weight records</td><td class="right">{{ number_format($weights['records']) }}</td></tr>
            <tr><td>Average recorded weight</td><td class="right">{{ number_format($weights['average_kg'], 1) }} kg</td></tr>
            <tr><td>Deaths</td><td class="right">{{ number_format($current['deaths']) }}</td></tr>
            <tr><td>Mortality rate</td><td class="right">{{ $current['mortality_rate'] === null ? 'N/A' : number_format($current['mortality_rate'], 2).'%' }}</td></tr>
        </tbody></table></div></td>
    </tr></table>

    <div class="section panel"><h2>Piglet tracking</h2><table class="data"><thead><tr><th>Litters</th><th>Total born</th><th>Live births</th><th>Stillbirths</th><th>Pre-weaning deaths</th><th>Weaned</th><th>Survival</th></tr></thead><tbody><tr><td>{{ $pigletTracking['litters'] }}</td><td>{{ $pigletTracking['total_born'] }}</td><td>{{ $pigletTracking['live_births'] }}</td><td>{{ $pigletTracking['stillbirths'] }}</td><td>{{ $pigletTracking['pre_weaning_deaths'] }}</td><td>{{ $pigletTracking['weaned'] }}</td><td>{{ $pigletTracking['survival_rate'] === null ? 'N/A' : $pigletTracking['survival_rate'].'%' }}</td></tr></tbody></table></div>

    <table class="two-col section"><tr>
        <td><div class="panel"><h2>Storefront</h2><table class="data"><tbody>
            <tr><td>Orders placed</td><td class="right">{{ $storefront['orders'] }}</td></tr><tr><td>Completed</td><td class="right">{{ $storefront['completed'] }}</td></tr><tr><td>Open pipeline</td><td class="right">GHS {{ number_format($storefront['pipeline'], 2) }}</td></tr><tr><td>Realised revenue</td><td class="right">GHS {{ number_format($storefront['realised_revenue'], 2) }}</td></tr>
        </tbody></table></div></td>
        <td><div class="panel"><h2>Finance ledger</h2><table class="data"><tbody>
            <tr><td>Revenue</td><td class="right">GHS {{ number_format($finance['revenue'], 2) }}</td></tr><tr><td>Cost of sales</td><td class="right">GHS {{ number_format($finance['cost_of_sales'], 2) }}</td></tr><tr><td>Gross profit</td><td class="right">GHS {{ number_format($finance['gross_profit'], 2) }}</td></tr><tr><td>Net profit</td><td class="right">GHS {{ number_format($finance['net_profit'], 2) }}</td></tr>
        </tbody></table></div></td>
    </tr></table>

    <table class="two-col section"><tr>
        <td><div class="panel"><h2>Active herd composition</h2><table class="data"><thead><tr><th>Stock type</th><th class="right">Head</th></tr></thead><tbody>@forelse($distribution as $row)<tr><td>{{ str($row->label)->replace('_', ' ')->title() }}</td><td class="right">{{ number_format($row->total) }}</td></tr>@empty<tr><td colspan="2">No active herd data.</td></tr>@endforelse</tbody></table></div></td>
        <td><div class="panel"><h2>Operational exceptions</h2><table class="data"><tbody>@forelse($exceptions as $exception)<tr><td><strong>{{ $exception['title'] }}</strong><br><span class="muted">{{ $exception['detail'] }}</span></td><td class="right">{{ ucfirst($exception['severity']) }}</td></tr>@empty<tr><td><span class="badge">No exceptions detected</span></td></tr>@endforelse</tbody></table></div></td>
    </tr></table>

    <div class="page-break"></div>
    <div class="section"><h2>Facility occupancy</h2><p class="muted" style="margin-bottom:8px">{{ $facilities['structures'] }} structures · {{ $facilities['rooms'] }} rooms · {{ $facilities['utilization_rate'] === null ? 'Capacity not configured' : $facilities['utilization_rate'].'% occupied' }}</p>
        <table class="data"><thead><tr><th>Structure</th><th>Room</th><th>Animals</th><th>Capacity</th><th>Utilization</th></tr></thead><tbody>@forelse($facilities['details'] as $room) @php $utilization = $room->capacity > 0 ? round(($room->pigs_count / $room->capacity) * 100, 1) : null; @endphp<tr><td>{{ $room->structure?->name ?? 'Unassigned' }}</td><td>{{ $room->name }}</td><td>{{ $room->pigs_count }}</td><td>{{ $room->capacity ?: 'Not set' }}</td><td>{{ $utilization === null ? 'N/A' : $utilization.'%' }}</td></tr>@empty<tr><td colspan="5">No rooms available.</td></tr>@endforelse</tbody></table>
    </div>

    <div class="footer">Farm performance report · Values are scoped to the current organisation and selected business-event dates.</div>
</body>
</html>
