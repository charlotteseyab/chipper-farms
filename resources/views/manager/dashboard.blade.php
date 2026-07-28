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

        .fm2-command-grid{
            display: grid;
            grid-template-columns: repeat(4, minmax(0,1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .fm2-command-card{
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 20px;
            box-shadow: 0 8px 24px rgba(15,23,42,.06);
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            min-height: 142px;
        }

        .fm2-command-label{
            font-size: .82rem;
            font-weight: 900;
            color: #334155;
        }

        .fm2-command-value{
            margin-top: 10px;
            font-size: 2rem;
            line-height: 1;
            font-weight: 900;
            color: #0f172a;
        }

        .fm2-command-sub{
            margin-top: 8px;
            font-size: .76rem;
            color: #64748b;
        }

        .fm2-command-icon{
            width: 58px;
            height: 58px;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .fm2-command-icon svg{
            width: 28px;
            height: 28px;
        }

        .fm2-command-green{ border-color:#bbf7d0; background: linear-gradient(180deg,#fff,#f0fdf4); }
        .fm2-command-blue{ border-color:#bfdbfe; background: linear-gradient(180deg,#fff,#eff6ff); }
        .fm2-command-red{ border-color:#fecaca; background: linear-gradient(180deg,#fff,#fef2f2); }
        .fm2-command-amber{ border-color:#fed7aa; background: linear-gradient(180deg,#fff,#fffbeb); }
        .fm2-command-green .fm2-command-icon{ background:#16a34a; color:#fff; }
        .fm2-command-blue .fm2-command-icon{ background:#2563eb; color:#fff; }
        .fm2-command-red .fm2-command-icon{ background:#dc2626; color:#fff; }
        .fm2-command-amber .fm2-command-icon{ background:#d97706; color:#fff; }

        .fm2-main-grid{
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(300px, .95fr);
            gap: 16px;
            margin-bottom: 16px;
        }

        .fm2-work-table{
            width: 100%;
            border-collapse: collapse;
        }

        .fm2-work-table th,
        .fm2-work-table td{
            padding: 12px 14px;
            border-top: 1px solid #eef2f7;
            text-align: left;
            font-size: .82rem;
            vertical-align: middle;
        }

        .fm2-work-table th{
            background: #f8fafc;
            color: #64748b;
            font-size: .68rem;
            text-transform: uppercase;
            font-weight: 900;
        }

        .fm2-work-title{
            color: #0f172a;
            font-weight: 900;
        }

        .fm2-work-sub{
            margin-top: 2px;
            color: #64748b;
            font-size: .72rem;
        }

        .fm2-priority{
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 5px 9px;
            font-size: .66rem;
            font-weight: 900;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .fm2-priority-high{ background:#fee2e2; color:#b91c1c; }
        .fm2-priority-medium{ background:#fef3c7; color:#b45309; }
        .fm2-priority-low{ background:#dcfce7; color:#15803d; }

        .fm2-view-link{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #bbf7d0;
            color: #166534;
            background: #f0fdf4;
            border-radius: 10px;
            padding: 6px 10px;
            font-size: .72rem;
            font-weight: 900;
            text-decoration: none;
        }

        .fm2-action-list{
            padding: 0 22px 22px;
            display: grid;
            gap: 10px;
        }

        .fm2-action-wide{
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border-radius: 12px;
            padding: 14px 16px;
            min-height: 48px;
            color: #fff;
            text-decoration: none;
            font-size: .86rem;
            font-weight: 900;
            box-shadow: 0 8px 18px rgba(15,23,42,.08);
        }

        .fm2-action-wide svg{
            width: 18px;
            height: 18px;
        }

        .fm2-action-green{ background: linear-gradient(135deg,#16a34a,#15803d); }
        .fm2-action-blue{ background: linear-gradient(135deg,#2563eb,#1d4ed8); }
        .fm2-action-red{ background: linear-gradient(135deg,#ef4444,#dc2626); }
        .fm2-action-slate{ background: linear-gradient(135deg,#475569,#334155); }

        .fm2-insight-grid{
            display: grid;
            grid-template-columns: repeat(3, minmax(0,1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .fm2-activity-list{
            padding: 0 22px 22px;
            display: grid;
            gap: 12px;
        }

        .fm2-activity-item{
            display: flex;
            gap: 10px;
            align-items: flex-start;
            padding: 10px 0;
            border-top: 1px solid #eef2f7;
        }

        .fm2-activity-dot{
            width: 8px;
            height: 8px;
            border-radius: 999px;
            margin-top: 6px;
            flex-shrink: 0;
        }

        .fm2-activity-high{ background:#dc2626; }
        .fm2-activity-medium{ background:#d97706; }
        .fm2-activity-low{ background:#16a34a; }

        .fm2-activity-title{
            font-size: .82rem;
            font-weight: 900;
            color: #0f172a;
        }

        .fm2-activity-sub{
            margin-top: 2px;
            font-size: .74rem;
            color: #64748b;
        }

        .fm2-activity-time{
            margin-left: auto;
            color: #94a3b8;
            font-size: .7rem;
            white-space: nowrap;
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
            .fm2-kpi-grid,
            .fm2-command-grid,
            .fm2-insight-grid{
                grid-template-columns: repeat(2, minmax(0,1fr));
            }
        }

        @media (max-width: 1024px){
            .fm2-panel-grid,
            .fm2-wide-grid,
            .fm2-bottom-grid,
            .fm2-main-grid{
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px){
            .fm2-wrap{
                padding: 14px 10px 40px;
            }

            .fm2-kpi-grid,
            .fm2-command-grid,
            .fm2-stat-grid,
            .fm2-insight-grid,
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
                    <p>Daily operations, priority work, stock condition, and manager actions.</p>
                </div>

                <div class="fm2-top-pills">
                    <div class="fm2-pill">Operational view</div>
                    <div class="fm2-pill">Manager view</div>
                </div>
            </header>

            <div class="fm2-command-grid">
                <a href="{{ route('all-stocks.index') }}" class="fm2-command-card fm2-command-green">
                    <div>
                        <div class="fm2-command-label">Live Stock</div>
                        <div class="fm2-command-value">{{ number_format($totalStockActive) }}</div>
                        <div class="fm2-command-sub">{{ number_format($weanedActive) }} weaned • {{ number_format($activePigletsAdjusted) }} piglets</div>
                    </div>
                    <div class="fm2-command-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 11h14M7 11V8h10v3m-9 0v6m8-6v6M6 17h12" />
                        </svg>
                    </div>
                </a>

                <a href="{{ route('mating.manage') }}" class="fm2-command-card fm2-command-blue">
                    <div>
                        <div class="fm2-command-label">Breeding Stock</div>
                        <div class="fm2-command-value">{{ number_format($breedingStockActive) }}</div>
                        <div class="fm2-command-sub">{{ number_format($breedingFemales) }} females • {{ number_format($breedingMales) }} males</div>
                    </div>
                    <div class="fm2-command-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m5-11a5 5 0 1 1-10 0 5 5 0 0 1 10 0Z" />
                        </svg>
                    </div>
                </a>

                <a href="{{ route('weight-alerts.index') }}" class="fm2-command-card fm2-command-red">
                    <div>
                        <div class="fm2-command-label">Critical Alerts</div>
                        <div class="fm2-command-value">{{ number_format($criticalAlerts) }}</div>
                        <div class="fm2-command-sub">{{ number_format($highAlerts) }} high • {{ number_format($mediumAlerts) }} medium</div>
                    </div>
                    <div class="fm2-command-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 4h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.2 16c-.77 1.33.19 3 1.73 3Z" />
                        </svg>
                    </div>
                </a>

                <button type="button" onclick="toggleWeighingAlertsModal()" class="fm2-command-card fm2-command-amber text-left cursor-pointer">
                    <div>
                        <div class="fm2-command-label">Overdue Weighing</div>
                        <div class="fm2-command-value">{{ number_format($overdueForWeighing) }}</div>
                        <div class="fm2-command-sub">Click to view animals</div>
                    </div>
                    <div class="fm2-command-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h18M7 6l-3 8a3 3 0 0 0 6 0L7 6Zm10 0-3 8a3 3 0 0 0 6 0l-3-8ZM12 3v18" />
                        </svg>
                    </div>
                </button>
            </div>

            <div class="fm2-main-grid">
                <div class="fm2-panel">
                    <div class="fm2-panel-head">
                        <div>
                            <h3 class="fm2-panel-title">Today's Work Queue</h3>
                            <div class="fm2-panel-sub">Priority work for weighing, breeding, health, and stock review</div>
                        </div>
                        <a href="{{ route('weight-alerts.index') }}" class="fm2-view-link">See all tasks</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="fm2-work-table">
                            <thead>
                                <tr>
                                    <th>Priority</th>
                                    <th>Task Type</th>
                                    <th>Details</th>
                                    <th>Count</th>
                                    <th>Due</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($managerWorkQueue as $task)
                                    <tr>
                                        <td><span class="fm2-priority fm2-priority-{{ $task['severity'] }}">{{ $task['severity'] }}</span></td>
                                        <td>
                                            <div class="fm2-work-title">{{ $task['item'] }}</div>
                                            <div class="fm2-work-sub">{{ $task['type'] }}</div>
                                        </td>
                                        <td>{{ $task['details'] }}</td>
                                        <td>{{ number_format($task['count']) }}</td>
                                        <td>{{ $task['due'] }}</td>
                                        <td><a href="{{ $task['route'] }}" class="fm2-view-link">View</a></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-slate-500">No urgent manager tasks for today.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="fm2-grid">
                    <div class="fm2-actions-card">
                        <div class="fm2-side-head">
                            <h3 class="fm2-side-title">Quick Actions</h3>
                            <div class="fm2-side-sub">Common manager actions</div>
                        </div>
                        <div class="fm2-action-list">
                            <a href="{{ route('weights.create') }}" class="fm2-action-wide fm2-action-green">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h18M7 6l-3 8a3 3 0 0 0 6 0L7 6Zm10 0-3 8a3 3 0 0 0 6 0l-3-8ZM12 3v18" /></svg>
                                Record Weight
                            </a>
                            <a href="{{ route('all-stocks.create') }}" class="fm2-action-wide fm2-action-green">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m7-7H5" /></svg>
                                Add Stock
                            </a>
                            <a href="{{ route('weight-alerts.index') }}" class="fm2-action-wide fm2-action-red">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0a3 3 0 0 1-6 0" /></svg>
                                View Alerts
                            </a>
                            <a href="{{ route('health.create') }}" class="fm2-action-wide fm2-action-blue">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v8m4-4H8m13 0A9 9 0 1 1 3 12a9 9 0 0 1 18 0Z" /></svg>
                                Start Health Check
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

            <div class="fm2-insight-grid">
                <div class="fm2-panel">
                    <div class="fm2-panel-head">
                        <div>
                            <h3 class="fm2-panel-title">Stock Composition</h3>
                            <div class="fm2-panel-sub">Breeding, standard, and active piglets</div>
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
                            <h3 class="fm2-panel-title">Weight Trend</h3>
                            <div class="fm2-panel-sub">Real average weights recorded over the last 7 days</div>
                        </div>
                    </div>
                    <div class="fm2-panel-body">
                        <div class="fm2-chart">
                            <canvas id="weightTrendsChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="fm2-panel">
                    <div class="fm2-panel-head">
                        <div>
                            <h3 class="fm2-panel-title">Breeding Readiness</h3>
                            <div class="fm2-panel-sub">Ready, near-ready, and not-ready breeding stock</div>
                        </div>
                    </div>
                    <div class="fm2-panel-body">
                        <div class="fm2-chart">
                            <canvas id="breedingReadinessChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="fm2-bottom-grid">
                <div class="fm2-panel">
                    <div class="fm2-panel-head">
                        <div>
                            <h3 class="fm2-panel-title">Stock Snapshot</h3>
                            <div class="fm2-panel-sub">Breed totals and core stock ratios</div>
                        </div>
                    </div>
                    <div class="fm2-panel-body">
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
                        <div class="fm2-inline-metrics mt-6">
                            <div class="fm2-inline-metric">
                                <div class="fm2-inline-bar" style="background:#10b981;"></div>
                                <div>
                                    <div class="fm2-inline-label">Fertility</div>
                                    <div class="fm2-inline-value">{{ number_format($fertilityRate, 1) }}%</div>
                                </div>
                            </div>
                            <div class="fm2-inline-metric">
                                <div class="fm2-inline-bar" style="background:#f59e0b;"></div>
                                <div>
                                    <div class="fm2-inline-label">Data Accuracy</div>
                                    <div class="fm2-inline-value">{{ $breedingStats['data_completeness'] }}%</div>
                                </div>
                            </div>
                            <div class="fm2-inline-metric">
                                <div class="fm2-inline-bar" style="background:#ef4444;"></div>
                                <div>
                                    <div class="fm2-inline-label">Mortality</div>
                                    <div class="fm2-inline-value">{{ number_format($mortalityRate, 1) }}%</div>
                                </div>
                            </div>
                        </div>
                        <div class="fm2-inline-metrics mt-6">
                            <div class="fm2-inline-metric">
                                <div class="fm2-inline-bar" style="background:#0ea5e9;"></div>
                                <div>
                                    <div class="fm2-inline-label">Purchased Added</div>
                                    <div class="fm2-inline-value">{{ number_format($purchasedStockMetrics['added_quantity'] ?? 0) }}</div>
                                </div>
                            </div>
                            <div class="fm2-inline-metric">
                                <div class="fm2-inline-bar" style="background:#f59e0b;"></div>
                                <div>
                                    <div class="fm2-inline-label">Pending Intake</div>
                                    <div class="fm2-inline-value">{{ number_format($purchasedStockMetrics['pending_intakes'] ?? 0) }}</div>
                                </div>
                            </div>
                            <div class="fm2-inline-metric">
                                <div class="fm2-inline-bar" style="background:#14b8a6;"></div>
                                <div>
                                    <div class="fm2-inline-label">Quarantined</div>
                                    <div class="fm2-inline-value">{{ number_format($purchasedStockMetrics['quarantined'] ?? 0) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="fm2-panel">
                    <div class="fm2-panel-head">
                        <div>
                            <h3 class="fm2-panel-title">Recent Activity</h3>
                            <div class="fm2-panel-sub">Recent operational signals</div>
                        </div>
                    </div>
                    <div class="fm2-activity-list">
                        @foreach($recentActivity as $activity)
                            <div class="fm2-activity-item">
                                <span class="fm2-activity-dot fm2-activity-{{ $activity['severity'] }}"></span>
                                <div>
                                    <div class="fm2-activity-title">{{ $activity['label'] }}</div>
                                    <div class="fm2-activity-sub">{{ $activity['description'] }}</div>
                                </div>
                                <div class="fm2-activity-time">{{ $activity['when'] }}</div>
                            </div>
                        @endforeach
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
        document.addEventListener('DOMContentLoaded', function () {
            Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
            Chart.defaults.color = '#6b7280';

            const gridOptions = {
                color: 'rgba(148,163,184,0.16)',
                drawBorder: false
            };

            function mountChart(id, config) {
                const canvas = document.getElementById(id);
                if (! canvas) {
                    return;
                }

                new Chart(canvas, config);
            }

            mountChart('stockDistributionChart', {
                type: 'doughnut',
                data: {
                    labels: ['Breeding', 'Standard', 'Piglets'],
                    datasets: [{
                        data: [
                            {{ $stockDistributionTotals['breeding'] }},
                            {{ $stockDistributionTotals['standard'] }},
                            {{ $stockDistributionTotals['piglets'] }}
                        ],
                        backgroundColor: ['#16a34a', '#2563eb', '#a855f7'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8 }
                        }
                    }
                }
            });

            mountChart('weightTrendsChart', {
                type: 'line',
                data: {
                    labels: @json($weightTrendLabels),
                    datasets: [{
                        label: 'Average Weight (kg)',
                        data: @json($weightTrendValues),
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37,99,235,.12)',
                        fill: true,
                        tension: .36,
                        pointRadius: 4,
                        pointBackgroundColor: '#2563eb',
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
                            labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8 }
                        }
                    },
                    scales: {
                        y: { beginAtZero: true, grid: gridOptions },
                        x: { grid: { display: false } }
                    }
                }
            });

            mountChart('breedingReadinessChart', {
                type: 'doughnut',
                data: {
                    labels: ['Ready', 'Due Soon', 'Not Ready'],
                    datasets: [{
                        data: [
                            {{ $breedingReadiness['ready'] }},
                            {{ $breedingReadiness['due_soon'] }},
                            {{ $breedingReadiness['not_ready'] }}
                        ],
                        backgroundColor: ['#16a34a', '#f59e0b', '#dc2626'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8 }
                        }
                    }
                }
            });
        });
    </script>
</x-app-layout>
