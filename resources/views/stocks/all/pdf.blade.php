<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Stock Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #374151;
            padding-bottom: 15px;
        }
        
        .header h1 {
            color: #374151;
            margin: 0;
            font-size: 24px;
        }
        
        .header p {
            margin: 5px 0;
            color: #666;
        }
        
        .summary {
            margin-bottom: 20px;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }
        
        .summary h3 {
            margin: 0 0 10px 0;
            color: #374151;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 10px;
        }
        
        th {
            background-color: #374151;
            color: white;
            font-weight: bold;
        }
        
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .stock-breeding { color: #059669; font-weight: bold; }
        .stock-standard { color: #2563eb; font-weight: bold; }
        
        .gender-m { color: #059669; }
        .gender-f { color: #db2777; }

        .ready-yes { color: #059669; font-weight: bold; }
        .ready-no { color: #dc2626; }
        .status-optimal { color: #059669; }
        .status-minimum-met { color: #f59e0b; }
        .status-too-young { color: #dc2626; }
        .status-too-old { color: #6b7280; }
        .status-too-light { color: #dc2626; }
        .status-not-checked { color: #6b7280; }
        .text-blue-600 { color: #2563eb; }
        .text-green-600 { color: #059669; }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .filters-applied {
            background-color: #e0f2fe;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            border-left: 4px solid #374151;
        }
        
        .filters-applied h4 {
            margin: 0 0 5px 0;
            color: #374151;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Chipper Farms</h1>
        <h2>All Stock Report</h2>
        <p>Generated on: {{ date('F j, Y \a\t g:i A') }}</p>
    </div>

    @if(request()->hasAny(['breed', 'gender', 'age', 'stock_type']))
    <div class="filters-applied">
        <h4>Filters Applied:</h4>
        @if(request('stock_type'))
            <strong>Stock Type:</strong> {{ ucfirst(request('stock_type')) }}<br>
        @endif
        @if(request('breed'))
            <strong>Breed:</strong>
            @if(request('breed') == 'LW') Large White
            @elseif(request('breed') == 'DR') Duroc
            @elseif(request('breed') == 'LWDR') Large White Duroc
            @else {{ request('breed') }}
            @endif
            <br>
        @endif
        @if(request('gender'))
            <strong>Gender:</strong> {{ request('gender') == 'M' ? 'Male' : 'Female' }}<br>
        @endif
        @if(request('age'))
            <strong>Max Age:</strong> {{ request('age') }} days<br>
        @endif
    </div>
    @endif

    <div class="summary">
        <h3>Summary</h3>
        <p><strong>Total Records:</strong> {{ count($pigs) }}</p>
        @if(count($pigs) > 0)
        <p><strong>Stock Types:</strong> 
            @php
                $stockTypes = $pigs->groupBy('stock_type');
                $stockCounts = [];
                foreach($stockTypes as $type => $group) {
                    $stockCounts[] = ucfirst($type) . ' (' . count($group) . ')';
                }
                echo implode(', ', $stockCounts);
            @endphp
        </p>
        <p><strong>Breeds:</strong> 
            @php
                $breeds = $pigs->groupBy('breed');
                $breedCounts = [];
                foreach($breeds as $breed => $group) {
                    $breedName = $breed == 'LW' ? 'Large White' : ($breed == 'DR' ? 'Duroc' : ($breed == 'LWDR' ? 'Large White Duroc' : $breed));
                    $breedCounts[] = $breedName . ' (' . count($group) . ')';
                }
                echo implode(', ', $breedCounts);
            @endphp
        </p>
        <p><strong>Gender Distribution:</strong>
            @php
                $genders = $pigs->groupBy('gender');
                $genderCounts = [];
                foreach($genders as $gender => $group) {
                    $genderName = $gender == 'M' ? 'Male' : 'Female';
                    $genderCounts[] = $genderName . ' (' . count($group) . ')';
                }
                echo implode(', ', $genderCounts);
            @endphp
        </p>
        @endif
    </div>

    @if(count($pigs) > 0)
    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Ear Tag</th>
                <th>Stock Type</th>
                <th>Breed</th>
                <th>Gender</th>
                <th>Age</th>
                <th>Breeding Ready</th>
                <th>Age Status</th>
                <th>Sale Status</th>
                <th>Father</th>
                <th>Mother</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pigs as $pig)
            @php
                $readiness = $pig->breeding_readiness;
                $saleStatus = $pig->sale_status;
            @endphp
            <tr>
                <td>{{ $pig->code }}</td>
                <td>{{ $pig->ear_tag_number }}</td>
                <td class="stock-{{ $pig->stock_type }}">
                    {{ ucfirst($pig->stock_type) }}
                </td>
                <td>
                    @if($pig->breed == 'LW') Large White
                    @elseif($pig->breed == 'DR') Duroc
                    @elseif($pig->breed == 'LWDR') Large White Duroc
                    @else {{ $pig->breed }}
                    @endif
                </td>
                <td class="gender-{{ strtolower($pig->gender) }}">
                    {{ $pig->gender == 'M' ? 'Male' : 'Female' }}
                </td>
                <td>{{ $pig->age_display }}</td>
                <td class="ready-{{ $readiness['ready'] ? 'yes' : 'no' }}">
                    {{ $readiness['ready'] ? 'Yes' : 'No' }}
                </td>
                <td class="status-{{ str_replace('_', '-', $readiness['age_status']) }}">
                    {{ ucfirst(str_replace('_', ' ', $readiness['age_status'])) }}
                </td>
                <td class="{{ $saleStatus['sold'] ? 'text-blue-600' : 'text-green-600' }}">
                    {{ $saleStatus['sold'] ? 'Sold' : 'Available' }}
                </td>
                <td>{{ $pig->father ? $pig->father->ear_tag_number : 'N/A' }}</td>
                <td>{{ $pig->mother ? $pig->mother->ear_tag_number : 'N/A' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="no-data">
        <h3>No Data Available</h3>
        <p>
            @if(request()->hasAny(['breed', 'gender', 'age', 'stock_type']))
                No stock records match the applied filters.
            @else
                No stock records found in the system.
            @endif
        </p>
    </div>
    @endif

    <div class="footer">
        <p>Chipper Farms - All Stock Management System</p>
        <p>This report contains {{ count($pigs) }} record(s) as of {{ date('Y-m-d H:i:s') }}</p>
    </div>
</body>
</html>
