<x-app-layout>
    <style>
        .weights-page{
            min-height: 100vh;
            padding: 20px 0 40px;
            background: #f6f8fb;
        }

        .weights-shell{
            width: min(100% - 32px, 1480px);
            margin: 0 auto;
        }

        .weights-hero{
            position: relative;
            overflow: visible;
            border-radius: 22px;
            padding: 20px;
            margin-bottom: 18px;
            background: #fff;
            color: #0f172a;
            border: 1px solid #dbe7f0;
            box-shadow: 0 12px 28px rgba(15,23,42,.05);
        }

        .weights-hero::before{
            content: none;
        }

        .weights-hero-inner{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .weights-title{
            margin: 0;
            font-size: 1.45rem;
            line-height: 1.15;
            font-weight: 900;
            letter-spacing: 0;
        }

        .weights-subtitle{
            margin: 6px 0 0;
            max-width: 620px;
            color: #64748b;
            font-size: .88rem;
            line-height: 1.55;
        }

        .weights-actions{
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: wrap;
            padding: 8px;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            background: #f8fafc;
        }

        .weights-action{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 42px;
            padding: 10px 14px;
            border-radius: 14px;
            border: 1px solid #dbe7f0;
            background: #fff;
            color: #334155;
            font-size: .82rem;
            font-weight: 800;
            line-height: 1;
            text-decoration: none;
            transition: .18s ease;
            box-shadow: none;
            white-space: nowrap;
        }

        .weights-action:hover{
            background: #f1f5f9;
            transform: translateY(-1px);
        }

        .weights-action.primary{
            background: #166534;
            color: #fff;
            border-color: #166534;
        }

        .weights-action.primary:hover{
            background: #14532d;
        }

        .weights-action.danger{
            background: #fff;
            color: #b91c1c;
            border-color: #fecaca;
        }

        .weights-action.danger:hover{
            background: #fef2f2;
        }

        .weights-export-form{
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }

        .weights-month-control{
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 5px 8px;
            border-radius: 14px;
            background: #fff;
            border: 1px solid #dbe7f0;
        }

        .weights-month-control label{
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-weight: 900;
            color: #64748b;
        }

        .weights-month-control input{
            width: 138px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 7px 8px;
            color: #0f172a;
            font-size: .8rem;
            outline: none;
            background: #f8fafc;
        }

        .weights-panel{
            background: rgba(255,255,255,.98);
            border: 1px solid #dbe7f0;
            border-radius: 24px;
            box-shadow: 0 14px 34px rgba(15,23,42,.05);
        }

        .weights-filter-panel{
            padding: 20px;
            margin-bottom: 18px;
        }

        .weights-filter-title{
            margin: 0 0 14px;
            font-size: .92rem;
            font-weight: 900;
            color: #0f172a;
        }

        .weights-filter-actions{
            display: flex;
            align-items: flex-end;
            gap: 8px;
        }

        .weights-filter-btn{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 42px;
            padding: 10px 14px;
            border-radius: 13px;
            font-size: .82rem;
            font-weight: 800;
            text-decoration: none;
            border: 1px solid transparent;
            transition: .18s ease;
            white-space: nowrap;
        }

        .weights-filter-btn.apply{
            background: #166534;
            color: #fff;
            border-color: #166534;
        }

        .weights-filter-btn.apply:hover{
            background: #14532d;
        }

        .weights-filter-btn.clear{
            background: #f8fafc;
            color: #475569;
            border-color: #cbd5e1;
        }

        .weights-filter-btn.clear:hover{
            background: #eef2f7;
        }

        .weights-table-card{
            overflow: hidden;
        }

        .weights-table-head{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 18px 20px;
            border-bottom: 1px solid #edf2f7;
            background: linear-gradient(180deg, #fbfdff 0%, #fff 100%);
        }

        .weights-table-title{
            margin: 0;
            font-size: 1rem;
            font-weight: 900;
            color: #0f172a;
        }

        .weights-table-sub{
            margin-top: 4px;
            font-size: .78rem;
            color: #64748b;
        }

        .weights-records-table{
            width: 100%;
            min-width: 1180px;
            border-collapse: separate;
            border-spacing: 0;
        }

        .weights-records-table thead th{
            background: #f8fafc;
            padding: 13px 18px;
            font-size: .7rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #64748b;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }

        .weights-records-table tbody td{
            padding: 15px 18px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            background: #fff;
        }

        .weights-records-table tbody tr:hover td{
            background: #f8fffb;
        }

        .weights-row-actions{
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: nowrap;
        }

        .weights-row-action{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 32px;
            padding: 7px 10px;
            border-radius: 11px;
            font-size: .74rem;
            font-weight: 800;
            text-decoration: none;
            border: 1px solid #dbeafe;
            color: #1d4ed8;
            background: #eff6ff;
            white-space: nowrap;
        }

        .weights-row-action.edit{
            color: #166534;
            background: #ecfdf5;
            border-color: #bbf7d0;
        }

        @media (max-width: 900px){
            .weights-shell{
                width: min(100% - 20px, 1480px);
            }

            .weights-hero{
                padding: 18px;
                border-radius: 20px;
            }

            .weights-actions,
            .weights-export-form,
            .weights-month-control{
                width: 100%;
            }

            .weights-action,
            .weights-month-control input{
                flex: 1 1 auto;
            }
        }
    </style>

    <main class="weights-page">
        <div class="weights-shell">
            <div class="weights-hero">
                <div class="weights-hero-inner">
                    <div>
                        <h2 class="weights-title">Weight Management</h2>
                        <p class="weights-subtitle">
                            Record animal weights, review latest measurements, export filtered reports, and keep growth tracking organized across the farm.
                        </p>
                    </div>

                    <div class="weights-actions">
                        <a href="{{ route('weights.create') }}" class="weights-action primary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            Record Weight
                        </a>

                        <a href="{{ route('weights.reports') }}" class="weights-action">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Weight Reports
                        </a>

                        <form method="GET" action="{{ route('weights.index.export-pdf') }}" class="weights-export-form">
                            @foreach(request()->except('page') as $key => $value)
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
                            @endforeach
                            <button type="submit" class="weights-action danger" title="Export all weights (filtered) as PDF">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Export PDF
                            </button>
                        </form>

                        <form method="GET" action="{{ route('weights.index.export-excel') }}" class="weights-export-form">
                            @foreach(request()->except(['page','month']) as $key => $value)
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
                            @endforeach
                            <div class="weights-month-control">
                                <label>Month</label>
                                <input type="month" name="month" value="{{ request('month') }}">
                            </div>
                            <button type="submit" class="weights-action" title="Download weekly gain analysis (Excel/CSV)">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5h14a2 2 0 012 2v6a2 2 0 01-2 2h-4l-4 4v-4H5a2 2 0 01-2-2V7a2 2 0 012-2z"/>
                                </svg>
                                Export Excel
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Weight Alerts Summary -->
            <x-weight-alerts-summary />

            <!-- Filters -->
            <div class="weights-panel weights-filter-panel">
                <h3 class="weights-filter-title">Filter Latest Weight Records</h3>
                <form method="GET" id="weightsFilterForm" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Pig</label>
                        <select name="pig_id" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 auto-submit">
                            <option value="" disabled selected >-- All Pigs --</option>
                            @foreach($pigs as $pig)
                                <option value="{{ $pig->id }}" {{ request('pig_id') == $pig->id ? 'selected' : '' }}>
                                    {{ $pig->ear_tag_number }} - {{ $pig->code }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Purpose</label>
                        <select name="purpose" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 auto-submit">
                            <option value="" disabled selected>-- All Purposes --</option>
                            @foreach($purposes as $purpose)
                                <option value="{{ $purpose }}" {{ request('purpose') == $purpose ? 'selected' : '' }}>
                                    {{ ucfirst($purpose) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 auto-debounce">
                    </div> -->

                    <!-- <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 auto-debounce">
                    </div> -->

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Weight From (kg)</label>
                        <input type="number" name="weight_from" value="{{ request('weight_from') }}" step="0.1" min="0" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 auto-debounce">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Weight To (kg)</label>
                        <input type="number" name="weight_to" value="{{ request('weight_to') }}" step="0.1" min="0" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 auto-debounce">
                    </div>

                    <div class="weights-filter-actions">
                        <button type="submit" class="weights-filter-btn apply">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.207A1 1 0 013 6.5V4z"/>
                            </svg>
                            Filter
                        </button>
                        <a href="{{ route('weights.index') }}" class="weights-filter-btn clear">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Weight Records Table -->
            <div class="weights-panel weights-table-card">
                <div class="weights-table-head">
                    <div>
                        <h3 class="weights-table-title">Weight Records</h3>
                        <div class="weights-table-sub">{{ $weights->total() }} latest record{{ $weights->total() === 1 ? '' : 's' }} in this view</div>
                    </div>
                </div>

                @if($weights->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="weights-records-table">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pig</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gender</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight & Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age at Weighing</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Health Status</th>
                                    <!-- <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Body Condition</th> -->
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($weights as $weight)
                                    <tr>
                                        @php $pig = $weight->pig; @endphp
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">{{ $pig->ear_tag_number ?? 'N/A' }}</div>
                                                    <div class="text-sm text-gray-500">{{ $pig->code ?? 'N/A' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($pig && $pig->gender)
                                                <span class="inline-flex items-center">
                                                    @if($pig->gender === 'M')
                                                        <svg class="w-4 h-4 text-blue-600 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                                            <circle cx="9" cy="15" r="6" stroke="currentColor" stroke-width="2" fill="none"/>
                                                            <path d="M15 9L21 3M21 3h-5M21 3v5" stroke="currentColor" stroke-width="2" fill="none"/>
                                                        </svg>
                                                        <span class="text-sm font-medium text-blue-600">Male</span>
                                                    @else
                                                        <svg class="w-4 h-4 text-pink-600 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                                            <circle cx="12" cy="8" r="5" stroke="currentColor" stroke-width="2" fill="none"/>
                                                            <path d="M12 13v8M9 18h6" stroke="currentColor" stroke-width="2" fill="none"/>
                                                        </svg>
                                                        <span class="text-sm font-medium text-pink-600">Female</span>
                                                    @endif
                                                </span>
                                            @else
                                                <span class="text-sm text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center gap-2">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">{{ number_format($weight->weight, 1) }} kg</div>
                                                    <div class="text-sm text-gray-500">{{ number_format($weight->weight_in_lbs, 1) }} lbs</div>
                                                </div>
                                                @if($weight->weightAlerts->count() > 0)
                                                    @php
                                                        $alert = $weight->weightAlerts->first();
                                                        $alertConfig = [
                                                            'under_weight' => ['icon' => 'Under', 'color' => 'text-blue-600', 'bg' => 'bg-blue-100', 'text' => 'Under Weight'],
                                                            'over_weight' => ['icon' => 'Over', 'color' => 'text-orange-600', 'bg' => 'bg-orange-100', 'text' => 'Over Weight'],
                                                            'normal' => ['icon' => 'OK', 'color' => 'text-green-600', 'bg' => 'bg-green-100', 'text' => 'Normal'],
                                                            'no_standard' => ['icon' => '?', 'color' => 'text-gray-600', 'bg' => 'bg-gray-100', 'text' => 'No Standard']
                                                        ];
                                                        $config = $alertConfig[$alert->alert_type] ?? $alertConfig['no_standard'];
                                                        $severityColor = $alert->severity == 'high' ? 'border-red-500' : ($alert->severity == 'medium' ? 'border-yellow-500' : 'border-gray-300');
                                                    @endphp
                                                    <div class="flex items-center gap-1">
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $config['bg'] }} {{ $config['color'] }} border {{ $severityColor }}"
                                                              title="{{ $alert->alert_message }}">
                                                            {{ $config['icon'] }} {{ $config['text'] }}
                                                            @if($alert->severity == 'high')
                                                                <span class="ml-1 text-red-600 font-bold">!</span>
                                                            @endif
                                                        </span>
                                                        @if($alert->deviation_percentage)
                                                            <span class="text-xs {{ $alert->severity == 'high' ? 'text-red-600' : 'text-gray-500' }}">
                                                                ({{ number_format(abs($alert->deviation_percentage), 1) }}% {{ $alert->alert_type == 'under_weight' ? 'under' : 'over' }})
                                                            </span>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $weight->weigh_date->format('M d, Y') }}
                                            @if($weight->weigh_date->format('H:i') !== '00:00')
                                                <div class="text-xs text-gray-500">{{ $weight->weigh_date->format('H:i') }}</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                {{ $weight->purpose === 'routine' ? 'bg-blue-100 text-blue-800' : 
                                                   ($weight->purpose === 'breeding' ? 'bg-green-100 text-green-800' : 
                                                   ($weight->purpose === 'health' ? 'bg-red-100 text-red-800' : 
                                                   ($weight->purpose === 'sale' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'))) }}">
                                                {{ ucfirst($weight->purpose) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            @if($pig && $pig->date_of_birth)
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
                                                <span title="{{ $ageAtWeighing }} days">{{ $ageDisplay }}</span>
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            @if($weight->health_status)
                                                @php
                                                    $healthConfig = [
                                                        'excellent' => 'bg-green-100 text-green-700 border-green-200',
                                                        'good' => 'bg-blue-100 text-blue-700 border-blue-200',
                                                        'fair' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                                        'poor' => 'bg-red-100 text-red-700 border-red-200',
                                                    ];
                                                    $config = $healthConfig[$weight->health_status] ?? $healthConfig['good'];
                                                @endphp
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {{ $config }}">
                                                    {{ ucfirst($weight->health_status) }}
                                                </span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <!-- <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            @if($weight->body_condition_score)
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                    {{ $weight->body_condition_score <= 2 ? 'text-red-800' : 
                                                    ($weight->body_condition_score == 3 ? 'text-yellow-800' : 'text-green-800') }}">
                                                    {{ $weight->body_condition_score }}/5
                                                </span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td> -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="weights-row-actions">
                                                <a href="{{ route('weights.edit', $weight) }}" class="weights-row-action edit">Edit</a>
                                                @if($pig)
                                                    <a href="{{ route('pigs.show', $pig->id) }}#weight" class="weights-row-action">View Pig</a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="px-6 py-4 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing {{ $weights->firstItem() }} to {{ $weights->lastItem() }} of {{ $weights->total() }} results
                            </div>
                            <div class="flex items-center space-x-2">
                                <x-simple-pagination :paginator="$weights" />
                            </div>
                        </div>
                    </div>
                @else
                    <div class="px-6 py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No weight records found</h3>
                        <p class="mt-1 text-sm text-gray-500">Get started by recording your first weight measurement.</p>
                        <div class="mt-6">
                            <a href="{{ route('weights.create') }}" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                Record Weight
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('weightsFilterForm');

            // Auto-submit on select change
            document.querySelectorAll('#weightsFilterForm select.auto-submit').forEach(sel => {
                sel.addEventListener('change', () => form.submit());
            });

            // Debounced submit for date/number inputs
            let debounceTimer;
            function debouncedSubmit() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => form.submit(), 400);
            }

            document.querySelectorAll('#weightsFilterForm input.auto-debounce').forEach(inp => {
                inp.addEventListener('input', debouncedSubmit);
                inp.addEventListener('change', () => form.submit());
            });
        });
    </script>
</x-app-layout>
