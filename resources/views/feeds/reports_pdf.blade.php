<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
        h1 { font-size: 20px; margin-bottom: 10px; text-align: center; }
        h2 { font-size: 16px; margin: 14px 0 8px; }
        h3 { margin-top: 14px; margin-bottom: 6px; font-size: 14px; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
        p { margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
        th { background: #f5f5f5; font-weight: bold; }
        .summary { background: #fafafa; padding: 8px; border: 1px solid #eee; margin-bottom: 12px; }
        .grid { display: table; width: 100%; table-layout: fixed; }
        .col { display: table-cell; vertical-align: top; padding: 6px; }
        .card { border: 1px solid #eee; background: #fff; padding: 8px; border-radius: 4px; }
        .muted { color: #666; }
    </style>
</head>
<body>
    <h1>Feed Management Report ({{ $from }} to {{ $to }})</h1>

    <div class="summary">
        <div class="grid">
            <div class="col card">
                <div class="muted">Total Scheduled Feed</div>
                <div style="font-size:18px;font-weight:bold;">{{ number_format($totalScheduledKg ?? 0, 2) }} kg</div>
            </div>
            <div class="col card">
                <div class="muted">Feed Types Scheduled</div>
                <div style="font-size:18px;font-weight:bold;">{{ isset($byType) ? $byType->count() : 0 }}</div>
            </div>
            <div class="col card">
                <div class="muted">Ingredients Used</div>
                <div style="font-size:18px;font-weight:bold;">{{ isset($ingredientUsage) ? count($ingredientUsage) : 0 }}</div>
            </div>
        </div>
        <p class="muted">Period: {{ $from }} → {{ $to }}</p>
    </div>

    <h3>Feed by Type</h3>
    <table>
        <thead>
            <tr>
                <th>Feed Type</th>
                <th>Scheduled Events</th>
                <th>Total Amount (kg)</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($byType ?? []) as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['count'] }}</td>
                    <td>{{ number_format($row['total_amount'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3">No feed types recorded.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Daily Feed vs Average Weight</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Feed (kg)</th>
                <th>Avg Weight (kg)</th>
            </tr>
        </thead>
        <tbody>
            @php
                $allDates = collect(($feedByDate ?? [])->keys())
                    ->merge(collect(($avgWeightByDate ?? [])->keys()))
                    ->unique()->sort();
            @endphp
            @forelse($allDates as $date)
                <tr>
                    <td>{{ $date }}</td>
                    <td>{{ number_format(($feedByDate[$date] ?? 0), 2) }}</td>
                    <td>{{ number_format(($avgWeightByDate[$date] ?? 0), 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3">No comparison data.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Estimated Ingredient Usage</h3>
    <table>
        <thead>
            <tr>
                <th>Ingredient</th>
                <th>Estimated Used (kg)</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($ingredientUsage ?? []) as $name => $qty)
                <tr>
                    <td>{{ $name }}</td>
                    <td>{{ number_format($qty, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="2">No ingredient usage data.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Scheduled Feedings (Detail)</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Feed Type</th>
                <th>Amount (kg)</th>
                <th>Structure/Group</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($schedules ?? []) as $sch)
                <tr>
                    <td>{{ $sch->scheduled_for->toDateString() }}</td>
                    <td>{{ optional($sch->feedType)->name ?? ('#'.$sch->feed_type_id) }}</td>
                    <td>{{ number_format($sch->planned_amount, 2) }}</td>
                    <td>
                        @if($sch->structure)
                            {{ $sch->structure->name }}
                        @elseif($sch->group)
                            {{ $sch->group->name }}
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ ucfirst($sch->status) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No scheduled feedings in this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
