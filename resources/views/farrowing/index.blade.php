<x-app-layout>
    <style>
        .farrowing-page{
            min-height: 100vh;
            padding: 18px 0 40px;
        }

        .farrowing-shell{
            max-width: 1500px;
            margin: 0 auto;
            padding: 0 10px;
        }

        .farrowing-hero{
            position: relative;
            overflow: hidden;
            border-radius: 26px;
            padding: 20px 18px; 
            background: linear-gradient(135deg,rgb(40, 19, 75) 100%);
            color: #fff;
            margin-bottom: 18px;
        }

        .farrowing-hero::before{
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,.16), transparent 28%);
            pointer-events: none;
        }

        .farrowing-hero-inner{
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .farrowing-title{
            margin: 0;
            font-size: clamp(1.85rem, 3vw, 2.8rem);
            line-height: 1.02;
            font-weight: 900;
            letter-spacing: -.04em;
        }

        .farrowing-subtitle{
            margin: 10px 0 0;
            max-width: 760px;
            color: rgba(255,255,255,.90);
            font-size: .94rem;
            line-height: 1.7;
        }

        .farrowing-pill-row{
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .farrowing-pill{
            display: inline-flex;
            align-items: center;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255,255,255,.14);
            border: 1px solid rgba(255,255,255,.16);
            font-size: .78rem;
            font-weight: 800;
            color: #fff;
        }

        .farrowing-stack{
            display: grid;
            gap: 18px;
        }

        .farrowing-stats-grid{
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .farrowing-stats-grid.secondary{
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .farrowing-stat{
            background: rgba(255,255,255,.98);
            border: 1px solid #eddce7;
            border-radius: 22px;
            padding: 16px;
            box-shadow: 0 10px 24px rgba(15,23,42,.04);
        }

        .farrowing-stat-row{
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .farrowing-stat-icon{
            width: 40px;
            height: 40px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .icon-blue{ background:#dbeafe; color:#2563eb; }
        .icon-green{ background:#dcfce7; color:#16a34a; }
        .icon-purple{ background:#f3e8ff; color:#7c3aed; }
        .icon-indigo{ background:#e0e7ff; color:#4338ca; }
        .icon-pink{ background:#fce7f3; color:#db2777; }
        .icon-yellow{ background:#fef9c3; color:#a16207; }
        .icon-red{ background:#fee2e2; color:#dc2626; }

        .farrowing-stat-label{
            font-size: .72rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #94a3b8;
            margin-bottom: 6px;
        }

        .farrowing-stat-value{
            font-size: 1.45rem;
            font-weight: 900;
            color: #0f172a;
            line-height: 1;
        }

        .farrowing-stat-note{
            margin-top: 7px;
            font-size: .76rem;
            color: #64748b;
            line-height: 1.55;
        }

        .farrowing-mini-card{
            background: rgba(255,255,255,.98);
            border: 1px solid #eddce7;
            border-radius: 22px;
            padding: 16px 18px;
            box-shadow: 0 10px 24px rgba(15,23,42,.04);
        }

        .farrowing-mini-title{
            font-size: .9rem;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 10px;
        }

        .farrowing-mini-list{
            display: grid;
            gap: 8px;
        }

        .farrowing-mini-row{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            font-size: .84rem;
            color: #475569;
        }

        .farrowing-mini-row strong{
            color: #0f172a;
        }

        .farrowing-compact-card{
            background: rgba(255,255,255,.98);
            border: 1px solid #e5e7eb;
            border-radius: 22px;
            box-shadow: 0 12px 28px rgba(15,23,42,.05);
            overflow: hidden;
        }

        .farrowing-compact-head{
            padding: 18px 20px 14px;
            border-bottom: 1px solid #eef2f7;
            background: linear-gradient(180deg, #fcfcfd 0%, #ffffff 100%);
        }

        .farrowing-compact-title{
            font-size: 1.05rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
        }

        .farrowing-compact-sub{
            margin-top: 4px;
            font-size: .8rem;
            color: #64748b;
        }

        .farrowing-compact-toolbar{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding: 12px 20px;
            background: #fafafa;
            border-bottom: 1px solid #eef2f7;
        }

        .farrowing-compact-meta{
            font-size: .82rem;
            color: #475569;
            font-weight: 600;
        }

        .farrowing-compact-meta strong{
            color: #0f172a;
        }

        .farrowing-compact-wrap{
            overflow-x: auto;
        }

        .farrowing-compact-table{
            width: 100%;
            min-width: 1120px;
            border-collapse: separate;
            border-spacing: 0;
        }

        .farrowing-compact-table thead th{
            background: #f8fafc;
            padding: 11px 14px;
            font-size: .7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #64748b;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }

        .farrowing-compact-table tbody td{
            padding: 12px 14px;
            vertical-align: top;
            border-bottom: 1px solid #f1f5f9;
            font-size: .84rem;
            color: #0f172a;
            background: #fff;
        }

        .farrowing-compact-table tbody tr:hover td{
            background: #fcfcfd;
        }

        .farrowing-sow{
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .farrowing-sow-avatar{
            width: 34px;
            height: 34px;
            border-radius: 999px;
            background: #fce7f3;
            color: #be185d;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: .9rem;
            font-weight: 700;
        }

        .farrowing-sow-meta{
            display: grid;
            gap: 2px;
            line-height: 1.25;
        }

        .farrowing-sow-tag{
            font-size: .74rem;
            color: #64748b;
            font-weight: 600;
        }

        .farrowing-sow-code{
            font-size: .85rem;
            color: #0f172a;
            font-weight: 800;
        }

        .farrowing-date{
            display: grid;
            gap: 2px;
            line-height: 1.25;
        }

        .farrowing-date-main{
            font-size: .84rem;
            font-weight: 700;
            color: #0f172a;
        }

        .farrowing-date-sub{
            font-size: .72rem;
            color: #64748b;
        }

        .farrowing-litter{
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }

        .farrowing-mini-chip{
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: .71rem;
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
        }

        .chip-live{
            background: #dcfce7;
            color: #166534;
        }

        .chip-still{
            background: #fee2e2;
            color: #b91c1c;
        }

        .chip-death{
            background: #fff1f2;
            color: #be123c;
        }

        .chip-survival{
            background: #eff6ff;
            color: #1d4ed8;
        }

        .farrowing-status-chip{
            display: inline-flex;
            align-items: center;
            padding: 4px 9px;
            border-radius: 999px;
            font-size: .71rem;
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
        }

        .farrowing-actions{
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }

        .farrowing-action-link,
        .farrowing-action-btn{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 700;
            line-height: 1;
            text-decoration: none;
            border: 1px solid transparent;
            transition: .18s ease;
        }

        .farrowing-action-link.view{
            background: #eff6ff;
            color: #1d4ed8;
            border-color: #bfdbfe;
        }

        .farrowing-action-link.view:hover{
            background: #dbeafe;
        }

        .farrowing-action-link.edit{
            background: #ecfdf5;
            color: #166534;
            border-color: #bbf7d0;
        }

        .farrowing-action-link.edit:hover{
            background: #dcfce7;
        }

        .farrowing-action-btn.danger{
            background: #fff1f2;
            color: #be123c;
            border-color: #fecdd3;
        }

        .farrowing-action-btn.danger:hover{
            background: #ffe4e6;
        }

        .farrowing-compact-footer{
            padding: 14px 20px;
            border-top: 1px solid #eef2f7;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .farrowing-empty{
            padding: 52px 18px;
            text-align: center;
        }

        .farrowing-empty svg{
            width: 48px;
            height: 48px;
            color: #94a3b8;
            margin: 0 auto 12px;
        }

        .farrowing-empty-title{
            font-size: .95rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .farrowing-empty-text{
            font-size: .84rem;
            color: #64748b;
            line-height: 1.65;
        }

        .farrowing-death-modal{
            width: 100%;
            max-width: 460px;
            border-radius: 18px;
            border: 1px solid #e5e7eb;
            background: #fff;
            box-shadow: 0 24px 60px rgba(15,23,42,.18);
            overflow: hidden;
        }

        .farrowing-death-modal-head{
            padding: 14px 16px;
            border-bottom: 1px solid #eef2f7;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            background: #fcfcfd;
        }

        .farrowing-death-modal-title{
            font-size: .9rem;
            font-weight: 800;
            color: #0f172a;
        }

        .farrowing-death-modal-body{
            padding: 16px;
        }

        @media (max-width: 1200px){
            .farrowing-stats-grid{
                grid-template-columns: repeat(2, minmax(0,1fr));
            }

            .farrowing-stats-grid.secondary{
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px){
            .farrowing-shell{
                padding: 0 12px;
            }

            .farrowing-hero{
                padding: 22px 18px;
                border-radius: 24px;
            }

            .farrowing-stats-grid{
                grid-template-columns: 1fr;
            }

            .farrowing-compact-head,
            .farrowing-compact-toolbar,
            .farrowing-compact-footer{
                padding-left: 16px;
                padding-right: 16px;
            }

            .farrowing-compact-table thead th,
            .farrowing-compact-table tbody td{
                padding-left: 12px;
                padding-right: 12px;
            }
        }
    </style>

    <div class="farrowing-page">
        <div class="farrowing-shell">
            <div class="farrowing-hero">
                <div class="farrowing-hero-inner">
                    <div>
                        <h1 class="farrowing-title">Farrowing Records</h1>
                        <p class="farrowing-subtitle">
                            Complete farrowing history, litter outcomes, sow condition, and piglet survival records in one compact management view.
                        </p>
                    </div>

                    <div class="farrowing-pill-row">
                        <span class="farrowing-pill">Compact tables</span>
                        <span class="farrowing-pill">Birth history</span>
                        <span class="farrowing-pill">Piglet survival tracking</span>
                    </div>
                </div>
            </div>

            @php
                $avgLitterSize = \App\Models\Farrowing::avg('live_births');
                $totalLive = \App\Models\Farrowing::sum('live_births');
                $totalBorn = \App\Models\Farrowing::sum('total_piglets_born');
                $survivalRate = $totalBorn > 0 ? round($totalLive / $totalBorn * 100, 1) : 0;
                $weaningPct = $totalLive > 0 ? round(\App\Models\Weaning::count() / $totalLive * 100, 1) : 0;
                $avgStillborns = \App\Models\Farrowing::avg('stillbirths');
            @endphp

            <div class="farrowing-stack">
                <div class="farrowing-stats-grid">
                    <div class="farrowing-stat">
                        <div class="farrowing-stat-row">
                            <div class="farrowing-stat-icon icon-blue">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <div>
                                <div class="farrowing-stat-label">Total Farrowings</div>
                                <div class="farrowing-stat-value">{{ $stats['total_farrowings'] }}</div>
                                <div class="farrowing-stat-note">All recorded farrowing events</div>
                            </div>
                        </div>
                    </div>

                    <div class="farrowing-stat">
                        <div class="farrowing-stat-row">
                            <div class="farrowing-stat-icon icon-green">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a4 4 0 118 0v4m-4 8a4 4 0 11-8 0v-1a4 4 0 014-4h4a4 4 0 014 4v1a4 4 0 11-8 0z"/>
                                </svg>
                            </div>
                            <div>
                                <div class="farrowing-stat-label">This Month</div>
                                <div class="farrowing-stat-value">{{ $stats['farrowings_this_month'] }}</div>
                                <div class="farrowing-stat-note">Farrowings recorded this month</div>
                            </div>
                        </div>
                    </div>

                    <div class="farrowing-stat">
                        <div class="farrowing-stat-row">
                            <div class="farrowing-stat-icon icon-purple">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                            </div>
                            <div>
                                <div class="farrowing-stat-label">Avg Litter Size</div>
                                <div class="farrowing-stat-value">
                                    @if($stats['average_litter_size'])
                                        {{ number_format($stats['average_litter_size'], 1) }}
                                    @else
                                        N/A
                                    @endif
                                </div>
                                <div class="farrowing-stat-note">Average live births per farrowing</div>
                            </div>
                        </div>
                    </div>

                    <div class="farrowing-stat">
                        <div class="farrowing-stat-row">
                            <div class="farrowing-stat-icon icon-indigo">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                            </div>
                            <div>
                                <div class="farrowing-stat-label">Survival Rate</div>
                                <div class="farrowing-stat-value">
                                    @if($stats['average_survival_rate'])
                                        {{ number_format($stats['average_survival_rate'], 1) }}%
                                    @else
                                        N/A
                                    @endif
                                </div>
                                <div class="farrowing-stat-note">Average litter survival performance</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="farrowing-stats-grid secondary">
                    <div class="farrowing-mini-card">
                        <div class="farrowing-mini-title">📊 Farrowing Types</div>
                        <div class="farrowing-mini-list">
                            <div class="farrowing-mini-row">
                                <span>Natural</span>
                                <strong class="text-green-600">{{ $stats['natural_farrowings'] }}</strong>
                            </div>
                            <div class="farrowing-mini-row">
                                <span>Assisted</span>
                                <strong class="text-yellow-600">{{ $stats['assisted_farrowings'] }}</strong>
                            </div>
                            <div class="farrowing-mini-row">
                                <span>Cesarean</span>
                                <strong class="text-red-600">{{ $stats['cesarean_farrowings'] }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="farrowing-mini-card">
                        <div class="farrowing-mini-title">🏥 Sow Conditions</div>
                        <div class="farrowing-mini-list">
                            <div class="farrowing-mini-row">
                                <span>Excellent</span>
                                <strong class="text-green-600">{{ $stats['excellent_condition'] }}</strong>
                            </div>
                            <div class="farrowing-mini-row">
                                <span>Good</span>
                                <strong class="text-blue-600">{{ $stats['good_condition'] }}</strong>
                            </div>
                            <div class="farrowing-mini-row">
                                <span>Fair</span>
                                <strong class="text-yellow-600">{{ $stats['fair_condition'] }}</strong>
                            </div>
                            <div class="farrowing-mini-row">
                                <span>Poor</span>
                                <strong class="text-red-600">{{ $stats['poor_condition'] }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Farrowing Records Table -->
                <div class="farrowing-compact-card">
                    <div class="farrowing-compact-head">
                        <h2 class="farrowing-compact-title">Farrowing Records ({{ $farrowingRecords->total() }})</h2>
                        <p class="farrowing-compact-sub">Complete birth and farrowing history</p>
                    </div>

                    <div class="farrowing-compact-toolbar">
                        <div class="farrowing-compact-meta">
                            @if($farrowingRecords->count() > 0)
                                <strong>{{ $farrowingRecords->total() }}</strong> total records
                            @else
                                No farrowing records available
                            @endif
                        </div>
                    </div>

                    @if($farrowingRecords->count() > 0)
                        <div class="farrowing-compact-wrap">
                            <table class="farrowing-compact-table" id="farrowing-records-table">
                                <thead>
                                    <tr>
                                        <th>Sow</th>
                                        <th>Farrowing Date</th>
                                        <th>Litter Details</th>
                                        <th>Type</th>
                                        <th>Difficulty</th>
                                        <th>Sow Condition</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="table-body">
                                    @foreach($farrowingRecords as $farrowing)
                                        <tr>
                                            <td>
                                                <div class="farrowing-sow">
                                                    <div class="farrowing-sow-avatar">🐷</div>
                                                    <div class="farrowing-sow-meta">
                                                        <div class="farrowing-sow-tag">{{ $farrowing->sow->ear_tag_number }}</div>
                                                        <div class="farrowing-sow-code">{{ $farrowing->sow->code }}</div>
                                                    </div>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="farrowing-date">
                                                    <div class="farrowing-date-main">{{ $farrowing->farrowing_date->format('M d, Y') }}</div>
                                                    @if($farrowing->farrowing_time)
                                                        <div class="farrowing-date-sub">{{ $farrowing->farrowing_time->format('H:i') }}</div>
                                                    @endif
                                                </div>
                                            </td>

                                            <td>
                                                <div class="farrowing-litter">
                                                    <span class="farrowing-mini-chip chip-live">
                                                        {{ $farrowing->current_litter_size }} live
                                                    </span>

                                                    @if($farrowing->stillbirths > 0)
                                                        <span class="farrowing-mini-chip chip-still">
                                                            {{ $farrowing->stillbirths }} still
                                                        </span>
                                                    @endif

                                                    @if($farrowing->total_piglet_deaths > 0)
                                                        <span class="farrowing-mini-chip chip-death">
                                                            {{ $farrowing->total_piglet_deaths }} deaths
                                                        </span>
                                                    @endif

                                                    <span class="farrowing-mini-chip chip-survival">
                                                        {{ number_format(($farrowing->total_piglets_born > 0) ? ($farrowing->current_litter_size / $farrowing->total_piglets_born) * 100 : 0, 1) }}% survival
                                                    </span>
                                                </div>
                                            </td>

                                            <td>
                                                <span class="farrowing-status-chip {{ $farrowing->farrowing_type_badge }}">
                                                    {{ ucfirst($farrowing->farrowing_type) }}
                                                </span>
                                            </td>

                                            <td>
                                                <span class="farrowing-status-chip {{ $farrowing->farrowing_difficulty_badge }}">
                                                    {{ ucfirst($farrowing->farrowing_difficulty) }}
                                                </span>
                                            </td>

                                            <td>
                                                <span class="farrowing-status-chip {{ $farrowing->sow_condition_badge }}">
                                                    {{ ucfirst($farrowing->sow_condition) }}
                                                </span>
                                            </td>

                                            <td>
                                                <div x-data="{ open: false }">
                                                    <div class="farrowing-actions">
                                                        <a href="{{ route('farrowing.show', $farrowing->id) }}" class="farrowing-action-link view">
                                                            View
                                                        </a>

                                                        <a href="{{ route('farrowing.edit', $farrowing->id) }}" class="farrowing-action-link edit">
                                                            Edit
                                                        </a>

                                                        <button type="button" @click="open = true" class="farrowing-action-btn danger">
                                                            Piglet Death
                                                        </button>
                                                    </div>

                                                    <!-- Modal -->
                                                    <div x-cloak x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                                                        <div class="absolute inset-0 bg-black/40" @click="open = false"></div>

                                                        <div class="relative farrowing-death-modal">
                                                            <div class="farrowing-death-modal-head">
                                                                <h3 class="farrowing-death-modal-title">Record Piglet Death</h3>
                                                                <button type="button" class="text-gray-400 hover:text-gray-600" @click="open = false">✕</button>
                                                            </div>

                                                            <div class="farrowing-death-modal-body">
                                                                <form method="POST" action="{{ route('farrowing.piglet-deaths.record', $farrowing) }}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                                    @csrf
                                                                    <div>
                                                                        <label class="block text-xs text-gray-600">Date</label>
                                                                        <input type="date" name="death_date" class="mt-1 block w-full rounded-md border-gray-300 focus:border-red-500 focus:ring-red-500" required>
                                                                    </div>
                                                                    <div>
                                                                        <label class="block text-xs text-gray-600">Count</label>
                                                                        <input type="number" min="1" name="count" class="mt-1 block w-full rounded-md border-gray-300 focus:border-red-500 focus:ring-red-500" value="1" required>
                                                                    </div>
                                                                    <div class="md:col-span-2">
                                                                        <label class="block text-xs text-gray-600">Cause (optional)</label>
                                                                        <input type="text" name="cause" class="mt-1 block w-full rounded-md border-gray-300 focus:border-red-500 focus:ring-red-500" placeholder="e.g., crushing">
                                                                    </div>
                                                                    <div class="md:col-span-2">
                                                                        <label class="block text-xs text-gray-600">Notes (optional)</label>
                                                                        <textarea name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 focus:border-red-500 focus:ring-red-500" placeholder="Additional details"></textarea>
                                                                    </div>
                                                                    <div class="md:col-span-2 flex items-center justify-end space-x-2">
                                                                        <button type="button" @click="open = false" class="px-3 py-1.5 rounded-md border text-xs text-gray-700 hover:bg-gray-50">
                                                                            Cancel
                                                                        </button>
                                                                        <button type="submit" class="px-3 py-1.5 rounded-md bg-red-600 text-white hover:bg-red-700 text-xs">
                                                                            Save
                                                                        </button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- End Modal -->
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach

                                    <tr id="no-search-results-row" style="display: none;">
                                        <td colspan="7">
                                            <div class="farrowing-empty">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <div class="farrowing-empty-title">No results found</div>
                                                <div class="farrowing-empty-text">Try adjusting your search term.</div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="farrowing-compact-footer">
                            <div class="text-sm text-gray-700">
                                Showing {{ $farrowingRecords->firstItem() }} to {{ $farrowingRecords->lastItem() }} of {{ $farrowingRecords->total() }} results
                            </div>
                            <div>
                                <x-simple-pagination :paginator="$farrowingRecords" />
                            </div>
                        </div>
                    @else
                        <div class="farrowing-empty" id="initial-empty-state">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <div class="farrowing-empty-title">No farrowing records</div>
                            <div class="farrowing-empty-text">Farrowing records will appear here once births are recorded.</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>