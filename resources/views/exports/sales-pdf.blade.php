<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Records Report - Chipper Farms</title>
    <style>
        @page {
            margin: 1in;
            size: A4;
        }
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 11px;
            line-height: 1.3;
            color: #2c3e50;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 3px solid #3498db;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            color: #2c3e50;
            font-weight: bold;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        .header p {
            margin: 8px 0 0 0;
            color: #7f8c8d;
            font-size: 12px;
        }
        .company-info {
            margin-top: 10px;
            font-size: 10px;
            color: #95a5a6;
        }
        .stats {
            display: flex;
            justify-content: space-around;
            margin-bottom: 25px;
            background-color: #ecf0f1;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #bdc3c7;
        }
        .stat-item {
            text-align: center;
            flex: 1;
        }
        .stat-value {
            font-size: 20px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 9px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            border: 1px solid #ddd;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px 8px;
            text-align: left;
        }
        th {
            background-color: #3498db;
            color: white;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        td {
            font-size: 10px;
            background: white;
        }
        tr:nth-child(even) td {
            background: #f8f9fa;
        }
        tr:hover td {
            background: #e3f2fd;
        }
        .currency {
            font-weight: bold;
            color: #27ae60;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9px;
            color: #95a5a6;
            border-top: 2px solid #ecf0f1;
            padding-top: 15px;
        }
        .page-break {
            page-break-before: always;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Sales Records Report</h1>
        <p>Generated on {{ now()->format('F d, Y \a\t g:i A') }}</p>
        <div class="company-info">Chipper Farms Management System</div>
    </div>

    <div class="stats">
        <div class="stat-item">
            <div class="stat-value">{{ $stats['total_sales'] ?? 0 }}</div>
            <div class="stat-label">Total Sales</div>
        </div>
        <div class="stat-item">
            <div class="stat-value currency">GHS {{ number_format($stats['total_value'] ?? 0, 2) }}</div>
            <div class="stat-label">Total Value</div>
        </div>
        <div class="stat-item">
            <div class="stat-value currency">GHS {{ number_format($stats['average_price'] ?? 0, 2) }}</div>
            <div class="stat-label">Average Price</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">{{ number_format($stats['average_weight'] ?? 0, 1) }} kg</div>
            <div class="stat-label">Average Weight</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Pig Code</th>
                <th>Ear Tag</th>
                <th>Sale Date</th>
                <th>Buyer Name</th>
                <th>Weight (kg)</th>
                <th>Price (GHS)</th>
                <th>Purpose</th>
                <th>Sold By</th>
                <th>Payment Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($sales as $sale)
                <tr>
                    <td>{{ $sale->pig->code ?? 'N/A' }}</td>
                    <td>{{ $sale->pig->ear_tag_number ?? 'N/A' }}</td>
                    <td>{{ $sale->sale_date->format('M d, Y') }}</td>
                    <td>{{ $sale->buyer_name }}</td>
                    <td>{{ $sale->sale_weight ? number_format($sale->sale_weight, 1) : 'N/A' }}</td>
                    <td class="currency">GHS {{ number_format($sale->sale_price, 2) }}</td>
                    <td>{{ ucfirst($sale->sale_purpose) }}</td>
                    <td>{{ $sale->display_sold_by ?? 'N/A' }}</td>
                    <td>{{ ucfirst($sale->payment_status) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="no-data">No sales records found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p><strong>Chipper Farms Management System</strong></p>
        <p>This report was generated automatically on {{ now()->format('F d, Y \a\t g:i A') }}</p>
        <p>For questions about this report, please contact the system administrator</p>
        <p>Page 1 of 1 | Total Records: {{ $sales->count() }}</p>
    </div>
</body>
</html>
