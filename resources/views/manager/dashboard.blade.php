<x-app-layout>
    <style>
        .fm2-page{
            min-height: 100vh;
            background: #f3f4f6;
        }

        .fm2-wrap{
            max-width: 1500px;
            margin: 0 auto;
            padding: 20px 14px 56px;
        }

        .fm2-header{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .fm2-header h1{
            margin: 0;
            font-size: 1.85rem;
            line-height: 1;
            font-weight: 900;
            color: #1f2937;
            letter-spacing: -.03em;
        }

        .fm2-header p{
            margin: 6px 0 0;
            color: #6b7280;
            font-size: .92rem;
        }

        .fm2-top-pills{
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .fm2-pill{
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 999px;
            box-shadow: 0 4px 18px rgba(15,23,42,.05);
            font-size: .78rem;
            font-weight: 700;
            color: #475569;
        }

        .fm2-grid{
            display: grid;
            gap: 16px;
        }

        .fm2-kpi-grid{
            display: grid;
            grid-template-columns: repeat(4, minmax(0,1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .fm2-kpi{
            position: relative;
            overflow: hidden;
            border-radius: 22px;
            padding: 18px 18px 16px;
            color: #fff;
            box-shadow: 0 10px 24px rgba(15,23,42,.10);
        }

        .fm2-kpi::after{
            content: "";
            position: absolute;
            width: 84px;
            height: 84px;
            right: -18px;
            top: -18px;
            border-radius: 999px;
            background: rgba(255,255,255,.10);
        }

        .fm2-kpi-blue{ background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .fm2-kpi-green{ background: linear-gradient(135deg, #22c55e, #16a34a); }
        .fm2-kpi-purple{ background: linear-gradient(135deg, #a855f7, #7c3aed); }
        .fm2-kpi-red{ background: linear-gradient(135deg, #ef4444, #dc2626); }
        .fm2-kpi-amber{ background: linear-gradient(135deg, #f59e0b, #d97706); }

        .fm2-kpi-label{
            font-size: .78rem;
            font-weight: 700;
            color: rgba(255,255,255,.88);
            margin-bottom: 10px;
        }

        .fm2-kpi-value{
            font-size: 1.75rem;
            font-weight: 900;
            line-height: 1.05;
            margin: 0;
        }

        .fm2-kpi-sub{
            margin-top: 8px;
            font-size: .74rem;
            color: rgba(255,255,255,.82);
        }

        .fm2-stat-grid{
            display: grid;
            grid-template-columns: repeat(3, minmax(0,1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .fm2-stat{
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 18px 20px;
            box-shadow: 0 4px 18px rgba(15,23,42,.05);
        }

        .fm2-stat-row{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
        }

        .fm2-stat-label{
            font-size: .78rem;
            color: #6b7280;
            margin-bottom: 6px;
        }

        .fm2-stat-value{
            font-size: 1.1rem;
            font-weight: 900;
            line-height: 1;
            color: #111827;
        }

        .fm2-stat-note{
            margin-top: 8px;
            font-size: .74rem;
            color: #94a3b8;
        }

        .fm2-stat-icon{
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .fm2-wide-grid{
            display: grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .fm2-wide{
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 22px 24px;
            box-shadow: 0 4px 18px rgba(15,23,42,.05);
        }

        .fm2-wide h3{
            margin: 0 0 14px;
            font-size: 1.05rem;
            font-weight: 800;
            color: #1f2937;
        }

        .fm2-wide-sub{
            font-size: .84rem;
            color: #6b7280;
            margin-top: -8px;
            margin-bottom: 18px;
        }

        .fm2-inline-metrics{
            display: grid;
            grid-template-columns: repeat(3, minmax(0,1fr));
            gap: 18px;
        }

        .fm2-inline-metric{
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .fm2-inline-bar{
            width: 10px;
            height: 54px;
            border-radius: 999px;
            flex-shrink: 0;
        }

        .fm2-inline-label{
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: .10em;
            font-weight: 800;
            color: #94a3b8;
        }

        .fm2-inline-value{
            font-size: 1.1rem;
            font-weight: 900;
            color: #111827;
            margin-top: 4px;
        }

        .fm2-panel-grid{
            display: grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .fm2-panel{
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            box-shadow: 0 4px 18px rgba(15,23,42,.05);
            overflow: hidden;
        }

        .fm2-panel-head{
            padding: 20px 22px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .fm2-panel-title{
            margin: 0;
            font-size: 1rem;
            font-weight: 800;
            color: #1f2937;
        }

        .fm2-panel-sub{
            margin-top: 4px;
            font-size: .8rem;
            color: #6b7280;
        }

        .fm2-panel-body{
            padding: 0 22px 22px;
        }

        .fm2-chart{
            height: 270px;
        }

        .fm2-chart-lg{
            height: 330px;
        }

        .fm2-big{
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 22px;
            box-shadow: 0 4px 18px rgba(15,23,42,.05);
            overflow: hidden;
            margin-bottom: 16px;
        }

        .fm2-big-head{
            padding: 22px 24px 14px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .fm2-toolbar{
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .fm2-toolbar input{
            border: 1px solid #d1d5db;
            border-radius: 12px;
            padding: 9px 12px;
            font-size: .82rem;
            background: #fff;
            color: #111827;
        }

        .fm2-toolbar button{
            border: none;
            border-radius: 12px;
            padding: 10px 14px;
            background: #2563eb;
            color: #fff;
            font-size: .82rem;
            font-weight: 700;
            transition: .2s ease;
        }

        .fm2-toolbar button:hover{
            background: #1d4ed8;
        }

        .fm2-big-body{
            padding: 0 24px 24px;
        }

        .fm2-bottom-grid{
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(320px, 1fr);
            gap: 16px;
        }

        .fm2-alert-card,
        .fm2-task-card,
        .fm2-actions-card{
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            box-shadow: 0 4px 18px rgba(15,23,42,.05);
            overflow: hidden;
        }

        .fm2-side-head{
            padding: 20px 22px 12px;
        }

        .fm2-side-title{
            margin: 0;
            font-size: 1rem;
            font-weight: 800;
            color: #1f2937;
        }

        .fm2-side-sub{
            margin-top: 4px;
            font-size: .8rem;
            color: #6b7280;
        }

        .fm2-alert-list,
        .fm2-task-list{
            padding: 0 22px 22px;
            display: grid;
            gap: 12px;
        }

        .fm2-alert-item{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 14px;
            background: #fef2f2;
            border: 1px solid #fee2e2;
        }

        .fm2-alert-left{
            min-width: 0;
        }

        .fm2-alert-code{
            font-size: .82rem;
            font-weight: 800;
            color: #991b1b;
        }

        .fm2-alert-meta{
            font-size: .72rem;
            color: #6b7280;
            margin-top: 2px;
        }

        .fm2-alert-right{
            text-align: right;
            flex-shrink: 0;
        }

        .fm2-alert-status{
            font-size: .72rem;
            font-weight: 800;
            color: #b91c1c;
        }

        .fm2-alert-days{
            font-size: .7rem;
            color: #6b7280;
            margin-top: 2px;
        }

        .fm2-task-item{
            position: relative;
            padding-left: 18px;
            border-left: 2px solid #e5e7eb;
        }

        .fm2-task-item::before{
            content: "";
            position: absolute;
            left: -5px;
            top: 3px;
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: currentColor;
            box-shadow: 0 0 0 4px #fff;
        }

        .fm2-task-title{
            font-size: .84rem;
            font-weight: 800;
            color: #111827;
            line-height: 1.4;
        }

        .fm2-task-meta{
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #94a3b8;
            margin-top: 4px;
            font-weight: 700;
        }

        .fm2-actions-body{
            padding: 0 22px 22px;
            display: grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap: 12px;
        }

        .fm2-action{
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 110px;
            text-decoration: none;
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            background: #f8fafc;
            color: #111827;
            transition: .22s ease;
        }

        .fm2-action:hover{
            transform: translateY(-2px);
            background: #fff;
            box-shadow: 0 10px 24px rgba(15,23,42,.06);
        }

        .fm2-action span{
            font-size: .74rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .fm2-empty{
            padding: 26px 22px 28px;
            text-align: center;
            color: #94a3b8;
            font-size: .82rem;
        }

        @media (max-width: 1280px){
            .fm2-kpi-grid{
                grid-template-columns: repeat(2, minmax(0,1fr));
            }
        }

        @media (max-width: 1024px){
            .fm2-panel-grid,
            .fm2-wide-grid,
            .fm2-bottom-grid{
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px){
            .fm2-wrap{
                padding: 14px 10px 40px;
            }

            .fm2-kpi-grid,
            .fm2-stat-grid,
            .fm2-inline-metrics,
            .fm2-actions-body{
                grid-template-columns: 1fr;
            }

            .fm2-big-head{
                align-items: stretch;
            }

            .fm2-toolbar{
                width: 100%;
            }

            .fm2-toolbar input,
            .fm2-toolbar button{
                width: 100%;
            }
        }
    </style>

    <div class="fm2-page">
        <div class="fm2-wrap">

            <header class="fm2-header">
                <div>
                    <h1>Farm Manager Dashboard</h1>
                    <p>Visual farm overview, alerts, performance trends, and management actions.</p>
                </div>

                <div class="fm2-top-pills">
                    <div class="fm2-pill">Live analytics</div>
                    <div class="fm2-pill">Manager view</div>
                </div>
            </header>

            <div class="fm2-kpi-grid">
                <div class="fm2-kpi fm2-kpi-blue">
                    <div class="fm2-kpi-label">Total Animals</div>
                    <p class="fm2-kpi-value">{{ number_format($totalStockActive) }}</p>
                    <div class="fm2-kpi-sub">Weaned pigs + active piglets</div>
                </div>

                <div class="fm2-kpi fm2-kpi-green">
                    <div class="fm2-kpi-label">Breeding Stock</div>
                    <p class="fm2-kpi-value">{{ number_format($breedingStockActive) }}</p>
                    <div class="fm2-kpi-sub">Active breeding pigs</div>
                </div>

                <div class="fm2-kpi fm2-kpi-red">
                    <div class="fm2-kpi-label">Critical Alerts</div>
                    <p class="fm2-kpi-value">{{ number_format($criticalAlerts) }}</p>
                    <div class="fm2-kpi-sub">Need urgent action</div>
                </div>

                <div onclick="toggleWeighingAlertsModal()" class="fm2-kpi fm2-kpi-amber cursor-pointer hover:-translate-y-1 transition-all duration-300">
                    <div class="fm2-kpi-label">Overdue Weighing</div>
                    <p class="fm2-kpi-value">{{ number_format($overdueForWeighing) }}</p>
                    <div class="fm2-kpi-sub flex items-center gap-2">
                        <span>Click to view overdue pigs</span>
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="fm2-stat-grid">
                <div class="fm2-stat">
                    <div class="fm2-stat-row">
                        <div>
                            <div class="fm2-stat-label">Fertility Rate</div>
                            <div class="fm2-stat-value">{{ number_format($fertilityRate, 1) }}%</div>
                        </div>
                        <div class="fm2-stat-icon" style="background:#fef3c7; color:#d97706;">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8c0-1.657-1.79-3-4-3S8 6.343 8 8c0 1.306 1.12 2.417 2.688 2.83L12 17l1.312-6.17C14.88 10.417 16 9.306 16 8z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="fm2-stat-note">Breeding performance indicator</div>
                </div>

                <div class="fm2-stat">
                    <div class="fm2-stat-row">
                        <div>
                            <div class="fm2-stat-label">Data Accuracy</div>
                            <div class="fm2-stat-value">{{ $breedingStats['data_completeness'] }}%</div>
                        </div>
                        <div class="fm2-stat-icon" style="background:#dcfce7; color:#16a34a;">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/>
                            </svg>
                        </div>
                    </div>
                    <div class="fm2-stat-note">Recorded data completeness</div>
                </div>

                <div class="fm2-stat">
                    <div class="fm2-stat-row">
                        <div>
                            <div class="fm2-stat-label">Mortality Rate</div>
                            <div class="fm2-stat-value" style="color:#dc2626;">{{ number_format($mortalityRate, 1) }}%</div>
                        </div>
                        <div class="fm2-stat-icon" style="background:#fee2e2; color:#dc2626;">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-7.938 4h15.876c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.268 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="fm2-stat-note">Current loss percentage</div>
                </div>
            </div>

            <div class="fm2-wide-grid">
                <div class="fm2-wide">
                    <h3>Breed Totals</h3>
                    <div class="fm2-wide-sub">Current count by main breed group</div>
                    <div class="fm2-inline-metrics">
                        <div class="fm2-inline-metric">
                            <div class="fm2-inline-bar" style="background:#3b82f6;"></div>
                            <div>
                                <div class="fm2-inline-label">LW</div>
                                <div class="fm2-inline-value">{{ $breedCounts['lw'] }}</div>
                            </div>
                        </div>
                        <div class="fm2-inline-metric">
                            <div class="fm2-inline-bar" style="background:#22c55e;"></div>
                            <div>
                                <div class="fm2-inline-label">Duroc</div>
                                <div class="fm2-inline-value">{{ $breedCounts['dr'] }}</div>
                            </div>
                        </div>
                        <div class="fm2-inline-metric">
                            <div class="fm2-inline-bar" style="background:#a855f7;"></div>
                            <div>
                                <div class="fm2-inline-label">LWDR</div>
                                <div class="fm2-inline-value">{{ $breedCounts['lwdr'] }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="fm2-wide">
                    <h3>Stock & Alert Summary</h3>
                    <div class="fm2-wide-sub">Quick glance at core system totals</div>
                    <div class="fm2-inline-metrics">
                        <div class="fm2-inline-metric">
                            <div class="fm2-inline-bar" style="background:#10b981;"></div>
                            <div>
                                <div class="fm2-inline-label">Breeding</div>
                                <div class="fm2-inline-value">{{ $stockDistributionTotals['breeding'] }}</div>
                            </div>
                        </div>
                        <div class="fm2-inline-metric">
                            <div class="fm2-inline-bar" style="background:#3b82f6;"></div>
                            <div>
                                <div class="fm2-inline-label">Standard</div>
                                <div class="fm2-inline-value">{{ $stockDistributionTotals['standard'] }}</div>
                            </div>
                        </div>
                        <div class="fm2-inline-metric">
                            <div class="fm2-inline-bar" style="background:#a855f7;"></div>
                            <div>
                                <div class="fm2-inline-label">Piglets</div>
                                <div class="fm2-inline-value">{{ $stockDistributionTotals['piglets'] }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="fm2-panel-grid">
                <div class="fm2-panel">
                    <div class="fm2-panel-head">
                        <div>
                            <h3 class="fm2-panel-title">Stock Composition</h3>
                            <div class="fm2-panel-sub">Breeding vs standard animals</div>
                        </div>
                    </div>
                    <div class="fm2-panel-body">
                        <div class="fm2-chart">
                            <canvas id="stockDistributionChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="fm2-panel">
                    <div class="fm2-panel-head">
                        <div>
                            <h3 class="fm2-panel-title">Breed Distribution</h3>
                            <div class="fm2-panel-sub">LW, Duroc and LWDR mix</div>
                        </div>
                    </div>
                    <div class="fm2-panel-body">
                        <div class="fm2-chart">
                            <canvas id="breedDistributionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="fm2-big">
                <div class="fm2-big-head">
                    <div>
                        <h3 class="fm2-panel-title">Monthly Weight Trends</h3>
                        <div class="fm2-panel-sub">Average pig weight trend over time</div>
                    </div>

                    <div class="fm2-toolbar">
                        <input type="date" id="startDate">
                        <input type="date" id="endDate">
                        <button type="button" onclick="updateChart()">Update Chart</button>
                    </div>
                </div>

                <div class="fm2-big-body">
                    <div class="fm2-chart-lg">
                        <canvas id="weightTrendsChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="fm2-bottom-grid">
                <div class="fm2-panel">
                    <div class="fm2-panel-head">
                        <div>
                            <h3 class="fm2-panel-title">Weight Alert Levels</h3>
                            <div class="fm2-panel-sub">High, medium, and low alert load</div>
                        </div>
                    </div>
                    <div class="fm2-panel-body">
                        <div class="fm2-chart-lg">
                            <canvas id="weightAlertsChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="fm2-grid">
                    <div class="fm2-actions-card">
                        <div class="fm2-side-head">
                            <h3 class="fm2-side-title">Quick Actions</h3>
                            <div class="fm2-side-sub">Fast manager shortcuts</div>
                        </div>
                        <div class="fm2-actions-body">
                            <a href="{{ route('all-stocks.index') }}" class="fm2-action">
                                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                <span>Add Stock</span>
                            </a>

                            <a href="{{ route('weight-alerts.dashboard') }}" class="fm2-action">
                                <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                <span>View Alerts</span>
                            </a>
                        </div>
                    </div>

                    <div class="fm2-task-card">
                        <div class="fm2-side-head">
                            <h3 class="fm2-side-title">Upcoming Tasks</h3>
                            <div class="fm2-side-sub">Priority management timeline</div>
                        </div>
                        <div class="fm2-task-list">
                            @forelse ($upcomingEvents as $event)
                                <div class="fm2-task-item text-{{ $event['color'] }}-500">
                                    <div class="fm2-task-title">{{ $event['title'] }}</div>
                                    <div class="fm2-task-meta">{{ optional($event['date'])->format('M d') }} • {{ $event['days_until'] }} days left</div>
                                </div>
                            @empty
                                <div class="fm2-empty">No upcoming tasks found.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="weighingAlertsModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-5xl w-full max-h-[90vh] overflow-hidden">
        <div class="flex items-center justify-between p-6 border-b border-gray-200">
            <div>
                <h2 class="text-xl font-bold text-gray-900">Overdue Weighing Details</h2>
                <p class="text-sm text-gray-600 mt-1">Pigs overdue for weighing with detailed information</p>
            </div>

            <button onclick="toggleWeighingAlertsModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
            @if($allOverduePigs->count() > 0)
                <div class="space-y-4">
                    @foreach($allOverduePigsDetails as $row)
                        <div class="bg-{{ $row['weighingStatus']['color'] }}-50 border border-{{ $row['weighingStatus']['color'] }}-200 rounded-xl p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1">
                                    <div class="flex items-center mb-3">
                                        <span class="text-2xl mr-3">{{ $row['weighingStatus']['icon'] }}</span>
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900">{{ $row['pig']->ear_tag_number }}</h3>
                                            <p class="text-sm text-gray-600">
                                                {{ $row['pig']->breed }} • {{ $row['pig']->gender }} • {{ $row['pig']->stock_type }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                                        <div class="bg-white rounded-lg p-3 border border-gray-200">
                                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Last Weighing</p>
                                            <p class="text-sm font-semibold text-gray-900">
                                                @if($row['lastWeighing'])
                                                    {{ $row['lastWeighing']->format('M j, Y') }}
                                                @else
                                                    Never weighed
                                                @endif
                                            </p>
                                        </div>

                                        <div class="bg-white rounded-lg p-3 border border-gray-200">
                                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Last Weight</p>
                                            <p class="text-sm font-semibold text-gray-900">
                                                @if($row['lastWeight'])
                                                    {{ number_format($row['lastWeight']->weight, 1) }} {{ $row['lastWeight']->weight_unit }}
                                                @else
                                                    No weight recorded
                                                @endif
                                            </p>
                                        </div>

                                        <div class="bg-white rounded-lg p-3 border border-gray-200">
                                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Overdue By</p>
                                            <p class="text-sm font-semibold text-{{ $row['weighingStatus']['color'] }}-700">
                                                {{ max(0, (int) $row['overdueDays']) }} days
                                            </p>
                                        </div>
                                    </div>

                                    <div class="mt-4 flex items-center justify-between gap-3 flex-wrap">
                                        <div class="flex items-center">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $row['weighingStatus']['color'] }}-100 text-{{ $row['weighingStatus']['color'] }}-800">
                                                {{ $row['weighingStatus']['message'] }}
                                            </span>
                                            <span class="ml-2 text-xs text-gray-500">
                                                {{ (int) $row['daysSince'] }} days since last weighing
                                            </span>
                                        </div>

                                        <a href="{{ route('weights.create', ['pig_id' => $row['pig']->id]) }}"
                                           class="inline-flex items-center px-3 py-2 rounded-lg text-xs font-medium text-white bg-{{ $row['weighingStatus']['color'] }}-600 hover:bg-{{ $row['weighingStatus']['color'] }}-700 transition-colors">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                                            </svg>
                                            Record Weight
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">All caught up!</h3>
                    <p class="text-gray-500">All pigs are up to date with their weighing schedule.</p>
                </div>
            @endif
        </div>

        <div class="flex items-center justify-between p-6 border-t border-gray-200 bg-gray-50">
            <div class="text-sm text-gray-600">
                <span class="font-medium">{{ $allOverduePigs->count() }}</span> pigs need weighing attention
            </div>

            <div class="flex space-x-3">
                <a href="{{ route('weights.index') }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                    View All Weights
                </a>

                <button onclick="toggleWeighingAlertsModal()"
                        class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium text-white bg-gray-700 hover:bg-gray-800 transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    function toggleWeighingAlertsModal() {
        const modal = document.getElementById('weighingAlertsModal');
        modal.classList.toggle('hidden');
    }

    document.addEventListener('click', function (e) {
        const modal = document.getElementById('weighingAlertsModal');
        if (e.target === modal) {
            toggleWeighingAlertsModal();
        }
    });

    document.addEventListener('keydown', function(e) {
        const modal = document.getElementById('weighingAlertsModal');
        if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
            toggleWeighingAlertsModal();
        }
    });
</script>
    <script>
        let weightTrendsChart;

        document.addEventListener('DOMContentLoaded', function () {
            Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
            Chart.defaults.color = '#6b7280';

            const gridOptions = {
                color: 'rgba(148,163,184,0.16)',
                drawBorder: false
            };

            const endDateInput = document.getElementById('endDate');
            const startDateInput = document.getElementById('startDate');

            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(startDate.getDate() - 30);

            endDateInput.value = endDate.toISOString().split('T')[0];
            startDateInput.value = startDate.toISOString().split('T')[0];

            new Chart(document.getElementById('stockDistributionChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Breeding', 'Standard', 'Piglets'],
                    datasets: [{
                        data: [
                            {{ $stockDistributionTotals['breeding'] }},
                            {{ $stockDistributionTotals['standard'] }},
                            {{ $stockDistributionTotals['piglets'] }}
                        ],
                        backgroundColor: ['#22c55e', '#3b82f6', '#a855f7'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle',
                                boxWidth: 8
                            }
                        }
                    }
                }
            });

            new Chart(document.getElementById('breedDistributionChart'), {
                type: 'doughnut',
                data: {
                    labels: ['LW', 'Duroc', 'LWDR'],
                    datasets: [{
                        data: [{{ $breedCounts['lw'] }}, {{ $breedCounts['dr'] }}, {{ $breedCounts['lwdr'] }}],
                        backgroundColor: ['#3b82f6', '#22c55e', '#a855f7'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle',
                                boxWidth: 8
                            }
                        }
                    }
                }
            });

            const trendCtx = document.getElementById('weightTrendsChart').getContext('2d');
            const trendGradient = trendCtx.createLinearGradient(0, 0, 0, 320);
            trendGradient.addColorStop(0, 'rgba(59,130,246,0.55)');
            trendGradient.addColorStop(1, 'rgba(59,130,246,0.12)');

            const labels = Array.from({length: 30}, (_, i) => {
                const d = new Date();
                d.setDate(d.getDate() - (29 - i));
                return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            });

            const data = Array.from({length: 30}, () => Math.floor(Math.random() * 60) + 20);

            weightTrendsChart = new Chart(trendCtx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Average Weight (kg)',
                        data,
                        backgroundColor: 'rgba(96, 165, 250, 0.75)',
                        borderColor: '#3b82f6',
                        borderWidth: 1.5,
                        borderRadius: 10,
                        borderSkipped: false,
                        barThickness: 18
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            align: 'end',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle',
                                boxWidth: 8
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: gridOptions
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });

            new Chart(document.getElementById('weightAlertsChart'), {
                type: 'line',
                data: {
                    labels: ['High', 'Medium', 'Low'],
                    datasets: [{
                        label: 'Alert Count',
                        data: [{{ $highAlerts }}, {{ $mediumAlerts }}, {{ $lowAlerts }}],
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59,130,246,0.16)',
                        fill: true,
                        tension: 0.42,
                        pointRadius: 4,
                        pointBackgroundColor: '#3b82f6',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            align: 'end',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle',
                                boxWidth: 8
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: gridOptions
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        });

        function updateChart() {
            if (!weightTrendsChart) return;

            const labels = Array.from({length: 30}, (_, i) => {
                const d = new Date();
                d.setDate(d.getDate() - (29 - i));
                return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            });

            const data = Array.from({length: 30}, () => Math.floor(Math.random() * 60) + 20);

            weightTrendsChart.data.labels = labels;
            weightTrendsChart.data.datasets[0].data = data;
            weightTrendsChart.update();
        }
    </script>
</x-app-layout>