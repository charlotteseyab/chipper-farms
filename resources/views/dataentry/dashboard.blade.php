<x-app-layout>
    <style>
        .entry-page{
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(16,185,129,.08), transparent 28%),
                radial-gradient(circle at bottom right, rgba(5,150,105,.06), transparent 24%),
                linear-gradient(180deg, #f7fbf9 0%, #f3f8f5 100%);
        }

        .entry-wrap{
            max-width: 1280px;
            margin: 0 auto;
            padding: 18px 14px 56px;
        }

        .entry-header{
            background: linear-gradient(135deg, #166534 0%, #15803d 55%, #16a34a 100%);
            border-radius: 28px;
            box-shadow: 0 22px 55px rgba(22,101,52,.20);
            overflow: hidden;
            position: relative;
        }

        .entry-header::before{
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,.12), transparent 26%),
                radial-gradient(circle at bottom left, rgba(255,255,255,.08), transparent 24%);
            pointer-events: none;
        }

        .entry-header-inner{
            position: relative;
            z-index: 1;
            padding: 26px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .entry-eyebrow{
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            background: rgba(255,255,255,.14);
            border: 1px solid rgba(255,255,255,.16);
            border-radius: 999px;
            color: #dcfce7;
            font-size: .76rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .entry-dot{
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #bbf7d0;
            box-shadow: 0 0 0 5px rgba(187,247,208,.15);
        }

        .entry-title{
            font-size: clamp(1.8rem, 3.2vw, 2.6rem);
            font-weight: 900;
            color: #fff;
            line-height: 1.05;
            margin: 0;
        }

        .entry-subtitle{
            color: #dcfce7;
            margin-top: 8px;
            font-size: .96rem;
            line-height: 1.75;
            max-width: 700px;
        }

        .entry-actions{
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .entry-btn{
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 16px;
            border-radius: 14px;
            font-size: .82rem;
            font-weight: 700;
            transition: .25s ease;
            text-decoration: none;
        }

        .entry-btn-soft{
            background: rgba(255,255,255,.14);
            color: #fff;
            border: 1px solid rgba(255,255,255,.18);
        }

        .entry-btn-soft:hover{
            background: rgba(255,255,255,.22);
            transform: translateY(-1px);
        }

        .entry-section{
            margin-top: 24px;
        }

        .metric-grid{
            display: grid;
            grid-template-columns: repeat(5, minmax(0,1fr));
            gap: 16px;
        }

        .metric-card{
            position: relative;
            overflow: hidden;
            border-radius: 22px;
            padding: 20px;
            color: #fff;
            box-shadow: 0 16px 34px rgba(15,23,42,.10);
            transition: .28s ease;
            text-decoration: none;
        }

        .metric-card:hover{
            transform: translateY(-4px);
            box-shadow: 0 22px 40px rgba(15,23,42,.13);
        }

        .metric-card::after{
            content:"";
            position:absolute;
            right:-18px;
            top:-18px;
            width:90px;
            height:90px;
            border-radius:999px;
            background: rgba(255,255,255,.10);
        }

        .metric-green{ background: linear-gradient(135deg, #16a34a, #15803d); }
        .metric-blue{ background: linear-gradient(135deg, #2563eb, #1d4ed8); }
        .metric-red{ background: linear-gradient(135deg, #ef4444, #dc2626); }
        .metric-yellow{ background: linear-gradient(135deg, #f59e0b, #d97706); }
        .metric-slate{ background: linear-gradient(135deg, #334155, #1f2937); }

        .metric-label{
            color: rgba(255,255,255,.86);
            font-size: .82rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .metric-value{
            font-size: 1.9rem;
            line-height: 1.05;
            font-weight: 900;
            margin: 0;
        }

        .metric-sub{
            color: rgba(255,255,255,.82);
            font-size: .76rem;
            margin-top: 8px;
        }

        .metric-icon{
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,.16);
            flex-shrink: 0;
        }

        .surface-card{
            background: rgba(255,255,255,.92);
            border: 1px solid #e5efe9;
            border-radius: 26px;
            box-shadow: 0 14px 34px rgba(15,23,42,.06);
        }

        .surface-head{
            padding: 22px 24px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }

        .surface-title{
            font-size: 1.1rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
        }

        .surface-sub{
            font-size: .86rem;
            color: #64748b;
            margin-top: 4px;
        }

        .chart-toolbar{
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .chart-toolbar label{
            font-size: .82rem;
            font-weight: 700;
            color: #475569;
        }

        .chart-input{
            padding: 9px 12px;
            border: 1px solid #d7e3dc;
            border-radius: 12px;
            background: #fff;
            font-size: .84rem;
            color: #0f172a;
            outline: none;
        }

        .chart-input:focus{
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16,185,129,.14);
        }

        .chart-btn{
            padding: 10px 14px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #059669, #10b981);
            color: #fff;
            font-size: .82rem;
            font-weight: 700;
            transition: .25s ease;
        }

        .chart-btn:hover{
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(16,185,129,.18);
        }

        .chart-body{
            padding: 0 24px 24px;
        }

        .chart-frame{
            height: 290px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbfa 100%);
            border: 1px solid #edf3ef;
            border-radius: 22px;
            padding: 14px;
        }

        .content-grid{
            display: grid;
            grid-template-columns: minmax(0,2fr) minmax(320px,1fr);
            gap: 24px;
            margin-top: 24px;
        }

        .quick-grid{
            display: grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap: 14px;
            padding: 0 24px 24px;
        }

        .quick-card{
            position: relative;
            overflow: hidden;
            border-radius: 20px;
            padding: 18px;
            color: #fff;
            text-decoration: none;
            transition: .28s ease;
            box-shadow: 0 12px 26px rgba(15,23,42,.08);
        }

        .quick-card:hover{
            transform: translateY(-4px);
            box-shadow: 0 18px 34px rgba(15,23,42,.12);
        }

        .quick-card::after{
            content:"";
            position:absolute;
            right:-18px;
            top:-14px;
            width:76px;
            height:76px;
            border-radius:999px;
            background: rgba(255,255,255,.10);
        }

        .quick-green{ background: linear-gradient(135deg, #16a34a, #15803d); }
        .quick-blue{ background: linear-gradient(135deg, #2563eb, #1d4ed8); }
        .quick-pink{ background: linear-gradient(135deg, #ec4899, #db2777); }
        .quick-purple{ background: linear-gradient(135deg, #8b5cf6, #7c3aed); }

        .quick-top{
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .quick-icon{
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display:flex;
            align-items:center;
            justify-content:center;
            background: rgba(255,255,255,.16);
            flex-shrink: 0;
        }

        .quick-title{
            font-size: 1rem;
            font-weight: 800;
            margin: 0;
        }

        .quick-desc{
            color: rgba(255,255,255,.84);
            font-size: .78rem;
            margin-top: 2px;
        }

        .quick-foot{
            display:flex;
            align-items:center;
            color: rgba(255,255,255,.86);
            font-size: .78rem;
            font-weight: 600;
        }

        .activity-list{
            padding: 0 24px 24px;
            display: grid;
            gap: 12px;
        }

        .activity-item{
            display:flex;
            align-items:center;
            gap: 14px;
            padding: 14px;
            border: 1px solid #e7efea;
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #fafdfb 100%);
            text-decoration: none;
            transition: .25s ease;
        }

        .activity-item:hover{
            border-color: #d2e7dd;
            box-shadow: 0 10px 20px rgba(15,23,42,.05);
            transform: translateY(-1px);
        }

        .activity-icon{
            width: 40px;
            height: 40px;
            border-radius: 14px;
            display:flex;
            align-items:center;
            justify-content:center;
            flex-shrink: 0;
        }

        .activity-main{
            flex: 1;
            min-width: 0;
        }

        .activity-title{
            font-size: .9rem;
            font-weight: 700;
            color: #0f172a;
        }

        .activity-desc,
        .activity-time{
            font-size: .78rem;
            color: #64748b;
            margin-top: 2px;
        }

        .side-stack{
            display: grid;
            gap: 24px;
        }

        .summary-list,
        .alert-preview{
            padding: 0 24px 24px;
            display: grid;
            gap: 12px;
        }

        .summary-row{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap: 12px;
            padding: 14px;
            border-radius: 18px;
            border: 1px solid #e8f0eb;
        }

        .summary-left{
            display:flex;
            align-items:center;
            gap: 12px;
            min-width: 0;
        }

        .summary-icon{
            width: 38px;
            height: 38px;
            border-radius: 14px;
            display:flex;
            align-items:center;
            justify-content:center;
            flex-shrink: 0;
        }

        .summary-text{
            min-width: 0;
        }

        .summary-label{
            font-size: .86rem;
            font-weight: 700;
            color: #334155;
        }

        .summary-sub{
            font-size: .75rem;
            color: #64748b;
            margin-top: 2px;
        }

        .summary-value{
            font-size: 1.05rem;
            font-weight: 900;
        }

        .alert-tile{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 18px;
            border: 1px solid #efe7b8;
            background: linear-gradient(180deg, #fffbeb 0%, #fef3c7 100%);
        }

        .alert-left{
            display:flex;
            align-items:center;
            gap: 10px;
            min-width: 0;
        }

        .alert-code{
            font-size: .8rem;
            font-weight: 700;
            color: #111827;
        }

        .alert-meta{
            font-size: .74rem;
            color: #6b7280;
            margin-top: 2px;
        }

        .alert-status{
            text-align: right;
        }

        .alert-message{
            font-size: .76rem;
            font-weight: 700;
        }

        .alert-days{
            font-size: .72rem;
            color: #6b7280;
            margin-top: 2px;
        }

        .empty-state{
            text-align:center;
            padding: 34px 20px;
            color:#64748b;
        }

        .empty-icon{
            width: 52px;
            height: 52px;
            border-radius: 999px;
            background: #f1f5f9;
            display:flex;
            align-items:center;
            justify-content:center;
            margin: 0 auto 14px;
        }

        @media (max-width: 1200px){
            .metric-grid{
                grid-template-columns: repeat(3, minmax(0,1fr));
            }
        }

        @media (max-width: 1024px){
            .content-grid{
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px){
            .entry-wrap{
                padding: 14px 10px 42px;
            }

            .metric-grid,
            .quick-grid{
                grid-template-columns: 1fr;
            }

            .entry-header-inner,
            .surface-head{
                padding-left: 18px;
                padding-right: 18px;
            }

            .chart-body,
            .quick-grid,
            .activity-list,
            .summary-list,
            .alert-preview{
                padding-left: 18px;
                padding-right: 18px;
            }

            .chart-toolbar{
                align-items: stretch;
            }

            .chart-toolbar > div{
                width: 100%;
            }

            .chart-input,
            .chart-btn{
                width: 100%;
            }
        }
    </style>

    <div class="entry-page">
        <div class="entry-wrap">

            <div class="entry-header">
                <div class="entry-header-inner">
                    <div>
                        <div class="entry-eyebrow">
                            <span class="entry-dot"></span>
                            Daily Operations
                        </div>
                        <h1 class="entry-title">Data Entry Dashboard</h1>
                        <p class="entry-subtitle">Quick overview, recent activity, and shortcuts for the most important daily farm tasks.</p>
                    </div>

                    <div class="entry-actions">
                        <a href="{{ route('weights.create') }}" class="entry-btn entry-btn-soft">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            New Weight
                        </a>

                        <a href="{{ route('all-stocks.create') }}" class="entry-btn entry-btn-soft">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            New Pig
                        </a>
                    </div>
                </div>
            </div>

            <div class="entry-section">
                <div class="metric-grid">
                    <a href="{{ route('all-stocks.index') }}" class="metric-card metric-green">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="metric-label">Total Pigs</div>
                                <p class="metric-value">{{ number_format($totalPigs) }}</p>
                                <div class="metric-sub">{{ number_format($pigletsCount) }} piglets recorded</div>
                            </div>
                            <div class="metric-icon">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('weights.create') }}" class="metric-card metric-blue">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="metric-label">Weights Today</div>
                                <p class="metric-value">{{ $todayWeights }}</p>
                                <div class="metric-sub">Entries recorded today</div>
                            </div>
                            <div class="metric-icon">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                                </svg>
                            </div>
                        </div>
                    </a>

                    <!-- <a href="{{ route('weight-alerts.index') }}" class="metric-card metric-red">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="metric-label">Active Alerts</div>
                                <p class="metric-value">{{ $activeAlerts }}</p>
                                <div class="metric-sub">Need attention now</div>
                            </div>
                            <div class="metric-icon">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 18.5c-.77.833.192 2.5 1.732 2.5z"/>
                                </svg>
                            </div>
                        </div>
                    </a> -->

                    <button onclick="toggleWeighingAlertsModal()" class="metric-card metric-yellow text-left">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="metric-label">Weighing Alerts</div>
                                <p class="metric-value">{{ $overdueForWeighing }}</p>
                                <div class="metric-sub">{{ \App\Models\PigManagement::countDueForWeighing() }} due for weighing</div>
                            </div>
                            <div class="metric-icon">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                                </svg>
                            </div>
                        </div>
                    </button>

                    <div class="metric-card metric-slate">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="metric-label">This Week</div>
                                <p class="metric-value">{{ \App\Models\PigWeight::where('created_at', '>=', now()->startOfWeek())->count() }}</p>
                                <div class="metric-sub">Weights added this week</div>
                            </div>
                            <div class="metric-icon">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="entry-section surface-card">
                <div class="surface-head">
                    <div>
                        <h2 class="surface-title">Daily Weights Recorded</h2>
                        <p class="surface-sub">Counts per day with a 7-day moving average</p>
                    </div>

                    <div class="chart-toolbar">
                        <div class="flex items-center gap-2">
                            <label for="startDate">From:</label>
                            <input type="date" id="startDate" class="chart-input">
                        </div>
                        <div class="flex items-center gap-2">
                            <label for="endDate">To:</label>
                            <input type="date" id="endDate" class="chart-input">
                        </div>
                        <button onclick="updateChart()" class="chart-btn">Update</button>
                    </div>
                </div>

                <div class="chart-body">
                    <div class="chart-frame">
                        <canvas id="dailyWeightsChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="content-grid">
                <div class="space-y-6">
                    <div class="surface-card">
                        <div class="surface-head">
                            <div>
                                <h2 class="surface-title">Quick Actions</h2>
                                <p class="surface-sub">Fast entry points for the most common tasks</p>
                            </div>
                        </div>

                        <div class="quick-grid">
                            <a href="{{ route('all-stocks.create') }}" class="quick-card quick-green">
                                <div class="quick-top">
                                    <div class="quick-icon">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="quick-title">Add New Pig</h3>
                                        <p class="quick-desc">Register new livestock</p>
                                    </div>
                                </div>
                                <div class="quick-foot">
                                    <span>Start registration</span>
                                    <svg class="w-3 h-3 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </div>
                            </a>

                            <a href="{{ route('weights.create') }}" class="quick-card quick-blue">
                                <div class="quick-top">
                                    <div class="quick-icon">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="quick-title">Record Weight</h3>
                                        <p class="quick-desc">Log weight measurements</p>
                                    </div>
                                </div>
                                <div class="quick-foot">
                                    <span>Enter weight data</span>
                                    <svg class="w-3 h-3 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </div>
                            </a>

                            <a href="{{ route('mating.index') }}" class="quick-card quick-pink">
                                <div class="quick-top">
                                    <div class="quick-icon">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="quick-title">Plan Mating</h3>
                                        <p class="quick-desc">Schedule breeding</p>
                                    </div>
                                </div>
                                <div class="quick-foot">
                                    <span>Create mating plan</span>
                                    <svg class="w-3 h-3 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </div>
                            </a>

                            <a href="{{ route('weights.reports') }}" class="quick-card quick-purple">
                                <div class="quick-top">
                                    <div class="quick-icon">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="quick-title">View Weight Reports</h3>
                                        <p class="quick-desc">Analytics and insights</p>
                                    </div>
                                </div>
                                <div class="quick-foot">
                                    <span>Generate reports</span>
                                    <svg class="w-3 h-3 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </div>
                            </a>
                        </div>
                    </div>

                    <div class="surface-card">
                        <div class="surface-head">
                            <div>
                                <h2 class="surface-title">Recent Activity</h2>
                                <p class="surface-sub">Latest actions recorded across the system</p>
                            </div>
                        </div>

                        <div class="activity-list">
                            @forelse($recentActivities as $activity)
                                <a href="{{ $activity['route'] }}" class="activity-item">
                                    <div class="activity-icon bg-{{ $activity['color'] }}-100 text-{{ $activity['color'] }}-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $activity['icon'] }}"/>
                                        </svg>
                                    </div>

                                    <div class="activity-main">
                                        <div class="activity-title">{{ $activity['title'] }}</div>
                                        <div class="activity-desc">{{ $activity['description'] }}</div>
                                        <div class="activity-time">{{ $activity['time']->diffForHumans() }}</div>
                                    </div>

                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            @empty
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                        </svg>
                                    </div>
                                    <p class="text-sm">No recent activity</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="side-stack">
                    <div class="surface-card">
                        <div class="surface-head">
                            <div>
                                <h2 class="surface-title">Today's Summary</h2>
                                <p class="surface-sub">Daily activity at a glance</p>
                            </div>
                        </div>

                        <div class="summary-list">
                            <div class="summary-row bg-blue-50/70">
                                <div class="summary-left">
                                    <div class="summary-icon bg-blue-100 text-blue-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                                        </svg>
                                    </div>
                                    <div class="summary-text">
                                        <div class="summary-label">Weights Recorded</div>
                                        <div class="summary-sub">Today</div>
                                    </div>
                                </div>
                                <div class="summary-value text-blue-600">{{ $todayWeights }}</div>
                            </div>

                            <div class="summary-row bg-green-50/70">
                                <div class="summary-left">
                                    <div class="summary-icon bg-green-100 text-green-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                    </div>
                                    <div class="summary-text">
                                        <div class="summary-label">New Pigs Added</div>
                                        <div class="summary-sub">Today</div>
                                    </div>
                                </div>
                                <div class="summary-value text-green-600">{{ \App\Models\PigManagement::whereDate('created_at', today())->count() }}</div>
                            </div>

                            <div class="summary-row bg-purple-50/70">
                                <div class="summary-left">
                                    <div class="summary-icon bg-purple-100 text-purple-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                        </svg>
                                    </div>
                                    <div class="summary-text">
                                        <div class="summary-label">Weights Added</div>
                                        <div class="summary-sub">This Week</div>
                                    </div>
                                </div>
                                <div class="summary-value text-purple-600">{{ \App\Models\PigWeight::where('created_at', '>=', now()->startOfWeek())->count() }}</div>
                            </div>

                            <div class="summary-row bg-red-50/70">
                                <div class="summary-left">
                                    <div class="summary-icon bg-red-100 text-red-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                        </svg>
                                    </div>
                                    <div class="summary-text">
                                        <div class="summary-label">Delayed Weighings</div>
                                        <div class="summary-sub">Overdue pigs</div>
                                    </div>
                                </div>
                                <div class="summary-value text-red-600">{{ $overdueForWeighing }}</div>
                            </div>
                        </div>
                    </div>

                    <button onclick="toggleWeighingAlertsModal()" class="surface-card text-left hover:shadow-xl transition-all duration-300">
                        <div class="surface-head">
                            <div>
                                <h2 class="surface-title">Weighing Schedule Alerts</h2>
                                <p class="surface-sub">Pigs that need attention now</p>
                            </div>
                            <div class="summary-icon bg-yellow-100 text-yellow-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                                </svg>
                            </div>
                        </div>

                        <div class="alert-preview">
                            @forelse($overduePigsPreview as $row)
                                @php $status = $row['weighingStatus']; @endphp

                                <div class="alert-tile">
                                    <div class="alert-left">
                                        <span class="text-lg">{{ $status['icon'] }}</span>
                                        <div>
                                            <div class="alert-code">{{ $row['pig']->ear_tag_number }}</div>
                                            <div class="alert-meta">{{ $row['pig']->breed }} • {{ $row['pig']->gender }}</div>
                                        </div>
                                    </div>

                                    <div class="alert-status">
                                        <div class="alert-message text-{{ $status['color'] }}-700">{{ $status['message'] }}</div>
                                        <div class="alert-days">{{ (int)$row['daysSince'] }} days</div>
                                    </div>
                                </div>
                            @empty
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <p class="text-sm">All pigs are up to date with weighing</p>
                                </div>
                            @endforelse

                            @if($overduePigsPreview->count() > 0)
                                <div class="text-center text-xs font-semibold text-yellow-700 mt-1">
                                    Click to view detailed alerts →
                                </div>
                            @endif
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="weighingAlertsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Weighing Alerts Details</h2>
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
                            <div class="bg-{{ $row['weighingStatus']['color'] }}-50 border border-{{ $row['weighingStatus']['color'] }}-200 rounded-lg p-4">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-2">
                                            <span class="text-2xl mr-3">{{ $row['weighingStatus']['icon'] }}</span>
                                            <div>
                                                <h3 class="text-lg font-semibold text-gray-900">{{ $row['pig']->ear_tag_number }}</h3>
                                                <p class="text-sm text-gray-600">{{ $row['pig']->breed }} • {{ $row['pig']->gender }} • {{ $row['pig']->stock_type }}</p>
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
                                                    {{ max(0, (int)$row['overdueDays']) }} days
                                                </p>
                                            </div>
                                        </div>

                                        <div class="mt-3 flex items-center justify-between">
                                            <div class="flex items-center">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $row['weighingStatus']['color'] }}-100 text-{{ $row['weighingStatus']['color'] }}-800">
                                                    {{ $row['weighingStatus']['message'] }}
                                                </span>
                                                <span class="ml-2 text-xs text-gray-500">
                                                    {{ (int)$row['daysSince'] }} days since last weighing
                                                </span>
                                            </div>

                                            <a href="{{ route('weights.create', ['pig_id' => $row['pig']->id]) }}"
                                               class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-{{ $row['weighingStatus']['color'] }}-600 hover:bg-{{ $row['weighingStatus']['color'] }}-700 transition-colors">
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
                    <a href="{{ route('weights.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        View All Weights
                    </a>
                    <button onclick="toggleWeighingAlertsModal()" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-600 hover:bg-gray-700 transition-colors">
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

        document.getElementById('weighingAlertsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                toggleWeighingAlertsModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('weighingAlertsModal');
                if (!modal.classList.contains('hidden')) {
                    toggleWeighingAlertsModal();
                }
            }
        });

        let dailyWeightsChart = null;

        document.addEventListener('DOMContentLoaded', function() {
            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(startDate.getDate() - 6);
            document.getElementById('startDate').value = startDate.toISOString().split('T')[0];
            document.getElementById('endDate').value = endDate.toISOString().split('T')[0];
            initializeChart();
        });

        function createGradient(ctx) {
            const gradient = ctx.createLinearGradient(0, 0, 0, 300);
            gradient.addColorStop(0, 'rgba(16, 185, 129, 0.35)');
            gradient.addColorStop(1, 'rgba(16, 185, 129, 0.05)');
            return gradient;
        }

        function movingAverage(values, windowSize = 7) {
            const result = [];
            for (let i = 0; i < values.length; i++) {
                const start = Math.max(0, i - windowSize + 1);
                const window = values.slice(start, i + 1);
                const avg = window.reduce((a, b) => a + b, 0) / window.length;
                result.push(Number(avg.toFixed(2)));
            }
            return result;
        }

        function initializeChart() {
            const ctx = document.getElementById('dailyWeightsChart').getContext('2d');
            const dailyWeightsData = @json($dailyWeightsData);
            const labels = dailyWeightsData.map(item => item.date);
            const counts = dailyWeightsData.map(item => item.count);
            const avg7 = movingAverage(counts, 7);

            const fillGradient = createGradient(ctx);

            dailyWeightsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Weights Recorded',
                            data: counts,
                            borderColor: 'rgb(16, 185, 129)',
                            backgroundColor: fillGradient,
                            borderWidth: 2,
                            fill: true,
                            tension: 0.35,
                            pointBackgroundColor: 'rgb(16, 185, 129)',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 4
                        },
                        {
                            label: '7-day Avg',
                            data: avg7,
                            borderColor: 'rgb(5, 150, 105)',
                            backgroundColor: 'transparent',
                            borderDash: [6, 6],
                            borderWidth: 2,
                            tension: 0.35,
                            pointRadius: 0
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0,0,0,0.85)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderWidth: 0,
                            padding: 10,
                            displayColors: true,
                            callbacks: {
                                title: (items) => items[0].label,
                                label: (item) => `${item.dataset.label}: ${item.parsed.y}`
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.06)', drawBorder: false },
                            ticks: { color: '#6B7280' }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#6B7280' }
                        }
                    },
                    animation: { duration: 800, easing: 'easeOutQuart' }
                }
            });
        }

        function updateChart() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            if (!startDate || !endDate) { alert('Please select both start and end dates'); return; }
            if (new Date(startDate) > new Date(endDate)) { alert('Start date cannot be after end date'); return; }

            const btn = document.querySelector('button[onclick="updateChart()"]');
            const originalText = btn.textContent;
            btn.textContent = 'Loading...';
            btn.disabled = true;

            fetch(`/api/daily-weights-data?start_date=${startDate}&end_date=${endDate}`)
                .then(r => r.json())
                .then(data => {
                    const avg7 = movingAverage(data.data, 7);
                    dailyWeightsChart.data.labels = data.labels;
                    dailyWeightsChart.data.datasets[0].data = data.data;
                    dailyWeightsChart.data.datasets[1].data = avg7;
                    dailyWeightsChart.update('active');
                })
                .catch(err => {
                    console.error('Error:', err);
                    alert('Error loading chart data.');
                })
                .finally(() => {
                    btn.textContent = originalText;
                    btn.disabled = false;
                });
        }
    </script>
</x-app-layout>