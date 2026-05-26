<x-app-layout>
    <style>
        .room-show-page{
            min-height: 100vh;
            padding: 18px 0 40px;
        }

        .room-show-shell{
            max-width: 1480px;
            margin: 0 auto;
            padding: 0 16px;
        }

        .room-show-hero{
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 26px 28px;
            background: linear-gradient(135deg, #14532d 0%, #166534 48%, #0f766e 100%);
            box-shadow: 0 24px 54px rgba(22,101,52,.16);
            color: #fff;
            margin-bottom: 18px;
        }

        .room-show-hero::before{
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,.16), transparent 28%),
                radial-gradient(circle at bottom left, rgba(255,255,255,.08), transparent 24%);
            pointer-events: none;
        }

        .room-show-hero-inner{
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .room-show-title{
            margin: 0;
            font-size: clamp(1.8rem, 3vw, 2.7rem);
            line-height: 1.02;
            font-weight: 900;
            letter-spacing: -.04em;
        }

        .room-show-sub{
            margin: 10px 0 0;
            max-width: 760px;
            color: rgba(255,255,255,.90);
            font-size: .95rem;
            line-height: 1.75;
        }

        .room-show-actions{
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .room-top-btn{
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

        .room-top-btn:hover{
            transform: translateY(-1px);
        }

        .room-top-btn.edit{
            background: rgba(37,99,235,.90);
        }

        .room-top-btn.back{
            background: rgba(15,23,42,.68);
        }

        .room-grid-3{
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 18px;
        }

        .room-metric{
            background: rgba(255,255,255,.96);
            border: 1px solid #dbe7f3;
            border-radius: 24px;
            padding: 18px;
            box-shadow: 0 12px 28px rgba(15,23,42,.05);
        }

        .room-metric-label{
            font-size: .76rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #94a3b8;
            margin-bottom: 8px;
        }

        .room-metric-value{
            font-size: 2rem;
            font-weight: 900;
            color: #0f172a;
            line-height: 1;
        }

        .room-metric-note{
            margin-top: 8px;
            font-size: .8rem;
            color: #64748b;
            line-height: 1.6;
        }

        .room-capacity-track{
            margin-top: 12px;
            height: 10px;
            width: 100%;
            border-radius: 999px;
            background: #e5e7eb;
            overflow: hidden;
        }

        .room-capacity-fill{
            height: 100%;
            border-radius: 999px;
            transition: width .3s ease-in-out;
        }

        .room-capacity-fill.ok{ background: #16a34a; }
        .room-capacity-fill.warn{ background: #eab308; }
        .room-capacity-fill.full{ background: #dc2626; }

        .room-chip{
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
        .chip-purple{ background: #f3e8ff; color: #7e22ce; }
        .chip-pink{ background: #fce7f3; color: #be185d; }

        .room-card{
            background: rgba(255,255,255,.96);
            border: 1px solid #dbe7f3;
            border-radius: 28px;
            box-shadow: 0 14px 34px rgba(15,23,42,.06);
            overflow: hidden;
            margin-bottom: 18px;
        }

        .room-card-head{
            padding: 22px 24px 14px;
            border-bottom: 1px solid #edf2f7;
            background: linear-gradient(180deg, rgba(248,250,252,.95) 0%, rgba(255,255,255,.98) 100%);
        }

        .room-card-title{
            margin: 0;
            font-size: 1.04rem;
            font-weight: 900;
            color: #0f172a;
        }

        .room-card-sub{
            margin-top: 4px;
            font-size: .82rem;
            color: #64748b;
        }

        .room-card-body{
            padding: 20px 24px 24px;
        }

        .room-info-grid{
            display: grid;
            grid-template-columns: repeat(4, minmax(0,1fr));
            gap: 14px;
        }

        .room-info-item{
            background: #f8fafc;
            border: 1px solid #edf2f7;
            border-radius: 18px;
            padding: 14px 15px;
        }

        .room-info-label{
            font-size: .73rem;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 5px;
        }

        .room-info-value{
            font-size: .88rem;
            font-weight: 800;
            color: #0f172a;
            word-break: break-word;
        }

        .room-alert{
            border-radius: 20px;
            padding: 16px 18px;
            margin-bottom: 18px;
            border: 1px solid;
        }

        .room-alert.red{
            background: linear-gradient(180deg, #fff1f2 0%, #ffe4e6 100%);
            border-color: #fecdd3;
            color: #9f1239;
        }

        .room-alert.yellow{
            background: linear-gradient(180deg, #fffbeb 0%, #fefce8 100%);
            border-color: #fde68a;
            color: #92400e;
        }

        .room-table-wrap{
            overflow-x: auto;
        }

        .room-table{
            width: 100%;
            min-width: 1180px;
            border-collapse: separate;
            border-spacing: 0;
        }

        .room-table thead th{
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

        .room-table tbody td{
            padding: 16px 18px;
            border-bottom: 1px solid #eef2f7;
            vertical-align: top;
            font-size: .88rem;
            color: #0f172a;
            background: rgba(255,255,255,.96);
        }

        .room-table tbody tr:hover td{
            background: #fbfefd;
        }

        .room-pig-name{
            display: grid;
            gap: 4px;
        }

        .room-pig-main{
            font-weight: 900;
            color: #0f172a;
        }

        .room-pig-sub{
            font-size: .76rem;
            font-weight: 700;
            color: #64748b;
        }

        .room-actions{
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .room-action-link{
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

        .room-action-link.view{
            color: #2563eb;
        }

        .room-action-link.view:hover{
            background: #eff6ff;
            border-color: #bfdbfe;
        }

        .room-empty{
            padding: 52px 18px;
            text-align: center;
        }

        .room-empty-icon{
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            color: #94a3b8;
        }

        .room-empty-title{
            font-size: 1.08rem;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .room-empty-text{
            max-width: 620px;
            margin: 0 auto;
            color: #64748b;
            line-height: 1.75;
            font-size: .92rem;
        }

        @media (max-width: 1200px){
            .room-grid-3{
                grid-template-columns: repeat(2, minmax(0,1fr));
            }

            .room-info-grid{
                grid-template-columns: repeat(2, minmax(0,1fr));
            }
        }

        @media (max-width: 768px){
            .room-show-shell{
                padding: 0 12px;
            }

            .room-show-hero{
                padding: 22px 18px;
                border-radius: 24px;
            }

            .room-grid-3,
            .room-info-grid{
                grid-template-columns: 1fr;
            }

            .room-card-head,
            .room-card-body{
                padding-left: 18px;
                padding-right: 18px;
            }

            .room-show-actions{
                width: 100%;
            }

            .room-show-actions > *{
                flex: 1 1 100%;
            }
        }
    </style>

    <div class="room-show-page">
        <div class="room-show-shell">
            @php
                $currentCount = $room->pigs->count();
                $capacity = $room->capacity ?? 0;
                $percentage = $capacity > 0 ? round(($currentCount / $capacity) * 100) : 0;
                $isFull = $capacity > 0 && $percentage >= 100;
                $isNearFull = $capacity > 0 && $percentage >= 75 && $percentage < 100;

                if ($isFull) {
                    $fillClass = 'full';
                } elseif ($isNearFull) {
                    $fillClass = 'warn';
                } else {
                    $fillClass = 'ok';
                }
            @endphp

            <div class="room-show-hero">
                <div class="room-show-hero-inner">
                    <div>
                        <h1 class="room-show-title">Room: {{ $room->name }}</h1>
                        <p class="room-show-sub">{{ $room->structure->name }} / {{ $room->code }}</p>
                    </div>

                    <div class="room-show-actions">
                        <a href="{{ route('rooms.edit', $room) }}" class="room-top-btn edit">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-7-3l-4 4m0 0h3m-3 0v3m9-9a2 2 0 012 2v1a2 2 0 01-2 2h-1a2 2 0 01-2-2V9a2 2 0 012-2h1z"/>
                            </svg>
                            Edit Room
                        </a>

                        <a href="{{ route('structures.show', $room->structure) }}" class="room-top-btn back">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                            Back to Structure
                        </a>
                    </div>
                </div>
            </div>

            @if($isFull)
                <div class="room-alert red">
                    <strong>Room Full:</strong> This room has reached or exceeded its capacity.
                </div>
            @elseif($isNearFull)
                <div class="room-alert yellow">
                    <strong>Near Capacity:</strong> This room is getting close to full.
                </div>
            @endif

            <div class="room-grid-3">
                <div class="room-metric">
                    <div class="room-metric-label">Current Occupancy</div>
                    <div class="room-metric-value text-blue-700">{{ $currentCount }}</div>
                    <div class="room-metric-note">Total pigs currently assigned to this room</div>
                </div>

                <div class="room-metric">
                    <div class="room-metric-label">Room Capacity</div>
                    <div class="room-metric-value text-green-700">
                        @if($room->capacity)
                            {{ $room->capacity }}
                        @else
                            ∞
                        @endif
                    </div>
                    <div class="room-metric-note">
                        @if($room->capacity)
                            Maximum pigs allowed in this room
                        @else
                            No fixed capacity has been set
                        @endif
                    </div>
                </div>

                <div class="room-metric">
                    <div class="room-metric-label">Utilization</div>
                    <div class="room-metric-value {{ $isFull ? 'text-red-700' : ($isNearFull ? 'text-yellow-600' : 'text-green-700') }}">
                        @if($room->capacity)
                            {{ $percentage }}%
                        @else
                            N/A
                        @endif
                    </div>
                    <div class="room-metric-note">
                        @if($room->capacity)
                            {{ $currentCount }} of {{ $room->capacity }} spots used
                        @else
                            Unlimited occupancy
                        @endif
                    </div>

                    @if($room->capacity)
                        <div class="room-capacity-track">
                            <div class="room-capacity-fill {{ $fillClass }}" style="width: {{ min($percentage, 100) }}%;"></div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="room-card">
                <div class="room-card-head">
                    <h2 class="room-card-title">Room Information</h2>
                    <div class="room-card-sub">Key room details and current housing context.</div>
                </div>

                <div class="room-card-body">
                    <div class="room-info-grid">
                        <div class="room-info-item">
                            <div class="room-info-label">Room Name</div>
                            <div class="room-info-value">{{ $room->name }}</div>
                        </div>

                        <div class="room-info-item">
                            <div class="room-info-label">Code</div>
                            <div class="room-info-value">{{ $room->code }}</div>
                        </div>

                        <div class="room-info-item">
                            <div class="room-info-label">Type</div>
                            <div class="room-info-value">
                                <span class="room-chip chip-gray">{{ ucfirst($room->type) }}</span>
                            </div>
                        </div>

                        <div class="room-info-item">
                            <div class="room-info-label">Structure</div>
                            <div class="room-info-value">{{ $room->structure->name }}</div>
                        </div>

                        <div class="room-info-item">
                            <div class="room-info-label">Capacity</div>
                            <div class="room-info-value">
                                @if($room->capacity)
                                    {{ $room->capacity }}
                                @else
                                    Unlimited
                                @endif
                            </div>
                        </div>

                        <div class="room-info-item">
                            <div class="room-info-label">Notes</div>
                            <div class="room-info-value">{{ $room->notes ?: 'No notes available' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="room-card">
                <div class="room-card-head">
                    <h2 class="room-card-title">Pigs in this Room</h2>
                    <div class="room-card-sub">Current pigs assigned to this room and their main details.</div>
                </div>

                <div class="room-table-wrap">
                    <table class="room-table">
                        <thead>
                            <tr>
                                <th>Pig</th>
                                <th>Breed</th>
                                <th>Stock Type</th>
                                <th>Gender</th>
                                <th>Age</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($room->pigs as $pig)
                                <tr>
                                    <td>
                                        <div class="room-pig-name">
                                            <div class="room-pig-main">{{ $pig->ear_tag_number }}</div>
                                            <div class="room-pig-sub">{{ $pig->code }}</div>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="room-chip chip-gray">{{ $pig->breed }}</span>
                                    </td>

                                    <td>
                                        <span class="room-chip {{ $pig->stock_type === 'breeding' ? 'chip-green' : 'chip-blue' }}">
                                            {{ ucfirst($pig->stock_type) }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="room-chip {{ $pig->gender === 'M' ? 'chip-blue' : 'chip-pink' }}">
                                            {{ $pig->gender === 'M' ? 'Male' : 'Female' }}
                                        </span>
                                    </td>

                                    <td>
                                        {{ $pig->current_age_in_days }} days
                                    </td>

                                    <td>
                                        @if($pig->isPregnant())
                                            <span class="room-chip chip-yellow">Pregnant</span>
                                        @else
                                            <span class="room-chip chip-gray">Normal</span>
                                        @endif
                                    </td>

                                    <td>
                                        <div class="room-actions">
                                            <a href="{{ route('pigs.show', $pig) }}" class="room-action-link view">
                                                View Pig
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">
                                        <div class="room-empty">
                                            <svg class="room-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.562M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.636 6.636a9 9 0 000 12.728"></path>
                                            </svg>
                                            <div class="room-empty-title">No pigs in this room</div>
                                            <div class="room-empty-text">
                                                This room currently has no pigs assigned to it.
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>