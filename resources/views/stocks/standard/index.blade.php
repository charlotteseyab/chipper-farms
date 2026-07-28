<x-app-layout>
    <style>
        .standard-index-page{
            min-height: 100vh;
            padding: 18px 0 40px;
        }

        .standard-index-shell{
            max-width: 1480px;
            margin: 0 auto;
            padding: 0 16px;
        }

        .standard-hero{
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 26px 28px;
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 48%, #0f766e 100%);
            box-shadow: 0 24px 54px rgba(37,99,235,.16);
            color: #fff;
            margin-bottom: 18px;
        }

        .standard-hero::before{
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,.16), transparent 28%),
                radial-gradient(circle at bottom left, rgba(255,255,255,.08), transparent 24%);
            pointer-events: none;
        }

        .standard-hero-inner{
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .standard-hero-title{
            margin: 0;
            font-size: clamp(1.8rem, 3vw, 2.7rem);
            line-height: 1.02;
            font-weight: 900;
            letter-spacing: -.04em;
        }

        .standard-hero-sub{
            margin: 10px 0 0;
            max-width: 760px;
            color: rgba(255,255,255,.90);
            font-size: .95rem;
            line-height: 1.75;
        }

        .standard-hero-actions{
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .standard-top-btn{
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
        }

        .standard-top-btn-primary{
            background: rgba(255,255,255,.14);
            color: #fff;
        }

        .standard-top-btn-primary:hover{
            background: rgba(255,255,255,.20);
            transform: translateY(-1px);
        }

        .standard-top-btn-purchase{
            background: #fff;
            color: #14532d;
            border-color: rgba(255,255,255,.85);
            box-shadow: 0 10px 24px rgba(15,23,42,.18);
        }

        .standard-top-btn-purchase:hover{
            background: #ecfdf5;
            transform: translateY(-1px);
        }

        .standard-card{
            background: rgba(255,255,255,.96);
            border: 1px solid #dbe7f0;
            border-radius: 28px;
            box-shadow: 0 14px 34px rgba(15,23,42,.06);
            overflow: hidden;
        }

        .standard-card-head{
            padding: 22px 24px 14px;
            border-bottom: 1px solid #edf2f7;
            background: linear-gradient(180deg, rgba(248,250,252,.95) 0%, rgba(255,255,255,.98) 100%);
        }

        .standard-card-title{
            margin: 0;
            font-size: 1.04rem;
            font-weight: 900;
            color: #0f172a;
        }

        .standard-card-sub{
            margin-top: 4px;
            font-size: .82rem;
            color: #64748b;
        }

        .standard-card-body{
            padding: 20px 24px 24px;
        }

        .standard-filter-grid{
            display: grid;
            grid-template-columns: repeat(5, minmax(0,1fr));
            gap: 14px;
            align-items: end;
        }

        .standard-field{
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .standard-label{
            font-size: .82rem;
            font-weight: 800;
            color: #334155;
        }

        .standard-input,
        .standard-select{
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 16px;
            background: #fff;
            padding: 11px 13px;
            font-size: .9rem;
            color: #0f172a;
            outline: none;
            transition: .2s ease;
        }

        .standard-input:focus,
        .standard-select:focus{
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37,99,235,.10);
        }

        .standard-filter-actions{
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .standard-btn{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 14px;
            padding: 11px 14px;
            font-size: .84rem;
            font-weight: 800;
            text-decoration: none;
            transition: .2s ease;
            border: none;
        }

        .standard-btn-blue{
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            box-shadow: 0 10px 24px rgba(37,99,235,.16);
        }

        .standard-btn-blue:hover{
            transform: translateY(-1px);
        }

        .standard-btn-green{
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: #fff;
            box-shadow: 0 10px 24px rgba(22,163,74,.16);
        }

        .standard-btn-green:hover{
            transform: translateY(-1px);
        }

        .standard-btn-red{
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: #fff;
            box-shadow: 0 10px 24px rgba(220,38,38,.14);
        }

        .standard-btn-red:hover{
            transform: translateY(-1px);
        }

        .standard-btn-gray{
            background: #e5e7eb;
            color: #374151;
        }

        .standard-btn-gray:hover{
            background: #d1d5db;
        }

        .standard-toolbar{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            padding: 16px 24px;
            background: #f8fafc;
            border-top: 1px solid #edf2f7;
            border-bottom: 1px solid #edf2f7;
        }

        .standard-toolbar-text{
            font-size: .84rem;
            color: #475569;
            font-weight: 700;
        }

        .standard-toolbar-text strong{
            color: #0f172a;
        }

        .standard-table-wrap{
            overflow-x: auto;
        }

        .standard-table{
            width: 100%;
            min-width: 1240px;
            border-collapse: separate;
            border-spacing: 0;
        }

        .standard-table thead th{
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

        .standard-table tbody td{
            padding: 16px 18px;
            border-bottom: 1px solid #eef2f7;
            vertical-align: top;
            font-size: .88rem;
            color: #0f172a;
            background: rgba(255,255,255,.96);
        }

        .standard-table tbody tr:hover td{
            background: #fbfdff;
        }

        .standard-code{
            font-weight: 900;
            color: #0f172a;
        }

        .standard-ear{
            font-weight: 800;
            color: #1d4ed8;
        }

        .standard-chip{
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: .74rem;
            font-weight: 800;
            line-height: 1;
        }

        .chip-blue{ background: #dbeafe; color: #1d4ed8; }
        .chip-green{ background: #dcfce7; color: #166534; }
        .chip-purple{ background: #f3e8ff; color: #7e22ce; }
        .chip-gray{ background: #f1f5f9; color: #475569; }
        .chip-yellow{ background: #fef9c3; color: #854d0e; }
        .chip-red{ background: #fee2e2; color: #b91c1c; }
        .chip-orange{ background: #ffedd5; color: #c2410c; }

        .standard-meta-stack{
            display: grid;
            gap: 4px;
            font-size: .8rem;
            color: #64748b;
        }

        .standard-meta-stack strong{
            color: #0f172a;
        }

        .standard-status-stack{
            display: grid;
            gap: 6px;
        }

        .standard-action-btn{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 12px;
            border-radius: 14px;
            border: 1px solid #d1d5db;
            background: #fff;
            color: #374151;
            font-size: .82rem;
            font-weight: 800;
            transition: .2s ease;
        }

        .standard-action-btn:hover{
            background: #f8fafc;
        }

        .standard-menu{
            width: 240px;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 18px 44px rgba(15,23,42,.12);
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }

        .standard-menu-item{
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

        .standard-menu-item:hover{
            background: #f8fafc;
        }

        .standard-menu-item.danger{
            color: #b91c1c;
        }

        .standard-menu-item.danger:hover{
            background: #fef2f2;
        }

        .standard-menu-note{
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            font-size: .8rem;
            color: #94a3b8;
            background: #fff;
        }

        .standard-empty{
            padding: 50px 18px;
            text-align: center;
        }

        .standard-empty-icon{
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            color: #94a3b8;
        }

        .standard-empty-title{
            font-size: 1.08rem;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .standard-empty-text{
            max-width: 620px;
            margin: 0 auto;
            color: #64748b;
            line-height: 1.75;
            font-size: .92rem;
        }

        .standard-footer{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            padding: 16px 24px;
            border-top: 1px solid #edf2f7;
            background: #fff;
        }

        .standard-footer-text{
            font-size: .84rem;
            color: #475569;
            font-weight: 700;
        }

        @media (max-width: 1200px){
            .standard-filter-grid{
                grid-template-columns: repeat(2, minmax(0,1fr));
            }
        }

        @media (max-width: 768px){
            .standard-index-shell{
                padding: 0 12px;
            }

            .standard-hero{
                padding: 22px 18px;
                border-radius: 24px;
            }

            .standard-card-head,
            .standard-card-body,
            .standard-toolbar,
            .standard-footer{
                padding-left: 18px;
                padding-right: 18px;
            }

            .standard-filter-grid{
                grid-template-columns: 1fr;
            }

            .standard-filter-actions,
            .standard-hero-actions{
                width: 100%;
            }

            .standard-filter-actions > *,
            .standard-hero-actions > *{
                flex: 1 1 100%;
            }
        }
    </style>

    <div class="standard-index-page">
        <div class="standard-index-shell">
            <div class="standard-hero">
                <div class="standard-hero-inner">
                    <div>
                        <h1 class="standard-hero-title">Standard Stock Management</h1>
                        <p class="standard-hero-sub">
                            Manage standard pigs for fattening, sale readiness, and possible promotion into breeding stock.
                            This view keeps the current workflow intact while making the page cleaner and easier to use.
                        </p>
                    </div>

                    <div class="standard-hero-actions">
                        <a href="{{ route('all-stocks.create') }}" class="standard-top-btn standard-top-btn-primary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            Add New Stock
                        </a>
                        <a href="{{ route('purchase-stock-intakes.create') }}" class="standard-top-btn standard-top-btn-purchase" style="background:#ffffff !important;color:#14532d !important;border:2px solid #ffffff !important;box-shadow:0 12px 28px rgba(15,23,42,.28) !important;">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h11v10H3V7Zm11 3h3l3 3v4h-6v-7Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 19a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm10 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"/>
                            </svg>
                            Add Purchased Stock
                        </a>
                    </div>
                </div>
            </div>

            <div class="standard-card">
                <div class="standard-card-head">
                    <h3 class="standard-card-title">Filters & Exports</h3>
                    <div class="standard-card-sub">Filter the standard stock list and export the records you need.</div>
                </div>

                <div class="standard-card-body">
                    <form method="GET" action="{{ route('standard-stocks.index') }}" id="filterForm">
                        <div class="standard-filter-grid">
                            <div class="standard-field">
                                <label class="standard-label">Breed</label>
                                <select name="breed" id="breedFilter" class="standard-select">
                                    <option value="">All Breeds</option>
                                    <option value="LW" {{ request('breed') == 'LW' ? 'selected' : '' }}>Large White</option>
                                    <option value="DR" {{ request('breed') == 'DR' ? 'selected' : '' }}>Duroc</option>
                                    <option value="LWDR" {{ request('breed') == 'LWDR' ? 'selected' : '' }}>Large White Duroc</option>
                                </select>
                            </div>

                            <div class="standard-field">
                                <label class="standard-label">Gender</label>
                                <select name="gender" id="genderFilter" class="standard-select">
                                    <option value="">All Genders</option>
                                    <option value="M" {{ request('gender') == 'M' ? 'selected' : '' }}>Male</option>
                                    <option value="F" {{ request('gender') == 'F' ? 'selected' : '' }}>Female</option>
                                </select>
                            </div>

                            <div class="standard-field">
                                <label class="standard-label">Min Age (days)</label>
                                <input type="number" name="age" id="ageFilter" value="{{ request('age') }}" class="standard-input" placeholder="e.g. 365">
                            </div>

                            <div class="standard-field">
                                <label class="standard-label">Actions</label>
                                <div class="standard-filter-actions">
                                    <button type="submit" class="standard-btn standard-btn-blue">Apply Filters</button>
                                    <a href="{{ route('standard-stocks.index') }}" class="standard-btn standard-btn-gray">Clear</a>
                                </div>
                            </div>

                            <div class="standard-field">
                                <label class="standard-label">Export</label>
                                <div class="standard-filter-actions">
                                    <a href="{{ route('standard-stocks.export.csv', request()->query()) }}" class="standard-btn standard-btn-green">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        CSV
                                    </a>
                                    <a href="{{ route('standard-stocks.export.pdf', request()->query()) }}" class="standard-btn standard-btn-red">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        PDF
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="standard-toolbar">
                    <div class="standard-toolbar-text">
                        @if($pigmanagement->total() > 0)
                            <strong>{{ $pigmanagement->total() }}</strong> standard pig{{ $pigmanagement->total() !== 1 ? 's' : '' }}
                            @if(request()->hasAny(['breed', 'gender', 'age']))
                                found with current filters
                            @else
                                in the system
                            @endif
                        @else
                            No standard pigs found
                        @endif
                    </div>

                    @if($pigmanagement->hasPages())
                        <div class="standard-toolbar-text">
                            Page <strong>{{ $pigmanagement->currentPage() }}</strong> of <strong>{{ $pigmanagement->lastPage() }}</strong>
                        </div>
                    @endif
                </div>

                <div class="standard-table-wrap">
                    <table class="standard-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Ear Tag</th>
                                <th>Breed</th>
                                <th>Gender</th>
                                <th>Age</th>
                                <th>Breeding Ready</th>
                                <th>Sale Status</th>
                                <th>Parents</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pigmanagement as $pig)
                                @php
                                    $readiness = $pig->breeding_readiness;
                                    $saleEligibility = $pig->sale_eligibility;
                                    $saleStatus = $pig->sale_status;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="standard-code">{{ $pig->code }}</div>
                                    </td>

                                    <td>
                                        <div class="standard-ear">{{ $pig->ear_tag_number }}</div>
                                    </td>

                                    <td>
                                        <span class="standard-chip {{ $pig->breed === 'LW' ? 'chip-blue' : ($pig->breed === 'DR' ? 'chip-green' : 'chip-purple') }}">
                                            {{ $pig->breed }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="standard-chip chip-gray">
                                            {{ $pig->gender === 'M' ? 'Male' : 'Female' }}
                                        </span>
                                    </td>

                                    <td title="{{ $pig->current_age_in_days }} days">
                                        <div class="standard-code">{{ $pig->age_compact }}</div>
                                        @if($pig->current_age_in_days < 0)
                                            <div class="text-red-500 text-xs mt-1">Invalid date</div>
                                        @endif
                                    </td>

                                    <td>
                                        <div class="standard-status-stack">
                                            @if($readiness['age_status'] === 'optimal')
                                                <span class="standard-chip chip-green">Optimal</span>
                                            @elseif($readiness['age_status'] === 'minimum_met')
                                                <span class="standard-chip chip-yellow">Warning Ready</span>
                                            @elseif($readiness['age_status'] === 'too_young')
                                                <span class="standard-chip chip-blue">⏳ Too Young</span>
                                            @elseif($readiness['age_status'] === 'too_old')
                                                <span class="standard-chip chip-gray">Too old Too Old</span>
                                            @endif

                                            @if($readiness['factors']['weight']['status'] !== 'not_checked')
                                                <div class="text-xs text-gray-500">
                                                    Weight:
                                                    @if($readiness['factors']['weight']['status'] === 'optimal')
                                                        OK
                                                    @elseif($readiness['factors']['weight']['status'] === 'minimum_met')
                                                        Warning
                                                    @else
                                                        No
                                                    @endif
                                                    @if($pig->latestWeight)
                                                        {{ $pig->latestWeight->formatted_weight }}
                                                    @endif
                                                </div>
                                            @else
                                                <div class="text-xs text-gray-400">No weight data</div>
                                            @endif
                                        </div>
                                    </td>

                                    <td>
                                        <div class="standard-status-stack">
                                            <span class="standard-chip {{ $saleStatus['status'] === 'sold' ? 'chip-blue' : ($saleEligibility['eligible'] ? 'chip-green' : 'chip-orange') }}">
                                                {{ ucfirst($saleStatus['status']) }}
                                            </span>

                                            @if($saleStatus['sold'])
                                                <div class="text-xs text-gray-500">{{ $saleStatus['sale_price'] }}</div>
                                            @else
                                                <div class="text-xs text-gray-500">{{ $saleEligibility['reason'] }}</div>
                                            @endif
                                        </div>
                                    </td>

                                    <td>
                                        @php
                                            $father = $pig->father_id ? $pig->father->ear_tag_number ?? 'N/A' : 'N/A';
                                            $mother = $pig->mother_id ? $pig->mother->ear_tag_number ?? 'N/A' : 'N/A';
                                        @endphp
                                        <div class="standard-meta-stack">
                                            <div><strong>F:</strong> {{ $father }}</div>
                                            <div><strong>M:</strong> {{ $mother }}</div>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="relative" x-data="{ open: false }">
                                            <button @click="open = !open" class="standard-action-btn">
                                                Actions
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                </svg>
                                            </button>

                                            <div x-show="open"
                                                 @click.away="open = false"
                                                 x-transition:enter="transition ease-out duration-100"
                                                 x-transition:enter-start="transform opacity-0 scale-95"
                                                 x-transition:enter-end="transform opacity-100 scale-100"
                                                 x-transition:leave="transition ease-in duration-75"
                                                 x-transition:leave-start="transform opacity-100 scale-100"
                                                 x-transition:leave-end="transform opacity-0 scale-95"
                                                 class="absolute right-0 z-10 mt-2 standard-menu">

                                                <a href="{{ route('pigs.show', $pig->id) }}" class="standard-menu-item">
                                                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                    </svg>
                                                    View Details
                                                </a>

                                                @if(!$saleStatus['sold'])
                                                    <a href="{{ route('standard-stocks.edit', $pig->id) }}" class="standard-menu-item">
                                                        <svg class="mr-3 h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                        </svg>
                                                        Edit
                                                    </a>

                                                    @if(in_array($readiness['age_status'], ['minimum_met', 'optimal']) && $readiness['factors']['weight']['status'] !== 'too_light')
                                                        <form action="{{ route('pigs.promote-to-breeding', $pig->id) }}" method="POST" onsubmit="return confirm('Promote {{ $pig->code }} to breeding stock?');">
                                                            @csrf
                                                            <button type="submit" class="standard-menu-item">
                                                                <svg class="mr-3 h-4 w-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
                                                                </svg>
                                                                Promote to Breeding
                                                            </button>
                                                        </form>
                                                    @else
                                                        <div class="standard-menu-note" title="{{ $readiness['age_message'] }}">
                                                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                                            </svg>
                                                            Not Ready for Promotion
                                                        </div>
                                                    @endif

                                                    @if($saleEligibility['eligible'])
                                                        <a href="{{ route('pigs.sale-form', $pig->id) }}" class="standard-menu-item">
                                                            <svg class="mr-3 h-4 w-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                                            </svg>
                                                            Record Sale
                                                        </a>
                                                    @else
                                                        <div class="standard-menu-note" title="{{ $saleEligibility['reason'] }}">
                                                            <span>{{ $saleEligibility['icon'] }}</span>
                                                            Cannot Sell
                                                        </div>
                                                    @endif

                                                    <div class="border-t border-gray-100"></div>

                                                    <form action="#" method="POST" onsubmit="return confirm('Are you sure you want to delete this pig?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="standard-menu-item danger">
                                                            <svg class="mr-3 h-4 w-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                            </svg>
                                                            Delete
                                                        </button>
                                                    </form>
                                                @else
                                                    <div class="standard-menu-note">
                                                        <svg class="mr-3 h-4 w-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                        </svg>
                                                        Sold
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9">
                                        <div class="standard-empty">
                                            <svg class="standard-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.562M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.636 6.636a9 9 0 000 12.728"></path>
                                            </svg>

                                            <div class="standard-empty-title">No standard stock found</div>

                                            <div class="standard-empty-text">
                                                @if(request()->hasAny(['breed', 'gender', 'age']))
                                                    No standard pigs match your current filter criteria. Try adjusting your filters or
                                                    <a href="{{ route('standard-stocks.index') }}" class="text-blue-600 hover:underline font-medium">clear all filters</a>.
                                                @else
                                                    There are no standard stock records in the system yet.
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($pigmanagement->hasPages())
                    <div class="standard-footer">
                        <div class="standard-footer-text">
                            Showing {{ $pigmanagement->firstItem() }} to {{ $pigmanagement->lastItem() }} of {{ $pigmanagement->total() }} results
                        </div>
                        <div>
                            <x-simple-pagination :paginator="$pigmanagement" />
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('filterForm');
            const breedFilter = document.getElementById('breedFilter');
            const genderFilter = document.getElementById('genderFilter');
            const ageFilter = document.getElementById('ageFilter');

            breedFilter.addEventListener('change', function() {
                form.submit();
            });

            genderFilter.addEventListener('change', function() {
                form.submit();
            });

            let ageTimeout;
            ageFilter.addEventListener('input', function() {
                clearTimeout(ageTimeout);
                ageTimeout = setTimeout(function() {
                    form.submit();
                }, 500);
            });

            ageFilter.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    clearTimeout(ageTimeout);
                    form.submit();
                }
            });
        });
    </script>
</x-app-layout>
