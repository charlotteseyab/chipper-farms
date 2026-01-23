<x-app-layout>
    <div class="bg-gray-150 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Command Center -->
            <div class="bg-gradient-to-r from-gray-900 via-gray-800 to-gray-700 text-white rounded-2xl px-6 py-4 shadow-lg mb-4 border border-gray-800">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <p class="inline-flex items-center px-3 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/20 text-emerald-200 border border-emerald-400/40">
                            Superuser Command Center
                        </p>
                        <h1 class="text-2xl font-semibold mt-2">High-Level Control Panel</h1>
                        <p class="text-gray-200 mt-1 text-sm max-w-2xl">
                            Fast overview for approvals, risk and performance across all farms.
                        </p>
                    </div>
                    <!-- <div class="flex flex-wrap gap-2">
                        <a href="{{ route('feed-purchase-requests.index') }}" class="px-3 py-2 bg-white text-gray-900 text-sm font-semibold rounded-lg shadow hover:shadow-md transition">
                            Review Requests
                        </a>
                        <a href="{{ route('weight-alerts.dashboard') }}" class="px-3 py-2 border border-white/40 text-white text-sm font-semibold rounded-lg hover:bg-white/10 transition">
                            Weight Alerts
                        </a>
                        <a href="{{ route('feeds.index') }}" class="px-3 py-2 border border-emerald-300 text-emerald-100 text-sm font-semibold rounded-lg hover:bg-emerald-500/20 transition">
                            Inventory
                        </a>
                    </div> -->
                </div>
            </div>

            <!-- Global Filters: Date Range + User Selector -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 px-4 py-3 mb-6">
                <form method="GET" action="{{ route('superuser.dashboard') }}" id="dashboardFilterForm" class="flex flex-col gap-4">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div class="flex items-center gap-3 flex-wrap">
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Timeframe</span>
                            @php
                                $currentRange = $range ?? 'all';
                                $rangeOptions = [
                                    'today' => 'Today',
                                    '7d' => 'Last 7 days',
                                    'mtd' => 'MTD',
                                    'ytd' => 'YTD',
                                    'custom' => 'Custom',
                                ];
                            @endphp
                            <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 overflow-hidden">
                                @foreach($rangeOptions as $key => $label)
                                    <button
                                        type="button"
                                        onclick="selectRange('{{ $key }}')"
                                        class="px-3 py-1.5 text-xs font-medium border-l border-gray-200 first:border-l-0 focus:outline-none focus:ring-1 focus:ring-emerald-500
                                            {{ $currentRange === $key ? 'bg-emerald-600 text-white' : 'bg-gray-50 text-gray-700 hover:bg-gray-100' }}">
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                            <input type="hidden" name="range" id="rangeInput" value="{{ $currentRange }}">
                        </div>

                        <div class="flex items-center gap-3 flex-wrap">
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">View as</span>
                            <select
                                id="user_id"
                                name="user_id"
                                class="min-w-[220px] px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                onchange="document.getElementById('dashboardFilterForm').submit()"
                            >
                                <option value="">All users</option>
                                @foreach($dashboardUsers as $u)
                                    <option value="{{ $u->id }}" @selected((int)($selectedUserId ?? 0) === $u->id)>
                                        {{ $u->first_name }} {{ $u->last_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Custom Date Range Picker -->
                    <div class="flex items-center gap-3 flex-wrap border-t border-gray-200 pt-3" id="customDateRange" style="display: {{ $currentRange === 'custom' ? 'flex' : 'none' }};">
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Custom Range</span>
                        <div class="flex items-center gap-2">
                            <input
                                type="date"
                                name="start_date"
                                id="start_date"
                                value="{{ request()->query('start_date', $startDate ? $startDate->format('Y-m-d') : '') }}"
                                class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            >
                            <span class="text-gray-500">to</span>
                            <input
                                type="date"
                                name="end_date"
                                id="end_date"
                                value="{{ request()->query('end_date', $endDate ? $endDate->format('Y-m-d') : '') }}"
                                class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            >
                            <button
                                type="button"
                                onclick="applyCustomDates()"
                                class="px-4 py-1.5 text-xs font-semibold bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            >
                                Apply
                            </button>
                            <button
                                type="button"
                                onclick="clearCustomDates()"
                                class="px-4 py-1.5 text-xs font-semibold bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-400"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                </form>
                </div>


            @if(!$selectedUser)
                <!-- Export Actions -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 px-4 py-3 mb-6">
                    <div class="flex items-center justify-between">
                    <div>
                            <h3 class="text-sm font-semibold text-gray-900">Export Dashboard</h3>
                            <!-- <p class="text-xs text-gray-500">Download current view as PDF or CSV</p> -->
                    </div>
                        <div class="flex gap-2">
                            <a href="{{ route('superuser.dashboard.export.pdf', request()->query()) }}" 
                               class="px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                                Export PDF
                            </a>
                            <a href="{{ route('superuser.dashboard.export.csv', request()->query()) }}" 
                               class="px-4 py-2 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 transition flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                                Export CSV
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Global KPI Strip -->
                <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Total Pigs</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900">
                            {{ number_format($globalKpis['total_pigs'] ?? 0) }}
                        </p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Farrowings</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900">
                            {{ number_format($globalKpis['total_farrowings'] ?? 0) }}
                        </p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Total Weaned</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900">
                            {{ number_format($globalKpis['total_weaned'] ?? 0) }}
                        </p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Sales (Count)</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900">
                            {{ number_format($globalKpis['total_sales'] ?? 0) }}
                        </p>
                </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Revenue</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900">
                            ₵{{ number_format($globalKpis['total_revenue'] ?? 0, 0) }}
                        </p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Critical Alerts</p>
                        <p class="mt-1 text-2xl font-bold text-rose-700">
                            {{ number_format($globalKpis['critical_alerts'] ?? 0) }}
                        </p>
                    </div>
                </div>

                <!-- Ops Snapshot: Data Quality + Overdue + Receivables -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <!-- Data Quality -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold text-gray-900">Data Quality</h3>
                            <span class="text-xs text-gray-500">Completeness & freshness</span>
                        </div>
                        <div class="space-y-2 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Missing Ear Tag</span>
                                <span class="font-semibold text-gray-900">{{ number_format($dataQuality['missing_ear_tag'] ?? 0) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Missing DOB</span>
                                <span class="font-semibold text-gray-900">{{ number_format($dataQuality['missing_dob'] ?? 0) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Missing Gender</span>
                                <span class="font-semibold text-gray-900">{{ number_format($dataQuality['missing_gender'] ?? 0) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Missing Breed</span>
                                <span class="font-semibold text-gray-900">{{ number_format($dataQuality['missing_breed'] ?? 0) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">No weight recorded (30d)</span>
                                <span class="font-semibold text-gray-900">{{ number_format($dataQuality['no_weights_30d'] ?? 0) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Duplicate Ear Tags</span>
                                <span class="font-semibold text-rose-700">{{ number_format($dataQuality['duplicate_ear_tags'] ?? 0) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Overdue / Upcoming -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold text-gray-900">Overdue & Upcoming</h3>
                            <span class="text-xs text-gray-500">Ops queue</span>
                        </div>
                        <div class="space-y-2 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Overdue Weighings</span>
                                <span class="font-semibold text-rose-700">{{ number_format($opsSnapshot['overdue_weighings'] ?? 0) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Due Weighings</span>
                                <span class="font-semibold text-gray-900">{{ number_format($opsSnapshot['due_weighings'] ?? 0) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Overdue Weaning</span>
                                <span class="font-semibold text-rose-700">{{ number_format($opsSnapshot['overdue_weaning'] ?? 0) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Due Soon Weaning</span>
                                <span class="font-semibold text-gray-900">{{ number_format($opsSnapshot['due_soon_weaning'] ?? 0) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Pregnancy Checks Overdue</span>
                                <span class="font-semibold text-rose-700">{{ number_format($opsSnapshot['preg_checks_overdue'] ?? 0) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Overdue Gestation</span>
                                <span class="font-semibold text-rose-700">{{ number_format($opsSnapshot['overdue_gestation'] ?? 0) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Receivables -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold text-gray-900">Receivables</h3>
                            <span class="text-xs text-gray-500">Unpaid sales aging</span>
                        </div>
                        <div class="space-y-2 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">0–7 days</span>
                                <span class="font-semibold text-gray-900">₵{{ number_format(($receivablesAging['0_7']['amount'] ?? 0), 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">8–30 days</span>
                                <span class="font-semibold text-gray-900">₵{{ number_format(($receivablesAging['8_30']['amount'] ?? 0), 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">31–60 days</span>
                                <span class="font-semibold text-gray-900">₵{{ number_format(($receivablesAging['31_60']['amount'] ?? 0), 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">60+ days</span>
                                <span class="font-semibold text-rose-700">₵{{ number_format(($receivablesAging['60_plus']['amount'] ?? 0), 2) }}</span>
                            </div>
                            <div class="pt-2 border-t border-gray-100 flex items-center justify-between">
                                <span class="text-gray-700 font-semibold">Total Unpaid</span>
                                <span class="font-bold text-gray-900">₵{{ number_format(($receivablesAging['total']['amount'] ?? 0), 2) }}</span>
                            </div>
                    </div>
                </div>
            </div>

                <!-- General summary across all workspaces -->
                <!-- <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                    <div class="flex items-center justify-between p-6 border-b border-gray-100">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900">Workspace Summary</h2>
                            <p class="text-sm text-gray-500">Performance and data density across all farms</p>
                        </div>
                        <div class="text-xs font-medium px-3 py-1 bg-gray-100 text-gray-600 rounded-full">
                            Range: {{ strtoupper($currentRange) }}
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        @if(count($workspaceMetrics) === 0)
                            <div class="p-12 text-center">
                                <p class="text-sm text-gray-500">No workspaces available.</p>
                            </div>
                        @else
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-gray-50 text-gray-600 text-[11px] uppercase tracking-wider font-semibold">
                                        <th class="px-6 py-4">Workspace</th>
                                        <th class="px-6 py-4">Users</th>
                                        <th class="px-6 py-4">Pigs</th>
                                        <th class="px-6 py-4">Farrowings</th>
                                        <th class="px-6 py-4">Weight Rec.</th>
                                        <th class="px-6 py-4">Health Rec.</th>
                                        <th class="px-6 py-4 text-right">Users</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                    @foreach($workspaceMetrics as $ws)
                                        <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4">
                                                <div class="font-semibold text-gray-900">{{ $ws['name'] }}</div>
                                                <div class="text-[10px] text-gray-400 font-mono">{{ $ws['code'] }}</div>
                                        </td>
                                        <td class="px-6 py-4">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">
                                                    {{ $ws['users'] }}
                                                </span>
                                            </td>
                                            {{-- Pigs Column --}}
                                            <td class="px-6 py-4">
                                                <div class="text-xs font-semibold text-gray-900">{{ number_format($ws['pigs']) }}</div>
                                                <div class="w-16 h-1 bg-gray-100 rounded-full mt-1 overflow-hidden">
                                                    <div class="h-full bg-emerald-500" style="width: {{ $workspaceMetricsMax['pigs'] ? (min(100, ($ws['pigs'] / $workspaceMetricsMax['pigs']) * 100)) : 0 }}%"></div>
                                                </div>
                                        </td>
                                            {{-- Farrowing Column --}}
                                        <td class="px-6 py-4">
                                                <div class="text-xs font-semibold text-gray-900">{{ number_format($ws['farrowings']) }}</div>
                                                <div class="w-16 h-1 bg-gray-100 rounded-full mt-1 overflow-hidden">
                                                    <div class="h-full bg-indigo-500" style="width: {{ $workspaceMetricsMax['farrowings'] ? (min(100, ($ws['farrowings'] / $workspaceMetricsMax['farrowings']) * 100)) : 0 }}%"></div>
                                                </div>
                                        </td>
                                            {{-- Weights Column --}}
                                        <td class="px-6 py-4">
                                                <div class="text-xs font-semibold text-gray-900">{{ number_format($ws['weights']) }}</div>
                                                <div class="w-16 h-1 bg-gray-100 rounded-full mt-1 overflow-hidden">
                                                    <div class="h-full bg-orange-500" style="width: {{ $workspaceMetricsMax['weights'] ? (min(100, ($ws['weights'] / $workspaceMetricsMax['weights']) * 100)) : 0 }}%"></div>
                                                </div>
                                        </td>
                                            {{-- Health Column --}}
                                        <td class="px-6 py-4">
                                                <div class="text-xs font-semibold text-gray-900">{{ number_format($ws['health']) }}</div>
                                                <div class="w-16 h-1 bg-gray-100 rounded-full mt-1 overflow-hidden">
                                                    <div class="h-full bg-rose-500" style="width: {{ $workspaceMetricsMax['health'] ? (min(100, ($ws['health'] / $workspaceMetricsMax['health']) * 100)) : 0 }}%"></div>
                                                </div>
                                            </td>
                                            {{-- Workspace Users Shortcut --}}
                                            <td class="px-6 py-4 text-right">
                                                <a href="{{ route('settings.users', ['workspace_id' => $ws['id']]) }}"
                                                   class="inline-flex items-center px-3 py-1.5 rounded-full text-[11px] font-medium bg-blue-50 text-blue-700 border border-blue-100 hover:bg-blue-100">
                                                    View users
                                                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                                    </svg>
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                            </tbody>
                        </table>
                        @endif
                    </div>
                </div> -->

                @if(!empty($financialTrends) && !empty($workspaceRevenueComparison))
                <!-- Financial Trends & Comparisons -->
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
                    <!-- Revenue Trend Chart -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Revenue Trend</h3>
                                <p class="text-sm text-gray-500">Monthly revenue over time</p>
                            </div>
                        </div>
                        <canvas id="revenueTrendChart" height="100"></canvas>
                    </div>

                    <!-- Workspace Revenue Comparison -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                                <div>
                                <h3 class="text-lg font-semibold text-gray-900">Revenue by Workspace</h3>
                                <p class="text-sm text-gray-500">Comparison across all farms</p>
                            </div>
                        </div>
                        <canvas id="workspaceRevenueChart" height="100"></canvas>
                    </div>
                </div>

                <!-- Financial Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Unpaid Sales</p>
                        <p class="mt-1 text-2xl font-bold text-amber-600">
                            ₵{{ number_format($financialTrends['unpaid_sales_amount'] ?? 0, 0) }}
                        </p>
                        <p class="text-xs text-gray-500 mt-1">{{ number_format($financialTrends['unpaid_sales_count'] ?? 0) }} transactions</p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Avg Sale Price</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900">
                            ₵{{ number_format(($financialTrends['avg_price_by_month']->avg('avg_price') ?? 0), 0) }}
                        </p>
                        <p class="text-xs text-gray-500 mt-1">Per transaction</p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Feed Costs</p>
                        <p class="mt-1 text-2xl font-bold text-orange-600">
                            ₵{{ number_format($financialTrends['feed_costs_by_month']->sum('total_cost') ?? 0, 0) }}
                        </p>
                        <p class="text-xs text-gray-500 mt-1">Approved purchases</p>
                                </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Net Profit</p>
                        @php
                            $totalRevenue = $financialTrends['revenue_by_month']->sum('revenue') ?? 0;
                            $totalFeedCosts = $financialTrends['feed_costs_by_month']->sum('total_cost') ?? 0;
                            $netProfit = $totalRevenue - $totalFeedCosts;
                        @endphp
                        <p class="mt-1 text-2xl font-bold {{ $netProfit >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                            ₵{{ number_format($netProfit, 0) }}
                        </p>
                        <p class="text-xs text-gray-500 mt-1">Revenue - Feed costs</p>
                                </div>
                            </div>
                @endif

                <!-- Top vs Bottom Farms -->
                <!-- <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
                        <h3 class="text-sm font-semibold text-gray-900 mb-3">Top Farms by Pigs</h3>
                        @if($topFarms->isEmpty())
                            <p class="text-sm text-gray-500">No data available.</p>
                        @else
                            <ul class="divide-y divide-gray-100">
                                @foreach($topFarms as $ws)
                                    <li class="py-2 flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">{{ $ws['name'] }}</p>
                                            <p class="text-xs text-gray-500">{{ $ws['code'] }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-semibold text-gray-900">{{ number_format($ws['pigs']) }} pigs</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
                        <h3 class="text-sm font-semibold text-gray-900 mb-3">Farms Needing Attention</h3>
                        @if($bottomFarms->isEmpty())
                            <p class="text-sm text-gray-500">No low-activity farms detected.</p>
                        @else
                            <ul class="divide-y divide-gray-100">
                                @foreach($bottomFarms as $ws)
                                    <li class="py-2 flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">{{ $ws['name'] }}</p>
                                            <p class="text-xs text-gray-500">{{ $ws['code'] }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-semibold text-gray-900">{{ number_format($ws['pigs']) }} pigs</p>
                                            <p class="text-[11px] text-amber-600">Low stock / activity</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div> -->

                <!-- Alert & Risk Overview + Activity + User Health -->
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
                    <!-- Alerts -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
                        <h3 class="text-sm font-semibold text-gray-900 mb-3">Alert & Risk Overview</h3>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                                    <span class="text-sm text-gray-700">High weight alerts</span>
                                </div>
                                <span class="text-sm font-semibold text-rose-700">
                                    {{ number_format($alertOverview['high_weight_alerts'] ?? 0) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                    <span class="text-sm text-gray-700">Open alerts</span>
                                </div>
                                <span class="text-sm font-semibold text-amber-700">
                                    {{ number_format($alertOverview['open_alerts'] ?? 0) }}
                                </span>
                                    </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                                    <span class="text-sm text-gray-700">Health records (this month)</span>
                                </div>
                                <span class="text-sm font-semibold text-indigo-700">
                                    {{ number_format($alertOverview['health_records_this_month'] ?? 0) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Snapshot -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
                        <h3 class="text-sm font-semibold text-gray-900 mb-3">Recent Admin Activity</h3>
                        @if($activityLog->isEmpty())
                            <p class="text-sm text-gray-500">No recent activity logged.</p>
                        @else
                            <ul class="divide-y divide-gray-100">
                                @foreach($activityLog as $log)
                                    <li class="py-2 text-sm">
                                        <p class="text-gray-900">
                                            <span class="font-semibold">
                                                {{ optional($log->user)->first_name ?? 'System' }}
                                            </span>
                                            <span class="text-gray-600">
                                                – {{ $log->action ?? 'activity' }}
                                            </span>
                                        </p>
                                        <p class="text-[11px] text-gray-500">
                                            {{ optional($log->created_at)->diffForHumans() }}
                                        </p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <!-- User / Login Health -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
                        <h3 class="text-sm font-semibold text-gray-900 mb-3">User & Login Health</h3>
                        <dl class="space-y-2 text-sm">
                            <div class="flex items-center justify-between">
                                <dt class="text-gray-600">Total users</dt>
                                <dd class="font-semibold text-gray-900">{{ number_format($userHealth['total'] ?? 0) }}</dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-gray-600">Active</dt>
                                <dd class="font-semibold text-emerald-700">{{ number_format($userHealth['active'] ?? 0) }}</dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-gray-600">Inactive</dt>
                                <dd class="font-semibold text-amber-700">{{ number_format($userHealth['inactive'] ?? 0) }}</dd>
                            </div>
                        </dl>
                        <p class="mt-3 text-[11px] text-gray-500">
                            Only active accounts can log in. Use the Users tab in Settings to manage access.
                        </p>
                    </div>
                </div>
            @endif

            @if($selectedUser)
                @if(!$userFarmMetrics)
                    <!-- User has no workspace -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                            <h3 class="mt-4 text-lg font-semibold text-gray-900">No Workspace Assigned</h3>
                            <p class="mt-2 text-sm text-gray-500">{{ $selectedUser->first_name }} {{ $selectedUser->last_name }} does not have a workspace assigned.</p>
                </div>
            </div>
                @else
                    @php $fm = $userFarmMetrics; @endphp
                    
                    <!-- User Farm Header -->
                    <div class="bg-gradient-to-r from-emerald-600 to-emerald-700 text-white rounded-2xl shadow-lg p-6 mb-6">
                        <div class="flex items-center justify-between">
                        <div>
                                <h2 class="text-2xl font-bold">{{ $fm['workspace']->name }}</h2>
                                <p class="text-emerald-100 mt-1">Workspace Code: {{ $fm['workspace']->code }}</p>
                                <p class="text-emerald-100 text-sm mt-2">
                                    Managed by: {{ $selectedUser->first_name }} {{ $selectedUser->last_name }} ({{ $selectedUser->email }})
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-emerald-100">Range: {{ strtoupper($range ?? 'all') }}</p>
                                <p class="text-xs text-emerald-200 mt-1">
                                    {{ count($fm['workspace_users']) }} user{{ count($fm['workspace_users']) !== 1 ? 's' : '' }} in workspace
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- User Farm KPIs -->
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Total Pigs</p>
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($fm['total_pigs']) }}</p>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Farrowings</p>
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($fm['total_farrowings']) }}</p>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Weaned</p>
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($fm['total_weaned']) }}</p>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Sales</p>
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($fm['total_sales']) }}</p>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Revenue</p>
                            <p class="text-xl font-bold text-emerald-600">₵{{ number_format($fm['total_revenue']) }}</p>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Alerts</p>
                            <p class="text-2xl font-bold {{ $fm['critical_alerts'] > 0 ? 'text-red-600' : 'text-gray-900' }}">
                                {{ number_format($fm['open_alerts']) }}
                            </p>
                            @if($fm['critical_alerts'] > 0)
                                <p class="text-xs text-red-600 mt-1">{{ $fm['critical_alerts'] }} critical</p>
                            @endif
                        </div>
                    </div>

                    <!-- Stock Overview -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Stock Overview</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div class="border-l-4 border-emerald-500 bg-emerald-50 rounded-lg p-4">
                                <p class="text-sm font-medium text-emerald-700 mb-1">Active Pigs</p>
                                <p class="text-2xl font-bold text-emerald-900">{{ number_format($fm['active_pigs']) }}</p>
                                <p class="text-xs text-emerald-600 mt-1">
                                    {{ number_format($fm['active_male']) }} ♂ • {{ number_format($fm['active_female']) }} ♀
                                </p>
                            </div>
                            <div class="border-l-4 border-purple-500 bg-purple-50 rounded-lg p-4">
                                <p class="text-sm font-medium text-purple-700 mb-1">Breeding Stock</p>
                                <p class="text-2xl font-bold text-purple-900">{{ number_format($fm['breeding_stock']) }}</p>
                                <p class="text-xs text-purple-600 mt-1">{{ number_format($fm['standard_stock']) }} standard</p>
                            </div>
                            <div class="border-l-4 border-red-500 bg-red-50 rounded-lg p-4">
                                <p class="text-sm font-medium text-red-700 mb-1">Mortality Rate</p>
                                <p class="text-2xl font-bold text-red-900">{{ number_format($fm['mortality_rate'], 1) }}%</p>
                                <p class="text-xs text-red-600 mt-1">
                                    {{ number_format($fm['deceased_pigs']) }} deceased • {{ number_format($fm['sold_pigs']) }} sold
                                </p>
                            </div>
                            <div class="border-l-4 border-blue-500 bg-blue-50 rounded-lg p-4">
                                <p class="text-sm font-medium text-blue-700 mb-1">Farm Value</p>
                                <p class="text-2xl font-bold text-blue-900">₵{{ number_format($fm['farm_value']) }}</p>
                                <p class="text-xs text-blue-600 mt-1">
                                    {{ number_format($fm['avg_weight'], 1) }} kg avg • {{ number_format($fm['total_weight'], 1) }} kg total
                                </p>
                    </div>
                </div>

                        <!-- Breed Distribution -->
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Breed Distribution</h4>
                            <div class="grid grid-cols-3 gap-4">
                                <div class="text-center">
                                    <p class="text-xs text-gray-500">LW</p>
                                    <p class="text-xl font-bold text-gray-900">{{ number_format($fm['breed_counts']['LW'] ?? 0) }}</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-xs text-gray-500">DR</p>
                                    <p class="text-xl font-bold text-gray-900">{{ number_format($fm['breed_counts']['DR'] ?? 0) }}</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-xs text-gray-500">LWDR</p>
                                    <p class="text-xl font-bold text-gray-900">{{ number_format($fm['breed_counts']['LWDR'] ?? 0) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Production Metrics -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <!-- Farrowing & Weaning -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Production Metrics</h3>
                            <div class="space-y-4">
                                <div class="flex items-center justify-between py-2 border-b border-gray-100">
                                    <span class="text-sm text-gray-600">Total Born</span>
                                    <span class="font-semibold text-gray-900">{{ number_format($fm['total_born']) }}</span>
                                </div>
                                <div class="flex items-center justify-between py-2 border-b border-gray-100">
                                    <span class="text-sm text-gray-600">Avg Litter Size</span>
                                    <span class="font-semibold text-gray-900">{{ number_format($fm['avg_litter_size'], 1) }}</span>
                                </div>
                                <div class="flex items-center justify-between py-2 border-b border-gray-100">
                                    <span class="text-sm text-gray-600">Survival Rate</span>
                                    <span class="font-semibold text-gray-900">{{ number_format($fm['survival_rate'], 1) }}%</span>
                                </div>
                                <div class="flex items-center justify-between py-2 border-b border-gray-100">
                                    <span class="text-sm text-gray-600">Weaning Rate</span>
                                    <span class="font-semibold text-gray-900">{{ number_format($fm['weaning_percentage'], 1) }}%</span>
                                </div>
                                <div class="flex items-center justify-between py-2">
                                    <span class="text-sm text-gray-600">Pregnancy Rate</span>
                                    <span class="font-semibold text-gray-900">{{ number_format($fm['pregnancy_rate'], 1) }}%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Health & Alerts -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Health & Alerts</h3>
                            <div class="space-y-4">
                                <div class="flex items-center justify-between py-2 border-b border-gray-100">
                                    <span class="text-sm text-gray-600">Health Records</span>
                                    <span class="font-semibold text-gray-900">{{ number_format($fm['total_health_records']) }}</span>
                                </div>
                                <div class="flex items-center justify-between py-2 border-b border-gray-100">
                                    <span class="text-sm text-gray-600">Open Alerts</span>
                                    <span class="font-semibold {{ $fm['open_alerts'] > 0 ? 'text-red-600' : 'text-gray-900' }}">
                                        {{ number_format($fm['open_alerts']) }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between py-2 border-b border-gray-100">
                                    <span class="text-sm text-gray-600">Critical Alerts</span>
                                    <span class="font-semibold {{ $fm['critical_alerts'] > 0 ? 'text-red-600' : 'text-gray-900' }}">
                                        {{ number_format($fm['critical_alerts']) }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between py-2 border-b border-gray-100">
                                    <span class="text-sm text-gray-600">Weight Records</span>
                                    <span class="font-semibold text-gray-900">{{ number_format($fm['total_weights']) }}</span>
                                </div>
                                @if($fm['low_stock_count'] > 0)
                                <div class="flex items-center justify-between py-2">
                                    <span class="text-sm text-gray-600">Low Stock Items</span>
                                    <span class="font-semibold text-amber-600">{{ number_format($fm['low_stock_count']) }}</span>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Recent Weight Alerts -->
                    @if($fm['weight_alerts']->count() > 0)
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Weight Alerts</h3>
                        <div class="space-y-3">
                            @foreach($fm['weight_alerts'] as $alert)
                            <div class="flex items-start justify-between p-3 bg-gray-50 rounded-lg">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">
                                                {{ optional($alert->pig)->ear_tag_number ?? 'Unknown Pig' }}
                                            </p>
                                    <p class="text-xs text-gray-500 mt-1">{{ $alert->alert_message ?? ucfirst($alert->alert_type) }}</p>
                                            <p class="text-xs text-gray-400 mt-1">{{ $alert->created_at->diffForHumans() }}</p>
                                        </div>
                                        @php
                                            $badgeColor = match($alert->severity) {
                                        'high' => 'bg-red-100 text-red-700',
                                                'medium' => 'bg-amber-100 text-amber-700',
                                                default => 'bg-blue-100 text-blue-700',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $badgeColor }}">
                                            {{ ucfirst($alert->severity) }}
                                        </span>
                                    </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <!-- Top Health Conditions -->
                    @if($fm['top_conditions']->count() > 0)
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Health Conditions</h3>
                        <div class="space-y-2">
                            @foreach($fm['top_conditions'] as $condition)
                            <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                                <span class="text-sm text-gray-700">{{ $condition->condition }}</span>
                                <span class="font-semibold text-gray-900">{{ $condition->count }} cases</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <!-- Workspace Users -->
                    @if(count($fm['workspace_users']) > 1)
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Workspace Users</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($fm['workspace_users'] as $wsUser)
                            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center font-semibold">
                                    {{ strtoupper(substr($wsUser->first_name, 0, 1) . substr($wsUser->last_name, 0, 1)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $wsUser->first_name }} {{ $wsUser->last_name }}</p>
                                    <p class="text-xs text-gray-500">{{ $wsUser->email }}</p>
                                    <p class="text-xs text-gray-400 mt-1">
                                        <span class="px-2 py-0.5 rounded-full {{ $wsUser->active_status ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                            {{ $wsUser->active_status ? 'Active' : 'Inactive' }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <!-- Recent Activity -->
                    @if($fm['activity_log']->count() > 0)
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Activity</h3>
                        <div class="space-y-3">
                            @foreach($fm['activity_log'] as $log)
                            <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                                <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs font-semibold">
                                    {{ optional($log->user)->first_name ? strtoupper(substr($log->user->first_name, 0, 1)) : 'S' }}
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900">{{ ucfirst($log->action ?? 'action') }}</p>
                                    <p class="text-xs text-gray-500">{{ $log->name ?? 'System' }}</p>
                                    <p class="text-xs text-gray-400 mt-1">{{ $log->created_at->diffForHumans() }}</p>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                @endif
            @endif

        </div>
    </div>

    <script>
                function selectRange(range) {
                    document.getElementById('rangeInput').value = range;
                    const customDateRange = document.getElementById('customDateRange');
                    
                    if (range === 'custom') {
                        customDateRange.style.display = 'flex';
                        // Set default dates if not already set
                        const startDate = document.getElementById('start_date');
                        const endDate = document.getElementById('end_date');
                        if (!startDate.value) {
                            startDate.value = new Date().toISOString().split('T')[0];
                        }
                        if (!endDate.value) {
                            endDate.value = new Date().toISOString().split('T')[0];
                        }
                    } else {
                        customDateRange.style.display = 'none';
                        // Clear custom date inputs
                        document.getElementById('start_date').value = '';
                        document.getElementById('end_date').value = '';
                        // Submit form immediately for predefined ranges
                        document.getElementById('dashboardFilterForm').submit();
                    }
                }

                function applyCustomDates() {
                    const startDate = document.getElementById('start_date').value;
                    const endDate = document.getElementById('end_date').value;
                    
                    if (!startDate || !endDate) {
                        alert('Please select both start and end dates.');
                        return;
                    }
                    
                    if (new Date(startDate) > new Date(endDate)) {
                        alert('Start date must be before end date.');
                        return;
                    }
                    
                    document.getElementById('rangeInput').value = 'custom';
                    document.getElementById('dashboardFilterForm').submit();
                }

                function clearCustomDates() {
                    document.getElementById('start_date').value = '';
                    document.getElementById('end_date').value = '';
                    document.getElementById('rangeInput').value = 'mtd';
                    document.getElementById('dashboardFilterForm').submit();
                }
            </script>

        @if(!empty($financialTrends) && !empty($workspaceRevenueComparison))
        <!-- Chart.js -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            // Revenue Trend Chart
            const revenueCtx = document.getElementById('revenueTrendChart');
            if (revenueCtx) {
                const revenueData = @json($financialTrends['revenue_by_month']);
                const feedCostData = @json($financialTrends['feed_costs_by_month']);
                
                const months = revenueData.map(item => {
                    const [year, month] = item.month.split('-');
                    return new Date(year, month - 1).toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                });
                
                const revenueValues = revenueData.map(item => parseFloat(item.revenue) || 0);
                const feedCostMap = {};
                feedCostData.forEach(item => {
                    feedCostMap[item.month] = parseFloat(item.total_cost) || 0;
                });
                const feedCostValues = revenueData.map(item => feedCostMap[item.month] || 0);

                new Chart(revenueCtx, {
                    type: 'line',
                    data: {
                        labels: months,
                        datasets: [
                            {
                                label: 'Revenue',
                                data: revenueValues,
                                borderColor: 'rgb(16, 185, 129)',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                tension: 0.4,
                                fill: true
                            },
                            {
                                label: 'Feed Costs',
                                data: feedCostValues,
                                borderColor: 'rgb(249, 115, 22)',
                                backgroundColor: 'rgba(249, 115, 22, 0.1)',
                                tension: 0.4,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ₵' + context.parsed.y.toLocaleString();
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '₵' + value.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Workspace Revenue Comparison Chart
            const workspaceCtx = document.getElementById('workspaceRevenueChart');
            if (workspaceCtx) {
                const workspaceData = @json($workspaceRevenueComparison);
                
                const workspaceNames = workspaceData.map(w => w.workspace_name);
                const revenues = workspaceData.map(w => parseFloat(w.revenue) || 0);

                new Chart(workspaceCtx, {
                    type: 'bar',
                    data: {
                        labels: workspaceNames,
                        datasets: [{
                            label: 'Revenue',
                            data: revenues,
                            backgroundColor: 'rgba(16, 185, 129, 0.8)',
                            borderColor: 'rgb(16, 185, 129)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'Revenue: ₵' + context.parsed.y.toLocaleString();
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '₵' + value.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
            }
        </script>
        @endif
    <x-copyright-minimal />
</x-app-layout>
