<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>System Reports - {{ now()->format('F j, Y') }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.3;
            color: #333;
            margin: 0;
            padding: 10px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 10px;
        }
        
        .header h1 {
            color: #2563eb;
            font-size: 20px;
            margin: 0;
        }
        
        .header p {
            color: #666;
            margin: 5px 0 0 0;
        }
        
        .section {
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        
        .section h2 {
            background-color: #f3f4f6;
            color: #374151;
            font-size: 14px;
            margin: 0 0 8px 0;
            padding: 6px;
            border-left: 4px solid #2563eb;
        }
        
        .section h3 {
            color: #374151;
            font-size: 12px;
            margin: 8px 0 5px 0;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 3px;
        }
        
        .stats-grid {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }
        
        .stats-row {
            display: table-row;
        }
        
        .stats-cell {
            display: table-cell;
            width: 20%;
            padding: 6px;
            text-align: center;
            border: 1px solid #e5e7eb;
            background-color: #f9fafb;
        }
        
        .stats-cell.blue { background-color: #eff6ff; }
        .stats-cell.green { background-color: #f0fdf4; }
        .stats-cell.purple { background-color: #faf5ff; }
        .stats-cell.orange { background-color: #fff7ed; }
        .stats-cell.indigo { background-color: #eef2ff; }
        
        .stats-value {
            font-size: 14px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 3px;
        }
        
        .stats-label {
            font-size: 9px;
            color: #6b7280;
            text-transform: uppercase;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        
        .data-table th,
        .data-table td {
            border: 1px solid #d1d5db;
            padding: 5px;
            text-align: left;
        }
        
        .data-table th {
            background-color: #f3f4f6;
            font-weight: bold;
            color: #374151;
        }
        
        .data-table tr:nth-child(even) {
            background-color: #f9fafb;
        }
        
        .two-column {
            display: table;
            width: 100%;
        }
        
        .column {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 8px;
        }
        
        .column:last-child {
            padding-right: 0;
            padding-left: 8px;
        }
        
        .metric-row {
            display: table;
            width: 100%;
            margin-bottom: 4px;
        }
        
        .metric-label {
            display: table-cell;
            width: 60%;
            font-weight: normal;
        }
        
        .metric-value {
            display: table-cell;
            width: 40%;
            font-weight: bold;
            text-align: right;
        }
        
        .footer {
            position: fixed;
            bottom: 10px;
            left: 10px;
            right: 10px;
            text-align: center;
            font-size: 9px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 5px;
        }
        
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Chipper Farms - System Reports</h1>
        <p>Generated on {{ now()->format('F j, Y \a\t g:i A') }}</p>
    </div>

    <!-- Executive Summary -->
    <div class="section">
        <h2>Executive Summary</h2>
        <div class="stats-grid">
            <div class="stats-row">
                <div class="stats-cell blue">
                    <div class="stats-value">{{ number_format($summary['total_pigs']) }}</div>
                    <div class="stats-label">Total Pigs</div>
                </div>
                <div class="stats-cell green">
                    <div class="stats-value">{{ number_format($summary['active_pigs']) }}</div>
                    <div class="stats-label">Active Pigs</div>
                </div>
                <div class="stats-cell purple">
                    <div class="stats-value">GHS {{ number_format($summary['total_sales_revenue'], 2) }}</div>
                    <div class="stats-label">Total Revenue</div>
                </div>
                <div class="stats-cell orange">
                    <div class="stats-value">{{ number_format($summary['total_weights_recorded']) }}</div>
                    <div class="stats-label">Weights Recorded</div>
                </div>
                <div class="stats-cell indigo">
                    <div class="stats-value">{{ number_format($summary['total_structures']) }}</div>
                    <div class="stats-label">Structures</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pig Distribution -->
    <div class="section">
        <h2>Pig Distribution</h2>
        <div class="two-column">
            <div class="column">
                <h3>By Stock Type</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Stock Type</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pigDistribution['by_stock_type'] as $type => $count)
                            <tr>
                                <td>{{ ucfirst($type) }}</td>
                                <td>{{ number_format($count) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <h3>By Gender</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Gender</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pigDistribution['by_gender'] as $gender => $count)
                            <tr>
                                <td>{{ ucfirst($gender) }}</td>
                                <td>{{ number_format($count) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="column">
                <h3>By Breed</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Breed</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pigDistribution['by_breed'] as $breed => $count)
                            <tr>
                                <td>{{ $breed }}</td>
                                <td>{{ number_format($count) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Financial Overview -->
    <div class="section">
        <h2>Financial Overview</h2>
        <div class="two-column">
            <div class="column">
                <h3>Revenue Summary</h3>
                <div class="metric-row">
                    <div class="metric-label">Total Revenue:</div>
                    <div class="metric-value">GHS {{ number_format($financialOverview['total_revenue'], 2) }}</div>
                </div>
                <div class="metric-row">
                    <div class="metric-label">Average Sale Price:</div>
                    <div class="metric-value">GHS {{ number_format($financialOverview['average_price_per_kg'], 2) }}</div>
                </div>
            </div>
            <div class="column">
                <h3>Monthly Sales</h3>
                @if($financialOverview['sales_by_month']->count() > 0)
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Revenue</th>
                                <th>Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($financialOverview['sales_by_month'] as $month)
                                <tr>
                                    <td>{{ \Carbon\Carbon::createFromFormat('Y-m', $month->month)->format('M Y') }}</td>
                                    <td>GHS {{ number_format($month->revenue, 2) }}</td>
                                    <td>{{ $month->count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p>No sales data available.</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Breeding Statistics & Health & Mortality -->
    <div class="section">
        <div class="two-column">
            <div class="column">
                <h2>Breeding Statistics</h2>
                <div class="metric-row">
                    <div class="metric-label">Total Matings:</div>
                    <div class="metric-value">{{ number_format($breedingStats['total_matings']) }}</div>
                </div>
                <div class="metric-row">
                    <div class="metric-label">Total Farrowings:</div>
                    <div class="metric-value">{{ number_format($breedingStats['total_farrowings']) }}</div>
                </div>
                <div class="metric-row">
                    <div class="metric-label">Total Weanings:</div>
                    <div class="metric-value">{{ number_format($breedingStats['total_weanings']) }}</div>
                </div>
                <div class="metric-row">
                    <div class="metric-label">Average Litter Size:</div>
                    <div class="metric-value">{{ number_format($breedingStats['average_litter_size'], 1) }}</div>
                </div>
                @php
                    $pregNum = \App\Models\Mating::where('pregnancy_status', 'pregnant')->count();
                    $pregDenom = \App\Models\Mating::whereNotNull('female_id')->count();
                    $pregnancyRate = $pregDenom > 0 ? round($pregNum / $pregDenom * 100, 1) : 0;
                    $totalMatings = \App\Models\Mating::count();
                    $totalFarrowings = \App\Models\Farrowing::where('farrowing_date', '!=', null)->count();
                    $farrowingRate = $totalMatings > 0 ? round($totalFarrowings / $totalMatings * 100, 1) : 0;
                    $totalWeaned = \App\Models\Weaning::count();
                    $totalBorn = \App\Models\Farrowing::sum('live_births');
                    $weaningPercentage = $totalBorn > 0 ? round($totalWeaned / $totalBorn * 100, 1) : 0;
                    $avgStillborns = \App\Models\Farrowing::avg('stillbirths');
                    $avgBirthWeight = \App\Models\Farrowing::whereNotNull('average_birth_weight')->avg('average_birth_weight');
                @endphp
                <div class="metric-row">
                    <div class="metric-label">Pregnancy Rate:</div>
                    <div class="metric-value">{{ $pregnancyRate }}%</div>
                </div>
                <div class="metric-row">
                    <div class="metric-label">Farrowing Rate:</div>
                    <div class="metric-value">{{ $farrowingRate }}%</div>
                </div>
                <div class="metric-row">
                    <div class="metric-label">Weaning Percentage:</div>
                    <div class="metric-value">{{ $weaningPercentage }}%</div>
                </div>
                <div class="metric-row">
                    <div class="metric-label">Average Stillbirths/Litter:</div>
                    <div class="metric-value">{{ number_format($avgStillborns, 1) }}</div>
                </div>
                <div class="metric-row">
                    <div class="metric-label">Average Birth Weight:</div>
                    <div class="metric-value">{{ number_format($avgBirthWeight, 1) }} kg</div>
                </div>
            </div>
            <div class="column">
                <h2>Health & Mortality</h2>
                <div class="metric-row">
                    <div class="metric-label">Total Deaths:</div>
                    <div class="metric-value">{{ number_format($healthStats['total_deaths']) }}</div>
                </div>
                @if($healthStats['deaths_by_cause']->count() > 0)
                    <h3>Deaths by Cause</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Cause</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($healthStats['deaths_by_cause'] as $cause => $count)
                                <tr>
                                    <td>{{ $cause }}</td>
                                    <td>{{ $count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>



    <!-- Weight Analytics -->
    <div class="section">
        <h2>Weight Analytics</h2>
        <div class="metric-row">
            <div class="metric-label">Average Weight:</div>
            <div class="metric-value">{{ number_format($weightAnalytics['average_weight'], 1) }} kg</div>
        </div>
        <div class="metric-row">
            <div class="metric-label">Total Weight Records:</div>
            <div class="metric-value">{{ number_format($weightAnalytics['total_weight_records']) }}</div>
        </div>
    </div>

    <!-- Structure & Room Utilization -->
    <div class="section">
        <h2>Structure & Room Utilization</h2>
        <div class="two-column">
            <div class="column">
                <h3>Structures by Status</h3>
                @if($structureStats['structures_by_status']->count() > 0)
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($structureStats['structures_by_status'] as $status => $count)
                                <tr>
                                    <td>{{ ucfirst(str_replace('_', ' ', $status)) }}</td>
                                    <td>{{ number_format($count) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p>No structure data available.</p>
                @endif
            </div>
            <div class="column">
                <h3>Room Utilization</h3>
                @if($structureStats['room_utilization']->count() > 0)
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Room Name</th>
                                <th>Structure</th>
                                <th>Pigs</th>
                                <th>Capacity</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($structureStats['room_utilization'] as $room)
                                <tr>
                                    <td>{{ $room->name }}</td>
                                    <td>{{ $room->structure->name ?? 'N/A' }}</td>
                                    <td>{{ $room->pigs_count }}</td>
                                    <td>{{ $room->capacity ?? 'N/A' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p>No room data available.</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Detailed Structure Table -->
    <div class="section">
        <h2>Structure Details & Capacity</h2>
        @if($structureStats['detailed_structures']->count() > 0)
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Structure Name</th>
                        <th>Status</th>
                        <th>Capacity</th>
                        <th>Current Rooms</th>
                        <th>Available</th>
                        <th>Utilization</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($structureStats['detailed_structures'] as $structure)
                        @php
                            $capacity = $structure->capacity ?? 'Unlimited';
                            $currentRooms = $structure->rooms_count;
                            $available = $structure->available_capacity;
                            $utilization = $structure->capacity ? round(($currentRooms / $structure->capacity) * 100, 1) : 'N/A';
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $structure->name }}</strong>
                                @if($structure->description)
                                    <br><small>{{ Str::limit($structure->description, 40) }}</small>
                                @endif
                            </td>
                            <td>{{ ucfirst(str_replace('_', ' ', $structure->status)) }}</td>
                            <td>{{ $capacity }}</td>
                            <td>{{ $currentRooms }}</td>
                            <td>
                                @if($available === -1)
                                    Unlimited
                                @else
                                    {{ $available }}
                                @endif
                            </td>
                            <td>
                                @if($utilization === 'N/A')
                                    N/A
                                @else
                                    {{ $utilization }}%
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p>No structure data available.</p>
        @endif
    </div>

    <!-- System Notifications & Alerts -->
    <div class="section">
        <h2>System Notifications & Alerts</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Type</th><th>Value</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>Stored Notifications (Unread / Total)</td><td>{{ $storedNotifications['unread'] ?? 0 }} / {{ $storedNotifications['total'] ?? 0 }}</td></tr>
                <tr><td>Live Weighing Alerts</td><td>{{ $realTimeAlerts['weight'] ?? 0 }}</td></tr>
                <tr><td>Critical Alerts</td><td>{{ $realTimeAlerts['critical'] ?? 0 }}</td></tr>
                <tr><td>Pending Weaning Alerts</td><td>{{ $realTimeAlerts['pending_weaning_alerts'] ?? 0 }}</td></tr>
            </tbody>
        </table>
    </div>
    <!-- Archival & Soft-deleted Records -->
    <div class="section">
        <h2>Archival & Soft-deleted Records</h2>
        <table class="data-table"><tbody>
            <tr><td>Archived Pigs</td><td>{{ $softDeletes['pigs_archived'] ?? 0 }}</td></tr>
            <tr><td>Archived Piglet Deaths</td><td>{{ $softDeletes['piglet_deaths_archived'] ?? 0 }}</td></tr>
            <tr><td>Archived Sales</td><td>{{ $softDeletes['sales_archived'] ?? 0 }}</td></tr>
            <tr><td>Archived Deaths</td><td>{{ $softDeletes['deaths_archived'] ?? 0 }}</td></tr>
            <tr><td>Active Pigs</td><td>{{ $softDeletes['pigs_active'] ?? 0 }}</td></tr>
        </tbody></table>
    </div>
    <!-- Piglet Survivability Analytics -->
    <div class="section">
        <h2>Piglet Survivability Summary</h2>
        <table class="data-table"><tbody>
            <tr><td>Total Live Births</td><td>{{ number_format($liveBirths ?? 0) }}</td></tr>
            <tr><td>Pre-Weaning Deaths</td><td>{{ number_format($preWeaningDeaths ?? 0) }}</td></tr>
            <tr><td>Piglet Survival Rate</td><td>{{ $pigletSurvivalRate !== null ? $pigletSurvivalRate.'%' : 'N/A' }}</td></tr>
        </tbody></table>
    </div>
    <!-- Weaning Summary -->
    <div class="section">
        <h2>Weaning Statistics</h2>
        <table class="data-table"><tbody>
            <tr><td>Total Litters Weaned</td><td>{{ number_format($totalLittersWeaned ?? 0) }}</td></tr>
            <tr><td>Total Piglets Weaned</td><td>{{ number_format($totalPigletsWeaned ?? 0) }}</td></tr>
            <tr><td>Avg Weaning Age (days)</td><td>{{ $avgWeaningAge !== null ? number_format($avgWeaningAge,1) : 'N/A' }}</td></tr>
            <tr><td>Avg Weaning Weight (kg)</td><td>{{ $avgWeaningWeight !== null ? number_format($avgWeaningWeight,1) : 'N/A' }}</td></tr>
        </tbody></table>
    </div>
    <!-- Sales/Procurement Card -->
    <div class="section">
        <h2>Sales & Procurement</h2>
        <table class="data-table"><tbody>
            <tr><td>Paid Sales</td><td>{{ $paidSales ?? 0 }}</td></tr>
            <tr><td>Unpaid Sales</td><td>{{ $unpaidSales ?? 0 }}</td></tr>
            <tr><td>Approved Purchase Req.</td><td>{{ $approvedFprs ?? 0 }}</td></tr>
            <tr><td>Pending Purchase Req.</td><td>{{ $pendingFprs ?? 0 }}</td></tr>
            <tr><td>Paid Purchase Req.</td><td>{{ $paidFprs ?? 0 }}</td></tr>
        </tbody></table>
    </div>
    <!-- User Roles Card -->
    <div class="section">
        <h2>User Role Breakdown</h2>
        <table class="data-table"><thead><tr><th>Role</th><th>Count</th></tr></thead><tbody>
        @foreach($roleCounts ?? [] as $role => $count)
            <tr><td>{{ ucfirst($role) }}</td><td>{{ $count }}</td></tr>
        @endforeach
        </tbody></table>
    </div>

    <div class="footer">
        <p>This report was generated automatically by the Chipper Farms Management System</p>
    </div>
</body>
</html>
