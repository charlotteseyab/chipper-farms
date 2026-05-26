<x-app-layout>
    <style>
        .structures-page{
            min-height: 100vh;
            padding: 18px 0 40px;
        }

        .structures-shell{
            max-width: 1380px;
            margin: 0 auto;
            padding: 0 16px;
        }

        .structures-hero{
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 26px 28px;
            background: linear-gradient(135deg, #14532d 0%, #166534 48%, #0f766e 100%);
            box-shadow: 0 24px 54px rgba(22,101,52,.16);
            color: #fff;
            margin-bottom: 18px;
        }

        .structures-hero::before{
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,.16), transparent 28%),
                radial-gradient(circle at bottom left, rgba(255,255,255,.08), transparent 24%);
            pointer-events: none;
        }

        .structures-hero-inner{
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .structures-hero-title{
            margin: 0;
            font-size: clamp(1.8rem, 3vw, 2.7rem);
            line-height: 1.02;
            font-weight: 900;
            letter-spacing: -.04em;
        }

        .structures-hero-sub{
            margin: 10px 0 0;
            max-width: 760px;
            color: rgba(255,255,255,.90);
            font-size: .95rem;
            line-height: 1.75;
        }

        .structures-hero-actions{
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .structures-top-btn{
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

        .structures-top-btn:hover{
            background: rgba(255,255,255,.20);
            transform: translateY(-1px);
        }

        .structures-card{
            background: rgba(255,255,255,.96);
            border: 1px solid #dbe7f0;
            border-radius: 28px;
            box-shadow: 0 14px 34px rgba(15,23,42,.06);
            overflow: hidden;
        }

        .structures-card-head{
            padding: 22px 24px 14px;
            border-bottom: 1px solid #edf2f7;
            background: linear-gradient(180deg, rgba(248,250,252,.95) 0%, rgba(255,255,255,.98) 100%);
        }

        .structures-card-title{
            margin: 0;
            font-size: 1.04rem;
            font-weight: 900;
            color: #0f172a;
        }

        .structures-card-sub{
            margin-top: 4px;
            font-size: .82rem;
            color: #64748b;
        }

        .structures-toolbar{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            padding: 16px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #edf2f7;
        }

        .structures-toolbar-text{
            font-size: .84rem;
            color: #475569;
            font-weight: 700;
        }

        .structures-toolbar-text strong{
            color: #0f172a;
        }

        .structures-table-wrap{
            overflow-x: auto;
        }

        .structures-table{
            width: 100%;
            min-width: 960px;
            border-collapse: separate;
            border-spacing: 0;
        }

        .structures-table thead th{
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

        .structures-table tbody td{
            padding: 16px 18px;
            border-bottom: 1px solid #eef2f7;
            vertical-align: top;
            font-size: .88rem;
            color: #0f172a;
            background: rgba(255,255,255,.96);
        }

        .structures-table tbody tr:hover td{
            background: #fbfefd;
        }

        .structures-name{
            display: grid;
            gap: 4px;
        }

        .structures-name-main{
            font-weight: 900;
            color: #0f172a;
        }

        .structures-name-code{
            font-size: .76rem;
            font-weight: 700;
            color: #64748b;
        }

        .structures-description{
            max-width: 300px;
            color: #475569;
            line-height: 1.6;
        }

        .structures-chip{
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

        .structures-capacity{
            display: grid;
            gap: 7px;
        }

        .structures-capacity-main{
            font-weight: 800;
            color: #0f172a;
        }

        .structures-actions{
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .structures-action-link{
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

        .structures-action-link.view{
            color: #2563eb;
        }

        .structures-action-link.view:hover{
            background: #eff6ff;
            border-color: #bfdbfe;
        }

        .structures-action-link.delete{
            color: #b91c1c;
        }

        .structures-action-link.delete:hover{
            background: #fef2f2;
            border-color: #fecaca;
        }

        .structures-empty{
            padding: 52px 18px;
            text-align: center;
        }

        .structures-empty-icon{
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            color: #94a3b8;
        }

        .structures-empty-title{
            font-size: 1.08rem;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .structures-empty-text{
            max-width: 620px;
            margin: 0 auto;
            color: #64748b;
            line-height: 1.75;
            font-size: .92rem;
        }

        .structures-footer{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            padding: 16px 24px;
            border-top: 1px solid #edf2f7;
            background: #fff;
        }

        .structures-footer-text{
            font-size: .84rem;
            color: #475569;
            font-weight: 700;
        }

        @media (max-width: 768px){
            .structures-shell{
                padding: 0 12px;
            }

            .structures-hero{
                padding: 22px 18px;
                border-radius: 24px;
            }

            .structures-card-head,
            .structures-toolbar,
            .structures-footer{
                padding-left: 18px;
                padding-right: 18px;
            }

            .structures-hero-actions{
                width: 100%;
            }

            .structures-hero-actions > *{
                flex: 1 1 100%;
            }
        }
    </style>

    <div class="structures-page">
        <div class="structures-shell">
            <div class="structures-hero">
                <div class="structures-hero-inner">
                    <div>
                        <h1 class="structures-hero-title">Structures</h1>
                        <p class="structures-hero-sub">
                            Manage farm structures, monitor room capacity, and quickly review housing availability from one clean and simple page.
                        </p>
                    </div>

                    <div class="structures-hero-actions">
                        <a href="{{ route('structures.create') }}" class="structures-top-btn">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            New Structure
                        </a>
                    </div>
                </div>
            </div>

            <div class="structures-card">
                <div class="structures-card-head">
                    <h2 class="structures-card-title">Structure List</h2>
                    <div class="structures-card-sub">Overview of all registered structures, their capacities, and current occupancy.</div>
                </div>

                <div class="structures-toolbar">
                    <div class="structures-toolbar-text">
                        @if($structures->total() > 0)
                            <strong>{{ $structures->total() }}</strong> structure{{ $structures->total() !== 1 ? 's' : '' }} in the system
                        @else
                            No structures found
                        @endif
                    </div>

                    @if($structures->hasPages())
                        <div class="structures-toolbar-text">
                            Page <strong>{{ $structures->currentPage() }}</strong> of <strong>{{ $structures->lastPage() }}</strong>
                        </div>
                    @endif
                </div>

                <div class="structures-table-wrap">
                    <table class="structures-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Capacity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($structures as $structure)
                                <tr>
                                    <td>
                                        <div class="structures-name">
                                            <div class="structures-name-main">{{ $structure->name }}</div>
                                            @if($structure->code)
                                                <div class="structures-name-code">Code: {{ $structure->code }}</div>
                                            @endif
                                        </div>
                                    </td>

                                    <td>
                                        <div class="structures-description" title="{{ $structure->description }}">
                                            {{ $structure->description ?? 'N/A' }}
                                        </div>
                                    </td>

                                    <td>
                                        <div class="structures-capacity">
                                            <div class="structures-capacity-main">
                                                @if($structure->capacity)
                                                    {{ $structure->current_room_count }}/{{ $structure->capacity }}
                                                @else
                                                    {{ $structure->current_room_count }} (Unlimited)
                                                @endif
                                            </div>

                                            <div>
                                                @if($structure->capacity)
                                                    @if($structure->is_full)
                                                        <span class="structures-chip chip-red">Full</span>
                                                    @elseif($structure->current_room_count == 0)
                                                        <span class="structures-chip chip-gray">Empty</span>
                                                    @else
                                                        <span class="structures-chip chip-blue">In Use</span>
                                                    @endif
                                                @else
                                                    <span class="structures-chip chip-yellow">Unlimited</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="structures-chip {{ $structure->status === 'active' ? 'chip-green' : 'chip-gray' }}">
                                            {{ ucfirst($structure->status) }}
                                        </span>
                                    </td>

                                    <td>
                                        <div class="structures-actions">
                                            <a href="{{ route('structures.show', $structure) }}" class="structures-action-link view">
                                                View
                                            </a>

                                            <form action="{{ route('structures.destroy', $structure) }}" method="POST" onsubmit="return confirm('Delete this structure?');" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="structures-action-link delete">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <div class="structures-empty">
                                            <svg class="structures-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M5 21V7l8-4 6 3v15M9 9h.01M9 12h.01M9 15h.01M13 9h.01M13 12h.01M13 15h.01"></path>
                                            </svg>
                                            <div class="structures-empty-title">No structures found</div>
                                            <div class="structures-empty-text">
                                                There are no structures in the system yet. Create a structure first so rooms and pigs can be organized properly.
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($structures->hasPages())
                    <div class="structures-footer">
                        <div class="structures-footer-text">
                            Showing {{ $structures->firstItem() }} to {{ $structures->lastItem() }} of {{ $structures->total() }} results
                        </div>
                        <div>
                            <x-simple-pagination :paginator="$structures" />
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>