<html>
<head>
    <meta charset="utf-8">
    <title>Feed Analytics Report ({{ $from }} to {{ $to }})</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1f2937;
            margin: 0;
            padding: 24px;
            background: #f8fafc;
        }

        h1 {
            font-size: 26px;
            text-align: center;
            color: #0f172a;
            margin-bottom: 6px;
        }

        h2 {
            font-size: 16px;
            margin: 26px 0 8px;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        h3 {
            font-size: 13px;
            margin: 18px 0 6px;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .subtext {
            text-align: center;
            color: #475569;
            margin-bottom: 20px;
        }

        .card-grid {
            display: table;
            width: 100%;
            table-layout: fixed;
            border-spacing: 14px;
            margin-bottom: 8px;
        }

        .card-grid .card {
            display: table-cell;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px;
            text-align: center;
        }

        .card .label {
            font-size: 10px;
            text-transform: uppercase;
            color: #94a3b8;
            letter-spacing: .8px;
        }

        .card .value {
            font-size: 22px;
            font-weight: 700;
            margin-top: 4px;
            color: #0f172a;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background: #fff;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        th, td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }

        th {
            background: #eef2ff;
            color: #4338ca;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        tr:nth-child(even) td {
            background: #f8fafc;
        }

        .table-total td {
            background: #ecfeff !important;
            font-weight: 600;
            color: #0f172a;
        }

        .pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .5px;
            background: #eef2ff;
            color: #4338ca;
        }

        .section-divider {
            margin: 30px 0 12px;
            border-top: 2px solid #e2e8f0;
        }

        .footer {
            margin-top: 24px;
            text-align: center;
            color: #94a3b8;
            font-size: 9px;
        }
    </style>
</head>
<body>
    <h1>Feed Analytics Report</h1>
    <p class="subtext">Reporting window: {{ $from }} to {{ $to }}</p>

    <div class="card-grid">
        <div class="card">
            <div class="label">Total Scheduled Feed</div>
            <div class="value">{{ number_format($totalScheduledKg ?? 0, 2) }} kg</div>
        </div>
        <div class="card">
            <div class="label">Active Feed Types</div>
            <div class="value">{{ isset($byType) ? $byType->count() : 0 }}</div>
        </div>
        <div class="card">
            <div class="label">Unique Ingredients</div>
            <div class="value">{{ isset($ingredientUsage) ? count($ingredientUsage) : 0 }}</div>
        </div>
        <div class="card">
            <div class="label">Planned Schedules</div>
            <div class="value">{{ number_format(($schedules ?? collect())->count()) }}</div>
        </div>
    </div>

@php
    $pdfWeekKey = collect($weeklyIdealTotals ?? [])->keys()->first();
    $pdfMonthKey = collect($monthlyIdealTotals ?? [])->keys()->first();
    $pdfWeekTotal = $pdfWeekKey ? ($weeklyIdealTotals[$pdfWeekKey]['total'] ?? $weeklyIdealTotals[$pdfWeekKey] ?? 0) : 0;
    $pdfMonthTotal = $pdfMonthKey ? ($monthlyIdealTotals[$pdfMonthKey]['total'] ?? $monthlyIdealTotals[$pdfMonthKey] ?? 0) : 0;
@endphp

<h3>Estimated Feed Need (Summary)</h3>
<table>
    <thead>
        <tr>
            <th>Period</th>
            <th style="text-align:right;">Total (kg)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Week ({{ $pdfWeekKey ?? 'N/A' }})</td>
            <td style="text-align:right;">{{ number_format($pdfWeekTotal, 1) }}</td>
        </tr>
        <tr>
            <td>Month ({{ $pdfMonthKey ?? 'N/A' }})</td>
            <td style="text-align:right;">{{ number_format($pdfMonthTotal, 1) }}</td>
        </tr>
    </tbody>
</table>

    <h2>Feed Scheduling Overview</h2>
    <table>
        <thead>
            <tr>
                <th>Feed Type</th>
                <th style="text-align:center;">Events</th>
                <th style="text-align:right;">Total Amount (kg)</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($byType ?? []) as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td style="text-align:center;">{{ $row['count'] }}</td>
                    <td style="text-align:right;">{{ number_format($row['total_amount'], 1) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align:center;">No feed data recorded.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Weekly Feed Need vs Used (kg)</h3>
    <table>
        <thead>
            <tr>
                <th>Week</th>
                <th style="text-align:right;">Planned (kg)</th>
                <th style="text-align:right;">Fed (kg)</th>
                <th style="text-align:right;">Variance</th>
            </tr>
        </thead>
        <tbody>
            @php
                $weeks = collect($weeklyRequiredTotals ?? [])->keys()
                    ->merge(collect($weeklyFedTotals ?? [])->keys())
                    ->unique()->sort();
            @endphp
            @forelse($weeks as $week)
                @php
                    $planned = ($weeklyRequiredTotals[$week] ?? 0);
                    $fed = ($weeklyFedTotals[$week] ?? 0);
                    $variance = $fed - $planned;
                @endphp
                <tr>
                    <td>{{ $week }}</td>
                    <td style="text-align:right;">{{ number_format($planned, 2) }}</td>
                    <td style="text-align:right;">{{ number_format($fed, 2) }}</td>
                    <td style="text-align:right; {{ $variance >= 0 ? 'color: #15803d;' : 'color: #b91c1c;' }}">{{ number_format($variance, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center;">No data</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Monthly Feed Need vs Used (kg)</h3>
    <table>
        <thead>
            <tr>
                <th>Month</th>
                <th style="text-align:right;">Planned (kg)</th>
                <th style="text-align:right;">Fed (kg)</th>
                <th style="text-align:right;">Variance</th>
            </tr>
        </thead>
        <tbody>
            @php
                $months = collect($monthlyRequiredTotals ?? [])->keys()
                    ->merge(collect($monthlyFedTotals ?? [])->keys())
                    ->unique()->sort();
            @endphp
            @forelse($months as $month)
                @php
                    $planned = ($monthlyRequiredTotals[$month] ?? 0);
                    $fed = ($monthlyFedTotals[$month] ?? 0);
                    $variance = $fed - $planned;
                @endphp
                <tr>
                    <td>{{ $month }}</td>
                    <td style="text-align:right;">{{ number_format($planned, 2) }}</td>
                    <td style="text-align:right;">{{ number_format($fed, 2) }}</td>
                    <td style="text-align:right; {{ $variance >= 0 ? 'color: #15803d;' : 'color: #b91c1c;' }}">{{ number_format($variance, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center;">No data</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-divider"></div>

    <h2>Ingredient Utilisation</h2>
    <table>
        <thead>
            <tr>
                <th>Ingredient</th>
                <th style="text-align:right;">Used (kg)</th>
                <th style="text-align:right;">Protein %</th>
                <th style="text-align:right;">Energy (kcal)</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($ingredientDetails ?? []) as $name => $details)
                <tr>
                    <td>{{ $name }}</td>
                    <td style="text-align:right;">{{ number_format($details['total_used'], 1) }}</td>
                    <td style="text-align:right;">{{ number_format($details['protein_percentage'], 1) }}%</td>
                    <td style="text-align:right;">{{ number_format($details['energy_kcal'], 0) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center;">No ingredient activity found.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-divider"></div>

    <h2>Structure Consumption</h2>
    <h3><span class="pill">Daily</span></h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Structure</th>
                <th style="text-align:right;">Total (kg)</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($dailyStructureTotals ?? []) as $date => $rows)
                @php $total = collect($rows)->sum('total'); @endphp
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $date }}</td>
                        <td>{{ $row['structure_name'] }}</td>
                        <td style="text-align:right;">{{ number_format($row['total'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="table-total">
                    <td colspan="2">Daily total for {{ $date }}</td>
                    <td style="text-align:right;">{{ number_format($total, 2) }} kg</td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align:center;">No consumption recorded.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3><span class="pill">Weekly</span></h3>
    <table>
        <thead>
            <tr>
                <th>Week</th>
                <th>Structure</th>
                <th style="text-align:right;">Total (kg)</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($weeklyStructureTotals ?? []) as $week => $rows)
                @php $total = collect($rows)->sum('total'); @endphp
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $week }}</td>
                        <td>{{ $row['structure_name'] }}</td>
                        <td style="text-align:right;">{{ number_format($row['total'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="table-total">
                    <td colspan="2">Weekly total for {{ $week }}</td>
                    <td style="text-align:right;">{{ number_format($total, 2) }} kg</td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align:center;">No weekly totals.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3><span class="pill">Monthly</span></h3>
    <table>
        <thead>
            <tr>
                <th>Month</th>
                <th>Structure</th>
                <th style="text-align:right;">Total (kg)</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($monthlyStructureTotals ?? []) as $month => $rows)
                @php $total = collect($rows)->sum('total'); @endphp
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $month }}</td>
                        <td>{{ $row['structure_name'] }}</td>
                        <td style="text-align:right;">{{ number_format($row['total'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="table-total">
                    <td colspan="2">Monthly total for {{ $month }}</td>
                    <td style="text-align:right;">{{ number_format($total, 2) }} kg</td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align:center;">No monthly totals.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3><span class="pill">Yearly</span></h3>
    <table>
        <thead>
            <tr>
                <th>Year</th>
                <th>Structure</th>
                <th style="text-align:right;">Total (kg)</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($yearlyStructureTotals ?? []) as $year => $rows)
                @php $total = collect($rows)->sum('total'); @endphp
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $year }}</td>
                        <td>{{ $row['structure_name'] }}</td>
                        <td style="text-align:right;">{{ number_format($row['total'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="table-total">
                    <td colspan="2">Year total for {{ $year }}</td>
                    <td style="text-align:right;">{{ number_format($total, 2) }} kg</td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align:center;">No yearly totals.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-divider"></div>

    <h2>Room Consumption</h2>
    <h3><span class="pill">Daily</span></h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Room</th>
                <th style="text-align:right;">Total (kg)</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($dailyRoomTotals ?? []) as $date => $rows)
                @php $total = collect($rows)->sum('total'); @endphp
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $date }}</td>
                        <td>{{ $row['room_name'] }}</td>
                        <td style="text-align:right;">{{ number_format($row['total'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="table-total">
                    <td colspan="2">Daily total for {{ $date }}</td>
                    <td style="text-align:right;">{{ number_format($total, 2) }} kg</td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align:center;">No room consumption recorded.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3><span class="pill">Weekly</span></h3>
    <table>
        <thead>
            <tr>
                <th>Week</th>
                <th>Room</th>
                <th style="text-align:right;">Total (kg)</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($weeklyRoomTotals ?? []) as $week => $rows)
                @php $total = collect($rows)->sum('total'); @endphp
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $week }}</td>
                        <td>{{ $row['room_name'] }}</td>
                        <td style="text-align:right;">{{ number_format($row['total'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="table-total">
                    <td colspan="2">Weekly total for {{ $week }}</td>
                    <td style="text-align:right;">{{ number_format($total, 2) }} kg</td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align:center;">No weekly room totals.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3><span class="pill">Monthly</span></h3>
    <table>
        <thead>
            <tr>
                <th>Month</th>
                <th>Room</th>
                <th style="text-align:right;">Total (kg)</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($monthlyRoomTotals ?? []) as $month => $rows)
                @php $total = collect($rows)->sum('total'); @endphp
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $month }}</td>
                        <td>{{ $row['room_name'] }}</td>
                        <td style="text-align:right;">{{ number_format($row['total'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="table-total">
                    <td colspan="2">Monthly total for {{ $month }}</td>
                    <td style="text-align:right;">{{ number_format($total, 2) }} kg</td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align:center;">No monthly room totals.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-divider"></div>

    <h2>Feed vs Average Weight</h2>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th style="text-align:right;">Feed (kg)</th>
                <th style="text-align:right;">Avg Weight (kg)</th>
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
                    <td style="text-align:right;">{{ number_format(($feedByDate[$date] ?? 0), 2) }}</td>
                    <td style="text-align:right;">{{ number_format(($avgWeightByDate[$date] ?? 0), 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align:center;">No comparison data available.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-divider"></div>

    <h2>Scheduled Feedings (Detail)</h2>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Feed Type</th>
                <th style="text-align: right;">Amount (kg)</th>
                <th>Structure / Group</th>
                <th>Room</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($schedules ?? []) as $sch)
                <tr>
                    <td>{{ $sch->scheduled_for->toDateString() }}</td>
                    <td>{{ optional($sch->feedType)->name ?? ('#'.$sch->feed_type_id) }}</td>
                    <td style="text-align: right;">{{ number_format($sch->planned_amount, 2) }}</td>
                    <td>{{ $sch->structure->name ?? ($sch->group->name ?? '—') }}</td>
                    <td>{{ $roomNames[$sch->room_id] ?? '—' }}</td>
                    <td>{{ ucfirst($sch->status) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align: center;">No scheduled feedings in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Ideal Feed Requirement (Theoretical, by Stage)</h2>
    <p style="color:#15803d; margin-bottom: 6px;">Calculated using population & per-stage setup</p>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th style="text-align:right;">Piglets</th>
                <th style="text-align:right;">Weaners</th>
                <th style="text-align:right;">Growers</th>
                <th style="text-align:right;">Finishers</th>
                <th style="text-align:right;">Boars</th>
                <th style="text-align:right;">Preg. Sows</th>
                <th style="text-align:right;">Other Sows</th>
                <th style="text-align:right;color:#166534">Total (kg)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($dailyIdealFeed as $date => $info)
            <tr>
                <td>{{ $date }}</td>
                <td style="text-align:right;">{{ number_format($info['needed']['piglets'] ?? 0, 1) }}</td>
                <td style="text-align:right;">{{ number_format($info['needed']['weaners'] ?? 0, 1) }}</td>
                <td style="text-align:right;">{{ number_format($info['needed']['growers'] ?? 0, 1) }}</td>
                <td style="text-align:right;">{{ number_format($info['needed']['finishers'] ?? 0, 1) }}</td>
                <td style="text-align:right;">{{ number_format($info['needed']['boars'] ?? 0, 1) }}</td>
                <td style="text-align:right;">{{ number_format($info['needed']['pregnant_sows'] ?? 0, 1) }}</td>
                <td style="text-align:right;">{{ number_format($info['needed']['other_sows'] ?? 0, 1) }}</td>
                <td style="text-align:right;color:#166534;font-weight:bold">{{ number_format($info['needed']['total'] ?? 0, 1) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h3 style="margin-top:18px;">Weekly Required Feed by Stage (kg)</h3>
    <table>
        <thead>
            <tr>
                <th>Week</th>
                <th style="text-align:right;">Piglets</th>
                <th style="text-align:right;">Weaners</th>
                <th style="text-align:right;">Growers</th>
                <th style="text-align:right;">Finishers</th>
                <th style="text-align:right;">Boars</th>
                <th style="text-align:right;">Preg. Sows</th>
                <th style="text-align:right;">Other Sows</th>
                <th style="text-align:right;color:#166534">Total (kg)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($weeklyIdealTotals as $week => $totals)
            <tr>
                <td>{{ $week }}</td>
                <td style="text-align:right;">{{ number_format($totals['piglets'] ?? 0, 1) }}</td>
                <td style="text-align:right;">{{ number_format($totals['weaners'] ?? 0, 1) }}</td>
                <td style="text-align:right;">{{ number_format($totals['growers'] ?? 0, 1) }}</td>
                <td style="text-align:right;">{{ number_format($totals['finishers'] ?? 0, 1) }}</td>
                <td style="text-align:right;">{{ number_format($totals['boars'] ?? 0, 1) }}</td>
                <td style="text-align:right;">{{ number_format($totals['pregnant_sows'] ?? 0, 1) }}</td>
                <td style="text-align:right;">{{ number_format($totals['other_sows'] ?? 0, 1) }}</td>
                <td style="text-align:right;color:#166534;font-weight:bold">{{ number_format($totals['total'] ?? 0, 1) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h3 style="margin-top:18px;">Monthly Required Feed by Stage (kg)</h3>
    <table>
        <thead>
            <tr>
                <th>Month</th>
                <th style="text-align:right;">Piglets</th>
                <th style="text-align:right;">Weaners</th>
                <th style="text-align:right;">Growers</th>
                <th style="text-align:right;">Finishers</th>
                <th style="text-align:right;">Boars</th>
                <th style="text-align:right;">Preg. Sows</th>
                <th style="text-align:right;">Other Sows</th>
                <th style="text-align:right;color:#166534">Total (kg)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($monthlyIdealTotals as $month => $totals)
            <tr>
                <td>{{ $month }}</td>
                <td style="text-align:right;">{{ number_format($totals['piglets'] ?? 0, 1) }}</td>
                <td style="text-align:right;">{{ number_format($totals['weaners'] ?? 0, 1) }}</td>
                <td style="text-align:right;">{{ number_format($totals['growers'] ?? 0, 1) }}</td>
                <td style="text-align:right;">{{ number_format($totals['finishers'] ?? 0, 1) }}</td>
                <td style="text-align:right;">{{ number_format($totals['boars'] ?? 0, 1) }}</td>
                <td style="text-align:right;">{{ number_format($totals['pregnant_sows'] ?? 0, 1) }}</td>
                <td style="text-align:right;">{{ number_format($totals['other_sows'] ?? 0, 1) }}</td>
                <td style="text-align:right;color:#166534;font-weight:bold">{{ number_format($totals['total'] ?? 0, 1) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Generated automatically by Chipper Farms • {{ now()->format('Y-m-d H:i:s') }}
    </div>
</body>
</html>
