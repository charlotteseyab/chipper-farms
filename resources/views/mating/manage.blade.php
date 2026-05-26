<x-app-layout>
    <style>
        .mating-manage-page{
            min-height: 100vh;
            padding: 18px 0 40px;
        }

        .mating-manage-shell{
            max-width: 1520px;
            margin: 0 auto;
            padding: 0 16px;
        }

        .mating-manage-hero{
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 26px 28px;
            background: linear-gradient(135deg, #be185d 100%);
            color: #fff;
            margin-bottom: 18px;
        }

        .mating-manage-hero::before{
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,.16), transparent 28%),
                radial-gradient(circle at bottom left, rgba(255,255,255,.08), transparent 24%);
            pointer-events: none;
        }

        .mating-manage-hero-inner{
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .mating-manage-title{
            margin: 0;
            font-size: clamp(1.9rem, 3vw, 2.9rem);
            line-height: 1.02;
            font-weight: 900;
            letter-spacing: -.04em;
        }

        .mating-manage-subtitle{
            margin: 10px 0 0;
            max-width: 760px;
            color: rgba(255,255,255,.90);
            font-size: .95rem;
            line-height: 1.75;
        }

        .mating-manage-actions{
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .mating-top-btn{
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

        .mating-top-btn:hover{
            background: rgba(255,255,255,.20);
            transform: translateY(-1px);
        }

        .mating-manage-stack{
            display: grid;
            gap: 18px;
        }

        .mating-card{
            background: rgba(255,255,255,.97);
            border: 1px solid #ecd7e5;
            border-radius: 28px;
            box-shadow: 0 14px 34px rgba(15,23,42,.06);
            overflow: hidden;
        }

        .mating-card-head{
            padding: 22px 24px 14px;
            border-bottom: 1px solid #f2e7ee;
            background: linear-gradient(180deg, rgba(253,242,248,.85) 0%, rgba(255,255,255,.98) 100%);
        }

        .mating-card-title{
            margin: 0;
            font-size: 1.04rem;
            font-weight: 900;
            color: #0f172a;
        }

        .mating-card-sub{
            margin-top: 4px;
            font-size: .82rem;
            color: #64748b;
        }

        .mating-card-body{
            padding: 20px 24px 24px;
        }

        .mating-stats-grid{
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 14px;
        }

        .mating-stats-grid.secondary{
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .mating-stat{
            background: rgba(255,255,255,.96);
            border: 1px solid #eadbe5;
            border-radius: 22px;
            padding: 16px;
            box-shadow: 0 10px 24px rgba(15,23,42,.04);
        }

        .mating-stat-row{
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .mating-stat-icon{
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
        .icon-gray{ background:#f1f5f9; color:#475569; }
        .icon-orange{ background:#ffedd5; color:#ea580c; }
        .icon-pink{ background:#fce7f3; color:#db2777; }
        .icon-purple{ background:#f3e8ff; color:#7c3aed; }

        .mating-stat-label{
            font-size: .73rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #94a3b8;
            margin-bottom: 6px;
        }

        .mating-stat-value{
            font-size: 1.55rem;
            font-weight: 900;
            color: #0f172a;
            line-height: 1;
        }

        .mating-stat-note{
            margin-top: 7px;
            font-size: .76rem;
            color: #64748b;
            line-height: 1.55;
        }

        .mating-table-toolbar{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            padding: 16px 24px;
            background: #fdf7fb;
            border-bottom: 1px solid #f2e7ee;
        }

        .mating-filter-wrap{
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .mating-filter-label{
            font-size: .84rem;
            font-weight: 800;
            color: #475569;
        }

        .mating-select{
            border: 1px solid #d1d5db;
            border-radius: 14px;
            background: #fff;
            padding: 10px 12px;
            font-size: .86rem;
            color: #0f172a;
            outline: none;
            transition: .2s ease;
        }

        .mating-select:focus{
            border-color: #db2777;
            box-shadow: 0 0 0 4px rgba(219,39,119,.10);
        }

        .mating-table-wrap{
            overflow-x: auto;
        }

        .mating-table{
            width: 100%;
            min-width: 1320px;
            border-collapse: separate;
            border-spacing: 0;
        }

        .mating-table thead th{
            background: #fdf7fb;
            padding: 14px 18px;
            font-size: .73rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #7c3aed;
            text-align: left;
            border-bottom: 1px solid #f2e7ee;
        }

        .mating-table tbody td{
            padding: 16px 18px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
            font-size: .88rem;
            color: #0f172a;
            background: rgba(255,255,255,.98);
        }

        .mating-table tbody tr:hover td{
            background: #fffafc;
        }

        .mating-row-overdue td{
            background: #fff5f5 !important;
        }

        .mating-pair{
            display: grid;
            gap: 4px;
        }

        .mating-pair-tags{
            font-size: .8rem;
            color: #64748b;
        }

        .mating-pair-codes{
            font-weight: 900;
            color: #0f172a;
        }

        .mating-note{
            max-width: 260px;
            color: #475569;
            line-height: 1.6;
        }

        .mating-chip{
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: .74rem;
            font-weight: 800;
            line-height: 1;
        }

        .mating-action-stack{
            display: grid;
            gap: 8px;
        }

        .mating-action-link,
        .mating-action-btn{
            display: inline-flex;
            align-items: center;
            justify-content: flex-start;
            gap: 8px;
            padding: 9px 12px;
            border-radius: 14px;
            border: 1px solid #d1d5db;
            background: #fff;
            font-size: .82rem;
            font-weight: 800;
            text-decoration: none;
            transition: .2s ease;
            cursor: pointer;
        }

        .mating-action-link.complete,
        .mating-action-btn.complete{
            color: #166534;
        }

        .mating-action-link.complete:hover,
        .mating-action-btn.complete:hover{
            background: #f0fdf4;
            border-color: #bbf7d0;
        }

        .mating-action-link.check,
        .mating-action-btn.check{
            color: #1d4ed8;
        }

        .mating-action-link.check:hover,
        .mating-action-btn.check:hover{
            background: #eff6ff;
            border-color: #bfdbfe;
        }

        .mating-action-link.cancel,
        .mating-action-btn.cancel{
            color: #b91c1c;
        }

        .mating-action-link.cancel:hover,
        .mating-action-btn.cancel:hover{
            background: #fef2f2;
            border-color: #fecaca;
        }

        .mating-action-muted{
            font-size: .8rem;
            color: #94a3b8;
            padding: 6px 0;
        }

        .mating-empty{
            padding: 56px 18px;
            text-align: center;
        }

        .mating-empty-icon{
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            color: #94a3b8;
        }

        .mating-empty-title{
            font-size: 1.08rem;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .mating-empty-text{
            max-width: 620px;
            margin: 0 auto;
            color: #64748b;
            line-height: 1.75;
            font-size: .92rem;
        }

        .mating-footer{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            padding: 16px 24px;
            border-top: 1px solid #f2e7ee;
            background: #fff;
        }

        .mating-footer-text{
            font-size: .84rem;
            color: #475569;
            font-weight: 700;
        }

        .mating-feature-grid{
            display: grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap: 16px;
        }

        .mating-feature{
            display: flex;
            align-items: flex-start;
            gap: 14px;
            border: 1px solid #f2e7ee;
            border-radius: 20px;
            background: #fff;
            padding: 16px;
        }

        .mating-feature-icon{
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .mating-modal-overlay{
            position: fixed;
            inset: 0;
            background: rgba(15,23,42,.55);
            backdrop-filter: blur(2px);
            display: none;
            z-index: 50;
            overflow-y: auto;
            padding: 20px;
        }

        .mating-modal-overlay.show{
            display: block;
        }

        .mating-modal{
            position: relative;
            max-width: 430px;
            margin: 60px auto;
            background: #fff;
            border-radius: 24px;
            border: 1px solid #ece7ef;
            box-shadow: 0 26px 60px rgba(15,23,42,.18);
            overflow: hidden;
        }

        .mating-modal-head{
            padding: 20px 22px 14px;
            border-bottom: 1px solid #f2e7ee;
            background: linear-gradient(180deg, rgba(253,242,248,.88) 0%, rgba(255,255,255,.98) 100%);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .mating-modal-title{
            font-size: 1rem;
            font-weight: 900;
            color: #0f172a;
        }

        .mating-modal-close{
            border: none;
            background: transparent;
            color: #94a3b8;
            cursor: pointer;
        }

        .mating-modal-body{
            padding: 20px 22px 22px;
        }

        .mating-modal-info{
            background: #f8fafc;
            border: 1px solid #eef2f7;
            border-radius: 16px;
            padding: 14px 16px;
            margin-bottom: 16px;
        }

        .mating-modal-form{
            display: grid;
            gap: 14px;
        }

        .mating-modal-note{
            border-radius: 16px;
            padding: 12px 14px;
            font-size: .83rem;
            line-height: 1.6;
        }

        .mating-modal-note.blue{
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }

        .mating-modal-actions{
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
            padding-top: 6px;
        }

        .mating-modal-btn{
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

        .mating-modal-btn.secondary{
            color: #475569;
        }

        .mating-modal-btn.primary-green{
            background: #16a34a;
            border-color: #16a34a;
            color: #fff;
        }

        .mating-modal-btn.primary-pink{
            background: #db2777;
            border-color: #db2777;
            color: #fff;
        }

        @media (max-width: 1200px){
            .mating-stats-grid{
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .mating-stats-grid.secondary{
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px){
            .mating-manage-shell{
                padding: 0 12px;
            }

            .mating-manage-hero{
                padding: 22px 18px;
                border-radius: 24px;
            }

            .mating-card-head,
            .mating-card-body,
            .mating-table-toolbar,
            .mating-footer{
                padding-left: 18px;
                padding-right: 18px;
            }

            .mating-stats-grid,
            .mating-feature-grid{
                grid-template-columns: 1fr;
            }

            .mating-manage-actions{
                width: 100%;
            }

            .mating-manage-actions > *{
                flex: 1 1 100%;
            }

            .mating-modal{
                margin: 20px auto;
            }
        }
    </style>

    <div class="mating-manage-page">
        <div class="mating-manage-shell">
            <div class="mating-manage-hero">
                <div class="mating-manage-hero-inner">
                    <div>
                        <h1 class="mating-manage-title">Manage Matings</h1>
                        <p class="mating-manage-subtitle">
                            Review planned and completed matings, monitor pregnancy follow-ups, and take action on breeding records from one clean management screen.
                        </p>
                    </div>

                    <div class="mating-manage-actions">
                        <a href="{{ route('mating.index') }}" class="mating-top-btn">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            Plan New Mating
                        </a>
                    </div>
                </div>
            </div>

            <div class="mating-manage-stack">
                <div class="mating-stats-grid">
                    <div class="mating-stat">
                        <div class="mating-stat-row">
                            <div class="mating-stat-icon icon-blue">
                                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 21.5a9.5 9.5 0 0 1-9.5-9.5c0-1.8.6-3.5 1.7-4.9l-.7-1.4c-.1-.3-.2-.5-.2-.8V2.5c0-.8.7-1.5 1.5-1.5h1.5c.8 0 1.5.7 1.5 1.5v.5h10v-.5c0-.8.7-1.5 1.5-1.5h1.5c.8 0 1.5.7 1.5 1.5v2.3c0 .3-.1.6-.2.8l-.7 1.4c1.1 1.4 1.7 3.1 1.7 4.9a9.5 9.5 0 0 1-9.5 9.5Z"/>
                                    <circle cx="15.5" cy="8.5" r=".5" fill="currentColor"/>
                                    <circle cx="8.5" cy="8.5" r=".5" fill="currentColor"/>
                                    <path d="M11 15.5h2c1.7 0 2 1.3 2 1.5v.5c0 .3-.2.5-.5.5h-5c-.3 0-.5-.2-.5-.5v-.5c0-.2.3-1.5 2-1.5Z"/>
                                </svg>
                            </div>
                            <div>
                                <div class="mating-stat-label">Total Matings</div>
                                <div class="mating-stat-value">{{ $stats['total'] }}</div>
                                <div class="mating-stat-note">All recorded mating plans and outcomes</div>
                            </div>
                        </div>
                    </div>

                    <div class="mating-stat">
                        <div class="mating-stat-row">
                            <div class="mating-stat-icon icon-yellow">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div>
                                <div class="mating-stat-label">Planned</div>
                                <div class="mating-stat-value">{{ $stats['planned'] }}</div>
                                <div class="mating-stat-note">Still awaiting completion</div>
                            </div>
                        </div>
                    </div>

                    <div class="mating-stat">
                        <div class="mating-stat-row">
                            <div class="mating-stat-icon icon-green">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <div class="mating-stat-label">Completed</div>
                                <div class="mating-stat-value">{{ $stats['completed'] }}</div>
                                <div class="mating-stat-note">Actual mating recorded</div>
                            </div>
                        </div>
                    </div>

                    <div class="mating-stat">
                        <div class="mating-stat-row">
                            <div class="mating-stat-icon icon-red">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <div class="mating-stat-label">Overdue</div>
                                <div class="mating-stat-value">{{ $stats['overdue'] }}</div>
                                <div class="mating-stat-note">Planned date already passed</div>
                            </div>
                        </div>
                    </div>

                    <div class="mating-stat">
                        <div class="mating-stat-row">
                            <div class="mating-stat-icon icon-gray">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </div>
                            <div>
                                <div class="mating-stat-label">Cancelled</div>
                                <div class="mating-stat-value">{{ $stats['cancelled'] }}</div>
                                <div class="mating-stat-note">Cancelled breeding plans</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mating-stats-grid secondary">
                    <div class="mating-stat">
                        <div class="mating-stat-row">
                            <div class="mating-stat-icon icon-orange">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <div class="mating-stat-label">Pregnancy Checks Due</div>
                                <div class="mating-stat-value">{{ $stats['pregnancy_checks_due'] }}</div>
                                <div class="mating-stat-note">
                                    @if($stats['pregnancy_checks_overdue'] > 0)
                                        {{ $stats['pregnancy_checks_overdue'] }} overdue
                                    @else
                                        {{ config('breeding.cycle.pregnancy_check_days') }}+ days after mating
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mating-stat">
                        <div class="mating-stat-row">
                            <div class="mating-stat-icon icon-pink">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                            </div>
                            <div>
                                <div class="mating-stat-label">Confirmed Pregnant</div>
                                <div class="mating-stat-value">{{ $stats['pregnant'] }}</div>
                                <div class="mating-stat-note">
                                    @if($stats['not_pregnant'] > 0)
                                        {{ $stats['not_pregnant'] }} not pregnant
                                    @else
                                        Pregnancy confirmed
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mating-stat">
                        <div class="mating-stat-row">
                            <div class="mating-stat-icon icon-purple">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                </svg>
                            </div>
                            <div>
                                <div class="mating-stat-label">Upcoming Matings</div>
                                <div class="mating-stat-value">{{ $stats['upcoming'] }}</div>
                                <div class="mating-stat-note">Next 7 days</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mating-card">
                    <div class="mating-card-head">
                        <h3 class="mating-card-title">All Matings</h3>
                        <div class="mating-card-sub">Track breeding plans, pregnancy follow-ups, and breeding outcomes.</div>
                    </div>

                    <div class="mating-table-toolbar">
                        <div class="text-sm font-semibold text-slate-700">
                            @if($matings->count() > 0)
                                {{ $matings->total() }} records in this dataset
                            @else
                                No mating records found
                            @endif
                        </div>

                        <div class="mating-filter-wrap">
                            <label for="status-filter" class="mating-filter-label">Filter by Status</label>
                            <select id="status-filter" class="mating-select">
                                <option value="">All Statuses</option>
                                <option value="planned">Planned</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                    </div>

                    @if($matings->count() > 0)
                        <div class="mating-table-wrap">
                            <table class="mating-table">
                                <thead>
                                    <tr>
                                        <th>Breeding Pair</th>
                                        <th>Mating Date</th>
                                        <th>Status</th>
                                        <th>Pregnancy</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($matings as $mating)
                                        <tr class="{{ $mating->is_overdue ? 'mating-row-overdue' : '' }}">
                                            <td>
                                                <div class="mating-pair">
                                                    <div class="mating-pair-tags">
                                                        {{ $mating->male->ear_tag_number }} × {{ $mating->female->ear_tag_number }}
                                                    </div>
                                                    <div class="mating-pair-codes">
                                                        {{ $mating->male->code }} × {{ $mating->female->code }}
                                                    </div>

                                                    @if($mating->status === 'completed')
                                                        @if($mating->pregnancy_status === 'not_pregnant')
                                                            <div class="text-xs text-green-600 mt-1">✅ Both pigs now available for breeding</div>
                                                        @elseif($mating->pregnancy_status === 'pending')
                                                            <div class="text-xs text-orange-600 mt-1">♂️ Male available, ♀️ Female awaiting pregnancy check</div>
                                                        @elseif($mating->pregnancy_status === 'pregnant')
                                                            <div class="text-xs text-orange-600 mt-1">♂️ Male available, ♀️ Female pregnant</div>
                                                        @elseif($mating->pregnancy_status === 'uncertain')
                                                            <div class="text-xs text-orange-600 mt-1">♂️ Male available, ♀️ Female pregnancy uncertain</div>
                                                        @endif
                                                    @elseif($mating->status === 'planned')
                                                        <div class="text-xs text-blue-600 mt-1">🔒 Both pigs reserved for planned mating</div>
                                                    @endif
                                                </div>
                                            </td>

                                            <td>
                                                @if($mating->status === 'completed' && $mating->actual_date)
                                                    <div class="font-semibold text-slate-900">{{ $mating->actual_date->format('M d, Y') }}</div>
                                                    <div class="text-xs text-slate-500">{{ $mating->days_since_mating }} days ago</div>
                                                @else
                                                    <div class="font-semibold text-slate-900">{{ $mating->planned_date->format('M d, Y') }}</div>
                                                    @if($mating->is_overdue)
                                                        <div class="text-xs text-red-600">{{ abs($mating->days_until_planned) }} days overdue</div>
                                                    @elseif($mating->days_until_planned > 0)
                                                        <div class="text-xs text-slate-500">{{ $mating->days_until_planned }} days to go</div>
                                                    @elseif($mating->days_until_planned == 0)
                                                        <div class="text-xs text-blue-600">Today</div>
                                                    @endif
                                                @endif
                                            </td>

                                            <td>
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $mating->status_badge }}">
                                                    {{ $mating->status_icon }} {{ ucfirst($mating->status) }}
                                                </span>
                                            </td>

                                            <td>
                                                @if($mating->status === 'completed')
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $mating->pregnancy_status_badge }}">
                                                        {{ $mating->pregnancy_status_icon }} {{ ucfirst(str_replace('_', ' ', $mating->pregnancy_status)) }}
                                                    </span>
                                                    @if($mating->pregnancy_check_due)
                                                        <div class="text-xs text-orange-600 mt-1">Check due</div>
                                                    @elseif($mating->pregnancy_check_overdue)
                                                        <div class="text-xs text-red-600 mt-1">Check overdue!</div>
                                                    @endif
                                                @else
                                                    <span class="text-xs text-gray-400">N/A</span>
                                                @endif
                                            </td>

                                            <td>
                                                <div class="mating-note">
                                                    {{ $mating->notes ?: 'No notes' }}
                                                </div>
                                            </td>

                                            <td>
                                                <div class="mating-action-stack">
                                                    @if($mating->status === 'planned')
                                                        <button
                                                            onclick="openCompleteMatingModal({{ $mating->id }}, '{{ $mating->male->ear_tag_number }}', '{{ $mating->female->ear_tag_number }}', '{{ $mating->planned_date->format('Y-m-d') }}')"
                                                            class="mating-action-btn complete"
                                                            type="button">
                                                            Complete
                                                        </button>

                                                        <form action="{{ route('mating.cancel', $mating->id) }}" method="POST">
                                                            @csrf
                                                            <button
                                                                type="submit"
                                                                onclick="return confirm('Are you sure you want to cancel this planned mating? This action cannot be undone.')"
                                                                class="mating-action-btn cancel">
                                                                Cancel
                                                            </button>
                                                        </form>
                                                    @elseif($mating->status === 'completed' && $mating->pregnancy_status === 'pending')
                                                        <button
                                                            onclick="openPregnancyCheckModal({{ $mating->id }}, '{{ $mating->male->ear_tag_number }}', '{{ $mating->female->ear_tag_number }}', '{{ $mating->actual_date->format('M d, Y') }}', {{ $mating->days_since_mating }})"
                                                            class="mating-action-btn check"
                                                            type="button">
                                                            Record Check
                                                        </button>
                                                    @elseif($mating->status === 'cancelled')
                                                        <div class="mating-action-muted">Cancelled</div>
                                                    @elseif($mating->pregnancy_status !== 'pending')
                                                        <div class="mating-action-muted">Check completed</div>
                                                    @else
                                                        <div class="mating-action-muted">No actions</div>
                                                    @endif

                                                    <form method="POST" action="{{ route('mating.destroy', $mating->id) }}" onsubmit="return confirm('Are you sure you want to delete this mating?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="mating-action-btn cancel">
                                                            Delete Mating
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mating-footer">
                            <div class="mating-footer-text">
                                Showing {{ $matings->firstItem() }} to {{ $matings->lastItem() }} of {{ $matings->total() }} results
                            </div>
                            <div>
                                <x-simple-pagination :paginator="$matings" />
                            </div>
                        </div>
                    @else
                        <div class="mating-empty">
                            <svg class="mating-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <div class="mating-empty-title">No matings planned</div>
                            <div class="mating-empty-text">
                                Get started by planning your first mating and begin tracking breeding activity from one place.
                            </div>
                            <div class="mt-6">
                                <a href="{{ route('mating.index') }}" class="mating-top-btn" style="background:#db2777;border-color:#db2777;">
                                    Plan First Mating
                                </a>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="mating-card">
                    <div class="mating-card-head">
                        <h3 class="mating-card-title">What You Can Do Now</h3>
                        <div class="mating-card-sub">Quick pointers for how this section supports daily breeding work.</div>
                    </div>
                    <div class="mating-card-body">
                        <div class="mating-feature-grid">
                            <div class="mating-feature">
                                <div class="mating-feature-icon icon-pink">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800 mb-2">Plan Matings</h4>
                                    <p class="text-gray-600 text-sm mb-3">Use the mating planner to select breeding pairs and check for inbreeding risks with real-time compatibility analysis.</p>
                                    <a href="{{ route('mating.index') }}" class="text-pink-600 hover:text-pink-700 font-medium text-sm">
                                        Go to Mating Planner →
                                    </a>
                                </div>
                            </div>

                            <div class="mating-feature">
                                <div class="mating-feature-icon icon-green">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800 mb-2">Compatibility Checking</h4>
                                    <p class="text-gray-600 text-sm mb-3">Advanced inbreeding detection analyzes relationships and provides breeding recommendations before pairings are confirmed.</p>
                                    <span class="text-green-600 font-medium text-sm">✓ Already Available</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Complete Mating Modal -->
    <div id="completeMatingModal" class="mating-modal-overlay">
        <div class="mating-modal">
            <div class="mating-modal-head">
                <h3 class="mating-modal-title">Complete Mating</h3>
                <button onclick="closeCompleteMatingModal()" class="mating-modal-close">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="mating-modal-body">
                <div id="completeMatingInfo" class="mating-modal-info">
                    <!-- Mating info will be populated by JavaScript -->
                </div>

                <form id="completeMatingForm" method="POST" class="mating-modal-form">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Actual Mating Date</label>
                        <input type="date" name="actual_date" required max="{{ now()->toDateString() }}" class="mating-select w-full">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes (optional)</label>
                        <textarea name="outcome_notes" rows="3" placeholder="Any observations about the mating process..." class="mating-select w-full" style="min-height:90px;"></textarea>
                    </div>

                    <div class="mating-modal-note blue">
                        <strong>Next Step:</strong> Pregnancy check will be due 14 days after the actual mating date.
                    </div>

                    <div class="mating-modal-actions">
                        <button type="button" onclick="closeCompleteMatingModal()" class="mating-modal-btn secondary">
                            Cancel
                        </button>
                        <button type="submit" class="mating-modal-btn primary-green">
                            Complete Mating
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Pregnancy Check Modal -->
    <div id="pregnancyCheckModal" class="mating-modal-overlay">
        <div class="mating-modal">
            <div class="mating-modal-head">
                <h3 class="mating-modal-title">Record Pregnancy Check</h3>
                <button onclick="closePregnancyCheckModal()" class="mating-modal-close">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="mating-modal-body">
                <div id="matingInfo" class="mating-modal-info">
                    <!-- Mating info will be populated by JavaScript -->
                </div>

                <form id="pregnancyCheckForm" method="POST" class="mating-modal-form">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Check Date</label>
                        <input type="date" name="pregnancy_check_date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required class="mating-select w-full">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Check Method</label>
                        <select name="pregnancy_check_method" required class="mating-select w-full">
                            <option value="">-- Select Method --</option>
                            <option value="physical_exam">Physical Examination</option>
                            <option value="blood_test">Blood Test</option>
                            <option value="ultrasound">Ultrasound</option>
                            <option value="behavioral_signs">Behavioral Signs</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Result</label>
                        <select name="pregnancy_status" required class="mating-select w-full">
                            <option value="">-- Select Result --</option>
                            <option value="pregnant">🤰 Pregnant</option>
                            <option value="not_pregnant">❌ Not Pregnant</option>
                            <option value="uncertain">❓ Uncertain</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Confidence Level</label>
                        <select name="pregnancy_confidence" required class="mating-select w-full">
                            <option value="">-- Select Confidence --</option>
                            <option value="high">High - Very confident in result</option>
                            <option value="medium">Medium - Reasonably confident</option>
                            <option value="low">Low - May need follow-up</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes (optional)</label>
                        <textarea name="pregnancy_check_notes" rows="3" placeholder="Any additional observations or notes..." class="mating-select w-full" style="min-height:90px;"></textarea>
                    </div>

                    <div class="mating-modal-actions">
                        <button type="button" onclick="closePregnancyCheckModal()" class="mating-modal-btn secondary">
                            Cancel
                        </button>
                        <button type="submit" class="mating-modal-btn primary-pink">
                            Record Check
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Keep your existing JavaScript functions block below this line unchanged --}}
    <script>
        function openCompleteMatingModal(matingId, maleEarTag, femaleEarTag, plannedDate) {
            document.getElementById('completeMatingForm').action = `/mating/${matingId}/complete`;
            document.querySelector('#completeMatingForm input[name="actual_date"]').value = plannedDate;
            document.getElementById('completeMatingInfo').innerHTML = `
                <div class="text-sm">
                    <div class="font-medium text-gray-900 mb-2">Breeding Pair: ${maleEarTag} × ${femaleEarTag}</div>
                    <div class="text-gray-600">Planned Date: ${plannedDate}</div>
                </div>
            `;
            document.getElementById('completeMatingModal').classList.add('show');
        }

        function closeCompleteMatingModal() {
            document.getElementById('completeMatingModal').classList.remove('show');
        }

        function openPregnancyCheckModal(matingId, maleEarTag, femaleEarTag, matingDate, daysSinceMating) {
            document.getElementById('pregnancyCheckForm').action = `/mating/${matingId}/pregnancy-check`;
            document.getElementById('matingInfo').innerHTML = `
                <div class="text-sm">
                    <div class="font-medium text-gray-900 mb-2">Breeding Pair: ${maleEarTag} × ${femaleEarTag}</div>
                    <div class="text-gray-600">Actual Mating Date: ${matingDate}</div>
                    <div class="text-gray-600">${daysSinceMating} days since mating</div>
                </div>
            `;
            document.getElementById('pregnancyCheckModal').classList.add('show');
        }

        function closePregnancyCheckModal() {
            document.getElementById('pregnancyCheckModal').classList.remove('show');
        }

        document.addEventListener('DOMContentLoaded', function () {
            const statusFilter = document.getElementById('status-filter');
            if (statusFilter) {
                statusFilter.addEventListener('change', function () {
                    const rows = document.querySelectorAll('.mating-table tbody tr');
                    const selected = this.value;

                    rows.forEach(row => {
                        if (!selected) {
                            row.style.display = '';
                            return;
                        }

                        const statusCell = row.children[2];
                        const statusText = statusCell ? statusCell.textContent.toLowerCase() : '';
                        row.style.display = statusText.includes(selected) ? '' : 'none';
                    });
                });
            }

            document.getElementById('completeMatingModal')?.addEventListener('click', function (e) {
                if (e.target === this) closeCompleteMatingModal();
            });

            document.getElementById('pregnancyCheckModal')?.addEventListener('click', function (e) {
                if (e.target === this) closePregnancyCheckModal();
            });
        });
    </script>
</x-app-layout>