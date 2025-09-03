<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Death Records Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #333;
        }
        .header p {
            margin: 5px 0 0 0;
            color: #666;
        }
        .stats {
            display: flex;
            justify-content: space-around;
            margin-bottom: 30px;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }
        .stat-item {
            text-align: center;
        }
        .stat-value {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        .stat-label {
            font-size: 10px;
            color: #666;
            margin-top: 5px;
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
            background-color: #f8f9fa;
            font-weight: bold;
            font-size: 10px;
        }
        td {
            font-size: 10px;
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
    <div class="header">
        <h1>Death Records Report</h1>
        <p>Generated on {{ now()->format('F d, Y \a\t g:i A') }}</p>
    </div>

    <div class="stats">
        <div class="stat-item">
            <div class="stat-value">{{ $stats['total_deaths'] ?? 0 }}</div>
            <div class="stat-label">Total Deaths</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">{{ $stats['adult_deaths'] ?? 0 }}</div>
            <div class="stat-label">Adult Deaths</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">{{ $stats['piglet_deaths'] ?? 0 }}</div>
            <div class="stat-label">Piglet Deaths</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">{{ $stats['recent_deaths'] ?? 0 }}</div>
            <div class="stat-label">Last 30 Days</div>
        </div>
    </div>

    <!-- Adult Deaths Table -->
    <h2 style="margin-top: 30px; margin-bottom: 15px; color: #333; font-size: 18px; font-weight: bold;">Adult Deaths (60+ days)</h2>
    <table>
        <thead>
            <tr>
                <th>Pig Code</th>
                <th>Ear Tag</th>
                <th>Death Date</th>
                <th>Age at Death</th>
                <th>Death Cause</th>
                <th>Final Weight (kg)</th>
                <th>Disposal Method</th>
                <th>Recorded By</th>
            </tr>
        </thead>
        <tbody>
            @forelse($adultDeaths as $death)
                <tr>
                    <td>{{ $death->pig->code ?? 'N/A' }}</td>
                    <td>{{ $death->pig->ear_tag_number ?? 'N/A' }}</td>
                    <td>{{ $death->death_date->format('M d, Y') }}</td>
                    <td>{{ $death->age_at_death_display ?? 'Unknown' }}</td>
                    <td>{{ $death->death_cause_display }}</td>
                    <td>{{ $death->final_weight ? number_format($death->final_weight, 2) : 'N/A' }}</td>
                    <td>{{ $death->disposal_method_display }}</td>
                    <td>{{ $death->recordedBy->name ?? 'System' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center; padding: 20px;">No adult death records found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Piglet Mortality Table -->
    <h2 style="margin-top: 30px; margin-bottom: 15px; color: #333; font-size: 18px; font-weight: bold;">Piglet Mortality (Under 60 days)</h2>
    <table>
        <thead>
            <tr>
                <th>Pig Code</th>
                <th>Ear Tag</th>
                <th>Age at Death</th>
                <th>Death Date</th>
                <th>Death Cause</th>
                <th>Weight (kg)</th>
                <th>Disposal Method</th>
                <th>Recorded By</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pigletDeaths as $death)
                <tr>
                    <td>{{ $death->pig->code ?? 'N/A' }}</td>
                    <td>{{ $death->pig->ear_tag_number ?? 'N/A' }}</td>
                    <td>{{ $death->age_at_death_display ?? 'Unknown' }}</td>
                    <td>{{ $death->death_date->format('M d, Y') }}</td>
                    <td>{{ $death->death_cause_display }}</td>
                    <td>{{ $death->final_weight ? number_format($death->final_weight, 2) : 'N/A' }}</td>
                    <td>{{ $death->disposal_method_display }}</td>
                    <td>{{ $death->recordedBy->name ?? 'System' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center; padding: 20px;">No piglet death records found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>This report was generated by Chipper Farms Management System</p>
        <p>For questions about this report, please contact the system administrator.</p>
    </div>
</body>
</html>
