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

        .entry-topbar{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap: 16px;
            flex-wrap:wrap;
            margin-bottom: 18px;
        }

        .entry-page-title{
            margin: 0;
            color:#0f172a;
            font-size: 2rem;
            line-height: 1.08;
            font-weight: 900;
        }

        .entry-page-subtitle{
            color:#64748b;
            margin-top: 6px;
            font-size:.92rem;
        }

        .entry-top-actions{
            display:flex;
            align-items:center;
            gap:10px;
            flex-wrap:wrap;
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

        .workbench-grid{
            display:grid;
            grid-template-columns: repeat(3, minmax(0,1fr));
            gap: 14px;
            padding: 0 24px 24px;
        }

        .workbench-action{
            border: 1px solid #e5efe9;
            border-radius: 18px;
            padding: 18px;
            min-height: 140px;
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            gap: 10px;
            text-align:center;
            background: linear-gradient(180deg,#fff 0%,#fbfefc 100%);
            color:#0f172a;
            text-decoration:none;
            transition:.24s ease;
        }

        .workbench-action:hover{
            transform: translateY(-2px);
            border-color:#bbf7d0;
            box-shadow:0 14px 28px rgba(15,23,42,.07);
        }

        .workbench-icon{
            width:56px;
            height:56px;
            border-radius:16px;
            display:flex;
            align-items:center;
            justify-content:center;
            color:#fff;
        }

        .workbench-icon svg{
            width:26px;
            height:26px;
        }

        .workbench-title{
            font-size:.95rem;
            font-weight:900;
        }

        .workbench-sub{
            font-size:.76rem;
            color:#64748b;
        }

        .needs-table{
            width:100%;
            border-collapse:collapse;
        }

        .needs-table th,
        .needs-table td{
            padding: 12px 14px;
            border-top:1px solid #eef2f7;
            text-align:left;
            vertical-align:middle;
            font-size:.82rem;
        }

        .needs-table th{
            color:#64748b;
            background:#f8fafc;
            font-size:.68rem;
            font-weight:900;
            text-transform:uppercase;
        }

        .priority-pill{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            padding:5px 9px;
            border-radius:999px;
            font-size:.66rem;
            font-weight:900;
            text-transform:uppercase;
        }

        .priority-high{ background:#fee2e2; color:#b91c1c; }
        .priority-medium{ background:#ffedd5; color:#c2410c; }
        .priority-low{ background:#dbeafe; color:#1d4ed8; }

        .entry-small-link{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            padding:6px 10px;
            border-radius:10px;
            border:1px solid #bbf7d0;
            background:#f0fdf4;
            color:#166534;
            font-size:.72rem;
            font-weight:900;
            text-decoration:none;
            white-space:nowrap;
        }

        .lower-grid{
            display:grid;
            grid-template-columns: minmax(0,1.1fr) minmax(0,1.2fr) minmax(300px,.8fr);
            gap: 16px;
            margin-top:24px;
        }

        .recent-entry-table{
            width:100%;
            border-collapse:collapse;
        }

        .recent-entry-table th,
        .recent-entry-table td{
            padding: 11px 14px;
            border-top:1px solid #eef2f7;
            text-align:left;
            font-size:.8rem;
        }

        .recent-entry-table th{
            font-size:.68rem;
            text-transform:uppercase;
            font-weight:900;
            color:#64748b;
            background:#f8fafc;
        }

        .quality-list{
            padding: 0 24px 24px;
            display:grid;
            gap:16px;
        }

        .quality-row{
            display:grid;
            gap:7px;
        }

        .quality-top{
            display:flex;
            justify-content:space-between;
            gap:12px;
            color:#334155;
            font-size:.82rem;
            font-weight:800;
        }

        .quality-track{
            height:7px;
            border-radius:999px;
            background:#e2e8f0;
            overflow:hidden;
        }

        .quality-fill{
            height:100%;
            border-radius:999px;
        }

        .quality-green{ background:#16a34a; }
        .quality-blue{ background:#2563eb; }
        .quality-amber{ background:#f59e0b; }
        .quality-red{ background:#dc2626; }

        .quality-detail-list{
            display:grid;
            gap:10px;
            padding-top:4px;
        }

        .quality-detail-row{
            display:grid;
            grid-template-columns: 1fr auto;
            gap:10px;
            align-items:center;
            padding:10px 12px;
            border:1px solid #e2e8f0;
            border-radius:14px;
            background:#f8fafc;
        }

        .quality-detail-title{
            color:#0f172a;
            font-size:.8rem;
            font-weight:900;
        }

        .quality-detail-place{
            margin-top:2px;
            color:#64748b;
            font-size:.7rem;
            line-height:1.35;
        }

        .quality-detail-meta{
            display:flex;
            align-items:center;
            gap:8px;
            flex-wrap:wrap;
            justify-content:flex-end;
        }

        .quality-count{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            min-width:32px;
            height:28px;
            border-radius:9px;
            background:#fff7ed;
            color:#c2410c;
            font-size:.78rem;
            font-weight:900;
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
            .content-grid,
            .lower-grid{
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px){
            .entry-wrap{
                padding: 14px 10px 42px;
            }

            .metric-grid,
            .quick-grid,
            .workbench-grid{
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

            <div class="entry-topbar">
                <div>
                    <h1 class="entry-page-title">Data Entry Dashboard</h1>
                    <div class="entry-page-subtitle">Enter daily records, clear missing data, and confirm recent work was saved.</div>
                </div>

                <div class="entry-top-actions">
                    <a href="{{ route('weights.create') }}" class="entry-btn entry-btn-soft" style="background:#166534;border-color:#166534;">
                        New Weight
                    </a>
                    <a href="{{ route('all-stocks.create') }}" class="entry-btn entry-btn-soft" style="background:#2563eb;border-color:#2563eb;">
                        New Pig
                    </a>
                </div>
            </div>

            <div class="entry-section">
                <div class="metric-grid">
                    @foreach($entryMetrics as $metric)
                        <a href="{{ $metric['route'] }}" class="metric-card {{ $metric['color'] }}">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="metric-label">{{ $metric['label'] }}</div>
                                    <p class="metric-value">{{ number_format($metric['value']) }}</p>
                                    <div class="metric-sub">{{ $metric['sub'] }}</div>
                                </div>
                                <div class="metric-icon">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414A1 1 0 0 1 19 9.414V19a2 2 0 0 1-2 2Z"/>
                                    </svg>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="content-grid">
                <div class="surface-card">
                    <div class="surface-head">
                        <div>
                            <h2 class="surface-title">Entry Workbench</h2>
                            <p class="surface-sub">Fast entry points for the daily records this role handles most often</p>
                        </div>
                    </div>

                    <div class="workbench-grid">
                        <a href="{{ route('all-stocks.create') }}" class="workbench-action">
                            <div class="workbench-icon" style="background:#16a34a;"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m7-7H5"/></svg></div>
                            <div class="workbench-title">Add Pig</div>
                            <div class="workbench-sub">Create a new pig record</div>
                        </a>
                        <a href="{{ route('weights.create') }}" class="workbench-action">
                            <div class="workbench-icon" style="background:#2563eb;"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h18M7 6l-3 8a3 3 0 0 0 6 0L7 6Zm10 0-3 8a3 3 0 0 0 6 0l-3-8ZM12 3v18"/></svg></div>
                            <div class="workbench-title">Record Weight</div>
                            <div class="workbench-sub">Add weight measurement</div>
                        </a>
                        <a href="{{ route('health.create') }}" class="workbench-action">
                            <div class="workbench-icon" style="background:#059669;"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v8m4-4H8m13 0A9 9 0 1 1 3 12a9 9 0 0 1 18 0Z"/></svg></div>
                            <div class="workbench-title">Record Health</div>
                            <div class="workbench-sub">Log health treatment</div>
                        </a>
                        <a href="{{ route('mating.index') }}" class="workbench-action">
                            <div class="workbench-icon" style="background:#8b5cf6;"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m5-11a5 5 0 1 1-10 0 5 5 0 0 1 10 0Z"/></svg></div>
                            <div class="workbench-title">Record Mating</div>
                            <div class="workbench-sub">Add breeding record</div>
                        </a>
                        <a href="{{ route('all-stocks.import.create') }}" class="workbench-action">
                            <div class="workbench-icon" style="background:#1d4ed8;"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0 4-4m-4 4-4-4M4 21h16"/></svg></div>
                            <div class="workbench-title">Import Stock</div>
                            <div class="workbench-sub">Upload Excel records</div>
                        </a>
                        <a href="{{ route('deaths.index') }}" class="workbench-action">
                            <div class="workbench-icon" style="background:#dc2626;"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 4h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.2 16c-.77 1.33.19 3 1.73 3Z"/></svg></div>
                            <div class="workbench-title">Record Mortality</div>
                            <div class="workbench-sub">Open mortality records</div>
                        </a>
                    </div>
                </div>

                <div class="surface-card">
                    <div class="surface-head">
                        <div>
                            <h2 class="surface-title">Needs Data Entry</h2>
                            <p class="surface-sub">Records that need entry or cleanup</p>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="needs-table">
                            <thead>
                                <tr>
                                    <th>Priority</th>
                                    <th>Item</th>
                                    <th>Count</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($needsDataEntry as $need)
                                    <tr>
                                        <td><span class="priority-pill priority-{{ $need['priority'] }}">{{ $need['priority'] }}</span></td>
                                        <td>
                                            <div class="font-bold text-slate-900">{{ $need['item'] }}</div>
                                            <div class="text-xs text-slate-500 mt-1">{{ $need['details'] }}</div>
                                        </td>
                                        <td class="font-black text-slate-900">{{ number_format($need['count']) }}</td>
                                        <td><a href="{{ $need['route'] }}" class="entry-small-link">Enter Data</a></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-slate-500 py-8">No data entry issues waiting right now.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="lower-grid">
                <div class="surface-card">
                    <div class="surface-head">
                        <div>
                            <h2 class="surface-title">Daily Entry Volume</h2>
                            <p class="surface-sub">Weight entries per day with a 7-day moving average</p>
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

                <div class="surface-card">
                    <div class="surface-head">
                        <div>
                            <h2 class="surface-title">Recent Entries</h2>
                            <p class="surface-sub">Latest saved records</p>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="recent-entry-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Type</th>
                                    <th>Record</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentActivities as $activity)
                                    <tr>
                                        <td>{{ $activity['time']->format('H:i') }}</td>
                                        <td>{{ $activity['title'] }}</td>
                                        <td>
                                            <a href="{{ $activity['route'] }}" class="font-semibold text-slate-900 hover:text-emerald-700">{{ $activity['description'] }}</a>
                                            <div class="text-xs text-slate-500 mt-1">{{ $activity['details'] }}</div>
                                        </td>
                                        <td><span class="priority-pill priority-low">Saved</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-slate-500 py-8">No recent entries found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="surface-card">
                    <div class="surface-head">
                        <div>
                            <h2 class="surface-title">Data Quality</h2>
                            <p class="surface-sub">Completeness and cleanup signals</p>
                        </div>
                    </div>
                    <div class="quality-list">
                        <div class="quality-row">
                            <div class="quality-top"><span>Completeness</span><span>{{ $dataQuality['completeness'] }}%</span></div>
                            <div class="quality-track"><div class="quality-fill quality-green" style="width: {{ $dataQuality['completeness'] }}%;"></div></div>
                        </div>
                        <div class="quality-row">
                            <div class="quality-top"><span>Duplicates</span><span>{{ number_format($dataQuality['duplicates']) }}</span></div>
                            <div class="quality-track"><div class="quality-fill quality-blue" style="width: {{ min(100, $dataQuality['duplicates'] * 10) }}%;"></div></div>
                        </div>
                        <div class="quality-row">
                            <div class="quality-top"><span>Missing Fields</span><span>{{ number_format($dataQuality['missing_fields']) }}</span></div>
                            <div class="quality-track"><div class="quality-fill quality-amber" style="width: {{ min(100, $dataQuality['missing_fields']) }}%;"></div></div>
                        </div>
                        <div class="quality-row">
                            <div class="quality-top"><span>Failed Imports</span><span>{{ number_format($dataQuality['failed_imports']) }}</span></div>
                            <div class="quality-track"><div class="quality-fill quality-red" style="width: {{ min(100, $dataQuality['failed_imports'] * 10) }}%;"></div></div>
                        </div>

                        <div class="quality-detail-list">
                            <div class="quality-top">
                                <span>Missing Details</span>
                                <span>{{ number_format($dataQualityDetails->sum('count')) }}</span>
                            </div>

                            @forelse($dataQualityDetails as $detail)
                                <div class="quality-detail-row">
                                    <div>
                                        <div class="quality-detail-title">{{ $detail['label'] }}</div>
                                        <div class="quality-detail-place">Fix in: {{ $detail['where'] }}</div>
                                    </div>
                                    <div class="quality-detail-meta">
                                        <span class="quality-count">{{ number_format($detail['count']) }}</span>
                                        <a href="{{ $detail['route'] }}" class="entry-small-link">Open</a>
                                    </div>
                                </div>
                            @empty
                                <div class="empty-state" style="padding:18px 12px;">
                                    <p class="text-sm">No missing data details found.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
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
