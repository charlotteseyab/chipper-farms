<html>
<head>
    <meta charset="utf-8">
    <title>Feed Management Report ({{ $from }} to {{ $to }})</title>
    <style>
        /* Base Styles */
        body { 
            font-family: DejaVu Sans, sans-serif; 
            font-size: 11px; 
            color: #333; 
            margin: 0; 
            padding: 20px;
        }
        
        /* Header and Headings */
        h1 { 
            font-size: 24px; 
            margin-bottom: 20px; 
            text-align: center; 
            color: #1a4f78; /* Dark Blue */
            border-bottom: 3px solid #1a4f78;
            padding-bottom: 8px;
        }
        h2 { font-size: 18px; margin: 20px 0 10px; color: #1a4f78; }
        h3 { 
            margin-top: 20px; 
            margin-bottom: 10px; 
            font-size: 14px; 
            border-bottom: 1px solid #ccc; 
            padding-bottom: 4px;
            color: #333;
            font-weight: bold;
        }
        p { margin: 4px 0; }

        /* Tables */
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 10px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        th, td { 
            border: 1px solid #ddd; 
            padding: 8px 10px; 
            text-align: left; 
        }
        th { 
            background: #e9eff5; /* Light blue-grey header */
            color: #1a4f78;
            font-weight: bold; 
            font-size: 11px;
            border-bottom: 2px solid #1a4f78;
        }
        /* Zebra Striping */
        tbody tr:nth-child(even) {
            background-color: #f8f8f8;
        }

        /* Summary/Cards */
        .summary { 
            background: #fff; 
            padding: 0; 
            margin-bottom: 15px; 
        }
        .grid { 
            display: table; 
            width: 100%; 
            table-layout: fixed;
            border-collapse: separate; /* Allows spacing between cells */
            border-spacing: 10px;
        }
        .col { 
            display: table-cell; 
            vertical-align: top; 
            padding: 0;
        }
        .card { 
            border: 1px solid #d3e0ea; 
            background: #f7f9fc; 
            padding: 15px; 
            border-radius: 4px;
            text-align: center;
        }
        .muted { color: #666; font-size: 10px; }
        .data-value { 
            font-size: 20px;
            font-weight: 700;
            color: #000;
            margin-top: 5px;
        }

        /* Footer */
        .footer {
            position: fixed; /* Note: Position fixed might behave differently in Dompdf, but inline styles are generally reliable */
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
            text-align: center;
            padding: 10px 0;
            border-top: 1px solid #ccc;
            margin-top: 30px; /* Add margin above the footer content */
            font-size: 9px;
        }
        .generated-stamp {
            color: #999;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <h1>Feed Management Report</h1>
    <p style="text-align: center; font-size: 14px; color: #555;">Reporting Period: {{ $from }} to {{ $to }}</p>

    <div class="summary">
        <div class="grid">
            <div class="col">
                <div class="card">
                    <div class="muted">Total Scheduled Feed</div>
                    <div class="data-value">{{ number_format($totalScheduledKg ?? 0, 2) }} kg</div>
                </div>
            </div>
            <div class="col">
                <div class="card">
                    <div class="muted">Feed Types Scheduled</div>
                    <div class="data-value">{{ isset($byType) ? $byType->count() : 0 }}</div>
                </div>
            </div>
            <div class="col">
                <div class="card">
                    <div class="muted">Ingredients Used</div>
                    <div class="data-value">{{ isset($ingredientUsage) ? count($ingredientUsage) : 0 }}</div>
                </div>
            </div>
        </div>
    </div>

    <h3>Feed by Type</h3>
    <table>
        <thead>
            <tr>
                <th>Feed Type</th>
                <th>Scheduled Events</th>
                <th style="text-align: right;">Total Amount (kg)</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($byType ?? []) as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['count'] }}</td>
                    <td style="text-align: right;">{{ number_format($row['total_amount'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align: center;">No feed types recorded.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Daily Feed vs Average Weight</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th style="text-align: right;">Feed (kg)</th>
                <th style="text-align: right;">Avg Weight (kg)</th>
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
                    <td style="text-align: right;">{{ number_format(($feedByDate[$date] ?? 0), 2) }}</td>
                    <td style="text-align: right;">{{ number_format(($avgWeightByDate[$date] ?? 0), 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align: center;">No comparison data.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Estimated Ingredient Usage</h3>
    <table>
        <thead>
            <tr>
                <th>Ingredient</th>
                <th style="text-align: right;">Estimated Used (kg)</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($ingredientUsage ?? []) as $name => $qty)
                <tr>
                    <td>{{ $name }}</td>
                    <td style="text-align: right;">{{ number_format($qty, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="2" style="text-align: center;">No ingredient usage data.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Structure Feed Consumption - Daily</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Structure</th>
                <th style="text-align: right;">Total (kg)</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($dailyStructureTotals ?? []) as $date => $rows)
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $date }}</td>
                        <td>{{ $row['structure_name'] }}</td>
                        <td style="text-align: right;">{{ number_format($row['total'], 2) }}</td>
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="3" style="text-align: center;">No daily data.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Structure Feed Consumption - Monthly</h3>
    <table>
        <thead>
            <tr>
                <th>Month</th>
                <th>Structure</th>
                <th style="text-align: right;">Total (kg)</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($monthlyStructureTotals ?? []) as $month => $rows)
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $month }}</td>
                        <td>{{ $row['structure_name'] }}</td>
                        <td style="text-align: right;">{{ number_format($row['total'], 2) }}</td>
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="3" style="text-align: center;">No monthly data.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Structure Feed Consumption - Yearly</h3>
    <table>
        <thead>
            <tr>
                <th>Year</th>
                <th>Structure</th>
                <th style="text-align: right;">Total (kg)</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($yearlyStructureTotals ?? []) as $year => $rows)
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $year }}</td>
                        <td>{{ $row['structure_name'] }}</td>
                        <td style="text-align: right;">{{ number_format($row['total'], 2) }}</td>
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="3" style="text-align: center;">No yearly data.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Scheduled Feedings (Detail)</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Feed Type</th>
                <th style="text-align: right;">Amount (kg)</th>
                <th>Structure/Group</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($schedules ?? []) as $sch)
                <tr>
                    <td>{{ $sch->scheduled_for->toDateString() }}</td>
                    <td>{{ optional($sch->feedType)->name ?? ('#'.$sch->feed_type_id) }}</td>
                    <td style="text-align: right;">{{ number_format($sch->planned_amount, 2) }}</td>
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
                <tr><td colspan="5" style="text-align: center;">No scheduled feedings in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>This report was generated automatically by the Chipper Farms Management System</p>
        <p class="generated-stamp">Generated on: {{ date('Y-m-d H:i:s') }}</p>
    </div>
</body>
</html>
