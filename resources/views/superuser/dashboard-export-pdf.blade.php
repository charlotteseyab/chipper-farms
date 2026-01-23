<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Superuser Dashboard Export - {{ now()->format('F j, Y') }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.3;
            color: #333;
            margin: 0;
            padding: 15px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 3px solid #10b981;
            padding-bottom: 15px;
        }
        
        .header h1 {
            color: #10b981;
            font-size: 24px;
            margin: 0;
            font-weight: bold;
        }
        
        .header p {
            color: #666;
            margin: 5px 0 0 0;
            font-size: 12px;
        }
        
        .info-bar {
            background-color: #f3f4f6;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            display: table;
            width: 100%;
        }
        
        .info-item {
            display: table-cell;
            padding: 0 15px;
            border-right: 1px solid #d1d5db;
        }
        
        .info-item:last-child {
            border-right: none;
        }
        
        .info-label {
            font-size: 9px;
            color: #6b7280;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        
        .info-value {
            font-size: 14px;
            font-weight: bold;
            color: #111827;
        }
        
        .section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        
        .section h2 {
            background-color: #10b981;
            color: white;
            font-size: 16px;
            margin: 0 0 12px 0;
            padding: 8px 12px;
            border-radius: 4px 4px 0 0;
        }
        
        .kpi-grid {
            display: table;
            width: 100%;
            margin-bottom: 15px;
            border-collapse: separate;
            border-spacing: 8px;
        }
        
        .kpi-row {
            display: table-row;
        }
        
        .kpi-cell {
            display: table-cell;
            width: 16.66%;
            padding: 12px;
            text-align: center;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            background-color: #f9fafb;
            vertical-align: middle;
        }
        
        .kpi-value {
            font-size: 20px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 5px;
        }
        
        .kpi-label {
            font-size: 9px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .data-table th {
            background-color: #f3f4f6;
            color: #374151;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 8px;
            text-align: left;
            border: 1px solid #d1d5db;
        }
        
        .data-table td {
            padding: 8px;
            border: 1px solid #e5e7eb;
            font-size: 11px;
        }
        
        .data-table tr:nth-child(even) {
            background-color: #f9fafb;
        }
        
        .data-table tr:hover {
            background-color: #f3f4f6;
        }
        
        .currency {
            text-align: right;
            font-weight: 600;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #e5e7eb;
            text-align: center;
            font-size: 9px;
            color: #6b7280;
        }
        
        .footer p {
            margin: 3px 0;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: 600;
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        @media print {
            .page-break {
                page-break-before: always;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Superuser Dashboard Export</h1>
        <p>Chipper Farms Management System</p>
        <p>Generated on {{ now()->format('F j, Y \a\t g:i A') }}</p>
    </div>

    <!-- Date Range Info -->
    <div class="info-bar">
        <div class="info-item">
            <div class="info-label">Date Range</div>
            <div class="info-value">
                @if($startDate)
                    {{ $startDate->format('M d, Y') }} - {{ $endDate->format('M d, Y') }}
                @else
                    All Time
                @endif
            </div>
        </div>
        <div class="info-item">
            <div class="info-label">Range Type</div>
            <div class="info-value">{{ strtoupper($range ?? 'ALL') }}</div>
        </div>
        @if($selectedUser)
        <div class="info-item">
            <div class="info-label">Selected User</div>
            <div class="info-value">{{ $selectedUser->first_name }} {{ $selectedUser->last_name }}</div>
        </div>
        @endif
    </div>

    <!-- Global KPIs -->
    <div class="section">
        <h2>Global Key Performance Indicators</h2>
        <div class="kpi-grid">
            <div class="kpi-row">
                <div class="kpi-cell">
                    <div class="kpi-value">{{ number_format($globalKpis['total_pigs'] ?? 0) }}</div>
                    <div class="kpi-label">Total Pigs</div>
                </div>
                <div class="kpi-cell">
                    <div class="kpi-value">₵{{ number_format($globalKpis['total_revenue'] ?? 0, 0) }}</div>
                    <div class="kpi-label">Total Revenue</div>
                </div>
                <div class="kpi-cell">
                    <div class="kpi-value">{{ number_format($globalKpis['total_sales'] ?? 0) }}</div>
                    <div class="kpi-label">Total Sales</div>
                </div>
                <div class="kpi-cell">
                    <div class="kpi-value">{{ number_format($globalKpis['total_farrowings'] ?? 0) }}</div>
                    <div class="kpi-label">Farrowings</div>
                </div>
                <div class="kpi-cell">
                    <div class="kpi-value">{{ number_format($globalKpis['total_weaned'] ?? 0) }}</div>
                    <div class="kpi-label">Weaned</div>
                </div>
                <div class="kpi-cell">
                    <div class="kpi-value">{{ number_format($globalKpis['critical_alerts'] ?? 0) }}</div>
                    <div class="kpi-label">Critical Alerts</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Workspace Breakdown -->
    @if(!empty($workspaceMetrics))
    <div class="section">
        <h2>Workspace Breakdown</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Workspace</th>
                    <th>Code</th>
                    <th style="text-align: right;">Pigs</th>
                    <th style="text-align: right;">Revenue (₵)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($workspaceMetrics as $ws)
                <tr>
                    <td><strong>{{ $ws['name'] }}</strong></td>
                    <td><span class="badge">{{ $ws['code'] }}</span></td>
                    <td style="text-align: right;">{{ number_format($ws['pigs']) }}</td>
                    <td style="text-align: right;" class="currency">₵{{ number_format($ws['revenue'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background-color: #f3f4f6; font-weight: bold;">
                    <td colspan="2"><strong>Total</strong></td>
                    <td style="text-align: right;">{{ number_format(collect($workspaceMetrics)->sum('pigs')) }}</td>
                    <td style="text-align: right;" class="currency">₵{{ number_format(collect($workspaceMetrics)->sum('revenue'), 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
    @endif

    <div class="footer">
        <p><strong>Chipper Farms Management System</strong></p>
        <p>This report was generated automatically on {{ now()->format('F d, Y \a\t g:i A') }}</p>
        <p>For questions about this report, please contact the system administrator</p>
        <p>Total Workspaces: {{ count($workspaceMetrics ?? []) }}</p>
    </div>
</body>
</html>
