<x-app-layout>
    @php $canManageFacilities = auth()->user()?->hasPermission('manage_facilities'); @endphp
    <style>
        .structure-show-page{
            min-height: 100vh;
            padding: 18px 0 40px;
        }

        .structure-show-shell{
            max-width: 1440px;
            margin: 0 auto;
            padding: 0 16px;
        }

        .structure-show-hero{
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 26px 28px;
            background: linear-gradient(135deg, #14532d 0%, #166534 48%, #0f766e 100%);
            box-shadow: 0 24px 54px rgba(22,101,52,.16);
            color: #fff;
            margin-bottom: 18px;
        }

        .structure-show-hero::before{
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,.16), transparent 28%),
                radial-gradient(circle at bottom left, rgba(255,255,255,.08), transparent 24%);
            pointer-events: none;
        }

        .structure-show-hero-inner{
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .structure-show-title{
            margin: 0;
            font-size: clamp(1.8rem, 3vw, 2.7rem);
            line-height: 1.02;
            font-weight: 900;
            letter-spacing: -.04em;
        }

        .structure-show-sub{
            margin: 10px 0 0;
            max-width: 760px;
            color: rgba(255,255,255,.90);
            font-size: .95rem;
            line-height: 1.75;
        }

        .structure-show-status{
            margin-top: 14px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .structure-top-chip{
            display: inline-flex;
            align-items: center;
            padding: 9px 14px;
            border-radius: 999px;
            background: rgba(255,255,255,.14);
            border: 1px solid rgba(255,255,255,.16);
            font-size: .78rem;
            font-weight: 800;
            color: #fff;
        }

        .structure-show-actions{
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .structure-top-btn{
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
            color: #fff;
        }

        .structure-top-btn:hover{
            transform: translateY(-1px);
        }

        .structure-top-btn.edit{
            background: rgba(37,99,235,.90);
        }

        .structure-top-btn.delete{
            background: rgba(220,38,38,.92);
        }

        .structure-grid-3{
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 18px;
        }

        .structure-metric{
            background: rgba(255,255,255,.96);
            border: 1px solid #dbe7f3;
            border-radius: 24px;
            padding: 18px;
            box-shadow: 0 12px 28px rgba(15,23,42,.05);
        }

        .structure-metric-label{
            font-size: .76rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #94a3b8;
            margin-bottom: 8px;
        }

        .structure-metric-value{
            font-size: 2rem;
            font-weight: 900;
            color: #0f172a;
            line-height: 1;
        }

        .structure-metric-note{
            margin-top: 8px;
            font-size: .8rem;
            color: #64748b;
            line-height: 1.6;
        }

        .structure-card{
            background: rgba(255,255,255,.96);
            border: 1px solid #dbe7f3;
            border-radius: 28px;
            box-shadow: 0 14px 34px rgba(15,23,42,.06);
            overflow: hidden;
            margin-bottom: 18px;
        }

        .structure-card-head{
            padding: 22px 24px 14px;
            border-bottom: 1px solid #edf2f7;
            background: linear-gradient(180deg, rgba(248,250,252,.95) 0%, rgba(255,255,255,.98) 100%);
        }

        .structure-card-head-row{
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .structure-card-title{
            margin: 0;
            font-size: 1.04rem;
            font-weight: 900;
            color: #0f172a;
        }

        .structure-card-sub{
            margin-top: 4px;
            font-size: .82rem;
            color: #64748b;
        }

        .structure-card-body{
            padding: 20px 24px 24px;
        }

        .structure-form-grid{
            display: grid;
            grid-template-columns: 1.15fr .9fr .8fr .9fr 1.35fr auto;
            gap: 14px;
            align-items: end;
        }

        .structure-field{
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .structure-label{
            font-size: .82rem;
            font-weight: 800;
            color: #334155;
        }

        .structure-input,
        .structure-select,
        .structure-textarea{
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 16px;
            background: #fff;
            padding: 11px 13px;
            font-size: .9rem;
            color: #0f172a;
            outline: none;
            transition: .2s ease;
            box-shadow: inset 0 1px 2px rgba(15,23,42,.02);
        }

        .structure-textarea{
            min-height: 44px;
            resize: vertical;
        }

        .structure-input:focus,
        .structure-select:focus,
        .structure-textarea:focus{
            border-color: #16a34a;
            box-shadow: 0 0 0 4px rgba(22,163,74,.10);
        }

        .structure-submit{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 44px;
            border: none;
            border-radius: 16px;
            padding: 0 18px;
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: #fff;
            font-size: .84rem;
            font-weight: 800;
            box-shadow: 0 10px 24px rgba(22,163,74,.16);
            transition: .2s ease;
        }

        .structure-submit:hover{
            transform: translateY(-1px);
        }

        .structure-add-room-btn{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 42px;
            border: none;
            border-radius: 14px;
            padding: 0 16px;
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: #fff;
            font-size: .84rem;
            font-weight: 900;
            box-shadow: 0 10px 24px rgba(22,163,74,.16);
            cursor: pointer;
        }

        .structure-filter-grid{
            display: grid;
            grid-template-columns: minmax(220px, 1.35fr) repeat(3, minmax(150px, .8fr)) auto;
            gap: 14px;
            align-items: end;
        }

        .structure-filter-actions{
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .structure-clear-btn{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 44px;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            padding: 0 14px;
            background: #fff;
            color: #334155;
            font-size: .82rem;
            font-weight: 900;
            cursor: pointer;
        }

        .structure-filter-count{
            color: #64748b;
            font-size: .82rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .structure-no-results{
            display: none;
            padding: 36px 18px;
            text-align: center;
            color: #64748b;
            font-weight: 800;
        }

        .structure-modal-backdrop{
            position: fixed;
            inset: 0;
            z-index: 60;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(15,23,42,.58);
        }

        .structure-modal-backdrop.open{
            display: flex;
        }

        .structure-modal{
            width: min(720px, 100%);
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            border-radius: 24px;
            background: #fff;
            box-shadow: 0 28px 80px rgba(15,23,42,.28);
        }

        .structure-modal-head{
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 22px 24px 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .structure-modal-title{
            margin: 0;
            color: #0f172a;
            font-size: 1.12rem;
            font-weight: 900;
        }

        .structure-modal-sub{
            margin: 5px 0 0;
            color: #64748b;
            font-size: .86rem;
            line-height: 1.6;
        }

        .structure-modal-close{
            width: 38px;
            height: 38px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
            color: #475569;
            font-size: 1.35rem;
            line-height: 1;
            cursor: pointer;
        }

        .structure-modal-body{
            padding: 20px 24px 24px;
        }

        .structure-modal-grid{
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .structure-modal-grid .wide{
            grid-column: 1 / -1;
        }

        .structure-modal-actions{
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid #edf2f7;
        }

        .structure-modal-cancel{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            padding: 0 16px;
            background: #fff;
            color: #334155;
            font-size: .84rem;
            font-weight: 900;
            cursor: pointer;
        }

        .structure-table-wrap{
            overflow-x: auto;
        }

        .structure-table{
            width: 100%;
            min-width: 1180px;
            border-collapse: separate;
            border-spacing: 0;
        }

        .structure-table thead th{
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

        .structure-table tbody td{
            padding: 16px 18px;
            border-bottom: 1px solid #eef2f7;
            vertical-align: top;
            font-size: .88rem;
            color: #0f172a;
            background: rgba(255,255,255,.96);
        }

        .structure-table tbody tr:hover td{
            background: #fbfefd;
        }

        .structure-room-name{
            display: grid;
            gap: 4px;
        }

        .structure-room-main{
            font-weight: 900;
            color: #0f172a;
        }

        .structure-room-code{
            font-size: .76rem;
            font-weight: 700;
            color: #64748b;
        }

        .structure-chip{
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: .74rem;
            font-weight: 800;
            line-height: 1;
        }

        .chip-green{ background: #dcfce7; color: #166534; }
        .chip-red{ background: #fee2e2; color: #b91c1c; }
        .chip-blue{ background: #dbeafe; color: #1d4ed8; }
        .chip-gray{ background: #f1f5f9; color: #475569; }
        .chip-yellow{ background: #fef9c3; color: #854d0e; }

        .structure-capacity{
            display: grid;
            gap: 8px;
        }

        .structure-capacity-bar{
            width: 120px;
            height: 8px;
            border-radius: 999px;
            background: #e5e7eb;
            overflow: hidden;
        }

        .structure-capacity-fill{
            height: 100%;
            border-radius: 999px;
        }

        .structure-capacity-fill.ok{ background: #16a34a; }
        .structure-capacity-fill.full{ background: #dc2626; }

        .structure-actions{
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .structure-action-link{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 12px;
            border-radius: 14px;
            border: 1px solid #d1d5db;
            background: #fff;
            font-size: .82rem;
            font-weight: 800;
            text-decoration: none;
            transition: .2s ease;
        }

        .structure-action-link.view{
            color: #2563eb;
        }

        .structure-action-link.view:hover{
            background: #eff6ff;
            border-color: #bfdbfe;
        }

        .structure-action-link.edit{
            color: #0f766e;
        }

        .structure-action-link.edit:hover{
            background: #ecfeff;
            border-color: #a5f3fc;
        }

        .structure-action-link.delete{
            color: #b91c1c;
        }

        .structure-action-link.delete:hover{
            background: #fef2f2;
            border-color: #fecaca;
        }

        .structure-empty{
            padding: 52px 18px;
            text-align: center;
        }

        .structure-empty-icon{
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            color: #94a3b8;
        }

        .structure-empty-title{
            font-size: 1.08rem;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .structure-empty-text{
            max-width: 620px;
            margin: 0 auto;
            color: #64748b;
            line-height: 1.75;
            font-size: .92rem;
        }

        @media (max-width: 1200px){
            .structure-form-grid{
                grid-template-columns: repeat(2, minmax(0,1fr));
            }

            .structure-filter-grid{
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px){
            .structure-show-shell{
                padding: 0 12px;
            }

            .structure-show-hero{
                padding: 22px 18px;
                border-radius: 24px;
            }

            .structure-grid-3{
                grid-template-columns: 1fr;
            }

            .structure-card-head,
            .structure-card-body{
                padding-left: 18px;
                padding-right: 18px;
            }

            .structure-form-grid{
                grid-template-columns: 1fr;
            }

            .structure-filter-grid,
            .structure-modal-grid{
                grid-template-columns: 1fr;
            }

            .structure-filter-actions,
            .structure-modal-actions{
                align-items: stretch;
                flex-direction: column;
            }

            .structure-clear-btn,
            .structure-submit,
            .structure-modal-cancel,
            .structure-add-room-btn{
                width: 100%;
            }

            .structure-show-actions{
                width: 100%;
            }

            .structure-show-actions > *{
                flex: 1 1 100%;
            }

            .structure-show-actions form button{
                width: 100%;
                justify-content: center;
            }
        }
    </style>

    <div class="structure-show-page">
        <div class="structure-show-shell">
            <div class="structure-show-hero">
                <div class="structure-show-hero-inner">
                    <div>
                        <h1 class="structure-show-title">{{ $structure->name }}</h1>
                        <p class="structure-show-sub">{{ $structure->description ?? 'No description provided.' }}</p>

                        <div class="structure-show-status">
                            <span class="structure-top-chip">
                                {{ $structure->status === 'under_maintenance' ? 'Under Maintenance' : ucfirst($structure->status) }}
                            </span>
                        </div>
                    </div>

                    @if($canManageFacilities)
                    <div class="structure-show-actions">
                        <a href="{{ route('structures.edit', $structure) }}" class="structure-top-btn edit">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-7-3l-4 4m0 0h3m-3 0v3m9-9a2 2 0 012 2v1a2 2 0 01-2 2h-1a2 2 0 01-2-2V9a2 2 0 012-2h1z"/>
                            </svg>
                            Edit Structure
                        </a>

                        <form action="{{ route('structures.destroy', $structure) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to permanently delete this structure and all associated rooms?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="structure-top-btn delete">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                Delete
                            </button>
                        </form>
                    </div>
                    @endif
                </div>
            </div>

            @php
                $weaningAge = config('breeding.cycle.birth_to_weaning_days', 45);
                $totalWeaned = $structure->rooms->sum(function($room) { return $room->pigs->count(); });
                $pigletsCount = 0;
                foreach ($structure->rooms as $room) {
                    foreach ($room->pigs as $sow) {
                        foreach ($sow->farrowingsAsSow as $farrowing) {
                            if ($farrowing->farrowing_date && $farrowing->farrowing_date >= now()->subDays($weaningAge) && $farrowing->status === 'active') {
                                $pigletsCount += max(0, (int) ($farrowing->net_live_piglets ?? 0));
                            }
                        }
                    }
                }
            @endphp

            <div class="structure-grid-3">
                <div class="structure-metric">
                    <div class="structure-metric-label">Total Pigs</div>
                    <div class="structure-metric-value text-blue-700">{{ $structure->total_pigs }}</div>
                    <div class="structure-metric-note">{{ $totalWeaned }} weaned + {{ $pigletsCount }} piglets</div>
                </div>

                <div class="structure-metric">
                    <div class="structure-metric-label">Total Rooms</div>
                    <div class="structure-metric-value text-purple-700">{{ $structure->current_room_count }}</div>
                    <div class="structure-metric-note">
                        @if($structure->capacity)
                            Capacity: {{ $structure->current_room_count }}/{{ $structure->capacity }}
                            @if($structure->is_full)
                                <span class="text-red-600 font-semibold">(Full)</span>
                            @endif
                        @else
                            Unlimited room capacity
                        @endif
                    </div>
                </div>

                @php
                    $allFemaleIds = $structure->rooms->flatMap(fn($room) => $room->pigs)->where('gender', 'F')->pluck('id');
                    $totalPregnantCount = $allFemaleIds->isNotEmpty()
                        ? \App\Models\Mating::whereIn('female_id', $allFemaleIds)
                            ->where('pregnancy_status','pregnant')
                            ->distinct('female_id')
                            ->count('female_id')
                        : 0;
                @endphp
                <div class="structure-metric">
                    <div class="structure-metric-label">Pregnant Sows</div>
                    <div class="structure-metric-value text-yellow-700">{{ $totalPregnantCount }}</div>
                    <div class="structure-metric-note">Across all {{ $structure->current_room_count }} rooms</div>
                </div>
            </div>

            <div class="structure-card">
                <div class="structure-card-head">
                    <div class="structure-card-head-row">
                        <div>
                            <h2 class="structure-card-title">Room Inventory</h2>
                            <div class="structure-card-sub">Filter rooms and manage existing room allocation inside this structure.</div>
                        </div>
                        @if($canManageFacilities)
                        <button type="button" class="structure-add-room-btn" data-room-modal-open>
                            Add Room
                        </button>
                        @endif
                    </div>
                </div>

                <div class="structure-card-body" style="border-bottom:1px solid #edf2f7;">
                    <div class="structure-filter-grid" data-room-filters>
                        <div class="structure-field">
                            <label for="roomSearch" class="structure-label">Search</label>
                            <input type="search" id="roomSearch" class="structure-input" placeholder="Search by room name, code, or notes" data-room-search>
                        </div>

                        <div class="structure-field">
                            <label for="roomTypeFilter" class="structure-label">Type</label>
                            <select id="roomTypeFilter" class="structure-select" data-room-type-filter>
                                <option value="">All types</option>
                                <option value="general">Breeding</option>
                                <option value="maternity">Growers</option>
                                <option value="gestation">Gestation</option>
                                <option value="weaning">Weaning</option>
                                <option value="finisher">Finisher</option>
                                <option value="quarantine">Quarantine</option>
                            </select>
                        </div>

                        <div class="structure-field">
                            <label for="roomCapacityFilter" class="structure-label">Capacity</label>
                            <select id="roomCapacityFilter" class="structure-select" data-room-capacity-filter>
                                <option value="">All capacity states</option>
                                <option value="available">Available</option>
                                <option value="full">Full</option>
                                <option value="unlimited">Unlimited</option>
                            </select>
                        </div>

                        <div class="structure-field">
                            <label for="roomOccupancyFilter" class="structure-label">Occupancy</label>
                            <select id="roomOccupancyFilter" class="structure-select" data-room-occupancy-filter>
                                <option value="">All rooms</option>
                                <option value="occupied">Occupied</option>
                                <option value="empty">Empty</option>
                            </select>
                        </div>

                        <div class="structure-filter-actions">
                            <button type="button" class="structure-clear-btn" data-room-clear-filters>Clear</button>
                            <span class="structure-filter-count" data-room-filter-count>{{ $structure->rooms->count() }} rooms</span>
                        </div>
                    </div>
                </div>

                <div class="structure-table-wrap">
                    <table class="structure-table">
                        <thead>
                            <tr>
                                <th>Name / Code</th>
                                <th>Type</th>
                                <th>Pigs Count</th>
                                <th>Capacity</th>
                                <th>Pregnant Sows</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($structure->rooms as $room)
                                @php
                                    $roomPigCount = $room->pigs->count();
                                    $roomCapacityStatus = $room->capacity
                                        ? ($roomPigCount >= $room->capacity ? 'full' : 'available')
                                        : 'unlimited';
                                    $roomOccupancyStatus = $roomPigCount > 0 ? 'occupied' : 'empty';
                                    $roomSearchText = strtolower(trim($room->name . ' ' . $room->code . ' ' . $room->type . ' ' . ($room->notes ?? '')));
                                @endphp
                                <tr
                                    data-room-row
                                    data-room-type="{{ $room->type }}"
                                    data-room-capacity-status="{{ $roomCapacityStatus }}"
                                    data-room-occupancy-status="{{ $roomOccupancyStatus }}"
                                    data-room-search="{{ $roomSearchText }}"
                                >
                                    <td>
                                        <div class="structure-room-name">
                                            <div class="structure-room-main">
                                                <a href="{{ route('rooms.show', $room) }}" class="text-indigo-600 hover:text-indigo-800">
                                                    {{ $room->name }}
                                                </a>
                                            </div>
                                            <div class="structure-room-code">{{ $room->code }}</div>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="structure-chip chip-gray">{{ ucfirst($room->type) }}</span>
                                    </td>

                                    <td>
                                        <span class="font-semibold text-gray-900">{{ $room->pigs->count() }}</span>
                                    </td>

                                    <td>
                                        <div class="structure-capacity">
                                            @if($room->capacity)
                                                <div class="structure-capacity-bar">
                                                    <div class="structure-capacity-fill {{ $room->pigs->count() >= $room->capacity ? 'full' : 'ok' }}"
                                                         style="width: {{ min(($room->pigs->count() / $room->capacity) * 100, 100) }}%;"></div>
                                                </div>

                                                <div class="text-xs text-gray-600 font-medium">
                                                    {{ $room->pigs->count() }} / {{ $room->capacity }}
                                                </div>

                                                @if($room->pigs->count() >= $room->capacity)
                                                    <span class="structure-chip chip-red">Full</span>
                                                @else
                                                    <span class="structure-chip chip-blue">Available</span>
                                                @endif
                                            @else
                                                <div class="text-sm text-gray-400">Unlimited</div>
                                                <span class="structure-chip chip-yellow">No Limit</span>
                                            @endif
                                        </div>
                                    </td>

                                    <td>
                                        @php
                                            $femaleIds = $room->pigs->where('gender', 'F')->pluck('id');
                                            $pregnantCount = $femaleIds->isNotEmpty()
                                                ? \App\Models\Mating::whereIn('female_id', $femaleIds)
                                                    ->where('pregnancy_status','pregnant')
                                                    ->distinct('female_id')
                                                    ->count('female_id')
                                                : 0;
                                        @endphp

                                        @if($pregnantCount > 0)
                                            <span class="structure-chip chip-yellow">{{ $pregnantCount }}</span>
                                        @else
                                            <span class="text-gray-400">0</span>
                                        @endif
                                    </td>

                                    <td class="text-sm text-gray-700 max-w-xs">
                                        {{ $room->notes ?? 'N/A' }}
                                    </td>

                                    <td>
                                        <div class="structure-actions">
                                            <a href="{{ route('rooms.show', $room) }}" class="structure-action-link view">View</a>
                                            @if($canManageFacilities)
                                            <a href="{{ route('rooms.edit', $room) }}" class="structure-action-link edit">Edit</a>

                                            <form action="{{ route('structures.rooms.destroy', [$structure, $room]) }}" method="POST" onsubmit="return confirm('Delete this room?');" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="structure-action-link delete">Delete</button>
                                            </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">
                                        <div class="structure-empty">
                                            <svg class="structure-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M5 21V7l8-4 6 3v15M9 9h.01M9 12h.01M9 15h.01M13 9h.01M13 12h.01M13 15h.01"></path>
                                            </svg>
                                            <div class="structure-empty-title">No rooms yet</div>
                                            <div class="structure-empty-text">
                                                This structure has no rooms yet. Use the form above to add rooms and begin organizing pig housing properly.
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                            <tr data-room-no-results style="display:none;">
                                <td colspan="7">
                                    <div class="structure-no-results">No rooms match the selected filters.</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if($canManageFacilities)
    <div class="structure-modal-backdrop" data-room-modal aria-hidden="true">
        <div class="structure-modal" role="dialog" aria-modal="true" aria-labelledby="roomModalTitle">
            <div class="structure-modal-head">
                <div>
                    <h2 id="roomModalTitle" class="structure-modal-title">Add Room</h2>
                    <p class="structure-modal-sub">Create a room inside {{ $structure->name }} and assign its capacity and purpose.</p>
                </div>
                <button type="button" class="structure-modal-close" data-room-modal-close aria-label="Close add room form">&times;</button>
            </div>

            <form method="POST" action="{{ route('structures.rooms.store', $structure) }}" class="structure-modal-body">
                @csrf

                <div class="structure-modal-grid">
                    <div class="structure-field">
                        <label for="modalRoomName" class="structure-label">Name</label>
                        <input type="text" id="modalRoomName" name="name" value="{{ old('name') }}" placeholder="Farrowing Bay A" class="structure-input" required>
                    </div>

                    <div class="structure-field">
                        <label for="modalRoomCode" class="structure-label">Code</label>
                        <input type="text" id="modalRoomCode" name="code" value="{{ old('code') }}" placeholder="RMA-01" class="structure-input" required>
                    </div>

                    <div class="structure-field">
                        <label for="modalRoomCapacity" class="structure-label">Capacity</label>
                        <input type="number" id="modalRoomCapacity" name="capacity" value="{{ old('capacity') }}" min="0" placeholder="Max animals" class="structure-input">
                    </div>

                    <div class="structure-field">
                        <label for="modalRoomType" class="structure-label">Type</label>
                        <select id="modalRoomType" name="type" class="structure-select" required>
                            <option value="" disabled @selected(! old('type'))>Select type</option>
                            <option value="general" @selected(old('type') === 'general')>Breeding</option>
                            <option value="maternity" @selected(old('type') === 'maternity')>Growers</option>
                            <option value="gestation" @selected(old('type') === 'gestation')>Gestation</option>
                            <option value="weaning" @selected(old('type') === 'weaning')>Weaning</option>
                            <option value="finisher" @selected(old('type') === 'finisher')>Finisher</option>
                            <option value="quarantine" @selected(old('type') === 'quarantine')>Quarantine</option>
                        </select>
                    </div>

                    <div class="structure-field wide">
                        <label for="modalRoomNotes" class="structure-label">Notes (Optional)</label>
                        <textarea id="modalRoomNotes" name="notes" rows="3" placeholder="Special requirements or status" class="structure-textarea">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <div class="structure-modal-actions">
                    <button type="button" class="structure-modal-cancel" data-room-modal-close>Cancel</button>
                    <button type="submit" class="structure-submit">Add Room</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.querySelector('[data-room-modal]');
            const openButtons = document.querySelectorAll('[data-room-modal-open]');
            const closeButtons = document.querySelectorAll('[data-room-modal-close]');
            const firstModalInput = document.getElementById('modalRoomName');
            const rows = Array.from(document.querySelectorAll('[data-room-row]'));
            const noResultsRow = document.querySelector('[data-room-no-results]');
            const noResultsContent = noResultsRow?.querySelector('.structure-no-results');
            const searchInput = document.querySelector('[data-room-search]');
            const typeFilter = document.querySelector('[data-room-type-filter]');
            const capacityFilter = document.querySelector('[data-room-capacity-filter]');
            const occupancyFilter = document.querySelector('[data-room-occupancy-filter]');
            const clearButton = document.querySelector('[data-room-clear-filters]');
            const countLabel = document.querySelector('[data-room-filter-count]');

            const openModal = () => {
                modal?.classList.add('open');
                modal?.setAttribute('aria-hidden', 'false');
                setTimeout(() => firstModalInput?.focus(), 50);
            };

            const closeModal = () => {
                modal?.classList.remove('open');
                modal?.setAttribute('aria-hidden', 'true');
            };

            const applyFilters = () => {
                const query = (searchInput?.value || '').trim().toLowerCase();
                const type = typeFilter?.value || '';
                const capacity = capacityFilter?.value || '';
                const occupancy = occupancyFilter?.value || '';
                let visibleCount = 0;

                rows.forEach((row) => {
                    const matchesSearch = !query || (row.dataset.roomSearch || '').includes(query);
                    const matchesType = !type || row.dataset.roomType === type;
                    const matchesCapacity = !capacity || row.dataset.roomCapacityStatus === capacity;
                    const matchesOccupancy = !occupancy || row.dataset.roomOccupancyStatus === occupancy;
                    const visible = matchesSearch && matchesType && matchesCapacity && matchesOccupancy;

                    row.style.display = visible ? '' : 'none';
                    if (visible) {
                        visibleCount += 1;
                    }
                });

                if (countLabel) {
                    countLabel.textContent = `${visibleCount} ${visibleCount === 1 ? 'room' : 'rooms'}`;
                }

                if (noResultsRow && noResultsContent) {
                    noResultsRow.style.display = rows.length && visibleCount === 0 ? '' : 'none';
                    noResultsContent.style.display = rows.length && visibleCount === 0 ? 'block' : 'none';
                }
            };

            openButtons.forEach((button) => button.addEventListener('click', openModal));
            closeButtons.forEach((button) => button.addEventListener('click', closeModal));
            modal?.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal();
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeModal();
                }
            });

            [searchInput, typeFilter, capacityFilter, occupancyFilter].forEach((control) => {
                control?.addEventListener('input', applyFilters);
                control?.addEventListener('change', applyFilters);
            });

            clearButton?.addEventListener('click', () => {
                if (searchInput) searchInput.value = '';
                if (typeFilter) typeFilter.value = '';
                if (capacityFilter) capacityFilter.value = '';
                if (occupancyFilter) occupancyFilter.value = '';
                applyFilters();
            });

            applyFilters();

            @if($errors->any() && (old('name') || old('code') || old('type') || old('capacity') || old('notes')))
                openModal();
            @endif
        });
    </script>
</x-app-layout>
