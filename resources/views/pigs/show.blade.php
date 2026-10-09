<x-app-layout>
    <style>
        .pigshow-page{
            min-height: 100vh;
            padding-bottom: 42px;
        }

        .pigshow-shell{
            max-width: 1440px;
            margin: 0 auto;
            padding: 18px 16px 0;
        }

        .pigshow-hero{
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 24px 26px;
            margin-bottom: 18px;
            background: linear-gradient(135deg, #14532d 0%, #166534 48%, #0f766e 100%);
            box-shadow: 0 24px 54px rgba(22,101,52,.18);
            color: #fff;
        }

        .pigshow-hero::before{
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,.15), transparent 28%),
                radial-gradient(circle at bottom left, rgba(255,255,255,.08), transparent 24%);
            pointer-events: none;
        }

        .pigshow-hero-inner{
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .pigshow-heading{
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .pigshow-back{
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            background: rgba(255,255,255,.14);
            border: 1px solid rgba(255,255,255,.16);
            color: #fff;
            transition: .2s ease;
        }

        .pigshow-back:hover{
            background: rgba(255,255,255,.22);
        }

        .pigshow-title{
            margin: 0;
            font-size: clamp(1.8rem, 3vw, 2.7rem);
            line-height: 1.02;
            font-weight: 900;
            letter-spacing: -.04em;
        }

        .pigshow-subtitle{
            margin: 8px 0 0;
            color: rgba(255,255,255,.90);
            font-size: .95rem;
            line-height: 1.7;
        }

        .pigshow-actions{
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .pigshow-top-btn{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 14px;
            border-radius: 16px;
            font-size: .83rem;
            font-weight: 800;
            text-decoration: none;
            transition: .2s ease;
            border: 1px solid rgba(255,255,255,.16);
            color: #fff;
        }

        .pigshow-top-btn:hover{
            transform: translateY(-1px);
        }

        .pigshow-top-btn.red{ background: rgba(220,38,38,.92); }
        .pigshow-top-btn.green{ background: rgba(22,163,74,.92); }
        .pigshow-top-btn.blue{ background: rgba(37,99,235,.92); }
        .pigshow-top-btn.dark{ background: rgba(15,23,42,.68); }

        .pigshow-top-badge{
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

        .pigshow-grid-4{
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 18px;
        }

        .pigshow-stat{
            background: rgba(255,255,255,.96);
            border: 1px solid #dbe7f3;
            border-radius: 24px;
            padding: 18px;
            box-shadow: 0 12px 28px rgba(15,23,42,.05);
        }

        .pigshow-stat-row{
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .pigshow-stat-icon{
            width: 52px;
            height: 52px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .pigshow-stat-icon.blue{ background: #dbeafe; color: #2563eb; }
        .pigshow-stat-icon.green{ background: #dcfce7; color: #16a34a; }
        .pigshow-stat-icon.purple{ background: #f3e8ff; color: #9333ea; }
        .pigshow-stat-icon.orange{ background: #ffedd5; color: #ea580c; }

        .pigshow-stat-label{
            font-size: .74rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #94a3b8;
            margin-bottom: 6px;
        }

        .pigshow-stat-value{
            font-size: 1.55rem;
            font-weight: 900;
            color: #0f172a;
            line-height: 1;
        }

        .pigshow-stat-note{
            margin-top: 6px;
            font-size: .76rem;
            color: #64748b;
        }

        .pigshow-main{
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(320px, 1fr);
            gap: 18px;
        }

        .pigshow-stack,
        .pigshow-side-stack,
        .pigshow-section-space{
            display: grid;
            gap: 18px;
        }

        .pigshow-card{
            background: rgba(255,255,255,.96);
            border: 1px solid #dbe7f3;
            border-radius: 26px;
            box-shadow: 0 12px 30px rgba(15,23,42,.05);
            overflow: hidden;
        }

        .pigshow-card-head{
            padding: 20px 22px 14px;
            border-bottom: 1px solid #edf2f7;
            background: linear-gradient(180deg, rgba(248,250,252,.95) 0%, rgba(255,255,255,.98) 100%);
        }

        .pigshow-card-title{
            margin: 0;
            font-size: 1.02rem;
            font-weight: 900;
            color: #0f172a;
        }

        .pigshow-card-sub{
            margin-top: 4px;
            font-size: .8rem;
            color: #64748b;
        }

        .pigshow-card-body{
            padding: 20px 22px 22px;
        }

        .pigshow-info-grid{
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .pigshow-info-item{
            background: #f8fafc;
            border: 1px solid #edf2f7;
            border-radius: 18px;
            padding: 14px 15px;
        }

        .pigshow-info-label{
            font-size: .73rem;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 5px;
        }

        .pigshow-info-value{
            font-size: .88rem;
            font-weight: 800;
            color: #0f172a;
            word-break: break-word;
        }

        .pigshow-status-banner{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            border-radius: 20px;
            padding: 16px 18px;
        }

        .pigshow-status-banner.good{
            background: linear-gradient(180deg, #ecfdf5 0%, #f0fdf4 100%);
            border: 1px solid #bbf7d0;
        }

        .pigshow-status-banner.bad{
            background: linear-gradient(180deg, #fff1f2 0%, #ffe4e6 100%);
            border: 1px solid #fecdd3;
        }

        .pigshow-factor{
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 16px 18px;
            background: #fff;
        }

        .pigshow-factor-head{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }

        .pigshow-badge{
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: .74rem;
            font-weight: 800;
            line-height: 1;
        }

        .badge-green{ background:#dcfce7; color:#166534; }
        .badge-blue{ background:#dbeafe; color:#1d4ed8; }
        .badge-yellow{ background:#fef9c3; color:#854d0e; }
        .badge-red{ background:#fee2e2; color:#b91c1c; }
        .badge-gray{ background:#f1f5f9; color:#475569; }

        .pigshow-mini-grid{
            display: grid;
            grid-template-columns: repeat(3, minmax(0,1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .pigshow-mini{
            background: #f8fafc;
            border: 1px solid #edf2f7;
            border-radius: 16px;
            padding: 11px 12px;
            font-size: .79rem;
            color: #475569;
        }

        .pigshow-mini strong{
            color: #0f172a;
        }

        .pigshow-simple-list{
            display: grid;
            gap: 12px;
        }

        .pigshow-simple-row{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 16px;
            background: #f8fafc;
            border: 1px solid #edf2f7;
        }

        .pigshow-simple-row-title{
            font-size: .78rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #94a3b8;
            margin-bottom: 4px;
        }

        .pigshow-simple-row-value{
            font-size: .88rem;
            font-weight: 800;
            color: #0f172a;
        }

        .pigshow-table-wrap{
            overflow-x: auto;
        }

        .pigshow-table{
            width: 100%;
            min-width: 920px;
            border-collapse: separate;
            border-spacing: 0;
        }

        .pigshow-table thead th{
            background: #f8fafc;
            padding: 14px 18px;
            font-size: .73rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #64748b;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .pigshow-table tbody td{
            padding: 15px 18px;
            border-bottom: 1px solid #eef2f7;
            vertical-align: top;
            font-size: .87rem;
            color: #0f172a;
            background: rgba(255,255,255,.98);
        }

        .pigshow-table tbody tr:hover td{
            background: #fbfdff;
        }

        .pigshow-link{
            color: #2563eb;
            font-weight: 700;
            text-decoration: none;
        }

        .pigshow-link:hover{
            color: #1d4ed8;
            text-decoration: underline;
        }

        @media (max-width: 1200px){
            .pigshow-grid-4{
                grid-template-columns: repeat(2, minmax(0,1fr));
            }

            .pigshow-main{
                grid-template-columns: 1fr;
            }

            .pigshow-info-grid{
                grid-template-columns: repeat(2, minmax(0,1fr));
            }
        }

        @media (max-width: 768px){
            .pigshow-shell{
                padding: 12px 12px 0;
            }

            .pigshow-hero{
                padding: 22px 18px;
                border-radius: 24px;
            }

            .pigshow-grid-4,
            .pigshow-info-grid,
            .pigshow-mini-grid{
                grid-template-columns: 1fr;
            }

            .pigshow-card-head,
            .pigshow-card-body{
                padding-left: 18px;
                padding-right: 18px;
            }

            .pigshow-actions{
                width: 100%;
            }

            .pigshow-actions .pigshow-top-btn,
            .pigshow-actions form{
                width: 100%;
            }

            .pigshow-actions form button{
                width: 100%;
                justify-content: center;
            }
        }
    </style>

    <div class="pigshow-page">
        <div class="pigshow-shell">
            <div class="pigshow-hero">
                <div class="pigshow-hero-inner">
                    <div>
                        <div class="pigshow-heading">
                            <a href="{{ \App\Helpers\NavigationHelper::getContextualBackUrl('pig-detail') }}" class="pigshow-back">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                            </a>

                            <div>
                                <h1 class="pigshow-title">{{ $pig->identityAt() }}</h1>
                                <p class="pigshow-subtitle">
                                    {{ ucfirst($pig->stock_type) }} Stock • {{ $pig->breed }} • {{ ucfirst($pig->gender === 'M' ? 'Male' : 'Female') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="pigshow-actions">
                        @if(!$pig->deaths()->exists())
                            <a href="{{ route('pigs.death-form', $pig->id) }}"
                               class="pigshow-top-btn red"
                               onclick="return confirm('Record death for {{ $pig->ear_tag_number }}?');">
                                Record Death
                            </a>
                        @else
                            <span class="pigshow-top-badge">Deceased</span>
                        @endif

                        @if($pig->herdExit)
                            <span class="pigshow-top-badge">{{ $pig->herdExit->type_label }} {{ $pig->herdExit->exit_date->format('d M Y') }}</span>
                        @elseif(! $pig->trashed() && auth()->user()?->can('create', \App\Models\PigExit::class))
                            <a href="{{ route('pig-exits.create', [$pig, 'type' => 'transfer_out']) }}" class="pigshow-top-btn dark">Transfer Out</a>
                            <a href="{{ route('pig-exits.create', [$pig, 'type' => 'cull']) }}" class="pigshow-top-btn dark">Cull</a>
                        @endif

                        @if($pig->stock_type === 'standard' && in_array($breedingReadiness['age_status'], ['minimum_met', 'optimal']) && $breedingReadiness['factors']['weight']['status'] !== 'too_light')
                            <form action="{{ route('pigs.promote-to-breeding', $pig->id) }}" method="POST" onsubmit="return confirm('Promote {{ $pig->ear_tag_number }} to breeding stock?');">
                                @csrf
                                <button type="submit" class="pigshow-top-btn green">Promote to Breeding</button>
                            </form>
                        @endif

                        @can('update', $pig)
                            @if(!$pig->trashed() && $pig->currentEarTagAssignment)
                                <a href="{{ route('pigs.ear-tag.replace', $pig) }}" class="pigshow-top-btn dark">Replace Tag</a>
                            @endif
                        @endcan
                        <a href="{{ route('breeding-stocks.edit', $pig->id) }}" class="pigshow-top-btn blue">Edit</a>
                        <a href="{{ route('pigs.export.pdf', $pig->id) }}" class="pigshow-top-btn dark">Download PDF</a>

                        <form action="{{ route('breeding-stocks.destroy', $pig->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete {{ $pig->ear_tag_number }}? This action cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="pigshow-top-btn red">Delete</button>
                        </form>
                    </div>
                </div>
            </div>

            <main>
                <div class="pigshow-grid-4">
                    <div class="pigshow-stat">
                        <div class="pigshow-stat-row">
                            <div class="pigshow-stat-icon blue">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <div class="pigshow-stat-label">Age</div>
                                <div class="pigshow-stat-value">{{ floor($pig->current_age_in_days / 30) }}m</div>
                                <div class="pigshow-stat-note">{{ $pig->current_age_in_days }} days</div>
                            </div>
                        </div>
                    </div>

                    <div class="pigshow-stat">
                        <div class="pigshow-stat-row">
                            <div class="pigshow-stat-icon green">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                                </svg>
                            </div>
                            <div>
                                <div class="pigshow-stat-label">Weight</div>
                                @if($pig->latestWeight)
                                    <div class="pigshow-stat-value">{{ $pig->latestWeight->formatted_weight }}</div>
                                    <div class="pigshow-stat-note">{{ $pig->latestWeight->days_ago }} days ago</div>
                                @else
                                    <div class="pigshow-stat-value text-gray-400">No data</div>
                                    <div class="pigshow-stat-note">Never weighed</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="pigshow-stat">
                        <div class="pigshow-stat-row">
                            <div class="pigshow-stat-icon purple">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                            </div>
                            <div>
                                <div class="pigshow-stat-label">Breeding Ready</div>
                                <div class="pigshow-stat-value {{ $breedingReadiness['ready'] ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $breedingReadiness['ready'] ? 'Yes' : 'No' }}
                                </div>
                                <div class="pigshow-stat-note">{{ $breedingReadiness['score'] }}% score</div>
                            </div>
                        </div>
                    </div>

                    <div class="pigshow-stat">
                        <div class="pigshow-stat-row">
                            <div class="pigshow-stat-icon orange">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4"/>
                                </svg>
                            </div>
                            <div>
                                <div class="pigshow-stat-label">Total Matings</div>
                                <div class="pigshow-stat-value">{{ $stats['total_matings'] }}</div>
                                <div class="pigshow-stat-note">{{ $stats['successful_matings'] }} successful</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pigshow-main">
                    <div class="pigshow-stack">
                @if(auth()->user()?->hasPermission('view_health_records'))
                    @php
                        $healthSeverity = strtolower((string) ($latestHealthRecord?->severity ?? ''));
                        $healthBadge = match (true) {
                            in_array($healthSeverity, ['high', 'severe'], true) => 'badge-red',
                            in_array($healthSeverity, ['medium', 'moderate'], true) => 'badge-yellow',
                            $latestHealthRecord !== null => 'badge-blue',
                            default => 'badge-gray',
                        };
                    @endphp
                    <div id="clinical-summary" class="pigshow-card order-2 scroll-mt-28">
                        <div class="pigshow-card-head flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="pigshow-card-title">Health &amp; Medication Summary</h2>
                                <div class="pigshow-card-sub">The animal's latest clinical position. Full histories remain available in the health record.</div>
                            </div>
                            <a href="{{ route('health.show', $pig) }}" class="pigshow-link whitespace-nowrap">View complete history →</a>
                        </div>
                        <div class="pigshow-card-body grid gap-4 lg:grid-cols-2">
                            <section class="rounded-2xl border border-rose-100 bg-rose-50/40 p-4 sm:p-5">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-black uppercase tracking-[.12em] text-rose-700">Latest health record</p>
                                        <h3 class="mt-2 text-lg font-black text-slate-950">{{ $latestHealthRecord?->condition ?? 'No health condition recorded' }}</h3>
                                    </div>
                                    @if($latestHealthRecord)
                                        <span class="pigshow-badge {{ $healthBadge }}">{{ ucfirst($latestHealthRecord->severity ?: 'Not graded') }}</span>
                                    @endif
                                </div>

                                @if($latestHealthRecord)
                                    <p class="mt-2 text-sm font-medium text-slate-600">Recorded {{ $latestHealthRecord->record_date->format('d M Y') }}</p>
                                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                                        <div class="rounded-xl bg-white p-3"><p class="text-xs font-bold uppercase text-slate-400">Treatment</p><p class="mt-1 text-sm font-extrabold text-slate-800">{{ ucfirst($latestHealthRecord->treatment_status ?: 'Not set') }}</p></div>
                                        <div class="rounded-xl bg-white p-3"><p class="text-xs font-bold uppercase text-slate-400">Outcome</p><p class="mt-1 text-sm font-extrabold text-slate-800">{{ ucfirst($latestHealthRecord->outcome ?: ($latestHealthRecord->resolved_at ? 'Resolved' : 'Not assessed')) }}</p></div>
                                    </div>
                                @else
                                    <p class="mt-3 text-sm leading-6 text-slate-600">No clinical condition has been recorded for this animal yet.</p>
                                @endif

                                <div class="mt-4 flex flex-wrap gap-2 text-xs font-bold text-slate-600">
                                    <span class="rounded-full bg-white px-3 py-1.5">{{ $healthSummary['total_records'] }} {{ \Illuminate\Support\Str::plural('record', $healthSummary['total_records']) }}</span>
                                    <span class="rounded-full bg-white px-3 py-1.5">{{ $healthSummary['open_cases'] }} open</span>
                                </div>
                            </section>

                            <section class="rounded-2xl border border-blue-100 bg-blue-50/50 p-4 sm:p-5">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-black uppercase tracking-[.12em] text-blue-700">Latest medication</p>
                                        <h3 class="mt-2 text-lg font-black text-slate-950">{{ $latestMedication?->medication_name ?? 'No medication recorded' }}</h3>
                                    </div>
                                    @if($healthSummary['under_withdrawal'])
                                        <span class="pigshow-badge badge-yellow">Withdrawal active</span>
                                    @elseif($latestMedication)
                                        <span class="pigshow-badge badge-green">No active withdrawal</span>
                                    @endif
                                </div>

                                @if($latestMedication)
                                    <p class="mt-2 text-sm font-medium text-slate-600">Administered {{ $latestMedication->administration_date->format('d M Y') }}</p>
                                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                                        <div class="rounded-xl bg-white p-3"><p class="text-xs font-bold uppercase text-slate-400">Dosage</p><p class="mt-1 text-sm font-extrabold text-slate-800">{{ $latestMedication->dosage ?: 'Not recorded' }}</p></div>
                                        <div class="rounded-xl bg-white p-3"><p class="text-xs font-bold uppercase text-slate-400">Route</p><p class="mt-1 text-sm font-extrabold text-slate-800">{{ ucfirst($latestMedication->route ?: 'Not recorded') }}</p></div>
                                    </div>
                                    @if($latestMedication->withdrawal_ends_on)
                                        <p class="mt-3 text-xs font-bold {{ $healthSummary['under_withdrawal'] ? 'text-amber-700' : 'text-slate-500' }}">Withdrawal {{ $healthSummary['under_withdrawal'] ? 'ends' : 'ended' }} {{ $latestMedication->withdrawal_ends_on->format('d M Y') }}</p>
                                    @endif
                                @else
                                    <p class="mt-3 text-sm leading-6 text-slate-600">No medication administration has been recorded for this animal yet.</p>
                                @endif

                                <div class="mt-4 flex flex-wrap gap-2 text-xs font-bold text-slate-600">
                                    <span class="rounded-full bg-white px-3 py-1.5">{{ $healthSummary['medication_records'] }} {{ \Illuminate\Support\Str::plural('administration', $healthSummary['medication_records']) }}</span>
                                </div>
                            </section>
                        </div>
                    </div>
                @endif

                        <div class="pigshow-card order-1">
                            <div class="pigshow-card-head">
                                <h2 class="pigshow-card-title">Basic Information</h2>
                            </div>
                            <div class="pigshow-card-body">
                                <dl class="pigshow-info-grid">
                                    <div class="pigshow-info-item">
                                        <dt class="pigshow-info-label">Ear Tag Number</dt>
                                        <dd class="pigshow-info-value font-mono">{{ $pig->ear_tag_number }}</dd>
                                    </div>
                                    <div class="pigshow-info-item">
                                        <dt class="pigshow-info-label">Pig Code</dt>
                                        <dd class="pigshow-info-value font-mono">{{ $pig->code }}</dd>
                                    </div>
                                    <div class="pigshow-info-item">
                                        <dt class="pigshow-info-label">Breed</dt>
                                        <dd class="pigshow-info-value">{{ $pig->breed }}</dd>
                                    </div>
                                    <div class="pigshow-info-item">
                                        <dt class="pigshow-info-label">Gender</dt>
                                        <dd class="pigshow-info-value">{{ $pig->gender === 'M' ? 'Male' : 'Female' }}</dd>
                                    </div>
                                    <div class="pigshow-info-item">
                                        <dt class="pigshow-info-label">Date of Birth</dt>
                                        <dd class="pigshow-info-value">{{ $pig->date_of_birth ? $pig->date_of_birth->format('M d, Y') : 'Not set' }}</dd>
                                    </div>
                                    <div class="pigshow-info-item">
                                        <dt class="pigshow-info-label">Stock Type</dt>
                                        <dd class="pigshow-info-value">
                                            <span class="pigshow-badge {{ $pig->stock_type === 'breeding' ? 'badge-green' : 'badge-blue' }}">
                                                {{ ucfirst($pig->stock_type) }} Stock
                                            </span>
                                        </dd>
                                    </div>
                                    <div class="pigshow-info-item">
                                        <dt class="pigshow-info-label">Location</dt>
                                        <dd class="pigshow-info-value">
                                            @if($pig->room)
                                                <span class="font-medium">{{ $pig->room->structure->name }}</span>
                                                <span class="text-gray-400">/</span>
                                                <span>{{ $pig->room->name }}</span>
                                            @elseif($pig->pen)
                                                <span>{{ $pig->pen->name }}</span>
                                            @else
                                                <span class="text-gray-400">Not assigned</span>
                                            @endif
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <div class="pigshow-card order-2">
                            <div class="pigshow-card-head"><h2 class="pigshow-card-title">Ear Tag History</h2><p class="pigshow-card-sub">Physical tags used by this permanent animal record.</p></div>
                            <div class="pigshow-card-body">
                                <div class="space-y-3">
                                    @forelse($pig->earTagAssignments as $assignment)
                                        <div class="flex flex-col gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                                            <div><p class="font-black text-slate-950"><span class="mr-2 inline-block h-3 w-3 rounded-full border border-slate-300 align-middle" style="background: {{ $assignment->tag?->color?->hex_color ?: '#CBD5E1' }}"></span>{{ $assignment->tag?->color?->name ?? 'Unspecified' }} {{ $assignment->tag?->tag_number }}</p><p class="mt-1 text-xs text-slate-500">Assigned {{ $assignment->assigned_at?->format('d M Y') }}@if($assignment->released_at) · Released {{ $assignment->released_at->format('d M Y') }}@endif</p></div>
                                            <span class="w-fit rounded-full px-3 py-1 text-xs font-bold {{ $assignment->is_current ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">{{ $assignment->is_current ? 'Current' : 'Previous · '.str($assignment->release_reason ?: 'Released')->headline() }}</span>
                                        </div>
                                    @empty
                                        <p class="text-sm text-slate-500">No tag assignment history is recorded for this animal.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <div class="pigshow-card order-3">
                            <div class="pigshow-card-head">
                                <h2 class="pigshow-card-title">Breeding Readiness Assessment</h2>
                            </div>
                            <div class="pigshow-card-body">
                                <div class="pigshow-section-space">
                                    <div class="pigshow-status-banner {{ $breedingReadiness['ready'] ? 'good' : 'bad' }}">
                                        <div>
                                            <h3 class="font-semibold {{ $breedingReadiness['ready'] ? 'text-green-800' : 'text-red-800' }}">
                                                Overall Status: {{ $breedingReadiness['ready'] ? 'Ready for Breeding' : 'Not Ready for Breeding' }}
                                            </h3>
                                            <p class="text-sm {{ $breedingReadiness['ready'] ? 'text-green-600' : 'text-red-600' }}">
                                                Readiness Score: {{ $breedingReadiness['score'] }}%
                                            </p>
                                        </div>
                                        <div class="text-2xl">
                                            {{ $breedingReadiness['ready'] ? 'OK' : 'No' }}
                                        </div>
                                    </div>

                                    <div class="pigshow-factor">
                                        <div class="pigshow-factor-head">
                                            <h4 class="font-medium text-gray-900">Age Requirements</h4>
                                            <span class="pigshow-badge {{ $breedingReadiness['age_status'] === 'optimal' ? 'badge-green' : ($breedingReadiness['age_status'] === 'minimum_met' ? 'badge-yellow' : ($breedingReadiness['age_status'] === 'too_young' ? 'badge-blue' : 'badge-gray')) }}">
                                                @if($breedingReadiness['age_status'] === 'optimal')
                                                    Optimal
                                                @elseif($breedingReadiness['age_status'] === 'minimum_met')
                                                    Warning Ready
                                                @elseif($breedingReadiness['age_status'] === 'too_young')
                                                    ⏳ Too Young
                                                @else
                                                    Too old Too Old
                                                @endif
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-600 mb-3">{{ $breedingReadiness['factors']['age']['message'] }}</p>

                                        <div class="pigshow-mini-grid">
                                            <div class="pigshow-mini"><span>Current:</span> <strong>{{ $breedingReadiness['age_in_days'] }} days</strong></div>
                                            <div class="pigshow-mini"><span>Minimum:</span> <strong>{{ $breedingReadiness['minimum_age'] }} days</strong></div>
                                            <div class="pigshow-mini"><span>Optimal:</span> <strong>{{ $breedingReadiness['optimal_age'] }} days</strong></div>
                                        </div>
                                    </div>

                                    <div class="pigshow-factor">
                                        <div class="pigshow-factor-head">
                                            <h4 class="font-medium text-gray-900">Weight Requirements</h4>
                                            <span class="pigshow-badge {{ $breedingReadiness['factors']['weight']['status'] === 'optimal' ? 'badge-green' : ($breedingReadiness['factors']['weight']['status'] === 'minimum_met' ? 'badge-yellow' : ($breedingReadiness['factors']['weight']['status'] === 'too_light' ? 'badge-red' : 'badge-gray')) }}">
                                                @if($breedingReadiness['factors']['weight']['status'] === 'optimal')
                                                    Optimal
                                                @elseif($breedingReadiness['factors']['weight']['status'] === 'minimum_met')
                                                    Warning Adequate
                                                @elseif($breedingReadiness['factors']['weight']['status'] === 'too_light')
                                                    No Too Light
                                                @else
                                                    ? No Data
                                                @endif
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-600 mb-3">{{ $breedingReadiness['factors']['weight']['message'] }}</p>

                                        @if($breedingReadiness['factors']['weight']['current_weight'])
                                            <div class="pigshow-mini-grid">
                                                <div class="pigshow-mini"><span>Current:</span> <strong>{{ $breedingReadiness['factors']['weight']['current_weight'] }}</strong></div>
                                                <div class="pigshow-mini"><span>Minimum:</span> <strong>{{ $breedingReadiness['factors']['weight']['minimum_required'] }}</strong></div>
                                                <div class="pigshow-mini"><span>Optimal:</span> <strong>{{ $breedingReadiness['factors']['weight']['optimal_weight'] }}</strong></div>
                                            </div>
                                            @if($breedingReadiness['factors']['weight']['last_weighed'])
                                                <p class="text-xs text-gray-500 mt-3">Last weighed: {{ $breedingReadiness['factors']['weight']['last_weighed'] }}</p>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pigshow-side-stack">
                        @if($pig->father || $pig->mother)
                            <div class="pigshow-card">
                                <div class="pigshow-card-head">
                                    <h2 class="pigshow-card-title">Parents</h2>
                                </div>
                                <div class="pigshow-card-body">
                                    <div class="pigshow-simple-list">
                                        @if($pig->father)
                                            <div class="pigshow-simple-row">
                                                <div>
                                                    <div class="pigshow-simple-row-title">Father (Sire)</div>
                                                    <div class="pigshow-simple-row-value">{{ $pig->father->ear_tag_number }} - {{ $pig->father->code }}</div>
                                                </div>
                                                <a href="{{ route('pigs.show', $pig->father->id) }}" class="pigshow-link">View</a>
                                            </div>
                                        @endif

                                        @if($pig->mother)
                                            <div class="pigshow-simple-row">
                                                <div>
                                                    <div class="pigshow-simple-row-title">Mother (Dam)</div>
                                                    <div class="pigshow-simple-row-value">{{ $pig->mother->ear_tag_number }} - {{ $pig->mother->code }}</div>
                                                </div>
                                                <a href="{{ route('pigs.show', $pig->mother->id) }}" class="pigshow-link">View</a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="pigshow-card">
                            <div class="pigshow-card-head">
                                <h2 class="pigshow-card-title">Quick Statistics</h2>
                            </div>
                            <div class="pigshow-card-body">
                                <div class="pigshow-simple-list">
                                    <div class="pigshow-simple-row">
                                        <span class="text-sm text-gray-600">Total Matings</span>
                                        <strong>{{ $stats['total_matings'] }}</strong>
                                    </div>
                                    <div class="pigshow-simple-row">
                                        <span class="text-sm text-gray-600">Successful Matings</span>
                                        <strong>{{ $stats['successful_matings'] }}</strong>
                                    </div>
                                    <div class="pigshow-simple-row">
                                        <span class="text-sm text-gray-600">Offspring</span>
                                        <strong>{{ $stats['total_offspring'] }}</strong>
                                    </div>
                                    <div class="pigshow-simple-row">
                                        <span class="text-sm text-gray-600">Weight Records</span>
                                        <strong>{{ $stats['weight_records'] }}</strong>
                                    </div>
                                    @if($stats['days_since_last_weight'])
                                        <div class="pigshow-simple-row">
                                            <span class="text-sm text-gray-600">Last Weighed</span>
                                            <strong>{{ $stats['days_since_last_weight'] }} days ago</strong>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="pigshow-card">
                            <div class="pigshow-card-head">
                                <h2 class="pigshow-card-title">Availability Status</h2>
                            </div>
                            <div class="pigshow-card-body">
                                <div class="pigshow-simple-row">
                                    <div>
                                        <div class="pigshow-simple-row-title">Current Status</div>
                                        <div class="pigshow-simple-row-value">{{ ucfirst($availabilityStatus['status']) }}</div>
                                        <div class="text-sm text-gray-600 mt-1">{{ $availabilityStatus['reason'] }}</div>
                                    </div>
                                    <div class="text-2xl">{{ $availabilityStatus['icon'] }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if($pig->weights->count() > 0)
                    <div id="weight" class="pigshow-card mt-8">
                        <div class="pigshow-card-head">
                            <h2 class="pigshow-card-title">Recent Weight History</h2>
                        </div>
                        <div class="pigshow-table-wrap">
                            <table class="pigshow-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Weight</th>
                                        <th>Purpose</th>
                                        <th>Health</th>
                                        <th>Body Condition</th>
                                        <th>Age</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pig->weights as $weight)
                                        <tr>
                                            <td>{{ $weight->weigh_date ? $weight->weigh_date->format('M d, Y') : 'Not set' }}</td>
                                            <td class="font-semibold">{{ $weight->formatted_weight }}</td>
                                            <td>
                                                <span class="pigshow-badge badge-gray">
                                                    {{ $weight->purpose_icon }} {{ ucfirst(str_replace('_', ' ', $weight->purpose ?? 'routine')) }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($weight->health_status)
                                                    @php
                                                        $healthConfig = [
                                                            'excellent' => ['class' => 'badge-green'],
                                                            'good' => ['class' => 'badge-blue'],
                                                            'fair' => ['class' => 'badge-gray'],
                                                            'poor' => ['class' => 'badge-red']
                                                        ];
                                                        $config = $healthConfig[$weight->health_status] ?? $healthConfig['good'];
                                                    @endphp
                                                    <span class="pigshow-badge {{ $config['class'] }}">
                                                        {{ ucfirst($weight->health_status) }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($weight->body_condition_score)
                                                    <span class="pigshow-badge {{ $weight->body_condition_score <= 2 ? 'badge-red' : ($weight->body_condition_score == 3 ? 'badge-green' : 'badge-blue') }}">
                                                        {{ $weight->body_condition_score }}/5
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td>{{ $weight->age_at_weighing }} days</td>
                                            <td>{{ $weight->notes ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @if($pig->weights->count() > 0 || $pig->maleMatings->count() > 0 || $pig->femaleMatings->count() > 0 || $offspring->count() > 0)
                    <div class="pigshow-section-space mt-8">
                        @if(!empty($analytics) && !empty($analytics['growth_periods']))
                            <div class="pigshow-card">
                                <div class="pigshow-card-head">
                                    <h2 class="pigshow-card-title">Growth Periods Analysis</h2>
                                </div>
                                <div class="pigshow-table-wrap">
                                    <table class="pigshow-table">
                                        <thead>
                                            <tr>
                                                <th>Period</th>
                                                <th>Duration</th>
                                                <th>Weight Range</th>
                                                <th>Total Gain</th>
                                                <th>Daily Gain</th>
                                                <th>Performance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($analytics['growth_periods'] as $period)
                                                <tr>
                                                    <td>{{ $period['from_date']->format('M d') }} - {{ $period['to_date']->format('M d, Y') }}</td>
                                                    <td>{{ $period['days'] }} days</td>
                                                    <td>{{ number_format($period['from_weight'], 1) }} → {{ number_format($period['to_weight'], 1) }} kg</td>
                                                    <td>
                                                        <span class="{{ $period['weight_gain'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                            {{ $period['weight_gain'] >= 0 ? '+' : '' }}{{ number_format($period['weight_gain'], 1) }} kg
                                                        </span>
                                                    </td>
                                                    <td>{{ number_format($period['daily_gain'], 3) }} kg/day</td>
                                                    <td>
                                                        @if($period['daily_gain'] >= 0.5)
                                                            <span class="pigshow-badge badge-green">Excellent</span>
                                                        @elseif($period['daily_gain'] >= 0.3)
                                                            <span class="pigshow-badge badge-blue">Good</span>
                                                        @elseif($period['daily_gain'] >= 0.1)
                                                            <span class="pigshow-badge badge-yellow">Average</span>
                                                        @else
                                                            <span class="pigshow-badge badge-red">Poor</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        @if($pig->maleMatings->count() > 0 || $pig->femaleMatings->count() > 0)
                            <div class="pigshow-card">
                                <div class="pigshow-card-head">
                                    <h2 class="pigshow-card-title">Mating History</h2>
                                </div>
                                <div class="pigshow-table-wrap">
                                    <table class="pigshow-table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Partner</th>
                                                <th>Role</th>
                                                <th>Status</th>
                                                <th>Expected Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($pig->maleMatings as $mating)
                                                <tr>
                                                    <td>{{ $mating->mating_date ? $mating->mating_date->format('M d, Y') : 'Not set' }}</td>
                                                    <td>
                                                        <a href="{{ route('pigs.show', $mating->female->id) }}" class="pigshow-link">
                                                            {{ $mating->female?->identityAt($mating->actual_date ?? $mating->planned_date) }}
                                                        </a>
                                                    </td>
                                                    <td>Sire</td>
                                                    <td>
                                                        <span class="pigshow-badge {{ $mating->status === 'completed' ? 'badge-green' : ($mating->status === 'planned' ? 'badge-blue' : 'badge-gray') }}">
                                                            {{ ucfirst($mating->status) }}
                                                        </span>
                                                    </td>
                                                    <td>{{ $mating->expected_farrowing_date ? $mating->expected_farrowing_date->format('M d, Y') : '-' }}</td>
                                                </tr>
                                            @endforeach

                                            @foreach($pig->femaleMatings as $mating)
                                                <tr>
                                                    <td>{{ $mating->mating_date ? $mating->mating_date->format('M d, Y') : 'Not set' }}</td>
                                                    <td>
                                                        <a href="{{ route('pigs.show', $mating->male->id) }}" class="pigshow-link">
                                                            {{ $mating->male?->identityAt($mating->actual_date ?? $mating->planned_date) }}
                                                        </a>
                                                    </td>
                                                    <td>Dam</td>
                                                    <td>
                                                        <span class="pigshow-badge {{ $mating->status === 'completed' ? 'badge-green' : ($mating->status === 'planned' ? 'badge-blue' : 'badge-gray') }}">
                                                            {{ ucfirst($mating->status) }}
                                                        </span>
                                                    </td>
                                                    <td>{{ $mating->expected_farrowing_date ? $mating->expected_farrowing_date->format('M d, Y') : '-' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        @if($offspring->count() > 0)
                            <div class="pigshow-card">
                                <div class="pigshow-card-head">
                                    <h2 class="pigshow-card-title">Offspring ({{ $offspring->count() }})</h2>
                                </div>
                                <div class="pigshow-table-wrap">
                                    <table class="pigshow-table">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Ear Tag</th>
                                                <th>Gender</th>
                                                <th>Birth Date</th>
                                                <th>Age</th>
                                                <th>Stock Type</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($offspring as $child)
                                                <tr>
                                                    <td class="font-medium">{{ $child->code }}</td>
                                                    <td class="font-medium">{{ $child->ear_tag_number }}</td>
                                                    <td>{{ $child->gender === 'M' ? 'Male' : 'Female' }}</td>
                                                    <td>{{ $child->date_of_birth ? $child->date_of_birth->format('M d, Y') : 'Not set' }}</td>
                                                    <td>{{ $child->current_age_in_days }} days</td>
                                                    <td>
                                                        <span class="pigshow-badge {{ $child->stock_type === 'breeding' ? 'badge-green' : 'badge-blue' }}">
                                                            {{ ucfirst($child->stock_type) }}
                                                        </span>
                                                    </td>
                                                    <td><a href="{{ route('pigs.show', $child->id) }}" class="pigshow-link">View</a></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        @if($siblings->count() > 0)
                            <div class="pigshow-card">
                                <div class="pigshow-card-head">
                                    <h2 class="pigshow-card-title">Siblings ({{ $siblings->count() }})</h2>
                                </div>
                                <div class="pigshow-table-wrap">
                                    <table class="pigshow-table">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Ear Tag</th>
                                                <th>Gender</th>
                                                <th>Age</th>
                                                <th>Stock Type</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($siblings as $sibling)
                                                <tr>
                                                    <td class="font-medium">{{ $sibling->code }}</td>
                                                    <td class="font-medium">{{ $sibling->ear_tag_number }}</td>
                                                    <td>{{ $sibling->gender === 'M' ? 'Male' : 'Female' }}</td>
                                                    <td>{{ $sibling->current_age_in_days }} days</td>
                                                    <td>
                                                        <span class="pigshow-badge {{ $sibling->stock_type === 'breeding' ? 'badge-green' : 'badge-blue' }}">
                                                            {{ ucfirst($sibling->stock_type) }}
                                                        </span>
                                                    </td>
                                                    <td><a href="{{ route('pigs.show', $sibling->id) }}" class="pigshow-link">View</a></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                @if($pig->gender === 'F' && $pig->farrowingsAsSow->count() > 0)
                    <div class="pigshow-card mt-10">
                        <div class="pigshow-card-head">
                            <h2 class="pigshow-card-title">Farrowing (Birthing) History</h2>
                            <div class="pigshow-card-sub">Detailed history of all farrowings for this sow</div>
                        </div>
                        <div class="pigshow-table-wrap">
                            <table class="pigshow-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Boar</th>
                                        <th>Total Born</th>
                                        <th>Live</th>
                                        <th>Still</th>
                                        <th>Deaths</th>
                                        <th>Surviving</th>
                                        <th>Status</th>
                                        <th>Weaned On</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pig->farrowingsAsSow as $farrowing)
                                        @php
                                            $surviving = max(0, ($farrowing->live_births ?? 0) - $farrowing->pigletDeaths->sum('count'));
                                            $deathCount = $farrowing->pigletDeaths->sum('count');

                                            $baseStatus = $farrowing->status ?: 'n/a';
                                            $weaningThreshold = (int) config('breeding.cycle.birth_to_weaning_days', 45);
                                            $litterAgeDays = $farrowing->farrowing_date
                                                ? (int) $farrowing->farrowing_date->diffInDays(now())
                                                : null;

                                            $litterStatusLabel = ucfirst($baseStatus);
                                            $litterBadgeClass = $baseStatus === 'completed'
                                                ? 'badge-green'
                                                : ($baseStatus === 'active' ? 'badge-blue' : 'badge-gray');

                                            if (
                                                $surviving === 0 &&
                                                ($farrowing->live_births ?? 0) > 0 &&
                                                !$farrowing->date_weaned &&
                                                $litterAgeDays !== null
                                            ) {
                                                $litterStatusLabel = $litterAgeDays < $weaningThreshold
                                                    ? 'Lost (pre-weaning)'
                                                    : 'Lost (post-weaning age)';
                                                $litterBadgeClass = 'badge-red';
                                            }

                                            $weanedText = 'N/A';
                                            if ($farrowing->date_weaned) {
                                                $weanedText = \Illuminate\Support\Carbon::parse($farrowing->date_weaned)->format('M d, Y');
                                            } elseif (!empty($farrowing->notes)) {
                                                $notesStr = (string) $farrowing->notes;
                                                $pos = stripos($notesStr, 'litter weaned on');
                                                if ($pos !== false) {
                                                    $after = trim(substr($notesStr, $pos + strlen('litter weaned on')));
                                                    $after = trim(strtok($after, "\r\n."));
                                                    try {
                                                        if ($after !== '') {
                                                            $parsed = \Illuminate\Support\Carbon::parse($after);
                                                            $weanedText = $parsed->format('M d, Y');
                                                        }
                                                    } catch (\Throwable $e) {
                                                    }
                                                }
                                            }
                                        @endphp
                                        <tr>
                                            <td>{{ $farrowing->farrowing_date ? $farrowing->farrowing_date->format('M d, Y') : 'N/A' }}</td>
                                            <td>
                                                @if($farrowing->boar)
                                                    <a href="{{ route('pigs.show', $farrowing->boar->id) }}" class="pigshow-link">{{ $farrowing->boar?->identityAt($farrowing->farrowing_date) }}</a>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="font-bold text-blue-700">{{ $farrowing->total_piglets_born }}</td>
                                            <td class="font-bold text-green-700">{{ $farrowing->live_births }}</td>
                                            <td class="font-bold text-yellow-700">{{ $farrowing->stillbirths }}</td>
                                            <td class="font-bold text-red-700">{{ $deathCount }}</td>
                                            <td class="text-lg font-extrabold text-green-600">{{ $surviving }}</td>
                                            <td>
                                                <span class="pigshow-badge {{ $litterBadgeClass }}">
                                                    {{ $litterStatusLabel }}
                                                </span>
                                            </td>
                                            <td>{{ $weanedText }}</td>
                                            <td>{{ Str::limit($farrowing->notes, 30) ?? 'N/A' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            @if($pig->farrowingsAsSow->isEmpty())
                                <div class="p-6 text-center text-gray-500">No farrowings recorded for this sow.</div>
                            @endif
                        </div>
                    </div>
                @endif
            </main>
        </div>
    </div>
</x-app-layout>
