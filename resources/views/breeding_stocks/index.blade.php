<x-app-layout>
    <style>
        .breed-index-page{
            min-height: 100vh;
            padding: 18px 0 40px;
        }

        .breed-index-shell{
            max-width: 1440px;
            margin: 0 auto;
            padding: 0 16px;
        }

        .breed-hero{
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 26px 28px;
            background: linear-gradient(135deg, #14532d 0%, #166534 48%, #15803d 100%);
            box-shadow: 0 24px 54px rgba(22,101,52,.16);
            color: #fff;
            margin-bottom: 18px;
        }

        .breed-hero::before{
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,.16), transparent 28%),
                radial-gradient(circle at bottom left, rgba(255,255,255,.08), transparent 24%);
            pointer-events: none;
        }

        .breed-hero-inner{
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .breed-hero-title{
            margin: 0;
            font-size: clamp(1.8rem, 3vw, 2.7rem);
            line-height: 1.02;
            font-weight: 900;
            letter-spacing: -.04em;
        }

        .breed-hero-sub{
            margin: 10px 0 0;
            max-width: 760px;
            color: rgba(255,255,255,.90);
            font-size: .95rem;
            line-height: 1.75;
        }

        .breed-hero-actions{
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .breed-top-btn{
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

        .breed-top-btn-primary{
            background: rgba(255,255,255,.14);
            color: #fff;
        }

        .breed-top-btn-primary:hover{
            background: rgba(255,255,255,.20);
            transform: translateY(-1px);
        }

        .breed-top-btn-purchase{
            background: #fff;
            color: #14532d;
            border-color: rgba(255,255,255,.85);
            box-shadow: 0 10px 24px rgba(15,23,42,.18);
        }

        .breed-top-btn-purchase:hover{
            background: #ecfdf5;
            transform: translateY(-1px);
        }

        .breed-top-btn-muted{
            background: rgba(255,255,255,.08);
            color: #ecfdf5;
        }

        .breed-top-btn-muted:hover{
            background: rgba(255,255,255,.16);
        }

        .breed-card{
            background: rgba(255,255,255,.96);
            border: 1px solid #dbe7f0;
            border-radius: 28px;
            box-shadow: 0 14px 34px rgba(15,23,42,.06);
            overflow: hidden;
        }

        .breed-card-head{
            padding: 22px 24px 14px;
            border-bottom: 1px solid #edf2f7;
            background: linear-gradient(180deg, rgba(248,250,252,.95) 0%, rgba(255,255,255,.98) 100%);
        }

        .breed-card-title{
            margin: 0;
            font-size: 1.04rem;
            font-weight: 900;
            color: #0f172a;
        }

        .breed-card-sub{
            margin-top: 4px;
            font-size: .82rem;
            color: #64748b;
        }

        .breed-card-body{
            padding: 20px 24px 24px;
        }

        .breed-filter-grid{
            display: grid;
            grid-template-columns: repeat(5, minmax(0,1fr));
            gap: 14px;
            align-items: end;
        }

        .breed-field{
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .breed-label{
            font-size: .82rem;
            font-weight: 800;
            color: #334155;
        }

        .breed-input,
        .breed-select{
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

        .breed-input:focus,
        .breed-select:focus{
            border-color: #16a34a;
            box-shadow: 0 0 0 4px rgba(22,163,74,.10);
        }

        .breed-filter-actions{
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .breed-btn{
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

        .breed-btn-green{
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: #fff;
            box-shadow: 0 10px 24px rgba(22,163,74,.16);
        }

        .breed-btn-green:hover{
            transform: translateY(-1px);
        }

        .breed-btn-gray{
            background: #e5e7eb;
            color: #374151;
        }

        .breed-btn-gray:hover{
            background: #d1d5db;
        }

        .breed-btn-red{
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: #fff;
            box-shadow: 0 10px 24px rgba(220,38,38,.14);
        }

        .breed-btn-red:hover{
            transform: translateY(-1px);
        }

        .breed-toolbar{
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

        .breed-toolbar-text{
            font-size: .84rem;
            color: #475569;
            font-weight: 700;
        }

        .breed-toolbar-text strong{
            color: #0f172a;
        }

        .breed-export-actions{
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .breed-table-wrap{
            overflow-x: auto;
        }

        .breed-table{
            width: 100%;
            min-width: 1100px;
            border-collapse: separate;
            border-spacing: 0;
        }

        .breed-table thead th{
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

        .breed-table tbody td{
            padding: 16px 18px;
            border-bottom: 1px solid #eef2f7;
            vertical-align: top;
            font-size: .88rem;
            color: #0f172a;
            background: rgba(255,255,255,.96);
        }

        .breed-table tbody tr:hover td{
            background: #fbfdff;
        }

        .breed-code{
            font-weight: 900;
            color: #0f172a;
        }

        .breed-ear{
            font-weight: 800;
            color: #14532d;
        }

        .breed-chip{
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: .74rem;
            font-weight: 800;
            line-height: 1;
        }

        .breed-chip-blue{
            background: #dbeafe;
            color: #1d4ed8;
        }

        .breed-chip-green{
            background: #dcfce7;
            color: #166534;
        }

        .breed-chip-purple{
            background: #f3e8ff;
            color: #7e22ce;
        }

        .breed-chip-gray{
            background: #f1f5f9;
            color: #475569;
        }

        .breed-meta-stack{
            display: grid;
            gap: 3px;
            font-size: .8rem;
            color: #64748b;
        }

        .breed-action-btn{
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

        .breed-action-btn:hover{
            background: #f8fafc;
        }

        .breed-menu{
            width: 220px;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 18px 44px rgba(15,23,42,.12);
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }

        .breed-menu-item{
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

        .breed-menu-item:hover{
            background: #f8fafc;
        }

        .breed-menu-item.danger{
            color: #b91c1c;
        }

        .breed-menu-item.danger:hover{
            background: #fef2f2;
        }

        .breed-menu-note{
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            font-size: .8rem;
            color: #94a3b8;
            background: #fff;
        }

        .breed-empty{
            padding: 50px 18px;
            text-align: center;
        }

        .breed-empty-icon{
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            color: #94a3b8;
        }

        .breed-empty-title{
            font-size: 1.08rem;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .breed-empty-text{
            max-width: 620px;
            margin: 0 auto;
            color: #64748b;
            line-height: 1.75;
            font-size: .92rem;
        }

        .breed-footer{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            padding: 16px 24px;
            border-top: 1px solid #edf2f7;
            background: #fff;
        }

        .breed-footer-text{
            font-size: .84rem;
            color: #475569;
            font-weight: 700;
        }

        @media (max-width: 1200px){
            .breed-filter-grid{
                grid-template-columns: repeat(2, minmax(0,1fr));
            }
        }

        @media (max-width: 768px){
            .breed-index-shell{
                padding: 0 12px;
            }

            .breed-hero{
                padding: 22px 18px;
                border-radius: 24px;
            }

            .breed-card-head,
            .breed-card-body,
            .breed-toolbar,
            .breed-footer{
                padding-left: 18px;
                padding-right: 18px;
            }

            .breed-filter-grid{
                grid-template-columns: 1fr;
            }

            .breed-filter-actions,
            .breed-export-actions,
            .breed-hero-actions{
                width: 100%;
            }

            .breed-filter-actions > *,
            .breed-export-actions > *,
            .breed-hero-actions > *{
                flex: 1 1 100%;
            }
        }
    </style>

    <div class="breed-index-page">
        <div class="breed-index-shell">
            <div class="breed-hero">
                <div class="breed-hero-inner">
                    <div>
                        <h1 class="breed-hero-title">Breeding Stock Management</h1>
                        <p class="breed-hero-sub">
                            Review, filter, export, and manage breeding pigs from one clean workspace.
                            This page keeps the current workflow intact while making daily management easier to scan and use.
                        </p>
                    </div>

                    <div class="breed-hero-actions">
                        <a href="{{ route('all-stocks.create') }}" class="breed-top-btn breed-top-btn-primary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            Add New Stock
                        </a>
                        <a href="{{ route('purchase-stock-intakes.create') }}" class="breed-top-btn breed-top-btn-purchase" style="background:#ffffff !important;color:#14532d !important;border:2px solid #ffffff !important;box-shadow:0 12px 28px rgba(15,23,42,.28) !important;">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h11v10H3V7Zm11 3h3l3 3v4h-6v-7Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 19a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm10 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"/>
                            </svg>
                            Add Purchased Stock
                        </a>
                    </div>
                </div>
            </div>

            <div class="breed-card">
                <div class="breed-card-head">
                    <h3 class="breed-card-title">Filters & Exports</h3>
                    <div class="breed-card-sub">Narrow the breeding stock list and export the exact records you need.</div>
                </div>

                <div class="breed-card-body">
                    <form method="GET" action="{{ route('breeding-stocks.index') }}" id="filterForm">
                        <div class="breed-filter-grid">
                            <div class="breed-field">
                                <label class="breed-label">Breed</label>
                                <select name="breed" id="breedFilter" class="breed-select" data-searchable-select>
                                    <option value="">All Breeds</option>
                                    @foreach($animalBreeds as $code => $name)
                                        <option value="{{ $code }}" @selected(request('breed') === $code)>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="breed-field">
                                <label class="breed-label">Gender</label>
                                <select name="gender" id="genderFilter" class="breed-select">
                                    <option value="">All Genders</option>
                                    <option value="M" {{ request('gender') == 'M' ? 'selected' : '' }}>Male</option>
                                    <option value="F" {{ request('gender') == 'F' ? 'selected' : '' }}>Female</option>
                                </select>
                            </div>

                            <div class="breed-field">
                                <label class="breed-label">Min Age (days)</label>
                                <input type="number" name="age" id="ageFilter" value="{{ request('age') }}" class="breed-input" placeholder="e.g. 365">
                            </div>

                            <div class="breed-field">
                                <label class="breed-label">Actions</label>
                                <div class="breed-filter-actions">
                                    <button type="submit" class="breed-btn breed-btn-green">Apply Filters</button>
                                    <a href="{{ route('breeding-stocks.index') }}" class="breed-btn breed-btn-gray">Clear</a>
                                </div>
                            </div>

                            <div class="breed-field">
                                <label class="breed-label">Export</label>
                                <div class="breed-export-actions">
                                    <a href="{{ route('breeding-stocks.export.csv', request()->query()) }}" class="breed-btn breed-btn-green">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        CSV
                                    </a>
                                    <a href="{{ route('breeding-stocks.export.pdf', request()->query()) }}" class="breed-btn breed-btn-red">
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

                <div class="breed-toolbar">
                    <div class="breed-toolbar-text">
                        @if($pigmanagement->total() > 0)
                            <strong>{{ $pigmanagement->total() }}</strong> breeding pig{{ $pigmanagement->total() !== 1 ? 's' : '' }}
                            @if(request()->hasAny(['breed', 'gender', 'age']))
                                found with current filters
                            @else
                                in the system
                            @endif
                        @else
                            No breeding pigs found
                        @endif
                    </div>

                    @if($pigmanagement->hasPages())
                        <div class="breed-toolbar-text">
                            Page <strong>{{ $pigmanagement->currentPage() }}</strong> of <strong>{{ $pigmanagement->lastPage() }}</strong>
                        </div>
                    @endif
                </div>

                <div class="breed-table-wrap">
                    <table class="breed-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Ear Tag</th>
                                <th>Breed</th>
                                <th>Gender</th>
                                <th>DOB</th>
                                <th>Age</th>
                                <th>Parents</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pigmanagement as $pig)
                                <tr>
                                    <td>
                                        <div class="breed-code">{{ $pig->code }}</div>
                                    </td>

                                    <td>
                                        <x-ear-tag-label :pig="$pig" />
                                    </td>

                                    <td>
                                        <span class="breed-chip {{ $pig->breed === 'LW' ? 'breed-chip-blue' : ($pig->breed === 'DR' ? 'breed-chip-green' : 'breed-chip-purple') }}">
                                            {{ $pig->breed }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="breed-chip breed-chip-gray">
                                            {{ $pig->gender === 'M' ? 'Male' : 'Female' }}
                                        </span>
                                    </td>

                                    <td>
                                        {{ $pig->date_of_birth->format('Y-m-d') }}
                                    </td>

                                    <td title="{{ $pig->current_age_in_days }} days">
                                        <div class="breed-code">{{ $pig->age_compact }}</div>
                                        @if($pig->current_age_in_days < 0)
                                            <div class="text-red-500 text-xs mt-1">Invalid date</div>
                                        @endif
                                    </td>

                                    <td>
                                        @php
                                            $father = $pig->father_id ? $pig->father->ear_tag_number ?? 'N/A' : 'N/A';
                                            $mother = $pig->mother_id ? $pig->mother->ear_tag_number ?? 'N/A' : 'N/A';
                                        @endphp
                                        <div class="breed-meta-stack">
                                            <div><strong>F:</strong> {{ $father }}</div>
                                            <div><strong>M:</strong> {{ $mother }}</div>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="relative" x-data="{ open: false }">
                                            <button @click="open = !open" class="breed-action-btn">
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
                                                 class="absolute right-0 z-20 mt-2 breed-menu">

                                                @php
                                                    $hasActiveMating = $pig->maleMatings()->whereIn('status', ['planned', 'completed'])->exists() ||
                                                                      $pig->femaleMatings()->whereIn('status', ['planned', 'completed'])->exists();
                                                @endphp

                                                <a href="{{ route('pigs.show', $pig->id) }}" class="breed-menu-item">
                                                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                    </svg>
                                                    View Details
                                                </a>

                                                <a href="{{ route('breeding-stocks.edit', $pig) }}" class="breed-menu-item">
                                                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                    </svg>
                                                    Edit
                                                </a>

                                                @if(!$hasActiveMating)
                                                    <form action="{{ route('pigs.demote-to-standard', $pig->id) }}" method="POST" onsubmit="return confirm('Demote {{ $pig->code }} to standard stock?');">
                                                        @csrf
                                                        <button type="submit" class="breed-menu-item">
                                                            <svg class="h-4 w-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                                                            </svg>
                                                            Demote to Standard
                                                        </button>
                                                    </form>
                                                @else
                                                    <div class="breed-menu-note" title="Cannot demote: pig has active matings">
                                                        <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                                        </svg>
                                                        Cannot demote while active matings exist
                                                    </div>
                                                @endif

                                                <div class="border-t border-gray-100"></div>

                                                <form action="{{ route('breeding-stocks.destroy', $pig) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this breeding stock?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="breed-menu-item danger">
                                                        <svg class="h-4 w-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <div class="breed-empty">
                                            <svg class="breed-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.562M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.636 6.636a9 9 0 000 12.728"></path>
                                            </svg>

                                            <div class="breed-empty-title">No breeding stock found</div>

                                            <div class="breed-empty-text">
                                                @if(request()->hasAny(['breed', 'gender', 'age']))
                                                    No pigs match your current filter criteria. Try changing the filters or clear them to see all breeding stock.
                                                @else
                                                    There are no breeding stock records in the system yet. Pigs are promoted into breeding stock after they meet the right age and weight requirements.
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
                    <div class="breed-footer">
                        <div class="breed-footer-text">
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
