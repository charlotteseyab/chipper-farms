<x-app-layout>
    <style>
        .admin-page{
            min-height: 100vh;
        }

        .admin-wrap{
            max-width: 1500px;
            margin: 0 auto;
            padding: 20px 14px 56px;
        }

        .admin-hero{
            background: linear-gradient(135deg, #14532d 0%, #166534 50%, #15803d 100%);
            border-radius: 30px;
            padding: 20px;
            box-shadow: 0 22px 52px rgba(22,101,52,.18);
            position: relative;
            overflow: hidden;
            margin-bottom: 18px;
        }

        .admin-hero::before{
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,.12), transparent 26%),
                radial-gradient(circle at bottom left, rgba(255,255,255,.08), transparent 22%);
            pointer-events: none;
        }

        .admin-hero-inner{
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .admin-hero h1{
            margin: 0;
            font-size: clamp(1.9rem, 3vw, 2.8rem);
            line-height: 1.02;
            color: #fff;
            font-weight: 900;
            letter-spacing: -.04em;
        }

        .admin-hero p{
            margin: 8px 0 0;
            color: #dcfce7;
            font-size: .95rem;
            line-height: 1.75;
            max-width: 760px;
        }

        .admin-hero-pills{
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .admin-hero-pill{
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.14);
            color: #ecfdf5;
            font-size: .78rem;
            font-weight: 700;
        }

        .admin-filter{
            background: rgba(255,255,255,.92);
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            box-shadow: 0 12px 32px rgba(15,23,42,.05);
            padding: 20px;
            margin-bottom: 18px;
        }

        .admin-filter-title{
            font-size: 1rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 14px;
        }

        .admin-topbar{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }

        .admin-title{
            margin: 0;
            font-size: 1.9rem;
            line-height: 1.1;
            font-weight: 900;
            color: #0f172a;
        }

        .admin-subtitle{
            margin-top: 6px;
            color: #64748b;
            font-size: .9rem;
        }

        .admin-quick-actions{
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .admin-input{
            width: 100%;
            border-radius: 14px;
            border: 1px solid #cbd5e1;
            background: #fff;
            padding: 10px 12px;
            font-size: .88rem;
            color: #0f172a;
            outline: none;
        }

        .admin-input:focus{
            border-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34,197,94,.12);
        }

        .admin-btn{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 16px;
            border-radius: 14px;
            font-size: .84rem;
            font-weight: 700;
            transition: .22s ease;
            text-decoration: none;
            border: none;
        }

        .admin-btn-green{
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: #fff;
            box-shadow: 0 12px 24px rgba(34,197,94,.18);
        }

        .admin-btn-green:hover{
            transform: translateY(-1px);
        }

        .admin-btn-gray{
            background: #e5e7eb;
            color: #374151;
        }

        .admin-card{
            background: rgba(255,255,255,.94);
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            box-shadow: 0 10px 28px rgba(15,23,42,.05);
            overflow: hidden;
        }

        .admin-card-head{
            padding: 22px 24px 14px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }

        .admin-card-title{
            margin: 0;
            font-size: 1.04rem;
            font-weight: 800;
            color: #0f172a;
        }

        .admin-card-sub{
            margin-top: 4px;
            font-size: .82rem;
            color: #64748b;
        }

        .admin-badge{
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            font-size: .74rem;
            font-weight: 700;
            color: #475569;
        }

        .admin-chart-wrap{
            padding: 0 24px 24px;
        }

        .admin-chart{
            height: 320px;
            border-radius: 20px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #eef2f7;
            padding: 14px;
        }

        .admin-value-icon{
            width: 54px;
            height: 54px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            color: #16a34a;
            flex-shrink: 0;
        }

        .admin-value-label{
            font-size: .72rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: #94a3b8;
            margin-bottom: 6px;
        }

        .admin-value-number{
            font-size: 1.7rem;
            font-weight: 900;
            color: #0f172a;
            line-height: 1;
        }

        .admin-value-note{
            margin-top: 8px;
            font-size: .78rem;
            color: #64748b;
        }

        .admin-small-top{
            display: grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .admin-small-card-body{
            padding: 0 24px 24px;
        }

        .admin-small-value{
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .admin-alert-card{
            background: linear-gradient(135deg, #fff7ed 0%, #fffbeb 100%);
            border: 1px solid #fed7aa;
            border-radius: 22px;
            box-shadow: 0 8px 24px rgba(249,115,22,.08);
            overflow: hidden;
        }

        .admin-alert-list{
            padding: 0 20px 20px;
            display: grid;
            gap: 10px;
        }

        .admin-alert-item{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            background: rgba(255,255,255,.78);
            border: 1px solid #fed7aa;
            border-radius: 16px;
        }

        .admin-alert-code{
            font-size: .82rem;
            font-weight: 800;
            color: #9a3412;
        }

        .admin-alert-meta{
            font-size: .72rem;
            color: #6b7280;
            margin-top: 2px;
        }

        .admin-alert-pill{
            font-size: .72rem;
            font-weight: 800;
            color: #b45309;
            background: #fef3c7;
            border-radius: 999px;
            padding: 6px 10px;
            flex-shrink: 0;
        }

        .admin-command-grid{
            display: grid;
            grid-template-columns: repeat(3, minmax(0,1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .admin-command-card{
            background: #fff;
            border: 1px solid #dbe3ea;
            border-radius: 18px;
            box-shadow: 0 14px 34px rgba(15,23,42,.08);
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            min-height: 250px;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .admin-command-card::after{
            content: "";
            position: absolute;
            right: -42px;
            top: -42px;
            width: 150px;
            height: 150px;
            border-radius: 999px;
            background: rgba(255,255,255,.12);
            pointer-events: none;
        }

        .admin-command-head{
            padding: 20px 20px 16px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
        }

        .admin-command-title-wrap{
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .admin-command-icon{
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .admin-command-icon svg{
            width: 22px;
            height: 22px;
        }

        .admin-command-currency{
            font-size: 1.45rem;
            line-height: 1;
            font-weight: 900;
            font-family: Georgia, "Times New Roman", serif;
        }

        .admin-command-title{
            margin: 0;
            font-size: 1.05rem;
            font-weight: 900;
            color: inherit;
        }

        .admin-command-kicker{
            margin-top: 3px;
            color: rgba(255,255,255,.78);
            font-size: .76rem;
            font-weight: 700;
        }

        .admin-command-main{
            padding: 0 20px 18px;
        }

        .admin-command-value{
            font-size: 2rem;
            line-height: 1;
            font-weight: 900;
            color: inherit;
        }

        .admin-command-note{
            margin-top: 8px;
            color: rgba(255,255,255,.78);
            font-size: .8rem;
        }

        .admin-gender-strip{
            margin: 0 20px 18px;
            padding: 12px;
            border-radius: 14px;
            background: rgba(255,255,255,.10);
            border: 1px solid rgba(255,255,255,.16);
        }

        .admin-gender-head{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 9px;
            color: rgba(255,255,255,.78);
            font-size: .7rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .admin-gender-track{
            height: 8px;
            display: flex;
            overflow: hidden;
            border-radius: 999px;
            background: rgba(255,255,255,.16);
        }

        .admin-gender-male{
            background: #60a5fa;
        }

        .admin-gender-female{
            background: #fb7185;
        }

        .admin-gender-legend{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-top: 9px;
            color: rgba(255,255,255,.88);
            font-size: .76rem;
            font-weight: 800;
        }

        .admin-gender-item{
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .admin-gender-dot{
            width: 8px;
            height: 8px;
            border-radius: 999px;
            flex-shrink: 0;
        }

        .admin-command-metrics{
            display: grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            margin-top: auto;
            border-top: 1px solid rgba(255,255,255,.18);
            background: rgba(255,255,255,.08);
        }

        .admin-command-metric{
            padding: 14px 16px;
            border-right: 1px solid rgba(255,255,255,.18);
            border-bottom: 1px solid rgba(255,255,255,.18);
            min-width: 0;
        }

        .admin-command-metric:nth-child(2n){
            border-right: 0;
        }

        .admin-command-metric:nth-last-child(-n+2){
            border-bottom: 0;
        }

        .admin-command-metric-label{
            color: rgba(255,255,255,.72);
            font-size: .7rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .admin-command-metric-value{
            margin-top: 5px;
            color: #fff;
            font-size: 1rem;
            font-weight: 900;
        }

        .admin-command-green{
            color: #fff;
            border-color: #15803d;
            background: linear-gradient(135deg, #16a34a 0%, #15803d 55%, #166534 100%);
        }

        .admin-command-blue{
            color: #fff;
            border-color: #0e7490;
            background: linear-gradient(135deg, #0891b2 0%, #0e7490 55%, #155e75 100%);
        }

        .admin-command-amber{
            color: #fff;
            border-color: #c2410c;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 55%, #c2410c 100%);
        }

        .admin-command-green .admin-command-icon,
        .admin-command-blue .admin-command-icon,
        .admin-command-amber .admin-command-icon{
            background: rgba(255,255,255,.18);
            color: #fff;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.18);
        }

        .admin-command-link{
            display: inline-flex;
            color: rgba(255,255,255,.88);
            font-size: .78rem;
            font-weight: 800;
            text-decoration: none;
            white-space: nowrap;
        }

        .admin-dashboard-grid{
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(360px, .85fr);
            gap: 16px;
            margin-bottom: 16px;
        }

        .admin-attention-table{
            width: 100%;
            border-collapse: collapse;
        }

        .admin-attention-table th,
        .admin-attention-table td{
            padding: 12px 16px;
            text-align: left;
            border-top: 1px solid #eef2f7;
            font-size: .8rem;
            vertical-align: middle;
        }

        .admin-attention-table th{
            color: #64748b;
            font-size: .68rem;
            font-weight: 900;
            text-transform: uppercase;
            background: #f8fafc;
        }

        .admin-attention-table td{
            color: #334155;
            font-weight: 650;
        }

        .admin-attention-link{
            color: #0f766e;
            font-weight: 900;
            text-decoration: none;
        }

        .admin-attention-link:hover{
            color: #14532d;
            text-decoration: underline;
        }

        .admin-status-pill{
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 5px 9px;
            font-size: .68rem;
            font-weight: 900;
            white-space: nowrap;
        }

        .admin-status-high{ background: #fee2e2; color: #b91c1c; }
        .admin-status-medium{ background: #fef3c7; color: #b45309; }
        .admin-status-low{ background: #dcfce7; color: #15803d; }

        .admin-ops-grid{
            display: grid;
            grid-template-columns: repeat(7, minmax(150px, 1fr));
            gap: 12px;
        }

        .admin-ops-card{
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 16px;
            text-decoration: none;
            color: inherit;
            min-height: 178px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 8px 18px rgba(15,23,42,.04);
            transition: .2s ease;
        }

        .admin-ops-card:hover{
            transform: translateY(-2px);
            border-color: #bbf7d0;
            box-shadow: 0 14px 28px rgba(15,23,42,.07);
        }

        .admin-ops-label{
            color: #166534;
            font-size: .78rem;
            font-weight: 900;
        }

        .admin-ops-value{
            margin-top: 16px;
            color: #0f172a;
            font-size: 1.55rem;
            line-height: 1;
            font-weight: 900;
        }

        .admin-ops-sub{
            margin-top: 5px;
            color: #64748b;
            font-size: .76rem;
            line-height: 1.45;
        }

        .admin-ops-lines{
            display: grid;
            gap: 7px;
            margin-top: 14px;
        }

        .admin-ops-line{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            border-radius: 10px;
            background: #f8fafc;
            padding: 7px 8px;
            color: #475569;
            font-size: .7rem;
            font-weight: 700;
        }

        .admin-ops-line strong{
            color: #0f172a;
        }

        .admin-stat-grid{
            display: grid;
            grid-template-columns: repeat(4, minmax(0,1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .admin-stat{
            position: relative;
            overflow: hidden;
            border-radius: 22px;
            padding: 18px;
            color: #fff;
            box-shadow: 0 10px 24px rgba(15,23,42,.08);
            text-decoration: none;
            transition: .22s ease;
        }

        .admin-stat:hover{
            transform: translateY(-3px);
        }

        .admin-stat::after{
            content:"";
            position:absolute;
            right:-16px;
            top:-16px;
            width:84px;
            height:84px;
            border-radius:999px;
            background: rgba(255,255,255,.10);
        }

        .admin-stat-green{ background: linear-gradient(135deg, #16a34a, #15803d); }
        .admin-stat-blue{ background: linear-gradient(135deg, #2563eb, #1d4ed8); }
        .admin-stat-purple{ background: linear-gradient(135deg, #9333ea, #7e22ce); }
        .admin-stat-red{ background: linear-gradient(135deg, #ef4444, #dc2626); }

        .admin-stat-label{
            font-size: .78rem;
            font-weight: 700;
            color: rgba(255,255,255,.88);
            margin-bottom: 10px;
        }

        .admin-stat-value{
            font-size: 1.7rem;
            font-weight: 900;
            line-height: 1.05;
            margin: 0;
        }

        .admin-stat-sub{
            margin-top: 8px;
            font-size: .74rem;
            color: rgba(255,255,255,.82);
        }

        .admin-stat-split{
            display: grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap: 12px;
        }

        .admin-stat-split-item{
            min-width: 0;
        }

        .admin-stat-split-label{
            margin-top: 8px;
            font-size: .74rem;
            font-weight: 700;
            color: rgba(255,255,255,.82);
        }

        .admin-section{
            margin-bottom: 18px;
        }

        .admin-section-head{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 12px;
        }

        .admin-section-head h2{
            margin: 0;
            font-size: 1.25rem;
            font-weight: 900;
            color: #111827;
        }

        .admin-section-head p{
            margin: 4px 0 0;
            font-size: .82rem;
            color: #6b7280;
        }

        .admin-link{
            color: #16a34a;
            text-decoration: none;
            font-size: .82rem;
            font-weight: 700;
        }

        .admin-link:hover{
            color: #15803d;
        }

        .admin-mini-grid{
            display: grid;
            grid-template-columns: repeat(4, minmax(0,1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .admin-mini-card{
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 18px;
            box-shadow: 0 6px 18px rgba(15,23,42,.04);
            text-decoration: none;
            transition: .2s ease;
        }

        .admin-mini-card:hover{
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(15,23,42,.06);
        }

        .admin-mini-label{
            font-size: .74rem;
            font-weight: 800;
            color: #64748b;
            margin-bottom: 8px;
        }

        .admin-mini-value{
            font-size: 1.45rem;
            font-weight: 900;
            color: #0f172a;
            line-height: 1;
        }

        .admin-mini-sub{
            font-size: .74rem;
            color: #94a3b8;
            margin-top: 8px;
        }

        .admin-two-col{
            display: grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap: 16px;
        }

        .admin-list{
            padding: 0 24px 24px;
            display: grid;
            gap: 12px;
        }

        .admin-list-row{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 16px;
            background: #f8fafc;
            border: 1px solid #eef2f7;
        }

        .admin-list-row span:first-child{
            color: #334155;
            font-size: .85rem;
            font-weight: 700;
        }

        .admin-list-row span:last-child{
            color: #0f172a;
            font-size: 1rem;
            font-weight: 900;
        }

        .admin-divider{
            height: 1px;
            background: linear-gradient(90deg, transparent, #cbd5e1, transparent);
            margin: 24px 0;
        }

        @media (max-width: 1280px){
            .admin-stat-grid,
            .admin-mini-grid,
            .admin-command-grid{
                grid-template-columns: repeat(2, minmax(0,1fr));
            }

            .admin-ops-grid{
                grid-template-columns: repeat(3, minmax(0,1fr));
            }
        }

        @media (max-width: 1024px){
            .admin-small-top,
            .admin-two-col,
            .admin-dashboard-grid{
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px){
            .admin-wrap{
                padding: 14px 10px 40px;
            }

            .admin-stat-grid,
            .admin-mini-grid,
            .admin-command-grid,
            .admin-ops-grid{
                grid-template-columns: 1fr;
            }

            .admin-card-head,
            .admin-chart-wrap,
            .admin-list,
            .admin-alert-list,
            .admin-small-card-body{
                padding-left: 18px;
                padding-right: 18px;
            }

            .admin-small-value{
                align-items: flex-start;
            }
        }
    </style>

    <div class="admin-page">
        <div class="admin-wrap">

            <div class="admin-topbar">
                <div>
                    <h1 class="admin-title">Dashboard</h1>
                    <div class="admin-subtitle">Revenue, farm value, stock position, and the work that needs attention.</div>
                </div>

                <form method="GET" action="{{ route('admin.dashboard') }}" class="admin-quick-actions">
                    <input type="date" id="date_from" name="date_from" value="{{ $dateFrom }}" class="admin-input" style="width: 160px;">
                    <input type="date" id="date_to" name="date_to" value="{{ $dateTo }}" class="admin-input" style="width: 160px;">
                    <button type="submit" class="admin-btn admin-btn-green">Apply</button>
                    <a href="{{ route('admin.dashboard') }}" class="admin-btn admin-btn-gray">Reset</a>
                </form>
            </div>

            <div class="admin-command-grid">
                <a href="{{ route('sales.index') }}" class="admin-command-card admin-command-green">
                    <div class="admin-command-head">
                        <div class="admin-command-title-wrap">
                            <div class="admin-command-icon">
                                <span class="admin-command-currency">₵</span>
                            </div>
                            <div>
                                <h2 class="admin-command-title">Revenue</h2>
                                <div class="admin-command-kicker">Sales performance</div>
                            </div>
                        </div>
                        <span class="admin-command-link">View Sales</span>
                    </div>
                    <div class="admin-command-main">
                        <div class="admin-command-value">GHS {{ number_format($totalRevenue, 2) }}</div>
                        <div class="admin-command-note">{{ number_format($salesThisMonth) }} sales this month</div>
                    </div>
                    <div class="admin-command-metrics">
                        <div class="admin-command-metric">
                            <div class="admin-command-metric-label">Total Sales</div>
                            <div class="admin-command-metric-value">{{ number_format($totalSales) }}</div>
                        </div>
                        <div class="admin-command-metric">
                            <div class="admin-command-metric-label">Avg Sale</div>
                            <div class="admin-command-metric-value">GHS {{ number_format($avgSalePrice ?? 0, 0) }}</div>
                        </div>
                        <div class="admin-command-metric">
                            <div class="admin-command-metric-label">Male Sales</div>
                            <div class="admin-command-metric-value">{{ number_format($maleSales) }}</div>
                        </div>
                        <div class="admin-command-metric">
                            <div class="admin-command-metric-label">Female Sales</div>
                            <div class="admin-command-metric-value">{{ number_format($femaleSales) }}</div>
                        </div>
                    </div>
                </a>

                <a href="{{ route('weights.index') }}" class="admin-command-card admin-command-blue">
                    <div class="admin-command-head">
                        <div class="admin-command-title-wrap">
                            <div class="admin-command-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M6 21V9l6-4 6 4v12M9 21v-6h6v6" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="admin-command-title">Overall Farm Value</h2>
                                <div class="admin-command-kicker">Live weight valuation</div>
                            </div>
                        </div>
                        <span class="admin-command-link">View Weights</span>
                    </div>
                    <div class="admin-command-main">
                        <div class="admin-command-value">GHS {{ number_format($totalFarmValue, 2) }}</div>
                        <div class="admin-command-note">{{ number_format($totalWeight, 1) }}kg live weight at GHS {{ number_format($liveWeightPrice, 2) }}/kg</div>
                    </div>
                    <div class="admin-command-metrics">
                        <div class="admin-command-metric">
                            <div class="admin-command-metric-label">Avg Weight</div>
                            <div class="admin-command-metric-value">{{ number_format($avgWeight ?? 0, 1) }} kg</div>
                        </div>
                        <div class="admin-command-metric">
                            <div class="admin-command-metric-label">Weight Records</div>
                            <div class="admin-command-metric-value">{{ number_format($totalWeights) }}</div>
                        </div>
                        <div class="admin-command-metric">
                            <div class="admin-command-metric-label">This Week</div>
                            <div class="admin-command-metric-value">{{ number_format($weightsThisWeek) }}</div>
                        </div>
                        <div class="admin-command-metric">
                            <div class="admin-command-metric-label">Due Soon</div>
                            <div class="admin-command-metric-value">{{ number_format($dueForWeighing) }}</div>
                        </div>
                    </div>
                </a>

                <a href="{{ route('all-stocks.index') }}" class="admin-command-card admin-command-amber">
                    <div class="admin-command-head">
                        <div class="admin-command-title-wrap">
                            <div class="admin-command-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7.5 12 3 4 7.5m16 0-8 4.5m8-4.5v9L12 21m0-9L4 7.5m8 4.5v9M4 7.5v9L12 21" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="admin-command-title">Stock Details</h2>
                                <div class="admin-command-kicker">Live inventory position</div>
                            </div>
                        </div>
                        <span class="admin-command-link">View Stock</span>
                    </div>
                    <div class="admin-command-main">
                        <div class="admin-command-value">{{ number_format($activePigs) }}</div>
                        <div class="admin-command-note">
                            {{ number_format($totalPigs) }} total records,
                            {{ number_format($bornPigletsCount) }} born piglets,
                            {{ number_format($purchasedPigletsCount) }} purchased piglets
                        </div>
                    </div>
                    @php
                        $genderTotal = max(1, (int) $activeMaleCount + (int) $activeFemaleCount);
                        $malePercent = round(((int) $activeMaleCount / $genderTotal) * 100, 1);
                        $femalePercent = max(0, 100 - $malePercent);
                    @endphp
                    <div class="admin-gender-strip">
                        <div class="admin-gender-head">
                            <span>Gender Distribution</span>
                            <span>{{ number_format($activeMaleCount + $activeFemaleCount) }}</span>
                        </div>
                        <div class="admin-gender-track" aria-label="Gender distribution">
                            <div class="admin-gender-male" style="width: {{ $malePercent }}%;"></div>
                            <div class="admin-gender-female" style="width: {{ $femalePercent }}%;"></div>
                        </div>
                        <div class="admin-gender-legend">
                            <span class="admin-gender-item">
                                <span class="admin-gender-dot admin-gender-male"></span>
                                Male {{ number_format($activeMaleCount) }}
                            </span>
                            <span class="admin-gender-item">
                                <span class="admin-gender-dot admin-gender-female"></span>
                                Female {{ number_format($activeFemaleCount) }}
                            </span>
                        </div>
                    </div>
                    <div class="admin-command-metrics">
                        <div class="admin-command-metric">
                            <div class="admin-command-metric-label">Breeding</div>
                            <div class="admin-command-metric-value">{{ number_format($breedingStock) }}</div>
                        </div>
                        <div class="admin-command-metric">
                            <div class="admin-command-metric-label">Standard</div>
                            <div class="admin-command-metric-value">{{ number_format($standardStock) }}</div>
                        </div>
                        <div class="admin-command-metric">
                            <div class="admin-command-metric-label">Purchased</div>
                            <div class="admin-command-metric-value">{{ number_format($purchasedStockMetrics['added_quantity'] ?? 0) }}</div>
                        </div>
                        <div class="admin-command-metric">
                            <div class="admin-command-metric-label">Pending Intake</div>
                            <div class="admin-command-metric-value">{{ number_format($purchasedStockMetrics['pending_intakes'] ?? 0) }}</div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="admin-dashboard-grid">
                <div class="admin-card">
                    <div class="admin-card-head">
                        <div>
                            <h3 class="admin-card-title">Farm Value & Stock Trend</h3>
                            <div class="admin-card-sub">Estimated value trend based on recent average weight and live price</div>
                        </div>
                        <div class="admin-badge">Last 6 months</div>
                    </div>
                    <div class="admin-chart-wrap">
                        <div class="admin-chart">
                            <canvas id="farmValueTrendChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="admin-card">
                    <div class="admin-card-head">
                        <div>
                            <h3 class="admin-card-title">Attention Needed</h3>
                            <div class="admin-card-sub">The items most likely to need action now</div>
                        </div>
                        <a href="{{ route('weight-alerts.index') }}" class="admin-command-link">View Alerts</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="admin-attention-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Item</th>
                                    <th>Severity</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if($overdueForWeighing > 0)
                                    <tr>
                                        <td>Weight</td>
                                        <td>
                                            <a href="{{ route('weights.index') }}" class="admin-attention-link">
                                                {{ number_format($overdueForWeighing) }} overdue
                                            </a>
                                        </td>
                                        <td><span class="admin-status-pill admin-status-high">High</span></td>
                                    </tr>
                                @endif
                                @if($firstWeightPending > 0)
                                    <tr>
                                        <td>Weight</td>
                                        <td>
                                            <a href="{{ route('weights.index') }}" class="admin-attention-link">
                                                {{ number_format($firstWeightPending) }} first weight pending
                                            </a>
                                        </td>
                                        <td><span class="admin-status-pill admin-status-medium">Medium</span></td>
                                    </tr>
                                @endif
                                @if($pregnancyChecksOverdue > 0)
                                    <tr>
                                        <td>Breeding</td>
                                        <td>
                                            <a href="{{ route('mating.manage') }}" class="admin-attention-link">
                                                {{ number_format($pregnancyChecksOverdue) }} pregnancy checks overdue
                                            </a>
                                        </td>
                                        <td><span class="admin-status-pill admin-status-high">High</span></td>
                                    </tr>
                                @endif
                                @if($pregnancyFollowUpsOverdue > 0)
                                    <tr>
                                        <td>Breeding</td>
                                        <td>
                                            <a href="{{ route('mating.manage') }}" class="admin-attention-link">
                                                {{ number_format($pregnancyFollowUpsOverdue) }} uncertain pregnancy follow-ups overdue
                                            </a>
                                        </td>
                                        <td><span class="admin-status-pill admin-status-high">High</span></td>
                                    </tr>
                                @endif
                                @if($overdueGestation > 0)
                                    <tr>
                                        <td>Gestation</td>
                                        <td>
                                            <a href="{{ route('gestation.index') }}" class="admin-attention-link">
                                                {{ number_format($overdueGestation) }} overdue farrowing preparations
                                            </a>
                                        </td>
                                        <td><span class="admin-status-pill admin-status-high">High</span></td>
                                    </tr>
                                @endif
                                @if($overdueWeaning > 0)
                                    <tr>
                                        <td>Weaning</td>
                                        <td>
                                            <a href="{{ route('weaning.dashboard') }}" class="admin-attention-link">
                                                {{ number_format($overdueWeaning) }} litters overdue
                                            </a>
                                        </td>
                                        <td><span class="admin-status-pill admin-status-medium">Medium</span></td>
                                    </tr>
                                @endif
                                @if($criticalAlerts > 0)
                                    <tr>
                                        <td>Weight Alert</td>
                                        <td>
                                            <a href="{{ route('weight-alerts.index', ['severity' => 'high', 'acknowledged' => 0]) }}" class="admin-attention-link">
                                                {{ number_format($criticalAlerts) }} critical alerts
                                            </a>
                                        </td>
                                        <td><span class="admin-status-pill admin-status-high">High</span></td>
                                    </tr>
                                @endif
                                @if($overdueForWeighing === 0 && $firstWeightPending === 0 && $pregnancyChecksOverdue === 0 && $pregnancyFollowUpsOverdue === 0 && $overdueGestation === 0 && $overdueWeaning === 0 && $criticalAlerts === 0)
                                    <tr>
                                        <td colspan="3">No urgent dashboard alerts for the selected period.</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <section class="admin-section">
                <div class="admin-section-head">
                    <div>
                        <h2>Operations Timeline</h2>
                        <p>Compact summaries for the farm workflows that feed the full module pages</p>
                    </div>
                    <a href="{{ route('all-stocks.index') }}" class="admin-link">Open Stock</a>
                </div>

                <div class="admin-ops-grid">
                    <a href="{{ route('mating.manage') }}" class="admin-ops-card">
                        <div>
                            <div class="admin-ops-label">Breeding</div>
                            <div class="admin-ops-value">{{ number_format($totalMatings) }}</div>
                            <div class="admin-ops-sub">Total matings</div>
                        </div>
                        <div class="admin-ops-lines">
                            <div class="admin-ops-line"><span>Pregnant</span><strong>{{ number_format($pregnantMatings) }}</strong></div>
                            <div class="admin-ops-line"><span>Checks due</span><strong>{{ number_format($pregnancyChecksDue) }}</strong></div>
                            <div class="admin-ops-line"><span>Follow-ups</span><strong>{{ number_format($pregnancyFollowUpsDue) }}</strong></div>
                            <div class="admin-ops-line"><span>Uncertain</span><strong>{{ number_format($uncertainPregnancies) }}</strong></div>
                        </div>
                    </a>

                    <a href="{{ route('gestation.index') }}" class="admin-ops-card">
                        <div>
                            <div class="admin-ops-label">Gestation</div>
                            <div class="admin-ops-value">{{ number_format($totalPregnant) }}</div>
                            <div class="admin-ops-sub">Tracked pregnancies</div>
                        </div>
                        <div class="admin-ops-lines">
                            <div class="admin-ops-line"><span>Due week</span><strong>{{ number_format($dueThisWeek) }}</strong></div>
                            <div class="admin-ops-line"><span>Overdue</span><strong>{{ number_format($overdueGestation) }}</strong></div>
                        </div>
                    </a>

                    <a href="{{ route('farrowing.index') }}" class="admin-ops-card">
                        <div>
                            <div class="admin-ops-label">Farrowing</div>
                            <div class="admin-ops-value">{{ number_format($totalFarrowings) }}</div>
                            <div class="admin-ops-sub">{{ number_format($totalBorn) }} piglets born</div>
                        </div>
                        <div class="admin-ops-lines">
                            <div class="admin-ops-line"><span>This month</span><strong>{{ number_format($farrowingsThisMonth) }}</strong></div>
                            <div class="admin-ops-line"><span>Survival</span><strong>{{ number_format($survivalRate, 1) }}%</strong></div>
                        </div>
                    </a>

                    <a href="{{ route('weaning.dashboard') }}" class="admin-ops-card">
                        <div>
                            <div class="admin-ops-label">Weaning</div>
                            <div class="admin-ops-value">{{ number_format($totalWeaned) }}</div>
                            <div class="admin-ops-sub">{{ number_format($weaningPercentage, 1) }}% weaning rate</div>
                        </div>
                        <div class="admin-ops-lines">
                            <div class="admin-ops-line"><span>Ready</span><strong>{{ number_format($readyToWean) }}</strong></div>
                            <div class="admin-ops-line"><span>Overdue</span><strong>{{ number_format($overdueWeaning) }}</strong></div>
                        </div>
                    </a>

                    <a href="{{ route('weights.index') }}" class="admin-ops-card">
                        <div>
                            <div class="admin-ops-label">Weight</div>
                            <div class="admin-ops-value">{{ number_format($avgWeight ?? 0, 1) }}</div>
                            <div class="admin-ops-sub">Average kg</div>
                        </div>
                        <div class="admin-ops-lines">
                            <div class="admin-ops-line"><span>Records</span><strong>{{ number_format($totalWeights) }}</strong></div>
                            <div class="admin-ops-line"><span>Overdue</span><strong>{{ number_format($overdueForWeighing) }}</strong></div>
                        </div>
                    </a>

                    <a href="{{ route('health.index') }}" class="admin-ops-card">
                        <div>
                            <div class="admin-ops-label">Health</div>
                            <div class="admin-ops-value">{{ number_format($totalHealthRecords) }}</div>
                            <div class="admin-ops-sub">Health records</div>
                        </div>
                        <div class="admin-ops-lines">
                            <div class="admin-ops-line"><span>Medication</span><strong>{{ number_format($totalMedications) }}</strong></div>
                            <div class="admin-ops-line"><span>Alerts</span><strong>{{ number_format($criticalAlerts) }}</strong></div>
                        </div>
                    </a>

                    <a href="{{ route('sales.index') }}" class="admin-ops-card">
                        <div>
                            <div class="admin-ops-label">Sales</div>
                            <div class="admin-ops-value">{{ number_format($totalSales) }}</div>
                            <div class="admin-ops-sub">Completed sales</div>
                        </div>
                        <div class="admin-ops-lines">
                            <div class="admin-ops-line"><span>Revenue</span><strong>GHS {{ number_format($totalRevenue, 0) }}</strong></div>
                            <div class="admin-ops-line"><span>This month</span><strong>{{ number_format($salesThisMonth) }}</strong></div>
                        </div>
                    </a>
                </div>
            </section>

            <div class="admin-two-col">
                <div class="admin-card">
                    <div class="admin-card-head">
                        <div>
                            <h3 class="admin-card-title">Breed Distribution</h3>
                            <div class="admin-card-sub">Current breakdown by main breed groups</div>
                        </div>
                    </div>
                    <div class="admin-list">
                        <div class="admin-list-row">
                            <span>LW (Large White)</span>
                            <span>{{ number_format($breedCounts['LW'] ?? 0) }}</span>
                        </div>
                        <div class="admin-list-row">
                            <span>DR (Duroc)</span>
                            <span>{{ number_format($breedCounts['DR'] ?? 0) }}</span>
                        </div>
                        <div class="admin-list-row">
                            <span>LWDR (Large White Duroc)</span>
                            <span>{{ number_format($breedCounts['LWDR'] ?? 0) }}</span>
                        </div>
                    </div>
                </div>

                <div class="admin-card">
                    <div class="admin-card-head">
                        <div>
                            <h3 class="admin-card-title">Health Activity</h3>
                            <div class="admin-card-sub">Health and medication activity over 6 months</div>
                        </div>
                    </div>
                    <div class="admin-chart-wrap">
                        <div class="admin-chart">
                            <canvas id="healthTrendsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Weighing Alerts Modal -->
    <div id="weighingAlertsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
            <div class="flex items-center justify-between p-6 border-b border-gray-200 bg-gradient-to-r from-red-50 to-red-100">
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
            @if(($allOverduePigsData->count() ?? 0) > 0 || ($allFirstWeightPendingData->count() ?? 0) > 0)
    <div class="space-y-8">

        @if(($allOverduePigsData->count() ?? 0) > 0)
            <div>
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Routine Weighing Overdue</h3>
                        <p class="text-sm text-gray-500">Pigs already in the weighing cycle that are late for their next weighing.</p>
                    </div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                        {{ number_format($allOverduePigsData->count()) }} overdue
                    </span>
                </div>

                <div class="space-y-4">
                    @foreach($allOverduePigsData as $item)
                        @php
                            $pig = $item['pig'];
                            $status = $item['status'] ?? 'unknown';
                            $statusMessage = $item['status_message'] ?? 'Unknown';
                            $daysSince = $item['days_since'];
                            $lastWeighing = $item['last_weighing'];
                            $lastWeight = $item['last_weight'];
                            $overdueDays = $item['overdue_days'] ?? 0;

                            $isCritical = $status === 'critical';
                            $isOverdue = $status === 'overdue';
                            $isDueSoon = $status === 'due_soon';

                            $cardClass = $isCritical
                                ? 'bg-red-50 border border-red-200'
                                : ($isOverdue ? 'bg-orange-50 border border-orange-200' : 'bg-yellow-50 border border-yellow-200');

                            $textClass = $isCritical
                                ? 'text-red-700'
                                : ($isOverdue ? 'text-orange-700' : 'text-yellow-700');

                            $badgeClass = $isCritical
                                ? 'bg-red-100 text-red-800'
                                : ($isOverdue ? 'bg-orange-100 text-orange-800' : 'bg-yellow-100 text-yellow-800');

                            $buttonClass = $isCritical
                                ? 'bg-red-600 hover:bg-red-700'
                                : ($isOverdue ? 'bg-orange-600 hover:bg-orange-700' : 'bg-yellow-600 hover:bg-yellow-700');
                        @endphp

                        <div class="rounded-lg p-4 hover:shadow-md transition-shadow {{ $cardClass }}">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center mb-2">
                                        <span class="text-2xl mr-3">
                                            @if($isCritical) Alert
                                            @elseif($isOverdue) ⏰
                                            @else Warning
                                            @endif
                                        </span>
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900">{{ $pig->ear_tag_number ?? 'N/A' }}</h3>
                                            <p class="text-sm text-gray-600">
                                                {{ $pig->breed ?? 'N/A' }} • {{ $pig->gender ?? 'N/A' }} • {{ ucfirst($pig->stock_type ?? 'N/A') }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                                        <div class="bg-white rounded-lg p-3 border border-gray-200">
                                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Last Weighing</p>
                                            <p class="text-sm font-semibold text-gray-900">
                                                @if($lastWeighing)
                                                    {{ $lastWeighing->format('M j, Y') }}
                                                @else
                                                    Never weighed
                                                @endif
                                            </p>
                                        </div>

                                        <div class="bg-white rounded-lg p-3 border border-gray-200">
                                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Last Weight</p>
                                            <p class="text-sm font-semibold text-gray-900">
                                                @if($lastWeight)
                                                    {{ number_format($lastWeight->weight, 1) }} {{ $lastWeight->weight_unit ?? 'kg' }}
                                                @else
                                                    No weight recorded
                                                @endif
                                            </p>
                                        </div>

                                        <div class="bg-white rounded-lg p-3 border border-gray-200">
                                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Overdue By</p>
                                            <p class="text-sm font-semibold {{ $textClass }}">
                                                {{ max(0, (int) $overdueDays) }} days
                                            </p>
                                        </div>
                                    </div>

                                    <div class="mt-3 flex items-center justify-between">
                                        <div class="flex items-center">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                                                @if($isCritical) Critical
                                                @elseif($isOverdue) Overdue
                                                @else Due Soon
                                                @endif
                                            </span>

                                            @if(!is_null($daysSince))
                                                <span class="ml-2 text-xs text-gray-500">
                                                    {{ (int) $daysSince }} days since last weighing
                                                </span>
                                            @endif
                                        </div>

                                        @if($pig)
                                            <a href="{{ route('weights.create', ['pig_id' => $pig->id]) }}"
                                               class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white {{ $buttonClass }} transition-colors">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                                                </svg>
                                                Record Weight
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if(($allFirstWeightPendingData->count() ?? 0) > 0)
            <div>
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">First Weight Pending</h3>
                        <p class="text-sm text-gray-500">Pigs that have not yet received their first recorded weight.</p>
                    </div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">
                        {{ number_format($allFirstWeightPendingData->count()) }} pending
                    </span>
                </div>

                <div class="space-y-4">
                    @foreach($allFirstWeightPendingData as $item)
                        @php
                            $pig = $item['pig'];
                            $overdueDays = $item['overdue_days'] ?? 0;
                        @endphp

                        <div class="rounded-lg p-4 hover:shadow-md transition-shadow bg-yellow-50 border border-yellow-200">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center mb-2">
                                        <span class="text-2xl mr-3">Notes</span>
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900">{{ $pig->ear_tag_number ?? 'N/A' }}</h3>
                                            <p class="text-sm text-gray-600">
                                                {{ $pig->breed ?? 'N/A' }} • {{ $pig->gender ?? 'N/A' }} • {{ ucfirst($pig->stock_type ?? 'N/A') }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                                        <div class="bg-white rounded-lg p-3 border border-gray-200">
                                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Last Weighing</p>
                                            <p class="text-sm font-semibold text-gray-900">Never weighed</p>
                                        </div>

                                        <div class="bg-white rounded-lg p-3 border border-gray-200">
                                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Last Weight</p>
                                            <p class="text-sm font-semibold text-gray-900">No weight recorded</p>
                                        </div>

                                        <div class="bg-white rounded-lg p-3 border border-gray-200">
                                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Pending By</p>
                                            <p class="text-sm font-semibold text-yellow-700">
                                                {{ max(0, (int) $overdueDays) }} days
                                            </p>
                                        </div>
                                    </div>

                                    <div class="mt-3 flex items-center justify-between">
                                        <div class="flex items-center">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                First Weight Pending
                                            </span>
                                            <span class="ml-2 text-xs text-gray-500">
                                                Initial weight record needed
                                            </span>
                                        </div>

                                        @if($pig)
                                            <a href="{{ route('weights.create', ['pig_id' => $pig->id]) }}"
                                               class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-yellow-600 hover:bg-yellow-700 transition-colors">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                                                </svg>
                                                Record First Weight
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

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
                    <span class="font-medium">
                        {{ ($allOverduePigsData->count() ?? 0) + ($allFirstWeightPendingData->count() ?? 0) }}
                    </span>
                    pigs need weighing attention
                    <span class="text-xs text-gray-500 ml-2">
                        ({{ $allOverduePigsData->count() ?? 0 }} overdue • {{ $allFirstWeightPendingData->count() ?? 0 }} first weight pending)
                    </span>
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

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        function mountDashboardChart(id, config) {
            const canvas = document.getElementById(id);
            if (! canvas) {
                return;
            }

            new Chart(canvas, config);
        }

        mountDashboardChart('stockTrendsChart', {
            type: 'line',
            data: {
                labels: @json($stockTrendsLabels),
                datasets: [
                    {
                        label: 'Active',
                        data: @json($stockTrendsActive),
                        borderColor: 'rgb(34, 197, 94)',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Sold',
                        data: @json($stockTrendsSold),
                        borderColor: 'rgb(168, 85, 247)',
                        backgroundColor: 'rgba(168, 85, 247, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Deceased',
                        data: @json($stockTrendsDeceased),
                        borderColor: 'rgb(239, 68, 68)',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2,
                plugins: { legend: { position: 'bottom' } },
                scales: { y: { beginAtZero: true } }
            }
        });

        mountDashboardChart('matingTrendsChart', {
            type: 'bar',
            data: {
                labels: @json($matingTrendsLabels),
                datasets: [{
                    label: 'Matings',
                    data: @json($matingTrendsData),
                    backgroundColor: 'rgba(59, 130, 246, 0.6)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { display: false }
                    },
                    x: {
                        grid: { display: false }
                    }
                },
                layout: { padding: 0 }
            }
        });

        mountDashboardChart('gestationStatusChart', {
            type: 'doughnut',
            data: {
                labels: Object.keys(@json($gestationStatusData)),
                datasets: [{
                    data: Object.values(@json($gestationStatusData)),
                    backgroundColor: [
                        'rgba(99, 102, 241, 0.8)',
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(245, 158, 11, 0.6)',
                        'rgba(239, 68, 68, 0.8)'
                    ],
                    hoverOffset: 4,
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        align: 'center',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                },
                layout: { padding: 10 }
            }
        });

        mountDashboardChart('farrowingTrendsChart', {
            type: 'line',
            data: {
                labels: @json($farrowingTrendsLabels),
                datasets: [{
                    label: 'Farrowings',
                    data: @json($farrowingTrendsData),
                    borderColor: 'rgb(236, 72, 153)',
                    backgroundColor: 'rgba(236, 72, 153, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });

        mountDashboardChart('pigletsBornChart', {
            type: 'bar',
            data: {
                labels: @json($farrowingTrendsLabels),
                datasets: [{
                    label: 'Piglets Born',
                    data: @json($farrowingTrendsBorn),
                    backgroundColor: 'rgba(34, 197, 94, 0.6)',
                    borderColor: 'rgb(34, 197, 94)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });

        mountDashboardChart('weaningTrendsChart', {
            type: 'line',
            data: {
                labels: @json($weaningTrendsLabels),
                datasets: [{
                    label: 'Weanings',
                    data: @json($weaningTrendsData),
                    borderColor: 'rgb(20, 184, 166)',
                    backgroundColor: 'rgba(20, 184, 166, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: 'rgb(20, 184, 166)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0, 0, 0, 0.05)' }
                    },
                    x: {
                        grid: { display: false }
                    }
                },
                layout: {
                    padding: { left: 0, right: 10, top: 10, bottom: 0 }
                }
            }
        });

        mountDashboardChart('weightTrendsChart', {
            type: 'line',
            data: {
                labels: @json($weightLabels),
                datasets: [{
                    label: 'Avg Weight (kg)',
                    data: @json($weightValues),
                    borderColor: 'rgb(249, 115, 22)',
                    backgroundColor: 'rgba(249, 115, 22, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: false } }
            }
        });

        mountDashboardChart('weightMonthlyChart', {
            type: 'bar',
            data: {
                labels: @json($weightMonthlyLabels),
                datasets: [{
                    label: 'Avg Weight (kg)',
                    data: @json($weightMonthlyAvg),
                    backgroundColor: 'rgba(59, 130, 246, 0.6)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: false } }
            }
        });

        mountDashboardChart('healthTrendsChart', {
            type: 'line',
            data: {
                labels: @json($healthTrendsLabels),
                datasets: [
                    {
                        label: 'Health Records',
                        data: @json($healthTrendsRecords),
                        borderColor: 'rgb(244, 63, 94)',
                        backgroundColor: 'rgba(244, 63, 94, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Medications',
                        data: @json($healthTrendsMedications),
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2,
                plugins: { legend: { position: 'bottom' } },
                scales: { y: { beginAtZero: true } }
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const farmValueCanvas = document.getElementById('farmValueTrendChart');
            if (! farmValueCanvas) {
                return;
            }

            const ctx = farmValueCanvas.getContext('2d');
            const labels = @json($weightMonthlyLabels);
            const avgWeights = @json($weightMonthlyAvg);
            const pricePerKg = {{ $liveWeightPrice }};
            const valueData = avgWeights.map(weight => (weight * pricePerKg).toFixed(2));

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Farm Value (GHS)',
                        data: valueData,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: false,
                            ticks: {
                                callback: (value) => 'GHS ' + value.toLocaleString()
                            }
                        }
                    }
                }
            });
        });

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
                if (modal && !modal.classList.contains('hidden')) {
                    toggleWeighingAlertsModal();
                }
            }
        });
    </script>
</x-app-layout>
