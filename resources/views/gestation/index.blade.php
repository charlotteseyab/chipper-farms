<x-app-layout>
    <style>
        .gestation-page{
            min-height: 100vh;
            padding: 18px 0 40px;
        }

        .gestation-shell{
            max-width: 1520px;
            margin: 0 auto;
            padding: 0 16px;
        }

        .gestation-hero{
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 26px 28px;
            background: linear-gradient(135deg, #14532d 0%, #166534 42%);
            box-shadow: 0 24px 54px rgba(22,101,52,.16);
            color: #fff;
            margin-bottom: 18px;
        }

        .gestation-hero::before{
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,.16), transparent 28%),
                radial-gradient(circle at bottom left, rgba(255,255,255,.08), transparent 24%);
            pointer-events: none;
        }

        .gestation-hero-inner{
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .gestation-title{
            margin: 0;
            font-size: clamp(1.9rem, 3vw, 2.9rem);
            line-height: 1.02;
            font-weight: 900;
            letter-spacing: -.04em;
        }

        .gestation-subtitle{
            margin: 10px 0 0;
            max-width: 760px;
            color: rgba(255,255,255,.90);
            font-size: .95rem;
            line-height: 1.75;
        }

        .gestation-actions{
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .gestation-top-btn{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 16px;
            padding: 11px 15px;
            font-size: .84rem;
            font-weight: 800;
            text-decoration: none;
            transition: .22s ease;
            border: 1px solid rgba(255,255,255,.16);
            background: rgba(255,255,255,.14);
            color: #fff;
        }

        .gestation-top-btn:hover{
            background: rgba(255,255,255,.20);
            transform: translateY(-1px);
        }

        .gestation-stack{
            display: grid;
            gap: 18px;
        }

        .gestation-stats-grid{
            display: grid;
            grid-template-columns: repeat(4, minmax(0,1fr));
            gap: 14px;
        }

        .gestation-stat{
            background: rgba(255,255,255,.97);
            border: 1px solid #dbe7f0;
            border-radius: 24px;
            padding: 16px;
            box-shadow: 0 10px 24px rgba(15,23,42,.04);
        }

        .gestation-stat-row{
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .gestation-stat-icon{
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .icon-blue{ background:#dbeafe; color:#2563eb; }
        .icon-yellow{ background:#fef9c3; color:#a16207; }
        .icon-green{ background:#dcfce7; color:#16a34a; }
        .icon-red{ background:#fee2e2; color:#dc2626; }

        .gestation-stat-label{
            font-size: .73rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #94a3b8;
            margin-bottom: 6px;
        }

        .gestation-stat-value{
            font-size: 1.55rem;
            font-weight: 900;
            color: #0f172a;
            line-height: 1;
        }

        .gestation-stat-note{
            margin-top: 7px;
            font-size: .76rem;
            color: #64748b;
            line-height: 1.55;
        }

        .gestation-card{
            background: rgba(255,255,255,.97);
            border: 1px solid #dbe7f0;
            border-radius: 28px;
            box-shadow: 0 14px 34px rgba(15,23,42,.06);
            overflow: hidden;
        }

        .gestation-card-head{
            padding: 22px 24px 14px;
            border-bottom: 1px solid #edf2f7;
            background: linear-gradient(180deg, rgba(248,250,252,.95) 0%, rgba(255,255,255,.98) 100%);
        }

        .gestation-card-title{
            margin: 0;
            font-size: 1.04rem;
            font-weight: 900;
            color: #0f172a;
        }

        .gestation-card-sub{
            margin-top: 4px;
            font-size: .82rem;
            color: #64748b;
        }

        .gestation-toolbar{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            padding: 16px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #edf2f7;
        }

        .gestation-search-wrap{
            width: 100%;
            max-width: 320px;
        }

        .gestation-search{
            position: relative;
        }

        .gestation-search svg{
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            color: #94a3b8;
            pointer-events: none;
        }

        .gestation-input,
        .gestation-select,
        .gestation-textarea{
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            background: #fff;
            padding: 11px 13px;
            font-size: .88rem;
            color: #0f172a;
            outline: none;
            transition: .2s ease;
        }

        .gestation-search .gestation-input{
            padding-left: 38px;
        }

        .gestation-input:focus,
        .gestation-select:focus,
        .gestation-textarea:focus{
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37,99,235,.10);
        }

        .gestation-table-wrap{
            overflow-x: auto;
        }

        .gestation-table{
            width: 100%;
            min-width: 1360px;
            border-collapse: separate;
            border-spacing: 0;
        }

        .gestation-table thead th{
            background: #eff6ff;
            padding: 14px 18px;
            font-size: .73rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #1d4ed8;
            text-align: left;
            border-bottom: 1px solid #dbeafe;
        }

        .gestation-table tbody td{
            padding: 16px 18px;
            border-bottom: 1px solid #eef2f7;
            vertical-align: top;
            font-size: .88rem;
            color: #0f172a;
            background: rgba(255,255,255,.98);
        }

        .gestation-table tbody tr:hover td{
            background: #fbfdff;
        }

        .pair-cell{
            display: grid;
            gap: 6px;
        }

        .pair-inline{
            display: inline-flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            color: #475569;
            font-size: .83rem;
        }

        .pair-inline strong{
            color: #0f172a;
        }

        .gestation-chip{
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: .74rem;
            font-weight: 800;
            line-height: 1;
        }

        .chip-green{ background:#dcfce7; color:#166534; }
        .chip-red{ background:#fee2e2; color:#b91c1c; }
        .chip-blue{ background:#dbeafe; color:#1d4ed8; }
        .chip-gray{ background:#f1f5f9; color:#475569; }
        .chip-yellow{ background:#fef9c3; color:#854d0e; }
        .chip-orange{ background:#ffedd5; color:#c2410c; }

        .gestation-date-block{
            display: grid;
            gap: 4px;
        }

        .gestation-date-main{
            font-weight: 800;
            color: #0f172a;
        }

        .gestation-date-sub{
            font-size: .78rem;
            color: #64748b;
        }

        .gestation-action-btn{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 12px;
            border-radius: 14px;
            border: 1px solid #d1d5db;
            background: #fff;
            color: #374151;
            font-size: .82rem;
            font-weight: 800;
            transition: .2s ease;
        }

        .gestation-action-btn:hover{
            background: #f8fafc;
        }

        .gestation-menu{
            width: 240px;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 18px 44px rgba(15,23,42,.12);
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }

        .gestation-menu-item{
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 12px 14px;
            font-size: .84rem;
            font-weight: 700;
            color: #334155;
            background: #fff;
            text-decoration: none;
            transition: .2s ease;
            text-align: left;
        }

        .gestation-menu-item:hover{
            background: #f8fafc;
        }

        .gestation-menu-item.danger{
            color: #b91c1c;
        }

        .gestation-menu-item.danger:hover{
            background: #fef2f2;
        }

        .gestation-empty{
            padding: 56px 18px;
            text-align: center;
        }

        .gestation-empty-icon{
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            color: #94a3b8;
        }

        .gestation-empty-title{
            font-size: 1.08rem;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .gestation-empty-text{
            max-width: 620px;
            margin: 0 auto;
            color: #64748b;
            line-height: 1.75;
            font-size: .92rem;
        }

        .gestation-footer{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            padding: 16px 24px;
            border-top: 1px solid #edf2f7;
            background: #fff;
        }

        .gestation-footer-text{
            font-size: .84rem;
            color: #475569;
            font-weight: 700;
        }

        .gestation-modal-overlay{
            position: fixed;
            inset: 0;
            background: rgba(15,23,42,.55);
            backdrop-filter: blur(2px);
            display: none;
            z-index: 50;
            overflow-y: auto;
            padding: 20px;
        }

        .gestation-modal-overlay.show{
            display: block;
        }

        .gestation-modal{
            position: relative;
            margin: 40px auto;
            background: #fff;
            border-radius: 24px;
            border: 1px solid #ece7ef;
            box-shadow: 0 26px 60px rgba(15,23,42,.18);
            overflow: hidden;
        }

        .gestation-modal.large{
            max-width: 1040px;
        }

        .gestation-modal.medium{
            max-width: 430px;
        }

        .gestation-modal-head{
            padding: 20px 22px 14px;
            border-bottom: 1px solid #edf2f7;
            background: linear-gradient(180deg, rgba(239,246,255,.88) 0%, rgba(255,255,255,.98) 100%);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .gestation-modal-title{
            font-size: 1rem;
            font-weight: 900;
            color: #0f172a;
        }

        .gestation-modal-close{
            border: none;
            background: transparent;
            color: #94a3b8;
            cursor: pointer;
        }

        .gestation-modal-body{
            padding: 20px 22px 22px;
        }

        .gestation-info-box{
            background: #f8fafc;
            border: 1px solid #eef2f7;
            border-radius: 16px;
            padding: 14px 16px;
            margin-bottom: 16px;
        }

        .gestation-error{
            background: #fff1f2;
            border: 1px solid #fecdd3;
            color: #9f1239;
            border-radius: 16px;
            padding: 14px 16px;
            margin-bottom: 16px;
        }

        .gestation-form-grid{
            display: grid;
            gap: 16px;
        }

        .gestation-form-grid.two-col{
            grid-template-columns: repeat(2, minmax(0,1fr));
        }

        .gestation-subcard{
            background: #f8fafc;
            border: 1px solid #eef2f7;
            border-radius: 18px;
            padding: 14px;
        }

        .gestation-subcard h4{
            margin: 0 0 10px;
            font-size: .95rem;
            font-weight: 900;
            color: #0f172a;
        }

        .gestation-note{
            border-radius: 16px;
            padding: 12px 14px;
            font-size: .83rem;
            line-height: 1.6;
        }

        .gestation-note.blue{
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }

        .gestation-actions-row{
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            padding-top: 8px;
            border-top: 1px solid #edf2f7;
            margin-top: 16px;
        }

        .gestation-modal-actions{
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .gestation-btn{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 120px;
            padding: 11px 14px;
            border-radius: 14px;
            font-size: .84rem;
            font-weight: 800;
            border: 1px solid #d1d5db;
            background: #fff;
            cursor: pointer;
        }

        .gestation-btn.secondary{
            color: #475569;
        }

        .gestation-btn.primary-green{
            background: #16a34a;
            border-color: #16a34a;
            color: #fff;
        }

        .gestation-btn.primary-blue{
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
        }

        @media (max-width: 1200px){
            .gestation-stats-grid{
                grid-template-columns: repeat(2, minmax(0,1fr));
            }

            .gestation-form-grid.two-col{
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px){
            .gestation-shell{
                padding: 0 12px;
            }

            .gestation-hero{
                padding: 22px 18px;
                border-radius: 24px;
            }

            .gestation-stats-grid{
                grid-template-columns: 1fr;
            }

            .gestation-card-head,
            .gestation-toolbar,
            .gestation-footer,
            .gestation-modal-head,
            .gestation-modal-body{
                padding-left: 18px;
                padding-right: 18px;
            }

            .gestation-search-wrap,
            .gestation-actions{
                width: 100%;
            }

            .gestation-actions > *{
                flex: 1 1 100%;
            }
        }
    </style>

    <div class="gestation-page">
        <div class="gestation-shell">
            <div class="gestation-hero">
                <div class="gestation-hero-inner">
                    <div>
                        <h1 class="gestation-title">Gestation Period Management</h1>
                        <p class="gestation-subtitle">
                            Monitor pregnant sows, track expected farrowing windows, and manage gestation outcomes from one clean workflow.
                        </p>
                    </div>

                    <div class="gestation-actions">
                        <a href="{{ route('mating.index') }}" class="gestation-top-btn">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                            Plan Mating
                        </a>
                    </div>
                </div>
            </div>

            <main class="gestation-stack">
                <div class="gestation-stats-grid">
                    <div class="gestation-stat">
                        <div class="gestation-stat-row">
                            <div class="gestation-stat-icon icon-blue">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                            </div>
                            <div>
                                <div class="gestation-stat-label">Total Pregnant</div>
                                <div class="gestation-stat-value">{{ $stats['total_pregnant'] }}</div>
                                <div class="gestation-stat-note">Confirmed pregnant sows being tracked</div>
                            </div>
                        </div>
                    </div>

                    <div class="gestation-stat">
                        <div class="gestation-stat-row">
                            <div class="gestation-stat-icon icon-yellow">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <div class="gestation-stat-label">Due This Week</div>
                                <div class="gestation-stat-value">{{ $stats['due_this_week'] }}</div>
                                <div class="gestation-stat-note">Expected to farrow within 7 days</div>
                            </div>
                        </div>
                    </div>

                    <div class="gestation-stat">
                        <div class="gestation-stat-row">
                            <div class="gestation-stat-icon icon-green">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div>
                                <div class="gestation-stat-label">Due Next Week</div>
                                <div class="gestation-stat-value">{{ $stats['due_next_week'] }}</div>
                                <div class="gestation-stat-note">Expected to farrow in the following week</div>
                            </div>
                        </div>
                    </div>

                    <div class="gestation-stat">
                        <div class="gestation-stat-row">
                            <div class="gestation-stat-icon icon-red">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <div class="gestation-stat-label">Overdue</div>
                                <div class="gestation-stat-value">{{ $stats['overdue'] }}</div>
                                <div class="gestation-stat-note">Past expected farrowing date</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="gestation-card">
                    <div class="gestation-card-head">
                        <h2 class="gestation-card-title">Pregnant Sows</h2>
                        <div class="gestation-card-sub">Monitor gestation progress, record farrowing, and update gestation outcomes.</div>
                    </div>

                    <div class="gestation-toolbar">
                        <div class="text-sm font-semibold text-slate-700">
                            @if($pregnantMatings->count() > 0)
                                {{ $pregnantMatings->total() }} gestation records in this dataset
                            @else
                                No gestation records found
                            @endif
                        </div>

                        <div class="gestation-search-wrap">
                            <form action="{{ route('gestation.index') }}" method="GET" class="gestation-search">
                                <svg fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                                </svg>
                                <input
                                    type="text"
                                    id="pregnant-sows-search"
                                    name="search"
                                    value="{{ request('search') }}"
                                    class="gestation-input"
                                    placeholder="Search sow or boar code..."
                                >
                                <button type="submit" class="hidden">Search</button>
                            </form>
                        </div>
                    </div>

                    <div class="gestation-table-wrap">
                        <table class="gestation-table">
                            <thead>
                                <tr>
                                    <th>Breeding Pair</th>
                                    <th>Mating Date</th>
                                    <th>Expected Farrowing</th>
                                    <th>Status</th>
                                    <th>Gestation Days</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pregnantMatings as $mating)
                                    <tr>
                                        <td>
                                            <div class="pair-cell">
                                                <div class="pair-inline">
                                                    <span class="gestation-chip chip-blue">♂ Male</span>
                                                    <strong>{{ $mating->male->ear_tag_number }}</strong>
                                                    <span class="text-slate-400">×</span>
                                                    <span class="gestation-chip chip-yellow">♀ Female</span>
                                                    <strong>{{ $mating->female->ear_tag_number }}</strong>
                                                </div>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="gestation-date-block">
                                                <div class="gestation-date-main">{{ $mating->actual_date->format('M d, Y') }}</div>
                                                <div class="gestation-date-sub">{{ $mating->actual_date->diffForHumans() }}</div>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="gestation-date-block">
                                                <div class="gestation-date-main">{{ $mating->expected_farrowing_date->format('M d, Y') }}</div>

                                                @if($mating->pregnancy_status === 'farrowed' && $mating->actual_farrowing_date)
                                                    @php
                                                        $delta = $mating->expected_farrowing_date->diffInDays($mating->actual_farrowing_date, false);
                                                    @endphp
                                                    <div class="flex flex-wrap gap-2 mt-1">
                                                        @if($delta < 0)
                                                            <span class="gestation-chip chip-blue">Farrowed early by {{ abs($delta) }} days</span>
                                                        @elseif($delta === 0)
                                                            <span class="gestation-chip chip-green">Farrowed on time</span>
                                                        @else
                                                            <span class="gestation-chip chip-orange">Farrowed late by {{ $delta }} days</span>
                                                        @endif
                                                    </div>
                                                    <div class="gestation-date-sub">
                                                        Actual: {{ $mating->actual_farrowing_date->format('M d, Y') }}
                                                    </div>
                                                @else
                                                    <div class="gestation-date-sub">
                                                        @if($mating->is_overdue_for_farrowing)
                                                            {{ abs($mating->days_to_farrowing) }} days overdue
                                                        @elseif(\Carbon\Carbon::now()->startOfDay()->equalTo($mating->expected_farrowing_date->copy()->startOfDay()))
                                                            Due today
                                                        @else
                                                            {{ \Carbon\Carbon::now()->startOfDay()->diffInDays($mating->expected_farrowing_date->copy()->startOfDay()) }} days to go
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        </td>

                                        <td>
                                            <span class="gestation-chip {{ $mating->farrowing_status_badge }}">
                                                {{ $mating->pregnancy_status_icon }} {{ $mating->farrowing_status }}
                                            </span>
                                        </td>

                                        <td>
                                            <strong>{{ $mating->gestation_days }}</strong> days
                                        </td>

                                        <td>
                                            <div class="relative inline-block text-left">
                                                <button type="button"
                                                        class="gestation-action-btn"
                                                        id="actions-menu-{{ $mating->id }}"
                                                        onclick="toggleDropdown({{ $mating->id }})">
                                                    Actions
                                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                                    </svg>
                                                </button>

                                                <div class="origin-top-right absolute right-0 mt-2 hidden z-50 gestation-menu" id="dropdown-menu-{{ $mating->id }}">
                                                    <div class="py-1">
                                                        <div class="px-4 py-3 border-b border-gray-100">
                                                            @if($mating->pregnancy_status === 'pregnant')
                                                                <span class="gestation-chip chip-yellow">Pregnant</span>
                                                            @elseif($mating->pregnancy_status === 'farrowed')
                                                                <div class="space-y-2">
                                                                    <span class="gestation-chip chip-green">✓ Farrowed</span>
                                                                    @if($mating->actual_farrowing_date && $mating->expected_farrowing_date)
                                                                        @php
                                                                            $delta = $mating->expected_farrowing_date->diffInDays($mating->actual_farrowing_date, false);
                                                                        @endphp
                                                                        @if($delta < 0)
                                                                            <span class="gestation-chip chip-blue">Early by {{ abs($delta) }}d</span>
                                                                        @elseif($delta === 0)
                                                                            <span class="gestation-chip chip-green">On time</span>
                                                                        @else
                                                                            <span class="gestation-chip chip-orange">Late by {{ $delta }}d</span>
                                                                        @endif
                                                                        <div class="text-xs text-gray-500">
                                                                            {{ $mating->actual_farrowing_date->format('M d, Y') }}
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            @else
                                                                <span class="text-gray-400 text-sm">Completed</span>
                                                            @endif
                                                        </div>

                                                        @if($mating->pregnancy_status === 'pregnant')
                                                            <button onclick="openFarrowingModal({{ $mating->id }}, '{{ $mating->female->ear_tag_number }}', '{{ $mating->expected_farrowing_date->format('Y-m-d') }}')"
                                                                    class="gestation-menu-item"
                                                                    type="button">
                                                                Record Farrowing
                                                            </button>

                                                            <button onclick="openStatusModal({{ $mating->id }}, '{{ $mating->female->ear_tag_number }}')"
                                                                    class="gestation-menu-item"
                                                                    type="button">
                                                                Update Status
                                                            </button>
                                                        @endif

                                                        <a href="{{ route('gestation.edit', $mating->id) }}" class="gestation-menu-item">
                                                            Edit Gestation
                                                        </a>

                                                        <form method="POST" action="{{ route('gestation.destroy', $mating->id) }}" onsubmit="return confirm('Are you sure you want to delete this gestation record?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="gestation-menu-item danger">Delete</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6">
                                            <div class="gestation-empty">
                                                <svg class="gestation-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                                </svg>
                                                <div class="gestation-empty-title">
                                                    @if(request('search'))
                                                        No gestation records matched your search query
                                                    @else
                                                        No pregnant sows
                                                    @endif
                                                </div>
                                                <div class="gestation-empty-text">
                                                    @if(request('search'))
                                                        Try a different sow or boar code, or clear the search box.
                                                    @else
                                                        No confirmed pregnancies are being tracked at this time.
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($pregnantMatings->hasPages())
                        <div class="gestation-footer">
                            <div class="gestation-footer-text">
                                Showing {{ $pregnantMatings->firstItem() }} to {{ $pregnantMatings->lastItem() }} of {{ $pregnantMatings->total() }} results
                            </div>
                            <div>
                                <x-simple-pagination :paginator="$pregnantMatings" />
                            </div>
                        </div>
                    @endif
                </div>
            </main>
        </div>
    </div>

    <!-- Record Farrowing Modal -->
    <div id="farrowingModal" class="gestation-modal-overlay hidden">
        <div class="gestation-modal large">
            <div class="gestation-modal-head">
                <h3 class="gestation-modal-title">Record Farrowing</h3>
                <button onclick="closeFarrowingModal()" class="gestation-modal-close">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="gestation-modal-body">
                <div id="farrowingInfo" class="gestation-info-box"></div>

                @if($errors->any())
                    <div class="gestation-error">
                        <div class="font-semibold mb-2">Validation Errors</div>
                        <ul class="list-disc pl-5 space-y-1 text-sm">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form id="farrowingForm" method="POST" onsubmit="return validateFarrowingForm()">
                    @csrf
                    <div class="gestation-form-grid two-col">
                        <div class="gestation-subcard">
                            <h4>Basic Information</h4>

                            <div class="gestation-form-grid two-col">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Farrowing Date</label>
                                    <input type="date" name="farrowing_date" required max="{{ now()->toDateString() }}" class="gestation-input">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Time (optional)</label>
                                    <input type="time" name="farrowing_time" class="gestation-input">
                                </div>
                            </div>

                            <div class="gestation-note blue mt-4">
                                💡 Live + Stillborn must equal Total Born
                            </div>

                            <div class="gestation-form-grid two-col mt-4" style="grid-template-columns: repeat(3,minmax(0,1fr));">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Total Born</label>
                                    <input type="number" name="total_piglets_born" min="0" max="20" required class="gestation-input" onchange="updateTotals()" id="totalPigletsInput">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Live</label>
                                    <input type="number" name="live_births" min="0" max="20" required class="gestation-input" onchange="autoCalculateTotal(); updateTotals();" id="liveBirthsInput">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Stillborn</label>
                                    <input type="number" name="stillbirths" min="0" max="20" required class="gestation-input" onchange="autoCalculateTotal(); updateTotals();" id="stillbirthsInput">
                                </div>
                            </div>

                            <div class="gestation-subcard mt-4">
                                <h4>Gender Distribution</h4>
                                <p class="text-xs text-blue-600 mb-3">Optional: Males + Females should equal Total live if provided</p>
                                <div class="gestation-form-grid two-col">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Males</label>
                                        <input type="number" name="number_of_males" min="0" max="20" class="gestation-input" onchange="updateTotals()">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Females</label>
                                        <input type="number" name="number_of_females" min="0" max="20" class="gestation-input" onchange="updateTotals()">
                                    </div>
                                </div>
                                <div id="genderValidation" class="mt-2 text-xs"></div>
                            </div>

                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Average Birth Weight (kg)</label>
                                <input type="number" name="average_birth_weight" step="0.1" min="0" max="5" placeholder="Optional" class="gestation-input">
                            </div>
                        </div>

                        <div class="gestation-subcard">
                            <h4>Medical Details</h4>

                            <div class="gestation-form-grid two-col">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Farrowing Type</label>
                                    <select name="farrowing_type" required class="gestation-select">
                                        <option value="natural">Natural</option>
                                        <option value="assisted">Assisted</option>
                                        <option value="cesarean">Cesarean</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Difficulty Level</label>
                                    <select name="farrowing_difficulty" required class="gestation-select">
                                        <option value="easy">Easy</option>
                                        <option value="moderate">Moderate</option>
                                        <option value="difficult">Difficult</option>
                                        <option value="complicated">Complicated</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Sow Condition</label>
                                <select name="sow_condition" required class="gestation-select">
                                    <option value="excellent">Excellent</option>
                                    <option value="good">Good</option>
                                    <option value="fair">Fair</option>
                                    <option value="poor">Poor</option>
                                </select>
                            </div>

                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Complications</label>
                                <textarea name="complications" rows="3" placeholder="Any complications during farrowing..." class="gestation-textarea"></textarea>
                            </div>

                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Additional Notes</label>
                                <textarea name="notes" rows="4" placeholder="Any observations about the farrowing process..." class="gestation-textarea"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="gestation-actions-row">
                        <div class="text-sm text-gray-500">All required fields must be completed</div>
                        <div class="gestation-modal-actions">
                            <button type="button" onclick="closeFarrowingModal()" class="gestation-btn secondary">Cancel</button>
                            <button type="submit" class="gestation-btn primary-green">Record Farrowing</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="statusModal" class="gestation-modal-overlay hidden">
        <div class="gestation-modal medium">
            <div class="gestation-modal-head">
                <h3 class="gestation-modal-title">Update Gestation Status</h3>
                <button onclick="closeStatusModal()" class="gestation-modal-close">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="gestation-modal-body">
                <div id="statusInfo" class="gestation-info-box"></div>

                <form id="statusForm" method="POST">
                    @csrf
                    <div class="gestation-form-grid">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status Date</label>
                            <input type="date" name="status_date" required max="{{ now()->toDateString() }}" class="gestation-input">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">New Status</label>
                            <select name="status" required class="gestation-select">
                                <option value="" disabled selected>-- Select Status --</option>
                                <option value="pregnant">🤰 Still Pregnant</option>
                                <option value="lost_pregnancy">💔 Lost Pregnancy</option>
                                <option value="aborted">🚫 Aborted</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes (optional)</label>
                            <textarea name="notes" rows="3" placeholder="Any additional information..." class="gestation-textarea"></textarea>
                        </div>

                        <div class="gestation-modal-actions">
                            <button type="button" onclick="closeStatusModal()" class="gestation-btn secondary">Cancel</button>
                            <button type="submit" class="gestation-btn primary-blue">Update Status</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const gestationFarrowingUrlTemplate = @json(route('gestation.farrowing', ['id' => '__ID__']));
        const gestationUpdateStatusUrlTemplate = @json(route('gestation.update-status', ['id' => '__ID__']));

        function toggleDropdown(id) {
            document.querySelectorAll('[id^="dropdown-menu-"]').forEach(menu => {
                if (menu.id !== `dropdown-menu-${id}`) {
                    menu.classList.add('hidden');
                }
            });
            document.getElementById(`dropdown-menu-${id}`)?.classList.toggle('hidden');
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('[id^="actions-menu-"]') && !e.target.closest('[id^="dropdown-menu-"]')) {
                document.querySelectorAll('[id^="dropdown-menu-"]').forEach(menu => menu.classList.add('hidden'));
            }
        });

        function openFarrowingModal(matingId, femaleEarTag, expectedDate) {
            const modal = document.getElementById('farrowingModal');
            modal.classList.remove('hidden');
            modal.classList.add('show');
            document.getElementById('farrowingForm').action = gestationFarrowingUrlTemplate.replace('__ID__', matingId);
            document.getElementById('farrowingInfo').innerHTML = `
                <div class="text-sm">
                    <div class="font-medium text-gray-900 mb-2">Sow: ${femaleEarTag}</div>
                    <div class="text-gray-600">Expected Farrowing: ${expectedDate}</div>
                </div>
            `;
        }

        function closeFarrowingModal() {
            const modal = document.getElementById('farrowingModal');
            modal.classList.add('hidden');
            modal.classList.remove('show');
        }

        function openStatusModal(matingId, femaleEarTag) {
            const modal = document.getElementById('statusModal');
            modal.classList.remove('hidden');
            modal.classList.add('show');
            document.getElementById('statusForm').action = gestationUpdateStatusUrlTemplate.replace('__ID__', matingId);
            document.getElementById('statusInfo').innerHTML = `
                <div class="text-sm">
                    <div class="font-medium text-gray-900 mb-2">Sow: ${femaleEarTag}</div>
                    <div class="text-gray-600">Update current gestation outcome or follow-up status.</div>
                </div>
            `;
        }

        function closeStatusModal() {
            const modal = document.getElementById('statusModal');
            modal.classList.add('hidden');
            modal.classList.remove('show');
        }

        function autoCalculateTotal() {
            const live = parseInt(document.getElementById('liveBirthsInput')?.value || 0);
            const still = parseInt(document.getElementById('stillbirthsInput')?.value || 0);
            const totalInput = document.getElementById('totalPigletsInput');
            if (totalInput) {
                totalInput.value = live + still;
            }
        }

        function updateTotals() {
            const live = parseInt(document.getElementById('liveBirthsInput')?.value || 0);
            const males = parseInt(document.querySelector('input[name="number_of_males"]')?.value || 0);
            const females = parseInt(document.querySelector('input[name="number_of_females"]')?.value || 0);
            const validation = document.getElementById('genderValidation');

            if (validation) {
                if ((males || females) && (males + females !== live)) {
                    validation.innerHTML = '<span class="text-red-600">Gender totals should equal live births.</span>';
                } else {
                    validation.innerHTML = '';
                }
            }
        }

        function validateFarrowingForm() {
            const total = parseInt(document.getElementById('totalPigletsInput')?.value || 0);
            const live = parseInt(document.getElementById('liveBirthsInput')?.value || 0);
            const still = parseInt(document.getElementById('stillbirthsInput')?.value || 0);

            if (total !== live + still) {
                alert('Total born must equal live births plus stillbirths.');
                return false;
            }

            return true;
        }

        document.getElementById('farrowingModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeFarrowingModal();
        });

        document.getElementById('statusModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeStatusModal();
        });
    </script>
</x-app-layout>