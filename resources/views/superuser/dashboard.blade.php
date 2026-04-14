<x-app-layout>
    <style>
        .su-page{
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(16,185,129,.08), transparent 24%),
                radial-gradient(circle at top right, rgba(59,130,246,.06), transparent 22%),
                linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        }

        .su-wrap{
            max-width: 1450px;
            margin: 0 auto;
            padding: 20px 14px 56px;
        }

        .su-hero{
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 45%, #0f766e 100%);
            color: #fff;
            border-radius: 30px;
            padding: 28px;
            box-shadow: 0 24px 60px rgba(15,23,42,.18);
            position: relative;
            overflow: hidden;
            margin-bottom: 18px;
        }

        .su-hero::before{
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,.10), transparent 26%),
                radial-gradient(circle at bottom left, rgba(255,255,255,.06), transparent 22%);
            pointer-events: none;
        }

        .su-hero-inner{
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .su-badge{
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(16,185,129,.16);
            border: 1px solid rgba(52,211,153,.24);
            color: #d1fae5;
            font-size: .74rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .su-hero h1{
            margin: 12px 0 0;
            font-size: clamp(1.9rem, 3vw, 2.8rem);
            line-height: 1.02;
            font-weight: 900;
            letter-spacing: -.04em;
        }

        .su-hero p{
            margin: 10px 0 0;
            color: #dbeafe;
            font-size: .96rem;
            line-height: 1.75;
            max-width: 760px;
        }

        .su-hero-pills{
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .su-hero-pill{
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255,255,255,.10);
            border: 1px solid rgba(255,255,255,.12);
            color: #f8fafc;
            font-size: .78rem;
            font-weight: 700;
        }

        .su-panel{
            background: rgba(255,255,255,.94);
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            box-shadow: 0 12px 32px rgba(15,23,42,.05);
        }

        .su-filter{
            padding: 18px;
            margin-bottom: 18px;
        }

        .su-filter-title{
            font-size: 1rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 14px;
        }

        .su-soft-label{
            font-size: .72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #64748b;
        }

        .su-range-wrap{
            display: inline-flex;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            overflow: hidden;
        }

        .su-range-btn{
            padding: 9px 12px;
            font-size: .76rem;
            font-weight: 700;
            border-left: 1px solid #e2e8f0;
            background: #f8fafc;
            color: #475569;
            transition: .18s ease;
        }

        .su-range-btn:first-child{
            border-left: 0;
        }

        .su-range-btn:hover{
            background: #f1f5f9;
        }

        .su-range-btn.active{
            background: linear-gradient(135deg, #059669, #10b981);
            color: #fff;
        }

        .su-input,
        .su-select{
            width: 100%;
            min-height: 42px;
            border-radius: 14px;
            border: 1px solid #cbd5e1;
            background: #fff;
            padding: 10px 12px;
            font-size: .88rem;
            color: #0f172a;
            outline: none;
        }

        .su-input:focus,
        .su-select:focus{
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16,185,129,.12);
        }

        .su-btn{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 14px;
            border-radius: 14px;
            font-size: .82rem;
            font-weight: 700;
            transition: .2s ease;
            text-decoration: none;
            border: none;
        }

        .su-btn-emerald{
            background: linear-gradient(135deg, #059669, #10b981);
            color: #fff;
            box-shadow: 0 10px 22px rgba(16,185,129,.18);
        }

        .su-btn-emerald:hover{
            transform: translateY(-1px);
        }

        .su-btn-gray{
            background: #e5e7eb;
            color: #374151;
        }

        .su-btn-red{
            background: linear-gradient(135deg, #dc2626, #ef4444);
            color: #fff;
        }

        .su-card-grid{
            display: grid;
            gap: 16px;
            margin-bottom: 18px;
        }

        .su-kpi-grid{
            grid-template-columns: repeat(6, minmax(0,1fr));
        }

        .su-kpi{
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 22px;
            padding: 18px;
            box-shadow: 0 8px 22px rgba(15,23,42,.04);
        }

        .su-kpi-label{
            font-size: .7rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: #94a3b8;
            margin-bottom: 10px;
        }

        .su-kpi-value{
            font-size: 1.7rem;
            font-weight: 900;
            line-height: 1;
            color: #0f172a;
        }

        .su-kpi-sub{
            margin-top: 8px;
            font-size: .74rem;
            color: #64748b;
        }

        .su-kpi.accent-rose .su-kpi-value{
            color: #be123c;
        }

        .su-three-grid{
            display: grid;
            grid-template-columns: repeat(3, minmax(0,1fr));
            gap: 16px;
            margin-bottom: 18px;
        }

        .su-info-card{
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 22px;
            box-shadow: 0 8px 22px rgba(15,23,42,.04);
            overflow: hidden;
        }

        .su-info-head{
            padding: 18px 20px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .su-info-title{
            font-size: .96rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
        }

        .su-info-sub{
            font-size: .74rem;
            color: #64748b;
            margin-top: 4px;
        }

        .su-info-body{
            padding: 0 20px 20px;
        }

        .su-stat-list{
            display: grid;
            gap: 10px;
        }

        .su-stat-row{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid #eef2f7;
        }

        .su-stat-row span:first-child{
            color: #475569;
            font-size: .84rem;
        }

        .su-stat-row span:last-child{
            color: #0f172a;
            font-size: .88rem;
            font-weight: 800;
        }

        .su-chart-grid{
            display: grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap: 16px;
            margin-bottom: 18px;
        }

        .su-chart-card{
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 22px;
            box-shadow: 0 8px 22px rgba(15,23,42,.04);
            overflow: hidden;
        }

        .su-chart-head{
            padding: 18px 20px 12px;
        }

        .su-chart-title{
            font-size: 1rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
        }

        .su-chart-sub{
            font-size: .78rem;
            color: #64748b;
            margin-top: 4px;
        }

        .su-chart-body{
            padding: 0 20px 20px;
        }

        .su-chart-shell{
            height: 320px;
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #eef2f7;
            padding: 12px;
        }

        .su-section{
            margin-bottom: 18px;
        }

        .su-section-head{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 12px;
        }

        .su-section-head h2{
            margin: 0;
            font-size: 1.2rem;
            font-weight: 900;
            color: #111827;
        }

        .su-section-head p{
            margin: 4px 0 0;
            font-size: .82rem;
            color: #64748b;
        }

        .su-export{
            padding: 16px 18px;
            margin-bottom: 18px;
        }

        .su-export-wrap{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }

        .su-table-card{
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 22px;
            box-shadow: 0 8px 22px rgba(15,23,42,.04);
            overflow: hidden;
        }

        .su-user-hero{
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            color: #fff;
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 18px 44px rgba(16,185,129,.16);
            margin-bottom: 18px;
        }

        .su-user-kpi-grid{
            display: grid;
            grid-template-columns: repeat(6, minmax(0,1fr));
            gap: 16px;
            margin-bottom: 18px;
        }

        .su-user-kpi{
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 18px;
            box-shadow: 0 8px 20px rgba(15,23,42,.04);
        }

        .su-user-kpi-label{
            font-size: .7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: #94a3b8;
            margin-bottom: 8px;
        }

        .su-user-kpi-value{
            font-size: 1.55rem;
            font-weight: 900;
            line-height: 1;
            color: #111827;
        }

        .su-user-kpi-sub{
            font-size: .74rem;
            color: #64748b;
            margin-top: 8px;
        }

        .su-detail-grid{
            display: grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap: 16px;
            margin-bottom: 18px;
        }

        .su-detail-card{
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 22px;
            box-shadow: 0 8px 22px rgba(15,23,42,.04);
            padding: 20px;
        }

        .su-detail-card h3{
            margin: 0 0 14px;
            font-size: 1rem;
            font-weight: 800;
            color: #111827;
        }

        .su-weight-alert{
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid #eef2f7;
        }

        .su-user-chip{
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: .7rem;
            font-weight: 700;
        }

        @media (max-width: 1280px){
            .su-kpi-grid,
            .su-user-kpi-grid{
                grid-template-columns: repeat(3, minmax(0,1fr));
            }
        }

        @media (max-width: 1024px){
            .su-three-grid,
            .su-chart-grid,
            .su-detail-grid{
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px){
            .su-wrap{
                padding: 14px 10px 40px;
            }

            .su-kpi-grid,
            .su-user-kpi-grid{
                grid-template-columns: repeat(2, minmax(0,1fr));
            }

            .su-hero,
            .su-user-hero{
                padding: 20px;
            }
        }

        @media (max-width: 560px){
            .su-kpi-grid,
            .su-user-kpi-grid{
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="su-page">
        <div class="su-wrap">
            <div class="su-hero">
                <div class="su-hero-inner">
                    <div>
                        <p class="su-badge">Superuser Command Center</p>
                        <h1>High-Level Control Panel</h1>
                        <p>
                            Monitor approvals, risk, data quality, financial performance, and workspace activity across all farms from one executive dashboard.
                        </p>
                    </div>

                    <div class="su-hero-pills">
                        <div class="su-hero-pill">Cross-farm oversight</div>
                        <div class="su-hero-pill">Risk visibility</div>
                        <div class="su-hero-pill">User-level drilldown</div>
                    </div>
                </div>
            </div>

            <div class="su-panel su-filter">
                <div class="su-filter-title">Dashboard Filters</div>
                <form method="GET" action="{{ route('superuser.dashboard') }}" id="dashboardFilterForm" class="flex flex-col gap-4">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div class="flex items-center gap-3 flex-wrap">
                            <span class="su-soft-label">Timeframe</span>
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
                            <div class="su-range-wrap">
                                @foreach($rangeOptions as $key => $label)
                                    <button
                                        type="button"
                                        onclick="selectRange('{{ $key }}')"
                                        class="su-range-btn {{ $currentRange === $key ? 'active' : '' }}">
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                            <input type="hidden" name="range" id="rangeInput" value="{{ $currentRange }}">
                        </div>

                        <div class="flex items-center gap-3 flex-wrap">
                            <span class="su-soft-label">View as</span>
                            <select
                                id="user_id"
                                name="user_id"
                                class="su-select min-w-[220px]"
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

                    <div class="flex items-center gap-3 flex-wrap border-t border-gray-200 pt-3" id="customDateRange" style="display: {{ $currentRange === 'custom' ? 'flex' : 'none' }};">
                        <span class="su-soft-label">Custom Range</span>
                        <div class="flex items-center gap-2 flex-wrap">
                            <input
                                type="date"
                                name="start_date"
                                id="start_date"
                                value="{{ request()->query('start_date', $startDate ? $startDate->format('Y-m-d') : '') }}"
                                class="su-input"
                            >
                            <span class="text-gray-500">to</span>
                            <input
                                type="date"
                                name="end_date"
                                id="end_date"
                                value="{{ request()->query('end_date', $endDate ? $endDate->format('Y-m-d') : '') }}"
                                class="su-input"
                            >
                            <button
                                type="button"
                                onclick="applyCustomDates()"
                                class="su-btn su-btn-emerald"
                            >
                                Apply
                            </button>
                            <button
                                type="button"
                                onclick="clearCustomDates()"
                                class="su-btn su-btn-gray"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            @if(!$selectedUser)
                <div class="su-panel su-export">
                    <div class="su-export-wrap">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">Export Dashboard</h3>
                        </div>
                        <div class="flex gap-2 flex-wrap">
                            <a href="{{ route('superuser.dashboard.export.pdf', request()->query()) }}"
                               class="su-btn su-btn-red flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                                Export PDF
                            </a>
                            <a href="{{ route('superuser.dashboard.export.csv', request()->query()) }}"
                               class="su-btn su-btn-emerald flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Export CSV
                            </a>
                        </div>
                    </div>
                </div>

                <div class="su-card-grid su-kpi-grid">
                    <div class="su-kpi">
                        <p class="su-kpi-label">Total Pigs</p>
                        <p class="su-kpi-value">{{ number_format($globalKpis['total_pigs'] ?? 0) }}</p>
                    </div>
                    <div class="su-kpi">
                        <p class="su-kpi-label">Farrowings</p>
                        <p class="su-kpi-value">{{ number_format($globalKpis['total_farrowings'] ?? 0) }}</p>
                    </div>
                    <div class="su-kpi">
                        <p class="su-kpi-label">Total Weaned</p>
                        <p class="su-kpi-value">{{ number_format($globalKpis['total_weaned'] ?? 0) }}</p>
                    </div>
                    <div class="su-kpi">
                        <p class="su-kpi-label">Sales Count</p>
                        <p class="su-kpi-value">{{ number_format($globalKpis['total_sales'] ?? 0) }}</p>
                    </div>
                    <div class="su-kpi">
                        <p class="su-kpi-label">Revenue</p>
                        <p class="su-kpi-value">₵{{ number_format($globalKpis['total_revenue'] ?? 0, 0) }}</p>
                    </div>
                    <div class="su-kpi accent-rose">
                        <p class="su-kpi-label">Critical Alerts</p>
                        <p class="su-kpi-value">{{ number_format($globalKpis['critical_alerts'] ?? 0) }}</p>
                    </div>
                </div>

                <div class="su-three-grid">
                    <div class="su-info-card">
                        <div class="su-info-head">
                            <div>
                                <h3 class="su-info-title">Data Quality</h3>
                                <div class="su-info-sub">Completeness and freshness</div>
                            </div>
                        </div>
                        <div class="su-info-body">
                            <div class="su-stat-list">
                                <div class="su-stat-row">
                                    <span>Missing Ear Tag</span>
                                    <span>{{ number_format($dataQuality['missing_ear_tag'] ?? 0) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Missing DOB</span>
                                    <span>{{ number_format($dataQuality['missing_dob'] ?? 0) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Missing Gender</span>
                                    <span>{{ number_format($dataQuality['missing_gender'] ?? 0) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Missing Breed</span>
                                    <span>{{ number_format($dataQuality['missing_breed'] ?? 0) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>No weight recorded (30d)</span>
                                    <span>{{ number_format($dataQuality['no_weights_30d'] ?? 0) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Duplicate Ear Tags</span>
                                    <span class="text-rose-700">{{ number_format($dataQuality['duplicate_ear_tags'] ?? 0) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="su-info-card">
                        <div class="su-info-head">
                            <div>
                                <h3 class="su-info-title">Overdue & Upcoming</h3>
                                <div class="su-info-sub">Operations queue</div>
                            </div>
                        </div>
                        <div class="su-info-body">
                            <div class="su-stat-list">
                                <div class="su-stat-row">
                                    <span>Overdue Weighings</span>
                                    <span class="text-rose-700">{{ number_format($opsSnapshot['overdue_weighings'] ?? 0) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Due Weighings</span>
                                    <span>{{ number_format($opsSnapshot['due_weighings'] ?? 0) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Overdue Weaning</span>
                                    <span class="text-rose-700">{{ number_format($opsSnapshot['overdue_weaning'] ?? 0) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Due Soon Weaning</span>
                                    <span>{{ number_format($opsSnapshot['due_soon_weaning'] ?? 0) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Pregnancy Checks Overdue</span>
                                    <span class="text-rose-700">{{ number_format($opsSnapshot['preg_checks_overdue'] ?? 0) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Overdue Gestation</span>
                                    <span class="text-rose-700">{{ number_format($opsSnapshot['overdue_gestation'] ?? 0) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="su-info-card">
                        <div class="su-info-head">
                            <div>
                                <h3 class="su-info-title">Receivables</h3>
                                <div class="su-info-sub">Unpaid sales aging</div>
                            </div>
                        </div>
                        <div class="su-info-body">
                            <div class="su-stat-list">
                                <div class="su-stat-row">
                                    <span>0–7 days</span>
                                    <span>₵{{ number_format(($receivablesAging['0_7']['amount'] ?? 0), 2) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>8–30 days</span>
                                    <span>₵{{ number_format(($receivablesAging['8_30']['amount'] ?? 0), 2) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>31–60 days</span>
                                    <span>₵{{ number_format(($receivablesAging['31_60']['amount'] ?? 0), 2) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>60+ days</span>
                                    <span class="text-rose-700">₵{{ number_format(($receivablesAging['60_plus']['amount'] ?? 0), 2) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span class="font-semibold text-gray-700">Total Unpaid</span>
                                    <span class="font-bold">₵{{ number_format(($receivablesAging['total']['amount'] ?? 0), 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if(!empty($financialTrends) && !empty($workspaceRevenueComparison))
                    <div class="su-chart-grid">
                        <div class="su-chart-card">
                            <div class="su-chart-head">
                                <h3 class="su-chart-title">Revenue Trend</h3>
                                <div class="su-chart-sub">Monthly revenue over time</div>
                            </div>
                            <div class="su-chart-body">
                                <div class="su-chart-shell">
                                    <canvas id="revenueTrendChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <div class="su-chart-card">
                            <div class="su-chart-head">
                                <h3 class="su-chart-title">Revenue by Workspace</h3>
                                <div class="su-chart-sub">Comparison across all farms</div>
                            </div>
                            <div class="su-chart-body">
                                <div class="su-chart-shell">
                                    <canvas id="workspaceRevenueChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="su-card-grid su-kpi-grid">
                        <div class="su-kpi">
                            <p class="su-kpi-label">Unpaid Sales</p>
                            <p class="su-kpi-value text-amber-600">₵{{ number_format($financialTrends['unpaid_sales_amount'] ?? 0, 0) }}</p>
                            <p class="su-kpi-sub">{{ number_format($financialTrends['unpaid_sales_count'] ?? 0) }} transactions</p>
                        </div>
                        <div class="su-kpi">
                            <p class="su-kpi-label">Avg Sale Price</p>
                            <p class="su-kpi-value">₵{{ number_format(($financialTrends['avg_price_by_month']->avg('avg_price') ?? 0), 0) }}</p>
                            <p class="su-kpi-sub">Per transaction</p>
                        </div>
                        <div class="su-kpi">
                            <p class="su-kpi-label">Operating Costs</p>
                            <p class="su-kpi-value text-orange-600">₵0</p>
                            <p class="su-kpi-sub">Approved purchases</p>
                        </div>
                        <div class="su-kpi">
                            @php
                                $totalRevenue = $financialTrends['revenue_by_month']->sum('revenue') ?? 0;
                                $totalCosts = 0;
                                $netProfit = $totalRevenue - $totalCosts;
                            @endphp
                            <p class="su-kpi-label">Net Profit</p>
                            <p class="su-kpi-value {{ $netProfit >= 0 ? 'text-emerald-600' : 'text-red-600' }}">₵{{ number_format($netProfit, 0) }}</p>
                            <p class="su-kpi-sub">Revenue - costs</p>
                        </div>
                    </div>
                @endif

                <div class="su-three-grid">
                    <div class="su-info-card">
                        <div class="su-info-head">
                            <div>
                                <h3 class="su-info-title">Alert & Risk Overview</h3>
                                <div class="su-info-sub">Priority attention areas</div>
                            </div>
                        </div>
                        <div class="su-info-body">
                            <div class="su-stat-list">
                                <div class="su-stat-row">
                                    <span>High weight alerts</span>
                                    <span class="text-rose-700">{{ number_format($alertOverview['high_weight_alerts'] ?? 0) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Open alerts</span>
                                    <span class="text-amber-700">{{ number_format($alertOverview['open_alerts'] ?? 0) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Health records (this month)</span>
                                    <span class="text-indigo-700">{{ number_format($alertOverview['health_records_this_month'] ?? 0) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="su-info-card">
                        <div class="su-info-head">
                            <div>
                                <h3 class="su-info-title">Recent Admin Activity</h3>
                                <div class="su-info-sub">Latest recorded actions</div>
                            </div>
                        </div>
                        <div class="su-info-body">
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
                    </div>

                    <div class="su-info-card">
                        <div class="su-info-head">
                            <div>
                                <h3 class="su-info-title">User & Login Health</h3>
                                <div class="su-info-sub">Access status overview</div>
                            </div>
                        </div>
                        <div class="su-info-body">
                            <div class="su-stat-list">
                                <div class="su-stat-row">
                                    <span>Total users</span>
                                    <span>{{ number_format($userHealth['total'] ?? 0) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Active</span>
                                    <span class="text-emerald-700">{{ number_format($userHealth['active'] ?? 0) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Inactive</span>
                                    <span class="text-amber-700">{{ number_format($userHealth['inactive'] ?? 0) }}</span>
                                </div>
                            </div>
                            <p class="mt-3 text-[11px] text-gray-500">
                                Only active accounts can log in. Use the Users tab in Settings to manage access.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            @if($selectedUser)
                @if(!$userFarmMetrics)
                    <div class="su-panel p-6">
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

                    <div class="su-user-hero">
                        <div class="flex items-center justify-between gap-4 flex-wrap">
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

                    <div class="su-user-kpi-grid">
                        <div class="su-user-kpi">
                            <div class="su-user-kpi-label">Total Pigs</div>
                            <div class="su-user-kpi-value">{{ number_format($fm['total_pigs']) }}</div>
                        </div>
                        <div class="su-user-kpi">
                            <div class="su-user-kpi-label">Farrowings</div>
                            <div class="su-user-kpi-value">{{ number_format($fm['total_farrowings']) }}</div>
                        </div>
                        <div class="su-user-kpi">
                            <div class="su-user-kpi-label">Weaned</div>
                            <div class="su-user-kpi-value">{{ number_format($fm['total_weaned']) }}</div>
                        </div>
                        <div class="su-user-kpi">
                            <div class="su-user-kpi-label">Sales</div>
                            <div class="su-user-kpi-value">{{ number_format($fm['total_sales']) }}</div>
                        </div>
                        <div class="su-user-kpi">
                            <div class="su-user-kpi-label">Revenue</div>
                            <div class="su-user-kpi-value text-emerald-600">₵{{ number_format($fm['total_revenue']) }}</div>
                        </div>
                        <div class="su-user-kpi">
                            <div class="su-user-kpi-label">Alerts</div>
                            <div class="su-user-kpi-value {{ $fm['critical_alerts'] > 0 ? 'text-red-600' : 'text-gray-900' }}">
                                {{ number_format($fm['open_alerts']) }}
                            </div>
                            @if($fm['critical_alerts'] > 0)
                                <div class="su-user-kpi-sub">{{ $fm['critical_alerts'] }} critical</div>
                            @endif
                        </div>
                    </div>

                    <div class="su-detail-grid">
                        <div class="su-detail-card">
                            <h3>Stock Overview</h3>
                            <div class="su-stat-list">
                                <div class="su-stat-row">
                                    <span>Active Pigs</span>
                                    <span>{{ number_format($fm['active_pigs']) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Active Male</span>
                                    <span>{{ number_format($fm['active_male']) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Active Female</span>
                                    <span>{{ number_format($fm['active_female']) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Breeding Stock</span>
                                    <span>{{ number_format($fm['breeding_stock']) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Standard Stock</span>
                                    <span>{{ number_format($fm['standard_stock']) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Farm Value</span>
                                    <span>₵{{ number_format($fm['farm_value']) }}</span>
                                </div>
                            </div>

                            <div class="mt-5 pt-5 border-t border-gray-200">
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

                        <div class="su-detail-card">
                            <h3>Production Metrics</h3>
                            <div class="su-stat-list">
                                <div class="su-stat-row">
                                    <span>Total Born</span>
                                    <span>{{ number_format($fm['total_born']) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Avg Litter Size</span>
                                    <span>{{ number_format($fm['avg_litter_size'], 1) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Survival Rate</span>
                                    <span>{{ number_format($fm['survival_rate'], 1) }}%</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Weaning Rate</span>
                                    <span>{{ number_format($fm['weaning_percentage'], 1) }}%</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Pregnancy Rate</span>
                                    <span>{{ number_format($fm['pregnancy_rate'], 1) }}%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="su-detail-grid">
                        <div class="su-detail-card">
                            <h3>Health & Alerts</h3>
                            <div class="su-stat-list">
                                <div class="su-stat-row">
                                    <span>Health Records</span>
                                    <span>{{ number_format($fm['total_health_records']) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Open Alerts</span>
                                    <span class="{{ $fm['open_alerts'] > 0 ? 'text-red-600' : '' }}">{{ number_format($fm['open_alerts']) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Critical Alerts</span>
                                    <span class="{{ $fm['critical_alerts'] > 0 ? 'text-red-600' : '' }}">{{ number_format($fm['critical_alerts']) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Weight Records</span>
                                    <span>{{ number_format($fm['total_weights']) }}</span>
                                </div>
                                @if($fm['low_stock_count'] > 0)
                                    <div class="su-stat-row">
                                        <span>Low Stock Items</span>
                                        <span class="text-amber-600">{{ number_format($fm['low_stock_count']) }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        @if($fm['top_conditions']->count() > 0)
                            <div class="su-detail-card">
                                <h3>Top Health Conditions</h3>
                                <div class="su-stat-list">
                                    @foreach($fm['top_conditions'] as $condition)
                                        <div class="su-stat-row">
                                            <span>{{ $condition->condition }}</span>
                                            <span>{{ $condition->count }} cases</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    @if($fm['weight_alerts']->count() > 0)
                        <div class="su-detail-card mb-4">
                            <h3>Recent Weight Alerts</h3>
                            <div class="space-y-3">
                                @foreach($fm['weight_alerts'] as $alert)
                                    <div class="su-weight-alert">
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

                    @if(count($fm['workspace_users']) > 1)
                        <div class="su-detail-card mb-4">
                            <h3>Workspace Users</h3>
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
                                                <span class="su-user-chip {{ $wsUser->active_status ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                                    {{ $wsUser->active_status ? 'Active' : 'Inactive' }}
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($fm['activity_log']->count() > 0)
                        <div class="su-detail-card">
                            <h3>Recent Activity</h3>
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
                document.getElementById('start_date').value = '';
                document.getElementById('end_date').value = '';
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
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            const revenueCtx = document.getElementById('revenueTrendChart');
            if (revenueCtx) {
                const revenueData = @json($financialTrends['revenue_by_month']);
                const operatingCostData = [];

                const months = revenueData.map(item => {
                    const [year, month] = item.month.split('-');
                    return new Date(year, month - 1).toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                });

                const revenueValues = revenueData.map(item => parseFloat(item.revenue) || 0);
                const operatingCostMap = {};
                operatingCostData.forEach(item => {
                    operatingCostMap[item.month] = parseFloat(item.total_cost) || 0;
                });
                const operatingCostValues = revenueData.map(item => operatingCostMap[item.month] || 0);

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
                                label: 'Operating Costs',
                                data: operatingCostValues,
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