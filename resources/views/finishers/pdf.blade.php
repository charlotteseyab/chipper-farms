<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Finisher Management Report - {{ date('Y-m-d') }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #1C6C51;
            padding-bottom: 15px;
        }
        
        .header h1 {
            color: #1C6C51;
            margin: 0;
            font-size: 24px;
        }
        
        .header p {
            margin: 5px 0;
            color: #666;
        }
        
        .stats-section {
            margin-bottom: 25px;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #e9ecef;
        }
        
        .stats-grid {
            display: table;
            width: 100%;
        }
        
        .stats-row {
            display: table-row;
        }
        
        .stats-cell {
            display: table-cell;
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        
        .stats-label {
            font-weight: bold;
            width: 40%;
        }
        
        .stats-value {
            text-align: right;
            font-weight: bold;
            color: #1C6C51;
        }
        
        .section-title {
            color: #1C6C51;
            font-size: 16px;
            font-weight: bold;
            margin: 20px 0 10px 0;
            border-bottom: 1px solid #1C6C51;
            padding-bottom: 5px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        th {
            background-color: #1C6C51;
            color: white;
            font-weight: bold;
            font-size: 11px;
        }
        
        td {
            font-size: 10px;
        }
        
        .status-growing {
            background-color: #dbeafe;
            color: #1e40af;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
        }
        
        .status-near-market {
            background-color: #fef3c7;
            color: #d97706;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
        }
        
        .status-market-ready {
            background-color: #dcfce7;
            color: #16a34a;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>Finisher Management Report</h1>
        <p>Generated on {{ date('F j, Y \a\t g:i A') }}</p>
        <p>Chipper Farms - Pig Management System</p>
    </div>

    <!-- Statistics Summary -->
    <div class="stats-section">
        <h2 class="section-title">Summary Statistics</h2>
        <div class="stats-grid">
            <div class="stats-row">
                <div class="stats-cell stats-label">Total Finishers (50kg+):</div>
                <div class="stats-cell stats-value">{{ $stats['total_finishers'] }}</div>
            </div>
            <div class="stats-row">
                <div class="stats-cell stats-label">Growing (50-80kg):</div>
                <div class="stats-cell stats-value">{{ $stats['growing'] }}</div>
            </div>
            <div class="stats-row">
                <div class="stats-cell stats-label">Near Market (80-100kg):</div>
                <div class="stats-cell stats-value">{{ $stats['near_market'] }}</div>
            </div>
            <div class="stats-row">
                <div class="stats-cell stats-label">Market Ready (100kg+):</div>
                <div class="stats-cell stats-value">{{ $stats['market_ready'] }}</div>
            </div>
            <div class="stats-row">
                <div class="stats-cell stats-label">Average Weight:</div>
                <div class="stats-cell stats-value">{{ number_format($stats['average_weight'] ?? 0, 1) }}kg</div>
            </div>
            <div class="stats-row">
                <div class="stats-cell stats-label">Estimated Total Value:</div>
                <div class="stats-cell stats-value">${{ number_format(($stats['average_weight'] ?? 0) * $stats['total_finishers'] * 8, 0) }}</div>
            </div>
        </div>
    </div>

    <!-- Finisher Details Table -->
    <h2 class="section-title">Finisher Details</h2>
    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Gender</th>
                <th>Age (Days)</th>
                <th>Weight (kg)</th>
                <th>Weigh Date</th>
                <th>Status</th>
                <th>Mother</th>
                <th>Father</th>
                <th>Est. Value</th>
            </tr>
        </thead>
        <tbody>
            @forelse($finishers as $pig)
                <tr>
                    <td>{{ $pig->code }}</td>
                    <td>{{ $pig->gender === 'M' ? 'Male' : 'Female' }}</td>
                    <td>{{ $pig->age_in_days }}</td>
                    <td>{{ $pig->current_weight ?? 'N/A' }}</td>
                    <td>{{ $pig->weigh_date ? \Carbon\Carbon::parse($pig->weigh_date)->format('M j, Y') : 'N/A' }}</td>
                    <td>
                        @if($pig->current_weight >= 100)
                            <span class="status-market-ready">Market Ready</span>
                        @elseif($pig->current_weight >= 80)
                            <span class="status-near-market">Near Market</span>
                        @else
                            <span class="status-growing">Growing</span>
                        @endif
                    </td>
                    <td>{{ $pig->mother ? $pig->mother->code : 'N/A' }}</td>
                    <td>{{ $pig->father ? $pig->father->code : 'N/A' }}</td>
                    <td>${{ $pig->current_weight ? number_format($pig->current_weight * 8, 0) : 'N/A' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align: center; color: #666;">No finishers found</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Category Breakdown -->
    @if($stats['total_finishers'] > 0)
        <div class="page-break">
            <h2 class="section-title">Category Breakdown</h2>
            
            @if($stats['growing'] > 0)
                <h3 style="color: #1e40af; margin-top: 20px;">Growing Finishers (50-80kg)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Weight (kg)</th>
                            <th>Progress to Market</th>
                            <th>Est. Days to Market</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($finishers->whereBetween('current_weight', [50, 79.99]) as $pig)
                            <tr>
                                <td>{{ $pig->code }}</td>
                                <td>{{ $pig->current_weight }}kg</td>
                                <td>{{ number_format((($pig->current_weight - 50) / 50) * 100, 1) }}%</td>
                                <td>{{ ceil((100 - $pig->current_weight) / 0.7) }} days</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if($stats['near_market'] > 0)
                <h3 style="color: #d97706; margin-top: 20px;">Near Market Ready (80-100kg)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Weight (kg)</th>
                            <th>Progress to Market</th>
                            <th>Est. Days to Market</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($finishers->whereBetween('current_weight', [80, 99.99]) as $pig)
                            <tr>
                                <td>{{ $pig->code }}</td>
                                <td>{{ $pig->current_weight }}kg</td>
                                <td>{{ number_format((($pig->current_weight - 80) / 20) * 100, 1) }}%</td>
                                <td>{{ ceil((100 - $pig->current_weight) / 0.7) }} days</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if($stats['market_ready'] > 0)
                <h3 style="color: #16a34a; margin-top: 20px;">Market Ready (100kg+)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Weight (kg)</th>
                            <th>Market Status</th>
                            <th>Est. Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($finishers->where('current_weight', '>=', 100) as $pig)
                            <tr>
                                <td>{{ $pig->code }}</td>
                                <td>{{ $pig->current_weight }}kg</td>
                                <td>
                                    @if($pig->current_weight >= 120)
                                        <span style="color: #dc2626;">Urgent Sale</span>
                                    @elseif($pig->current_weight >= 110)
                                        <span style="color: #d97706;">Prime Weight</span>
                                    @else
                                        <span style="color: #16a34a;">Ready for Sale</span>
                                    @endif
                                </td>
                                <td>${{ number_format($pig->current_weight * 8, 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <p>This report was generated by the Chipper Farms Pig Management System</p>
        <p>Report Date: {{ date('F j, Y \a\t g:i A') }} | Total Pages: 1-2</p>
    </div>
</body>
</html>
