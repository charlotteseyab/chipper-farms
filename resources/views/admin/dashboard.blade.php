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
            .admin-mini-grid{
                grid-template-columns: repeat(2, minmax(0,1fr));
            }
        }

        @media (max-width: 1024px){
            .admin-small-top,
            .admin-two-col{
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px){
            .admin-wrap{
                padding: 14px 10px 40px;
            }

            .admin-stat-grid,
            .admin-mini-grid{
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

            <div class="admin-filter">
                <div class="admin-filter-title">Dashboard Filters</div>
                <form method="GET" action="{{ route('admin.dashboard') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div>
                        <label for="date_from" class="block text-sm font-medium text-slate-700 mb-2">From Date</label>
                        <input type="date" id="date_from" name="date_from" value="{{ $dateFrom }}" class="admin-input">
                    </div>

                    <div>
                        <label for="date_to" class="block text-sm font-medium text-slate-700 mb-2">To Date</label>
                        <input type="date" id="date_to" name="date_to" value="{{ $dateTo }}" class="admin-input">
                    </div>

                    <div class="flex gap-2 md:col-span-2">
                        <button type="submit" class="admin-btn admin-btn-green">Apply Filter</button>
                        <a href="{{ route('admin.dashboard') }}" class="admin-btn admin-btn-gray">Reset</a>
                    </div>
                </form>
            </div>

            <section class="admin-section">
                <div class="admin-section-head">
                    <div>
                        <h2>Stock Overview</h2>
                        <p>Current inventory and stock distribution</p>
                    </div>
                    <a href="{{ route('all-stocks.index') }}" class="admin-link">View All →</a>
                </div>

                <div class="admin-stat-grid">
                    <a href="{{ route('all-stocks.index') }}" class="admin-stat admin-stat-green">
                        <div class="admin-stat-label">Total Pigs</div>
                        <p class="admin-stat-value">{{ number_format($totalPigs) }}</p>
                        <div class="admin-stat-sub">{{ number_format($weanedPigs) }} weaned • {{ number_format($pigletsCount) }} piglets</div>
                    </a>

                    <a href="{{ route('all-stocks.index') }}" class="admin-stat admin-stat-blue">
                        <div class="admin-stat-label">Active Pigs</div>
                        <p class="admin-stat-value">{{ number_format($activePigs) }}</p>
                        <div class="admin-stat-sub">{{ number_format($activeMaleCount) }} males • {{ number_format($activeFemaleCount) }} females</div>
                    </a>

                    <a href="{{ route('all-stocks.index') }}" class="admin-stat admin-stat-purple">
                        <div class="admin-stat-label">Breeding Stock</div>
                        <p class="admin-stat-value">{{ number_format($breedingStock) }}</p>
                        <div class="admin-stat-sub">{{ number_format($standardStock) }} standard stock</div>
                    </a>

                    <a href="{{ route('deaths.index') }}" class="admin-stat admin-stat-red">
                        <div class="admin-stat-label">Mortality Rate</div>
                        <p class="admin-stat-value">{{ number_format($mortalityRate, 1) }}%</p>
                        <div class="admin-stat-sub">{{ number_format($deceasedPigs) }} deceased • {{ number_format($soldPigs) }} sold</div>
                    </a>
                </div>

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
                                <h3 class="admin-card-title">Stock Trends</h3>
                                <div class="admin-card-sub">Active, sold, and deceased over 6 months</div>
                            </div>
                        </div>

                        <div class="admin-chart-wrap">
                            <div class="admin-chart">
                                <canvas id="stockTrendsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="admin-small-top">
                <div class="admin-card">
                    <div class="admin-card-head">
                        <div>
                            <h3 class="admin-card-title">Farm Value</h3>
                            <div class="admin-card-sub">Current estimated live value of the farm</div>
                        </div>
                        <div class="admin-badge">Live snapshot</div>
                    </div>

                    <div class="admin-small-card-body">
                        <div class="admin-small-value">
                            <div class="admin-value-icon">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>

                            <div>
                                <div class="admin-value-number">GHS {{ number_format($totalFarmValue, 2) }}</div>
                                <div class="admin-value-note">
                                    {{ number_format($totalWeight, 1) }}kg total live weight @ GHS {{ number_format($liveWeightPrice, 2) }}/kg
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div onclick="toggleWeighingAlertsModal()" class="admin-alert-card cursor-pointer hover:-translate-y-1 transition-all duration-300">
                    <div class="admin-card-head">
                        <div>
                            <h3 class="admin-card-title">Weighing Alerts</h3>
                            <div class="admin-card-sub">Click to view overdue routine weighing and first-weight pending pigs</div>
                        </div>
                        <div class="admin-badge">Attention needed</div>
                    </div>

                    <div class="admin-small-card-body">
                        <div class="admin-small-value">
                            <div class="admin-value-icon" style="background: linear-gradient(135deg, #fee2e2, #fecaca); color: #dc2626;">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-7.938 4h15.876c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.268 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                            </div>

                            <div>
                                <div class="admin-value-number">
                                    {{ number_format(($allOverduePigsData->count() ?? 0) + ($allFirstWeightPendingData->count() ?? 0)) }}
                                </div>
                                <div class="admin-value-note">
                                    {{ number_format($allOverduePigsData->count() ?? 0) }} overdue •
                                    {{ number_format($allFirstWeightPendingData->count() ?? 0) }} first weight pending
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="admin-card mb-4">
                <div class="admin-card-head">
                    <div>
                        <h3 class="admin-card-title">Farm Value Trend</h3>
                        <div class="admin-card-sub">Trend based on average weight and live price over recent months</div>
                    </div>
                    <div class="admin-badge">Last 6 months</div>
                </div>

                <div class="admin-chart-wrap">
                    <div class="admin-chart">
                        <canvas id="farmValueTrendChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="admin-divider"></div>

            <section class="admin-section">
                <div class="admin-section-head">
                    <div>
                        <h2>Mating Management</h2>
                        <p>Breeding activities and pregnancy tracking</p>
                    </div>
                    <a href="{{ route('mating.manage') }}" class="admin-link">View All →</a>
                </div>

                <div class="admin-mini-grid">
                    <a href="{{ route('mating.manage') }}" class="admin-mini-card">
                        <div class="admin-mini-label">Total Matings</div>
                        <div class="admin-mini-value">{{ number_format($totalMatings) }}</div>
                        <div class="admin-mini-sub">{{ number_format($plannedMatings) }} planned • {{ number_format($completedMatings) }} completed</div>
                    </a>

                    <a href="{{ route('mating.manage') }}" class="admin-mini-card">
                        <div class="admin-mini-label">Pregnant</div>
                        <div class="admin-mini-value">{{ number_format($pregnantMatings) }}</div>
                        <div class="admin-mini-sub">{{ number_format($pregnancyRate, 1) }}% pregnancy rate</div>
                    </a>

                    <a href="{{ route('mating.manage') }}" class="admin-mini-card">
                        <div class="admin-mini-label">Checks Due</div>
                        <div class="admin-mini-value">{{ number_format($pregnancyChecksDue) }}</div>
                        <div class="admin-mini-sub">{{ number_format($pregnancyChecksOverdue) }} overdue</div>
                    </a>

                    <a href="{{ route('mating.manage') }}" class="admin-mini-card">
                        <div class="admin-mini-label">Fertility Rate</div>
                        <div class="admin-mini-value">{{ number_format($fertilityRate, 1) }}%</div>
                        <div class="admin-mini-sub">{{ number_format($upcomingMatings) }} upcoming</div>
                    </a>
                </div>

                <div class="admin-card">
                    <div class="admin-card-head">
                        <div>
                            <h3 class="admin-card-title">Mating Activity</h3>
                            <div class="admin-card-sub">Six-month breeding activity chart</div>
                        </div>
                    </div>

                    <div class="admin-chart-wrap">
                        <div class="admin-chart">
                            <canvas id="matingTrendsChart"></canvas>
                        </div>
                    </div>
                </div>
            </section>

            <div class="admin-divider"></div>

            <section class="admin-section">
                <div class="admin-section-head">
                    <div>
                        <h2>Gestation Tracking</h2>
                        <p>Pregnancy monitoring and farrowing preparation</p>
                    </div>
                    <a href="{{ route('gestation.index') }}" class="admin-link">View All →</a>
                </div>

                <div class="admin-mini-grid">
                    <a href="{{ route('gestation.index') }}" class="admin-mini-card">
                        <div class="admin-mini-label">Pregnant</div>
                        <div class="admin-mini-value">{{ number_format($totalPregnant) }}</div>
                        <div class="admin-mini-sub">{{ number_format($avgGestationDays, 1) }} avg days</div>
                    </a>

                    <a href="{{ route('gestation.index') }}" class="admin-mini-card">
                        <div class="admin-mini-label">Due This Week</div>
                        <div class="admin-mini-value">{{ number_format($dueThisWeek) }}</div>
                        <div class="admin-mini-sub">{{ number_format($dueNextWeek) }} due next week</div>
                    </a>

                    <a href="{{ route('gestation.index') }}" class="admin-mini-card">
                        <div class="admin-mini-label">Overdue</div>
                        <div class="admin-mini-value">{{ number_format($overdueGestation) }}</div>
                        <div class="admin-mini-sub">Requires attention</div>
                    </a>

                    <a href="{{ route('farrowing.index') }}" class="admin-mini-card">
                        <div class="admin-mini-label">Farrowed This Month</div>
                        <div class="admin-mini-value">{{ number_format($farrowedThisMonth) }}</div>
                        <div class="admin-mini-sub">{{ number_format($totalFarrowed) }} total</div>
                    </a>
                </div>

                <div class="admin-card">
                    <div class="admin-card-head">
                        <div>
                            <h3 class="admin-card-title">Gestation Status Distribution</h3>
                            <div class="admin-card-sub">Distribution across tracked gestation states</div>
                        </div>
                    </div>

                    <div class="admin-chart-wrap">
                        <div class="admin-chart">
                            <canvas id="gestationStatusChart"></canvas>
                        </div>
                    </div>
                </div>
            </section>

            <div class="admin-divider"></div>

            <section class="admin-section">
                <div class="admin-section-head">
                    <div>
                        <h2>Farrowing Records</h2>
                        <p>Birth records and piglet statistics</p>
                    </div>
                    <a href="{{ route('farrowing.index') }}" class="admin-link">View All →</a>
                </div>

                <div class="admin-mini-grid">
                    <a href="{{ route('farrowing.index') }}" class="admin-mini-card">
                        <div class="admin-mini-label">Total Farrowings</div>
                        <div class="admin-mini-value">{{ number_format($totalFarrowings) }}</div>
                        <div class="admin-mini-sub">{{ number_format($farrowingsThisMonth) }} this month</div>
                    </a>

                    <a href="{{ route('farrowing.index') }}" class="admin-mini-card">
                        <div class="admin-mini-label">Total Born</div>
                        <div class="admin-mini-value">{{ number_format($totalBorn) }}</div>
                        <div class="admin-mini-sub">{{ number_format($avgLitterSize, 1) }} avg litter size</div>
                    </a>

                    <a href="{{ route('farrowing.index') }}" class="admin-mini-card">
                        <div class="admin-mini-label">Survival Rate</div>
                        <div class="admin-mini-value">{{ number_format($survivalRate, 1) }}%</div>
                        <div class="admin-mini-sub">{{ number_format($avgStillborns, 1) }} avg stillborns</div>
                    </a>

                    <a href="{{ route('farrowing.index') }}" class="admin-mini-card">
                        <div class="admin-mini-label">Avg Birth Weight</div>
                        <div class="admin-mini-value">{{ number_format($avgBirthWeight ?? 0, 2) }} kg</div>
                        <div class="admin-mini-sub">{{ number_format($farrowingsThisYear) }} this year</div>
                    </a>
                </div>

                <div class="admin-two-col">
                    <div class="admin-card">
                        <div class="admin-card-head">
                            <div>
                                <h3 class="admin-card-title">Farrowing Trends</h3>
                                <div class="admin-card-sub">Six-month farrowing activity</div>
                            </div>
                        </div>
                        <div class="admin-chart-wrap">
                            <div class="admin-chart">
                                <canvas id="farrowingTrendsChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="admin-card">
                        <div class="admin-card-head">
                            <div>
                                <h3 class="admin-card-title">Piglets Born</h3>
                                <div class="admin-card-sub">Six-month piglet birth totals</div>
                            </div>
                        </div>
                        <div class="admin-chart-wrap">
                            <div class="admin-chart">
                                <canvas id="pigletsBornChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="admin-divider"></div>

            <section class="admin-section">
                <div class="admin-section-head">
                    <div>
                        <h2>Weaning Management</h2>
                        <p>Piglet weaning and maturity tracking</p>
                    </div>
                    <a href="{{ route('weaning.dashboard') }}" class="admin-link">View All →</a>
                </div>

                <div class="admin-mini-grid">
                    <a href="{{ route('weaning.dashboard') }}" class="admin-mini-card">
                        <div class="admin-mini-label">Total Weaned</div>
                        <div class="admin-mini-value">{{ number_format($totalWeaned) }}</div>
                        <div class="admin-mini-sub">{{ number_format($weaningPercentage, 1) }}% weaning rate</div>
                    </a>

                    <a href="{{ route('weaning.dashboard') }}" class="admin-mini-card">
                        <div class="admin-mini-label">Ready to Wean</div>
                        <div class="admin-mini-value">{{ number_format($readyToWean) }}</div>
                        <div class="admin-mini-sub">{{ number_format($dueSoonWeaning) }} due soon</div>
                    </a>

                    <a href="{{ route('weaning.dashboard') }}" class="admin-mini-card">
                        <div class="admin-mini-label">Overdue</div>
                        <div class="admin-mini-value">{{ number_format($overdueWeaning) }}</div>
                        <div class="admin-mini-sub">Past {{ $weaningAge + 7 }} days</div>
                    </a>

                    <a href="{{ route('weaning.dashboard') }}" class="admin-mini-card">
                        <div class="admin-mini-label">Avg Weaning</div>
                        <div class="admin-mini-value">{{ number_format($avgWeaningAge ?? 0, 0) }} days</div>
                        <div class="admin-mini-sub">{{ number_format($avgWeaningWeight ?? 0, 2) }} kg avg weight</div>
                    </a>
                </div>

                <div class="admin-card">
                    <div class="admin-card-head">
                        <div>
                            <h3 class="admin-card-title">Weaning Activity</h3>
                            <div class="admin-card-sub">Six-month weaning trend</div>
                        </div>
                    </div>
                    <div class="admin-chart-wrap">
                        <div class="admin-chart">
                            <canvas id="weaningTrendsChart"></canvas>
                        </div>
                    </div>
                </div>
            </section>

            <div class="admin-divider"></div>

            <section class="admin-section">
                <div class="admin-section-head">
                    <div>
                        <h2>Weight Management</h2>
                        <p>Weight tracking, growth monitoring, and farm value</p>
                    </div>
                    <a href="{{ route('weights.index') }}" class="admin-link">View All →</a>
                </div>

                <div class="admin-mini-grid">
                    <a href="{{ route('weights.index') }}" class="admin-mini-card">
                        <div class="admin-mini-label">Total Records</div>
                        <div class="admin-mini-value">{{ number_format($totalWeights) }}</div>
                        <div class="admin-mini-sub">{{ number_format($weightsThisWeek) }} this week</div>
                    </a>

                    <a href="{{ route('weights.index') }}" class="admin-mini-card">
                        <div class="admin-mini-label">Overdue</div>
                        <div class="admin-mini-value">{{ number_format($overdueForWeighing) }}</div>
                        <div class="admin-mini-sub">{{ number_format($dueForWeighing) }} due soon</div>
                    </a>

                    <a href="{{ route('weights.index') }}" class="admin-mini-card">
                        <div class="admin-mini-label">Avg Weight</div>
                        <div class="admin-mini-value">{{ number_format($avgWeight ?? 0, 1) }} kg</div>
                        <div class="admin-mini-sub">{{ number_format($totalWeight ?? 0, 1) }} kg total</div>
                    </a>

                    <a href="{{ route('weights.index') }}" class="admin-mini-card">
                        <div class="admin-mini-label">Farm Value</div>
                        <div class="admin-mini-value">₵{{ number_format($totalFarmValue ?? 0, 0) }}</div>
                        <div class="admin-mini-sub">₵{{ number_format($liveWeightPrice, 2) }}/kg</div>
                    </a>
                </div>

                <div class="admin-two-col mb-6">
                    <div class="admin-card">
                        <div class="admin-card-head">
                            <div>
                                <h3 class="admin-card-title">Weight Trends</h3>
                                <div class="admin-card-sub">Last 30 days average weight movement</div>
                            </div>
                        </div>
                        <div class="admin-chart-wrap">
                            <div class="admin-chart">
                                <canvas id="weightTrendsChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="admin-card">
                        <div class="admin-card-head">
                            <div>
                                <h3 class="admin-card-title">Average Weight</h3>
                                <div class="admin-card-sub">Six-month average weight trend</div>
                            </div>
                        </div>
                        <div class="admin-chart-wrap">
                            <div class="admin-chart">
                                <canvas id="weightMonthlyChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="admin-divider"></div>

            <section class="admin-section">
                <div class="admin-section-head">
                    <div>
                        <h2>Health & Performance</h2>
                        <p>Health records and medication administration</p>
                    </div>
                    <a href="{{ route('health.index') }}" class="admin-link">View All →</a>
                </div>

                <div class="admin-mini-grid">
                    <a href="{{ route('health.index') }}" class="admin-mini-card">
                        <div class="admin-mini-label">Health Records</div>
                        <div class="admin-mini-value">{{ number_format($totalHealthRecords) }}</div>
                        <div class="admin-mini-sub">{{ number_format($healthRecordsThisMonth) }} this month</div>
                    </a>

                    <a href="{{ route('medications.index') }}" class="admin-mini-card">
                        <div class="admin-mini-label">Medications</div>
                        <div class="admin-mini-value">{{ number_format($totalMedications) }}</div>
                        <div class="admin-mini-sub">{{ number_format($medicationsThisMonth) }} this month</div>
                    </a>

                    <a href="{{ route('health.index') }}" class="admin-mini-card">
                        <div class="admin-mini-label">Critical Alerts</div>
                        <div class="admin-mini-value">{{ number_format($criticalAlerts) }}</div>
                        <div class="admin-mini-sub">{{ number_format($totalAlerts) }} total alerts</div>
                    </a>

                    <a href="{{ route('sales.index') }}" class="admin-mini-card">
                        <div class="admin-mini-label">Total Sales</div>
                        <div class="admin-mini-value">{{ number_format($totalSales) }}</div>
                        <div class="admin-mini-sub">₵{{ number_format($totalRevenue, 0) }} revenue</div>
                    </a>
                </div>

                <div class="admin-two-col">
                    <div class="admin-card">
                        <div class="admin-card-head">
                            <div>
                                <h3 class="admin-card-title">Top Health Conditions</h3>
                                <div class="admin-card-sub">Most frequent recorded conditions</div>
                            </div>
                        </div>

                        <div class="admin-list">
                            @forelse($topConditions as $condition)
                                <div class="admin-list-row">
                                    <span>{{ $condition->condition }}</span>
                                    <span>{{ $condition->count }} cases</span>
                                </div>
                            @empty
                                <div class="text-sm text-rose-600">No health records yet</div>
                            @endforelse
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
            </section>

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
                                            @if($isCritical) 🚨
                                            @elseif($isOverdue) ⏰
                                            @else ⚠️
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
                                        <span class="text-2xl mr-3">📝</span>
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
        new Chart(document.getElementById('stockTrendsChart'), {
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

        new Chart(document.getElementById('matingTrendsChart'), {
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

        new Chart(document.getElementById('gestationStatusChart'), {
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

        new Chart(document.getElementById('farrowingTrendsChart'), {
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

        new Chart(document.getElementById('pigletsBornChart'), {
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

        new Chart(document.getElementById('weaningTrendsChart'), {
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

        new Chart(document.getElementById('weightTrendsChart'), {
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

        new Chart(document.getElementById('weightMonthlyChart'), {
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

        new Chart(document.getElementById('healthTrendsChart'), {
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
            const ctx = document.getElementById('farmValueTrendChart').getContext('2d');
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