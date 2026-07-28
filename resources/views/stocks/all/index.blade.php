<x-app-layout>
    <style>
        .allstock-page{
            min-height: 100vh;
            padding: 18px 0 40px;
        }

        .allstock-shell{
            max-width: 1550px;
            margin: 0 auto;
            padding: 0 16px;
        }

        .allstock-hero{
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 26px 28px;
            background: linear-gradient(135deg, #14532d 0%, #166534 48%, #0f766e 100%);
            box-shadow: 0 24px 54px rgba(22,101,52,.16);
            color: #fff;
            margin-bottom: 18px;
        }

        .allstock-hero::before{
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,.16), transparent 28%),
                radial-gradient(circle at bottom left, rgba(255,255,255,.08), transparent 24%);
            pointer-events: none;
        }

        .allstock-hero-inner{
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .allstock-hero-title{
            margin: 0;
            font-size: clamp(1.8rem, 3vw, 2.8rem);
            line-height: 1.02;
            font-weight: 900;
            letter-spacing: -.04em;
        }

        .allstock-hero-sub{
            margin: 10px 0 0;
            max-width: 800px;
            color: rgba(255,255,255,.90);
            font-size: .95rem;
            line-height: 1.75;
        }

        .allstock-hero-actions{
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .allstock-top-btn{
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

        .allstock-top-btn-primary{
            background: rgba(255,255,255,.14);
            color: #fff;
        }

        .allstock-top-btn-primary:hover{
            background: rgba(255,255,255,.20);
            transform: translateY(-1px);
        }

        .allstock-top-btn-purchase{
            background: #fff;
            color: #14532d;
            border-color: rgba(255,255,255,.85);
            box-shadow: 0 10px 24px rgba(15,23,42,.18);
        }

        .allstock-top-btn-purchase:hover{
            background: #ecfdf5;
            transform: translateY(-1px);
        }

        .allstock-card{
            background: rgba(255,255,255,.96);
            border: 1px solid #dbe7f0;
            border-radius: 28px;
            box-shadow: 0 14px 34px rgba(15,23,42,.06);
            overflow: hidden;
        }

        .allstock-card-head{
            padding: 22px 24px 14px;
            border-bottom: 1px solid #edf2f7;
            background: linear-gradient(180deg, rgba(248,250,252,.95) 0%, rgba(255,255,255,.98) 100%);
        }

        .allstock-card-title{
            margin: 0;
            font-size: 1.04rem;
            font-weight: 900;
            color: #0f172a;
        }

        .allstock-card-sub{
            margin-top: 4px;
            font-size: .82rem;
            color: #64748b;
        }

        .allstock-card-body{
            padding: 20px 24px 24px;
        }

        .allstock-filter-grid{
            display: grid;
            grid-template-columns: 1.4fr 1fr 1fr 1fr auto;
            gap: 14px;
            align-items: end;
        }

        .allstock-field{
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .allstock-label{
            font-size: .82rem;
            font-weight: 800;
            color: #334155;
        }

        .allstock-input,
        .allstock-select{
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

        .allstock-input:focus,
        .allstock-select:focus{
            border-color: #16a34a;
            box-shadow: 0 0 0 4px rgba(22,163,74,.10);
        }

        .allstock-filter-actions{
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .allstock-btn{
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

        .allstock-btn-green{
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: #fff;
            box-shadow: 0 10px 24px rgba(22,163,74,.16);
        }

        .allstock-btn-green:hover{
            transform: translateY(-1px);
        }

        .allstock-btn-blue{
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            box-shadow: 0 10px 24px rgba(37,99,235,.16);
        }

        .allstock-btn-blue:hover{
            transform: translateY(-1px);
        }

        .allstock-btn-red{
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: #fff;
            box-shadow: 0 10px 24px rgba(220,38,38,.14);
        }

        .allstock-btn-red:hover{
            transform: translateY(-1px);
        }

        .allstock-btn-gray{
            background: #e5e7eb;
            color: #374151;
        }

        .allstock-btn-gray:hover{
            background: #d1d5db;
        }

        .allstock-toolbar{
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

        .allstock-toolbar-text{
            font-size: .84rem;
            color: #475569;
            font-weight: 700;
        }

        .allstock-toolbar-text strong{
            color: #0f172a;
        }

        .allstock-export-actions{
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .allstock-table-wrap{
            overflow-x: auto;
        }

        .allstock-table{
            width: 100%;
            min-width: 1380px;
            border-collapse: separate;
            border-spacing: 0;
        }

        .allstock-table thead th{
            background: #ecfdf5;
            padding: 14px 18px;
            font-size: .73rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #166534;
            text-align: left;
            border-bottom: 1px solid #d1fae5;
        }

        .allstock-table tbody td{
            padding: 16px 18px;
            border-bottom: 1px solid #eef2f7;
            vertical-align: top;
            font-size: .88rem;
            color: #0f172a;
            background: rgba(255,255,255,.96);
        }

        .allstock-table tbody tr:hover td{
            background: #fbfefd;
        }

        .allstock-code{
            font-weight: 900;
            color: #0f172a;
        }

        .allstock-ear{
            font-weight: 800;
            color: #14532d;
        }

        .allstock-chip{
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: .74rem;
            font-weight: 800;
            line-height: 1;
        }

        .chip-breed{ background: #f1f5f9; color: #334155; }
        .chip-breeding{ background: #dcfce7; color: #166534; }
        .chip-standard{ background: #dbeafe; color: #1d4ed8; }
        .chip-male{ background: #dbeafe; color: #1d4ed8; }
        .chip-female{ background: #fce7f3; color: #be185d; }

        .allstock-meta-stack{
            display: grid;
            gap: 4px;
            font-size: .8rem;
            color: #64748b;
        }

        .allstock-meta-stack strong{
            color: #0f172a;
        }

        .allstock-weight-badge{
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: .74rem;
            font-weight: 800;
            line-height: 1;
        }

        .allstock-action-btn{
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

        .allstock-action-btn:hover{
            background: #f8fafc;
        }

        .allstock-menu{
            width: 220px;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 18px 44px rgba(15,23,42,.12);
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }

        .allstock-menu-item{
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

        .allstock-menu-item:hover{
            background: #f8fafc;
        }

        .allstock-menu-item.danger{
            color: #b91c1c;
        }

        .allstock-menu-item.danger:hover{
            background: #fef2f2;
        }

        .allstock-empty{
            padding: 50px 18px;
            text-align: center;
        }

        .allstock-empty-icon{
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            color: #94a3b8;
        }

        .allstock-empty-title{
            font-size: 1.08rem;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .allstock-empty-text{
            max-width: 620px;
            margin: 0 auto;
            color: #64748b;
            line-height: 1.75;
            font-size: .92rem;
        }

        .allstock-footer{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            padding: 16px 24px;
            border-top: 1px solid #edf2f7;
            background: #fff;
        }

        .allstock-footer-text{
            font-size: .84rem;
            color: #475569;
            font-weight: 700;
        }

        @media (max-width: 1200px){
            .allstock-filter-grid{
                grid-template-columns: repeat(2, minmax(0,1fr));
            }
        }

        @media (max-width: 768px){
            .allstock-shell{
                padding: 0 12px;
            }

            .allstock-hero{
                padding: 22px 18px;
                border-radius: 24px;
            }

            .allstock-card-head,
            .allstock-card-body,
            .allstock-toolbar,
            .allstock-footer{
                padding-left: 18px;
                padding-right: 18px;
            }

            .allstock-filter-grid{
                grid-template-columns: 1fr;
            }

            .allstock-filter-actions,
            .allstock-export-actions,
            .allstock-hero-actions{
                width: 100%;
            }

            .allstock-filter-actions > *,
            .allstock-export-actions > *,
            .allstock-hero-actions > *{
                flex: 1 1 100%;
            }
        }
    </style>

    <div class="allstock-page">
        <div class="allstock-shell">
            <div class="allstock-hero">
                <div class="allstock-hero-inner">
                    <div>
                        <h1 class="allstock-hero-title">All Stock Management</h1>
                        <p class="allstock-hero-sub">
                            View and manage all pigs in one place, with filters for stock type, breed, gender, and a full search
                            that works across the complete dataset, not just the current page.
                        </p>
                    </div>

                    <div class="allstock-hero-actions">
                        <a href="{{ route('all-stocks.create') }}" class="allstock-top-btn allstock-top-btn-primary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            Add New Stock
                        </a>
                        <a href="{{ route('purchase-stock-intakes.create') }}" class="allstock-top-btn allstock-top-btn-purchase" style="background:#ffffff !important;color:#14532d !important;border:2px solid #ffffff !important;box-shadow:0 12px 28px rgba(15,23,42,.28) !important;">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h11v10H3V7Zm11 3h3l3 3v4h-6v-7Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 19a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm10 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"/>
                            </svg>
                            Add Purchased Stock
                        </a>
                    </div>
                </div>
            </div>

            <div class="allstock-card">
                <div class="allstock-card-head">
                    <h3 class="allstock-card-title">Search, Filters & Exports</h3>
                    <div class="allstock-card-sub">Search the full stock database and narrow the results with the filters below.</div>
                </div>

                <div class="allstock-card-body">
                    <form method="GET" action="{{ route('all-stocks.index') }}" id="filterForm">
                        <div class="allstock-filter-grid">
                            <div class="allstock-field">
                                <label class="allstock-label">Search</label>
                                <input
                                    type="text"
                                    name="q"
                                    id="quickSearch"
                                    value="{{ request('q', $q ?? '') }}"
                                    placeholder="Search code, ear tag, breed, type, room, parents..."
                                    class="allstock-input"
                                >
                            </div>

                            <div class="allstock-field">
                                <label class="allstock-label">Stock Type</label>
                                <select name="stock_type" id="stockTypeFilter" class="allstock-select">
                                    <option value="">All Types</option>
                                    <option value="breeding" {{ request('stock_type') == 'breeding' ? 'selected' : '' }}>Breeding Stock</option>
                                    <option value="standard" {{ request('stock_type') == 'standard' ? 'selected' : '' }}>Standard Stock</option>
                                </select>
                            </div>

                            <div class="allstock-field">
                                <label class="allstock-label">Breed</label>
                                <select name="breed" id="breedFilter" class="allstock-select">
                                    <option value="">All Breeds</option>
                                    <option value="LW" {{ request('breed') == 'LW' ? 'selected' : '' }}>Large White</option>
                                    <option value="DR" {{ request('breed') == 'DR' ? 'selected' : '' }}>Duroc</option>
                                    <option value="LWDR" {{ request('breed') == 'LWDR' ? 'selected' : '' }}>Large White Duroc</option>
                                </select>
                            </div>

                            <div class="allstock-field">
                                <label class="allstock-label">Gender</label>
                                <select name="gender" id="genderFilter" class="allstock-select">
                                    <option value="">All Genders</option>
                                    <option value="M" {{ request('gender') == 'M' ? 'selected' : '' }}>Male</option>
                                    <option value="F" {{ request('gender') == 'F' ? 'selected' : '' }}>Female</option>
                                </select>
                            </div>

                            <div class="allstock-field">
                                <label class="allstock-label">Actions</label>
                                <div class="allstock-filter-actions">
                                    <button type="submit" class="allstock-btn allstock-btn-green">Apply</button>
                                    <a href="{{ route('all-stocks.index') }}" class="allstock-btn allstock-btn-gray">Clear</a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="allstock-toolbar">
                    <div class="allstock-toolbar-text">
                        @if($pigmanagement->total() > 0)
                            <strong>{{ $pigmanagement->total() }}</strong> pig{{ $pigmanagement->total() !== 1 ? 's' : '' }}
                            @if(request()->hasAny(['q', 'breed', 'gender', 'stock_type', 'has_weights', 'created_from', 'created_to', 'has_recent_weigh_date', 'recent_weigh_days', 'has_recent_weight', 'recent_weight_days']))
                                found with current filters
                            @else
                                in the system
                            @endif
                        @else
                            No pigs found
                        @endif
                    </div>

                    <div class="allstock-export-actions">
                        <a href="{{ route('all-stocks.import.sample') }}" class="allstock-btn allstock-btn-gray">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0 4-4m-4 4-4-4M4 19h16"/>
                            </svg>
                            Download Sample
                        </a>
                        <a href="{{ route('all-stocks.import.create') }}" class="allstock-btn allstock-btn-green">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21V9m0 0 4 4m-4-4-4 4M5 3h14"/>
                            </svg>
                            Import Stock
                        </a>
                        <a href="{{ route('all-stocks.export.excel', request()->query()) }}" class="allstock-btn allstock-btn-green">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Export Stock
                        </a>
                        <a href="{{ route('all-stocks.export.pdf', request()->query()) }}" class="allstock-btn allstock-btn-red">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Export PDF
                        </a>
                    </div>
                </div>

                <div class="allstock-table-wrap">
                    <table class="allstock-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Ear Tag</th>
                                <th>Breed</th>
                                <th>Type</th>
                                <th>Gender</th>
                                <th>DOB</th>
                                <th>Age</th>
                                <th>Location</th>
                                <th>Weight</th>
                                <th>Parents</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pigmanagement as $pig)
                                <tr>
                                    <td><div class="allstock-code">{{ $pig->code }}</div></td>

                                    <td><div class="allstock-ear">{{ $pig->ear_tag_number }}</div></td>

                                    <td>
                                        <span class="allstock-chip chip-breed">
                                            {{ $pig->breed_display ?? $pig->breed }}
                                        </span>
                                    </td>

                                    <td>
                                        @if($pig->stock_type === 'breeding')
                                            <span class="allstock-chip chip-breeding">Breeding</span>
                                        @else
                                            <span class="allstock-chip chip-standard">Standard</span>
                                        @endif
                                    </td>

                                    <td>
                                        @if($pig->gender === 'M')
                                            <span class="allstock-chip chip-male">Male</span>
                                        @else
                                            <span class="allstock-chip chip-female">Female</span>
                                        @endif
                                    </td>

                                    <td>{{ optional($pig->date_of_birth)->format('Y-m-d') ?? 'N/A' }}</td>

                                    <td>{{ $pig->age_compact }}</td>

                                    <td>
                                        @if($pig->room && $pig->room->structure)
                                            <div class="allstock-meta-stack">
                                                <div><strong>{{ $pig->room->structure->name }}</strong></div>
                                                <div>{{ $pig->room->name }}</div>
                                            </div>
                                        @else
                                            <span class="text-gray-400 text-sm">N/A</span>
                                        @endif
                                    </td>

                                    <td>
                                        @if($pig->recentWeightAlerts->count() > 0)
                                            @php
                                                $alert = $pig->recentWeightAlerts->first();
                                                $alertConfig = [
                                                    'under_weight' => ['icon' => 'Under', 'color' => 'text-blue-700', 'bg' => 'bg-blue-100', 'text' => 'Under'],
                                                    'over_weight' => ['icon' => 'Over', 'color' => 'text-orange-700', 'bg' => 'bg-orange-100', 'text' => 'Over'],
                                                    'normal' => ['icon' => 'OK', 'color' => 'text-green-700', 'bg' => 'bg-green-100', 'text' => 'Normal'],
                                                    'no_standard' => ['icon' => 'None', 'color' => 'text-gray-700', 'bg' => 'bg-gray-100', 'text' => 'No Std'],
                                                ];
                                                $config = $alertConfig[$alert->alert_type] ?? $alertConfig['no_standard'];
                                            @endphp

                                            <span class="allstock-weight-badge {{ $config['bg'] }} {{ $config['color'] }}">
                                                {{ $config['icon'] }} {{ $config['text'] }}
                                                @if($alert->severity === 'high')
                                                    <span class="text-red-600 font-black">!</span>
                                                @endif
                                            </span>
                                        @else
                                            <span class="allstock-weight-badge bg-gray-50 text-gray-500 border border-gray-200">
                                                No Data
                                            </span>
                                        @endif
                                    </td>

                                    <td>
                                        @php
                                            $father = $pig->father_id ? $pig->father->ear_tag_number ?? 'N/A' : 'N/A';
                                            $mother = $pig->mother_id ? $pig->mother->ear_tag_number ?? 'N/A' : 'N/A';
                                        @endphp
                                        <div class="allstock-meta-stack">
                                            <div><strong>F:</strong> {{ $father }}</div>
                                            <div><strong>M:</strong> {{ $mother }}</div>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="relative inline-block text-left" x-data="{ open: false, place: 'down', toggle(){ this.open = !this.open; if(!this.open){ return; } this.$nextTick(() => { const menu = this.$refs.menu; const trigger = this.$refs.trigger; if(!menu || !trigger) return; const mr = menu.getBoundingClientRect(); const tr = trigger.getBoundingClientRect(); const below = window.innerHeight - tr.bottom; const above = tr.top; this.place = (below < mr.height + 16 && above > mr.height) ? 'up' : 'down'; }); } }">
                                            <button x-ref="trigger" @click="toggle()" class="allstock-action-btn">
                                                Actions
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                </svg>
                                            </button>

                                            <div x-ref="menu"
                                                 x-show="open"
                                                 @click.away="open = false"
                                                 x-transition:enter="transition ease-out duration-100"
                                                 x-transition:enter-start="transform opacity-0 scale-95"
                                                 x-transition:enter-end="transform opacity-100 scale-100"
                                                 x-transition:leave="transition ease-in duration-75"
                                                 x-transition:leave-start="transform opacity-100 scale-100"
                                                 x-transition:leave-end="transform opacity-0 scale-95"
                                                 :class="place === 'down' ? 'origin-top-right mt-2 top-full' : 'origin-bottom-right mb-2 bottom-full'"
                                                 class="absolute right-0 z-50 allstock-menu">

                                                <a href="{{ route('pigs.show', $pig->id) }}" class="allstock-menu-item">View Details</a>

                                                <a href="{{ route('weights.create') }}?pig_id={{ $pig->id }}" class="allstock-menu-item">Record Weight</a>

                                                <a href="{{ route('breeding-stocks.edit', $pig) }}" class="allstock-menu-item">Edit Pig</a>

                                                @if($pig->stock_type === 'breeding')
                                                    <a href="{{ route('mating.manage') }}" class="allstock-menu-item">Manage Mating</a>
                                                @endif

                                                <div class="border-t border-gray-100"></div>

                                                <form action="{{ route('breeding-stocks.destroy', $pig) }}" method="POST" class="inline w-full">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            onclick="return confirm('Are you sure you want to delete {{ $pig->ear_tag_number }}? This action cannot be undone.')"
                                                            class="allstock-menu-item danger">
                                                        Delete Pig
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11">
                                        <div class="allstock-empty">
                                            <svg class="allstock-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.562M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.636 6.636a9 9 0 000 12.728"></path>
                                            </svg>
                                            <div class="allstock-empty-title">No stock found</div>
                                            <div class="allstock-empty-text">
                                                @if(request()->hasAny(['q', 'breed', 'gender', 'stock_type']))
                                                    No pigs match your current search and filter criteria. Try changing the filters or clearing them.
                                                @else
                                                    There are no stock records in the system yet.
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
                    <div class="allstock-footer">
                        <div class="allstock-footer-text">
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
            const stockTypeFilter = document.getElementById('stockTypeFilter');
            const breedFilter = document.getElementById('breedFilter');
            const genderFilter = document.getElementById('genderFilter');
            const quickSearch = document.getElementById('quickSearch');

            stockTypeFilter.addEventListener('change', function() {
                form.submit();
            });

            breedFilter.addEventListener('change', function() {
                form.submit();
            });

            genderFilter.addEventListener('change', function() {
                form.submit();
            });

            let searchTimeout;
            quickSearch.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    form.submit();
                }, 450);
            });

            quickSearch.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    clearTimeout(searchTimeout);
                    form.submit();
                }
            });
        });
    </script>
</x-app-layout>
