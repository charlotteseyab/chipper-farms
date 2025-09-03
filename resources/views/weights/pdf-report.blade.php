<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Weight Report - {{ $pig->ear_tag_number }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #2563eb;
            font-size: 24px;
            margin: 0 0 10px 0;
        }
        
        .header p {
            color: #666;
            margin: 5px 0;
        }
        
        .pig-info {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .pig-info h2 {
            color: #1e40af;
            font-size: 18px;
            margin: 0 0 15px 0;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 5px;
        }
        
        .info-grid {
            display: table;
            width: 100%;
        }
        
        .info-row {
            display: table-row;
        }
        
        .info-cell {
            display: table-cell;
            padding: 8px 15px 8px 0;
            vertical-align: top;
            width: 25%;
        }
        
        .info-label {
            font-weight: bold;
            color: #374151;
        }
        
        .info-value {
            color: #1f2937;
        }
        
        .analytics {
            margin-bottom: 30px;
        }
        
        .analytics h2 {
            color: #1e40af;
            font-size: 18px;
            margin: 0 0 20px 0;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 5px;
        }
        
        .stats-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        
        .stat-item {
            display: table-cell;
            text-align: center;
            padding: 15px;
            background-color: #f1f5f9;
            border: 1px solid #e2e8f0;
            width: 25%;
        }
        
        .stat-value {
            font-size: 20px;
            font-weight: bold;
            color: #1e40af;
            display: block;
        }
        
        .stat-label {
            font-size: 11px;
            color: #64748b;
            margin-top: 5px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .table th {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 10px 8px;
            text-align: left;
            font-weight: bold;
            color: #374151;
            font-size: 11px;
        }
        
        .table td {
            border: 1px solid #e2e8f0;
            padding: 8px;
            font-size: 11px;
        }
        
        .table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        
        .performance-excellent { color: #059669; font-weight: bold; }
        .performance-good { color: #2563eb; font-weight: bold; }
        .performance-average { color: #d97706; font-weight: bold; }
        .performance-poor { color: #dc2626; font-weight: bold; }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            color: #64748b;
            font-size: 10px;
        }
        
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>Weight Analysis Report</h1>
        <p><strong>{{ $pig->ear_tag_number }}</strong> - {{ $pig->code }}</p>
        <p>Generated on {{ now()->format('F d, Y \a\t H:i') }}</p>
    </div>

    <!-- Pig Information -->
    <div class="pig-info">
        <h2>Pig Information</h2>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-cell">
                    <div class="info-label">Ear Tag Number:</div>
                    <div class="info-value">{{ $pig->ear_tag_number }}</div>
                </div>
                <div class="info-cell">
                    <div class="info-label">Pig Code:</div>
                    <div class="info-value">{{ $pig->code }}</div>
                </div>
                <div class="info-cell">
                    <div class="info-label">Breed:</div>
                    <div class="info-value">{{ $pig->breed }}</div>
                </div>
                <div class="info-cell">
                    <div class="info-label">Gender:</div>
                    <div class="info-value">{{ $pig->gender === 'M' ? 'Male' : 'Female' }}</div>
                </div>
            </div>
            <div class="info-row">
                <div class="info-cell">
                    <div class="info-label">Date of Birth:</div>
                    <div class="info-value">{{ $pig->date_of_birth ? $pig->date_of_birth->format('M d, Y') : 'N/A' }}</div>
                </div>
                <div class="info-cell">
                    <div class="info-label">Current Age:</div>
                    <div class="info-value">{{ $pig->age_display }}</div>
                </div>
                <div class="info-cell">
                    <div class="info-label">Stock Type:</div>
                    <div class="info-value">{{ ucfirst($pig->stock_type) }}</div>
                </div>
                <div class="info-cell">
                    <div class="info-label">Total Weight Records:</div>
                    <div class="info-value">{{ $weights->count() }}</div>
                </div>
            </div>
        </div>
    </div>

    @if(!empty($analytics))
        <!-- Analytics Summary -->
        <div class="analytics">
            <h2>Weight Analytics Summary</h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-value">{{ $analytics['total_records'] }}</span>
                    <div class="stat-label">Total Records</div>
                </div>
                <div class="stat-item">
                    <span class="stat-value">{{ number_format($analytics['weight_gain'], 1) }} kg</span>
                    <div class="stat-label">Total Weight Gain</div>
                </div>
                <div class="stat-item">
                    <span class="stat-value">{{ number_format($analytics['avg_daily_gain'], 3) }} kg</span>
                    <div class="stat-label">Avg Daily Gain</div>
                </div>
                <div class="stat-item">
                    <span class="stat-value">{{ number_format($analytics['latest_weight']->weight, 1) }} kg</span>
                    <div class="stat-label">Current Weight</div>
                </div>
            </div>
        </div>

        <!-- Growth Periods Analysis -->
        <div class="analytics">
            <h2>Growth Periods Analysis</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Duration</th>
                        <th>Weight Range</th>
                        <th>Phase</th>
                        <th>Total Gain</th>
                        <th>Daily Gain</th>
                        <th>Performance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($analytics['growth_periods'] as $period)
                        <tr>
                            <td>{{ $period['from_date']->format('M d') }} - {{ $period['to_date']->format('M d, Y') }}</td>
                            <td>{{ $period['days'] }} days</td>
                            <td>{{ number_format($period['from_weight'], 1) }} → {{ number_format($period['to_weight'], 1) }} kg</td>
                            <td>{{ $period['phase'] }}</td>
                            <td style="color: {{ $period['weight_gain'] >= 0 ? '#059669' : '#dc2626' }}">
                                {{ $period['weight_gain'] >= 0 ? '+' : '' }}{{ number_format($period['weight_gain'], 1) }} kg
                            </td>
                            <td>{{ number_format($period['daily_gain'], 3) }} kg/day</td>
                            <td class="
                                @if($period['daily_gain'] >= 0.5) performance-excellent
                                @elseif($period['daily_gain'] >= 0.3) performance-good
                                @elseif($period['daily_gain'] >= 0.1) performance-average
                                @else performance-poor
                                @endif
                            ">
                                @if($period['daily_gain'] >= 0.5) Excellent
                                @elseif($period['daily_gain'] >= 0.3) Good
                                @elseif($period['daily_gain'] >= 0.1) Average
                                @else Poor
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <!-- Weight History -->
    @if($weights->count() > 0)
        <div class="page-break">
            <h2 style="color: #1e40af; font-size: 18px; margin: 0 0 20px 0; border-bottom: 1px solid #cbd5e1; padding-bottom: 5px;">
                Complete Weight History ({{ $weights->count() }} records)
            </h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Weight (kg)</th>
                        <th>Weight (lbs)</th>
                        <th>Age</th>
                        <th>Purpose</th>
                        <th>Body Condition</th>
                        <th>Health Status</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($weights as $weight)
                        <tr>
                            <td>
                                {{ $weight->weigh_date->format('M d, Y') }}
                                @if($weight->weigh_date->format('H:i') !== '00:00')
                                    <br><small style="color: #64748b;">{{ $weight->weigh_date->format('H:i') }}</small>
                                @endif
                            </td>
                            <td><strong>{{ number_format($weight->weight, 1) }}</strong></td>
                            <td>{{ number_format($weight->weight_in_lbs, 1) }}</td>
                            <td>
                                @if($pig->date_of_birth)
                                    @php
                                        $ageAtWeighing = $pig->date_of_birth->diffInDays($weight->weigh_date);
                                        $years = floor($ageAtWeighing / 365);
                                        $remainingDays = $ageAtWeighing % 365;
                                        $months = floor($remainingDays / 30);
                                        $days = $remainingDays % 30;

                                        $ageParts = [];
                                        if ($years > 0) $ageParts[] = $years . 'y';
                                        if ($months > 0) $ageParts[] = $months . 'm';
                                        if ($days > 0 || empty($ageParts)) $ageParts[] = $days . 'd';

                                        $ageDisplay = implode(' ', $ageParts);
                                    @endphp
                                    {{ $ageDisplay }}
                                @else
                                    N/A
                                @endif
                            </td>
                            <td>{{ ucfirst($weight->purpose) }}</td>
                            <td>
                                @if($weight->body_condition_score)
                                    {{ $weight->body_condition_score }}/5
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                @if($weight->health_status)
                                    {{ ucfirst($weight->health_status) }}
                                @else
                                    -
                                @endif
                            </td>
                            <td style="font-size: 10px;">
                                {{ $weight->notes ? Str::limit($weight->notes, 50) : '-' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <p>This report was generated by the Chipper Farms Weight Management System</p>
        <p>Report generated on {{ now()->format('F d, Y \a\t H:i:s') }}</p>
    </div>
</body>
</html>
