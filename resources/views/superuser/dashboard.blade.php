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
            max-width: 100%;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            overflow-x: auto;
        }

        .su-range-btn{
            flex: 0 0 auto;
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

        .su-select option{
            background: #ffffff;
            color: #0f172a;
        }

        .su-select option:checked{
            background: #d1fae5;
            color: #064e3b;
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
            min-width: 0;
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
            min-width: 0;
        }

        .su-chart-shell{
            height: 320px;
            position: relative;
            width: 100%;
            min-width: 0;
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #eef2f7;
            padding: 12px;
        }

        .su-chart-shell canvas{
            display:block;
            width:100% !important;
            height:100% !important;
            max-width:100%;
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

        .su-command-grid{
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 18px;
        }

        .su-command-card{
            background: #fff;
            border: 1px solid #dbeafe;
            border-top: 5px solid #10b981;
            border-radius: 18px;
            box-shadow: 0 12px 30px rgba(15,23,42,.06);
            overflow: hidden;
        }

        .su-command-card.blue{
            border-color: #bfdbfe;
            border-top-color: #0ea5e9;
        }

        .su-command-card.amber{
            border-color: #fed7aa;
            border-top-color: #f59e0b;
        }

        .su-command-head{
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 18px 18px 12px;
        }

        .su-command-icon{
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #047857;
            background: #d1fae5;
            flex: 0 0 auto;
        }

        .su-command-card.blue .su-command-icon{
            color: #0369a1;
            background: #e0f2fe;
        }

        .su-command-card.amber .su-command-icon{
            color: #b45309;
            background: #ffedd5;
        }

        .su-command-title{
            margin: 0;
            font-size: 1rem;
            font-weight: 900;
            color: #0f172a;
        }

        .su-command-sub{
            margin-top: 3px;
            font-size: .76rem;
            color: #64748b;
        }

        .su-command-metrics{
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            border-top: 1px solid #eef2f7;
        }

        .su-command-metric{
            padding: 14px 16px;
            border-right: 1px solid #eef2f7;
            border-bottom: 1px solid #eef2f7;
        }

        .su-command-metric:nth-child(2n){
            border-right: 0;
        }

        .su-command-metric:nth-last-child(-n+2){
            border-bottom: 0;
        }

        .su-command-metric span{
            display: block;
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 7px;
        }

        .su-command-metric strong{
            display: block;
            font-size: 1.15rem;
            font-weight: 900;
            color: #0f172a;
            line-height: 1;
            overflow-wrap: anywhere;
        }

        .su-main-grid{
            display: grid;
            grid-template-columns: minmax(0, 1.8fr) minmax(320px, .9fr);
            gap: 16px;
            margin-bottom: 18px;
        }

        .su-table-wrap{
            overflow-x: auto;
        }

        .su-data-table{
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }

        .su-data-table th{
            text-align: left;
            padding: 12px 16px;
            font-size: .68rem;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #64748b;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
        }

        .su-data-table td{
            padding: 13px 16px;
            font-size: .84rem;
            color: #334155;
            border-bottom: 1px solid #eef2f7;
            vertical-align: middle;
        }

        .su-data-table tr:last-child td{
            border-bottom: 0;
        }

        .su-status{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 76px;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: .7rem;
            font-weight: 800;
        }

        .su-status.healthy{
            background: #dcfce7;
            color: #166534;
        }

        .su-status.at-risk{
            background: #fef3c7;
            color: #92400e;
        }

        .su-status.critical{
            background: #fee2e2;
            color: #991b1b;
        }

        .su-progress{
            height: 7px;
            width: 92px;
            overflow: hidden;
            border-radius: 999px;
            background: #e5e7eb;
        }

        .su-progress span{
            display: block;
            height: 100%;
            border-radius: inherit;
            background: #22c55e;
        }

        .su-progress.medium span{
            background: #f59e0b;
        }

        .su-progress.low span{
            background: #ef4444;
        }

        .su-queue-list{
            display: grid;
            gap: 10px;
        }

        .su-queue-item{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 13px 14px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid #eef2f7;
        }

        .su-queue-item h4{
            margin: 0;
            font-size: .86rem;
            font-weight: 800;
            color: #0f172a;
        }

        .su-queue-item p{
            margin: 3px 0 0;
            font-size: .72rem;
            color: #64748b;
            line-height: 1.35;
        }

        .su-queue-count{
            min-width: 48px;
            text-align: center;
            border-radius: 12px;
            padding: 8px 10px;
            font-size: .92rem;
            font-weight: 900;
            color: #0f172a;
            background: #fff;
            border: 1px solid #e2e8f0;
        }

        .su-queue-count.red{
            color: #991b1b;
            background: #fee2e2;
            border-color: #fecaca;
        }

        .su-queue-count.amber{
            color: #92400e;
            background: #fef3c7;
            border-color: #fde68a;
        }

        .su-queue-count.green{
            color: #166534;
            background: #dcfce7;
            border-color: #bbf7d0;
        }

        .su-risk-grid{
            display: grid;
            gap: 8px;
            overflow-x: auto;
        }

        .su-risk-row{
            display: grid;
            grid-template-columns: 130px repeat(4, minmax(0, 1fr));
            gap: 8px;
            align-items: center;
        }

        .su-risk-label{
            font-size: .78rem;
            font-weight: 800;
            color: #475569;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .su-risk-cell{
            height: 28px;
            border-radius: 8px;
            border: 1px solid rgba(15,23,42,.05);
        }

        .su-risk-cell.healthy{ background: #86efac; }
        .su-risk-cell.low{ background: #fde68a; }
        .su-risk-cell.medium{ background: #fdba74; }
        .su-risk-cell.high{ background: #f87171; }

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

        .su-user-hero-inner > *{ min-width:0; }
        .su-user-hero-inner p,
        .su-user-hero-inner h2{ overflow-wrap:anywhere; }

        @media (max-width: 1280px){
            .su-kpi-grid,
            .su-user-kpi-grid{
                grid-template-columns: repeat(3, minmax(0,1fr));
            }

            .su-command-grid{
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 1024px){
            .su-three-grid,
            .su-chart-grid,
            .su-detail-grid,
            .su-main-grid{
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

            .su-filter{ padding:16px; }
            .su-filter-row,
            .su-filter-group,
            .su-custom-controls{ width:100%; }
            .su-filter-group{ align-items:stretch !important; }
            .su-filter-group .su-select{ min-width:0 !important; }
            .su-range-wrap{ display:flex; width:100%; }
            .su-chart-shell{ height:280px; padding:8px; }
            .su-info-head,
            .su-chart-head{ padding-left:16px; padding-right:16px; }
            .su-info-body,
            .su-chart-body{ padding-left:16px; padding-right:16px; padding-bottom:16px; }
            .su-user-hero-inner{ align-items:flex-start !important; }
            .su-user-hero-inner .text-right{ width:100%; text-align:left; }

            .su-risk-row{
                grid-template-columns: 1fr repeat(4, minmax(46px, 1fr));
            }
        }

        @media (max-width: 560px){
            .su-kpi-grid,
            .su-user-kpi-grid{
                grid-template-columns: 1fr;
            }

            .su-panel,
            .su-info-card,
            .su-chart-card,
            .su-table-card,
            .su-detail-card{ border-radius:18px; }
            .su-custom-controls{ flex-direction:column; align-items:stretch !important; }
            .su-custom-controls > *{ width:100%; }
            .su-custom-controls > span{ width:auto; text-align:center; }
            .su-export-actions{ display:grid !important; grid-template-columns:1fr 1fr; width:100%; }
            .su-command-metric{ padding:12px; }
            .su-command-metric strong{ font-size:1rem; }
            .su-risk-row{ min-width:320px; grid-template-columns:104px repeat(4, minmax(42px,1fr)); }
            .su-weight-alert{ flex-wrap:wrap; }
            .su-chart-shell{ height:250px; }
        }
    </style>

    <div class="su-page">
        <div class="su-wrap">
            <!-- <div class="su-hero">
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
            </div> -->

            <x-dashboard.livestock-overview />

            <div class="su-panel su-filter">
                <div class="su-filter-title">Dashboard Filters</div>
                <form method="GET" action="{{ route('superuser.dashboard') }}" id="dashboardFilterForm" class="flex flex-col gap-4">
                    <div class="su-filter-row flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div class="su-filter-group flex items-center gap-3 flex-wrap">
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

                        <div class="su-filter-group flex items-center gap-3 flex-wrap">
                            <span class="su-soft-label">View as</span>
                            <select
                                id="user_id"
                                name="user_id"
                                class="su-select min-w-[220px]"
                                onchange="document.getElementById('dashboardFilterForm').submit()"
                            >
                                <option value="">All users</option>
                                @foreach($dashboardUsers as $u)
                                    @php
                                        $userLabel = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
                                        $userLabel = $userLabel !== '' ? $userLabel : ($u->name ?: $u->email);
                                    @endphp
                                    <option value="{{ $u->id }}" @selected((int)($selectedUserId ?? 0) === $u->id)>
                                        {{ $userLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 flex-wrap border-t border-gray-200 pt-3" id="customDateRange" style="display: {{ $currentRange === 'custom' ? 'flex' : 'none' }};">
                        <span class="su-soft-label">Custom Range</span>
                        <div class="su-custom-controls flex items-center gap-2 flex-wrap">
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
                            <h3 class="text-sm font-semibold text-gray-900">System Snapshot</h3>
                            <p class="text-xs text-gray-500 mt-1">Export the current superuser view using the selected filters.</p>
                        </div>
                        <div class="su-export-actions flex gap-2 flex-wrap">
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

                <div class="su-command-grid">
                    <div class="su-command-card">
                        <div class="su-command-head">
                            <div class="su-command-icon">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="su-command-title">Platform Health</h3>
                                <p class="su-command-sub">Access status and active risk signals.</p>
                            </div>
                        </div>
                        <div class="su-command-metrics">
                            <div class="su-command-metric">
                                <span>Active Users</span>
                                <strong>{{ number_format($platformCards['health']['active_users'] ?? 0) }}</strong>
                            </div>
                            <div class="su-command-metric">
                                <span>Inactive Users</span>
                                <strong>{{ number_format($platformCards['health']['inactive_users'] ?? 0) }}</strong>
                            </div>
                            <div class="su-command-metric">
                                <span>Open Alerts</span>
                                <strong>{{ number_format($platformCards['health']['open_alerts'] ?? 0) }}</strong>
                            </div>
                            <div class="su-command-metric">
                                <span>Critical Alerts</span>
                                <strong class="text-rose-700">{{ number_format($platformCards['health']['critical_alerts'] ?? 0) }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="su-command-card blue">
                        <div class="su-command-head">
                            <div class="su-command-icon">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10.5L12 4l9 6.5M5 10v9h14v-9M9 19v-5h6v5"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="su-command-title">Farm Portfolio</h3>
                                <p class="su-command-sub">Stock, breeding base, and estimated value.</p>
                            </div>
                        </div>
                        <div class="su-command-metrics">
                            <div class="su-command-metric">
                                <span>Total Pigs</span>
                                <strong>{{ number_format($platformCards['portfolio']['total_pigs'] ?? 0) }}</strong>
                            </div>
                            <div class="su-command-metric">
                                <span>Live Stock</span>
                                <strong>{{ number_format($platformCards['portfolio']['live_stock'] ?? 0) }}</strong>
                            </div>
                            <div class="su-command-metric">
                                <span>Breeding Stock</span>
                                <strong>{{ number_format($platformCards['portfolio']['breeding_stock'] ?? 0) }}</strong>
                            </div>
                            <div class="su-command-metric">
                                <span>Farm Value</span>
                                <strong>₵{{ number_format($platformCards['portfolio']['farm_value'] ?? 0, 0) }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="su-command-card amber">
                        <div class="su-command-head">
                            <div class="su-command-icon">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V6m0 10v2M5 4h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="su-command-title">Financial Exposure</h3>
                                <p class="su-command-sub">Revenue, unpaid sales, and aged risk.</p>
                            </div>
                        </div>
                        <div class="su-command-metrics">
                            <div class="su-command-metric">
                                <span>Revenue</span>
                                <strong>₵{{ number_format($platformCards['finance']['revenue'] ?? 0, 0) }}</strong>
                            </div>
                            <div class="su-command-metric">
                                <span>Unpaid Sales</span>
                                <strong>₵{{ number_format($platformCards['finance']['unpaid_sales'] ?? 0, 0) }}</strong>
                            </div>
                            <div class="su-command-metric">
                                <span>60+ Days</span>
                                <strong class="text-rose-700">₵{{ number_format($platformCards['finance']['aged_receivables'] ?? 0, 0) }}</strong>
                            </div>
                            <div class="su-command-metric">
                                <span>Net Position</span>
                                <strong>₵{{ number_format($platformCards['finance']['net_position'] ?? 0, 0) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="su-main-grid">
                    <div class="su-table-card">
                        <div class="su-info-head">
                            <div>
                                <h3 class="su-info-title">Portfolio Overview</h3>
                                <div class="su-info-sub">Workspace comparison by stock, revenue, data quality, alerts, and status</div>
                            </div>
                        </div>
                        <div class="su-table-wrap">
                            <table class="su-data-table">
                                <thead>
                                    <tr>
                                        <th>Workspace</th>
                                        <th>Manager</th>
                                        <th>Stock</th>
                                        <th>Revenue</th>
                                        <th>Data Quality</th>
                                        <th>Alerts</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($portfolioOverview as $workspace)
                                        @php
                                            $qualityTone = $workspace['data_quality'] >= 85 ? 'good' : ($workspace['data_quality'] >= 70 ? 'medium' : 'low');
                                            $statusClass = str($workspace['status'])->lower()->replace(' ', '-');
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="font-semibold text-gray-900">{{ $workspace['name'] }}</div>
                                                <div class="text-xs text-gray-500">{{ $workspace['active'] ? 'Active workspace' : 'Inactive workspace' }}</div>
                                            </td>
                                            <td>{{ $workspace['manager'] }}</td>
                                            <td>{{ number_format($workspace['stock']) }}</td>
                                            <td>₵{{ number_format($workspace['revenue'], 0) }}</td>
                                            <td>
                                                <div class="flex items-center gap-2">
                                                    <div class="su-progress {{ $qualityTone === 'good' ? '' : $qualityTone }}">
                                                        <span style="width: {{ $workspace['data_quality'] }}%"></span>
                                                    </div>
                                                    <span class="text-xs font-semibold text-gray-600">{{ $workspace['data_quality'] }}%</span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="{{ $workspace['alerts'] > 0 ? 'text-rose-700 font-bold' : 'text-gray-700 font-semibold' }}">
                                                    {{ number_format($workspace['alerts']) }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="su-status {{ $statusClass }}">{{ $workspace['status'] }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-gray-500 py-8">No workspace data available.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="su-info-card">
                        <div class="su-info-head">
                            <div>
                                <h3 class="su-info-title">Governance Queue</h3>
                                <div class="su-info-sub">Items needing superuser attention</div>
                            </div>
                        </div>
                        <div class="su-info-body">
                            <div class="su-queue-list">
                                @foreach($governanceQueue as $item)
                                    <div class="su-queue-item">
                                        <div>
                                            <h4>{{ $item['label'] }}</h4>
                                            <p>{{ $item['detail'] }}</p>
                                        </div>
                                        <div class="su-queue-count {{ $item['tone'] }}">
                                            @if($item['money'] ?? false)
                                                ₵{{ number_format($item['count'], 0) }}
                                            @else
                                                {{ number_format($item['count']) }}
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                @if(!empty($financialTrends) && !empty($workspaceRevenueComparison))
                    <div class="su-chart-grid">
                        <div class="su-chart-card">
                            <div class="su-chart-head">
                                <h3 class="su-chart-title">Cross-Farm Performance</h3>
                                <div class="su-chart-sub">Monthly revenue trend for the selected period</div>
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
                                <div class="su-chart-sub">Portfolio comparison across farms</div>
                            </div>
                            <div class="su-chart-body">
                                <div class="su-chart-shell">
                                    <canvas id="workspaceRevenueChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="su-chart-grid">
                    <div class="su-info-card">
                        <div class="su-info-head">
                            <div>
                                <h3 class="su-info-title">Risk Heatmap</h3>
                                <div class="su-info-sub">Financial, operational, data quality, and stock risk by workspace</div>
                            </div>
                        </div>
                        <div class="su-info-body">
                            <div class="su-risk-grid">
                                <div class="su-risk-row">
                                    <span></span>
                                    <span class="text-[11px] font-bold text-gray-500">Finance</span>
                                    <span class="text-[11px] font-bold text-gray-500">Ops</span>
                                    <span class="text-[11px] font-bold text-gray-500">Data</span>
                                    <span class="text-[11px] font-bold text-gray-500">Stock</span>
                                </div>
                                @forelse($riskMatrix as $risk)
                                    <div class="su-risk-row">
                                        <div class="su-risk-label" title="{{ $risk['name'] }}">{{ $risk['name'] }}</div>
                                        <div class="su-risk-cell {{ $risk['financial'] }}"></div>
                                        <div class="su-risk-cell {{ $risk['operational'] }}"></div>
                                        <div class="su-risk-cell {{ $risk['data_quality'] }}"></div>
                                        <div class="su-risk-cell {{ $risk['stock'] }}"></div>
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-500">No risk data available.</p>
                                @endforelse
                                <div class="flex items-center gap-3 pt-2 text-xs text-gray-500 flex-wrap">
                                    <span class="inline-flex items-center gap-1"><span class="su-risk-cell healthy w-4 h-4"></span> Healthy</span>
                                    <span class="inline-flex items-center gap-1"><span class="su-risk-cell low w-4 h-4"></span> Low</span>
                                    <span class="inline-flex items-center gap-1"><span class="su-risk-cell medium w-4 h-4"></span> Medium</span>
                                    <span class="inline-flex items-center gap-1"><span class="su-risk-cell high w-4 h-4"></span> High</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="su-info-card">
                        <div class="su-info-head">
                            <div>
                                <h3 class="su-info-title">Data Quality</h3>
                                <div class="su-info-sub">Core record completeness issues</div>
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
                </div>

                <div class="su-three-grid">
                    <div class="su-info-card">
                        <div class="su-info-head">
                            <div>
                                <h3 class="su-info-title">Purchased Stock</h3>
                                <div class="su-info-sub">External stock intake and stock-entry progress</div>
                            </div>
                        </div>
                        <div class="su-info-body">
                            <div class="su-stat-list">
                                <div class="su-stat-row">
                                    <span>Pending Intake</span>
                                    <span class="text-amber-700">{{ number_format($purchasedStockMetrics['pending_intakes'] ?? 0) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Quarantined</span>
                                    <span>{{ number_format($purchasedStockMetrics['quarantined'] ?? 0) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Ready for Stock</span>
                                    <span>{{ number_format($purchasedStockMetrics['approved_waiting_stock'] ?? 0) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Added Quantity</span>
                                    <span>{{ number_format($purchasedStockMetrics['added_quantity'] ?? 0) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>Investment Added</span>
                                    <span>₵{{ number_format($purchasedStockMetrics['total_investment'] ?? 0, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="su-info-card">
                        <div class="su-info-head">
                            <div>
                                <h3 class="su-info-title">Receivables Aging</h3>
                                <div class="su-info-sub">Unpaid sales grouped by age</div>
                            </div>
                        </div>
                        <div class="su-info-body">
                            <div class="su-stat-list">
                                <div class="su-stat-row">
                                    <span>0-7 days</span>
                                    <span>₵{{ number_format(($receivablesAging['0_7']['amount'] ?? 0), 2) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>8-30 days</span>
                                    <span>₵{{ number_format(($receivablesAging['8_30']['amount'] ?? 0), 2) }}</span>
                                </div>
                                <div class="su-stat-row">
                                    <span>31-60 days</span>
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

                    <div class="su-info-card">
                        <div class="su-info-head">
                            <div>
                                <h3 class="su-info-title">Audit Trail</h3>
                                <div class="su-info-sub">Recent system activity and record changes</div>
                            </div>
                        </div>
                        <div class="su-info-body">
                            @if($auditTrail->isEmpty())
                                <p class="text-sm text-gray-500">No recent activity logged.</p>
                            @else
                                <ul class="divide-y divide-gray-100">
                                    @foreach($auditTrail as $log)
                                        <li class="py-2 text-sm">
                                            <p class="text-gray-900">
                                                <span class="font-semibold">
                                                    {{ trim((optional($log->user)->first_name ?? '') . ' ' . (optional($log->user)->last_name ?? '')) ?: 'System' }}
                                                </span>
                                                <span class="text-gray-600">
                                                    - {{ $log->action ?? 'activity' }}
                                                </span>
                                            </p>
                                            <p class="text-xs text-gray-500">{{ $log->record_label ?? $log->name ?? $log->description ?? 'System activity' }}</p>
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
                                <h3 class="su-info-title">Operations Snapshot</h3>
                                <div class="su-info-sub">Overdue and upcoming work across the system</div>
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
                        <div class="su-user-hero-inner flex items-center justify-between gap-4 flex-wrap">
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
            const compactChartLayout = window.matchMedia('(max-width: 640px)').matches;
            const compactMoney = (value) => {
                const amount = Number(value) || 0;
                return '₵' + new Intl.NumberFormat('en-GH', {
                    notation: compactChartLayout ? 'compact' : 'standard',
                    maximumFractionDigits: compactChartLayout ? 1 : 0,
                }).format(amount);
            };

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
                        maintainAspectRatio: false,
                        resizeDelay: 120,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        layout: {
                            padding: compactChartLayout ? 2 : 8,
                        },
                        plugins: {
                            legend: {
                                position: 'top',
                                align: 'start',
                                labels: {
                                    boxWidth: compactChartLayout ? 10 : 16,
                                    boxHeight: compactChartLayout ? 10 : 12,
                                    padding: compactChartLayout ? 10 : 16,
                                    font: { size: compactChartLayout ? 10 : 12 },
                                },
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
                                    maxTicksLimit: compactChartLayout ? 5 : 7,
                                    font: { size: compactChartLayout ? 10 : 12 },
                                    callback: function(value) {
                                        return compactMoney(value);
                                    }
                                },
                                grid: {
                                    color: 'rgba(148, 163, 184, 0.16)',
                                }
                            },
                            x: {
                                ticks: {
                                    autoSkip: true,
                                    maxTicksLimit: compactChartLayout ? 4 : 8,
                                    maxRotation: 0,
                                    minRotation: 0,
                                    font: { size: compactChartLayout ? 10 : 12 },
                                },
                                grid: { display: false },
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

                if (compactChartLayout && workspaceCtx.parentElement) {
                    workspaceCtx.parentElement.style.height = Math.min(520, Math.max(250, workspaceNames.length * 44 + 56)) + 'px';
                }

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
                        indexAxis: compactChartLayout ? 'y' : 'x',
                        responsive: true,
                        maintainAspectRatio: false,
                        resizeDelay: 120,
                        layout: {
                            padding: compactChartLayout ? 2 : 8,
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const value = compactChartLayout ? context.parsed.x : context.parsed.y;
                                        return 'Revenue: ₵' + Number(value).toLocaleString();
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: {
                                    autoSkip: true,
                                    maxTicksLimit: compactChartLayout ? 4 : 7,
                                    maxRotation: compactChartLayout ? 0 : 35,
                                    minRotation: 0,
                                    font: { size: compactChartLayout ? 10 : 12 },
                                    callback: function(value) {
                                        if (compactChartLayout) {
                                            return compactMoney(value);
                                        }

                                        const label = this.getLabelForValue(value);
                                        return label.length > 16 ? label.slice(0, 15) + '…' : label;
                                    }
                                },
                                grid: {
                                    display: compactChartLayout,
                                    color: 'rgba(148, 163, 184, 0.16)',
                                }
                            },
                            y: {
                                beginAtZero: !compactChartLayout,
                                ticks: {
                                    autoSkip: false,
                                    font: { size: compactChartLayout ? 10 : 12 },
                                    callback: function(value) {
                                        if (!compactChartLayout) return compactMoney(value);
                                        const label = this.getLabelForValue(value);
                                        return label.length > 13 ? label.slice(0, 12) + '…' : label;
                                    }
                                },
                                grid: {
                                    display: !compactChartLayout,
                                    color: 'rgba(148, 163, 184, 0.16)',
                                },
                            }
                        }
                    }
                });
            }
        </script>
    @endif

</x-app-layout>
