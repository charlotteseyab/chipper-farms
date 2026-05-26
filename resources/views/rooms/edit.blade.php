<x-app-layout>
    <style>
        .room-edit-page{
            min-height: 100vh;
            padding: 18px 0 40px;
        }

        .room-edit-shell{
            max-width: 1180px;
            margin: 0 auto;
            padding: 0 16px;
        }

        .room-edit-hero{
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 26px 28px;
            background: linear-gradient(135deg, #14532d 0%, #166534 48%, #0f766e 100%);
            box-shadow: 0 24px 54px rgba(22,101,52,.16);
            color: #fff;
            margin-bottom: 18px;
        }

        .room-edit-hero::before{
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,.16), transparent 28%),
                radial-gradient(circle at bottom left, rgba(255,255,255,.08), transparent 24%);
            pointer-events: none;
        }

        .room-edit-hero-inner{
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .room-edit-title{
            margin: 0;
            font-size: clamp(1.8rem, 3vw, 2.7rem);
            line-height: 1.02;
            font-weight: 900;
            letter-spacing: -.04em;
        }

        .room-edit-sub{
            margin: 10px 0 0;
            max-width: 760px;
            color: rgba(255,255,255,.90);
            font-size: .95rem;
            line-height: 1.75;
        }

        .room-edit-pill-row{
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .room-edit-pill{
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

        .room-edit-card{
            background: rgba(255,255,255,.96);
            border: 1px solid #dbe7f0;
            border-radius: 28px;
            box-shadow: 0 14px 34px rgba(15,23,42,.06);
            overflow: hidden;
        }

        .room-edit-card-head{
            padding: 22px 24px 14px;
            border-bottom: 1px solid #edf2f7;
            background: linear-gradient(180deg, rgba(248,250,252,.95) 0%, rgba(255,255,255,.98) 100%);
        }

        .room-edit-card-title{
            margin: 0;
            font-size: 1.04rem;
            font-weight: 900;
            color: #0f172a;
        }

        .room-edit-card-sub{
            margin-top: 4px;
            font-size: .82rem;
            color: #64748b;
        }

        .room-edit-card-body{
            padding: 22px 24px 24px;
        }

        .room-edit-error{
            background: linear-gradient(180deg, #fff1f2 0%, #ffe4e6 100%);
            border: 1px solid #fecdd3;
            border-radius: 20px;
            padding: 14px 16px;
            margin-bottom: 18px;
            box-shadow: 0 10px 24px rgba(244,63,94,.08);
        }

        .room-edit-error-title{
            font-size: .88rem;
            font-weight: 900;
            color: #be123c;
            margin-bottom: 8px;
        }

        .room-edit-success{
            background: linear-gradient(180deg, #ecfdf5 0%, #f0fdf4 100%);
            border: 1px solid #bbf7d0;
            border-radius: 20px;
            padding: 14px 16px;
            margin-bottom: 18px;
            color: #166534;
            font-size: .88rem;
            font-weight: 800;
            box-shadow: 0 10px 24px rgba(22,163,74,.08);
        }

        .room-edit-grid{
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .room-edit-field{
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .room-edit-field.full{
            grid-column: 1 / -1;
        }

        .room-edit-label{
            font-size: .82rem;
            font-weight: 800;
            color: #334155;
        }

        .room-edit-input,
        .room-edit-select,
        .room-edit-textarea{
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 16px;
            background: #fff;
            padding: 12px 14px;
            font-size: .9rem;
            color: #0f172a;
            outline: none;
            transition: .2s ease;
            box-shadow: inset 0 1px 2px rgba(15,23,42,.02);
        }

        .room-edit-textarea{
            min-height: 110px;
            resize: vertical;
        }

        .room-edit-input:focus,
        .room-edit-select:focus,
        .room-edit-textarea:focus{
            border-color: #16a34a;
            box-shadow: 0 0 0 4px rgba(22,163,74,.10);
        }

        .room-edit-help{
            font-size: .75rem;
            color: #64748b;
            line-height: 1.55;
        }

        .room-edit-meta{
            margin-top: 18px;
            border-radius: 20px;
            padding: 16px 18px;
            background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
            border: 1px solid #e2e8f0;
        }

        .room-edit-meta-title{
            font-size: .82rem;
            font-weight: 900;
            color: #334155;
            margin-bottom: 8px;
        }

        .room-edit-meta-list{
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 6px;
        }

        .room-edit-meta-list li{
            font-size: .83rem;
            color: #475569;
        }

        .room-edit-actions{
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .room-edit-btn{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-width: 150px;
            padding: 12px 16px;
            border-radius: 16px;
            font-size: .86rem;
            font-weight: 800;
            text-decoration: none;
            transition: .2s ease;
            border: none;
            cursor: pointer;
        }

        .room-edit-btn.cancel{
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .room-edit-btn.cancel:hover{
            background: #e2e8f0;
            color: #334155;
        }

        .room-edit-btn.save{
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            box-shadow: 0 12px 28px rgba(37,99,235,.16);
        }

        .room-edit-btn.save:hover{
            transform: translateY(-1px);
            box-shadow: 0 16px 30px rgba(37,99,235,.20);
        }

        @media (max-width: 768px){
            .room-edit-shell{
                padding: 0 12px;
            }

            .room-edit-hero{
                padding: 22px 18px;
                border-radius: 24px;
            }

            .room-edit-card-head,
            .room-edit-card-body{
                padding-left: 18px;
                padding-right: 18px;
            }

            .room-edit-grid{
                grid-template-columns: 1fr;
            }

            .room-edit-actions{
                justify-content: stretch;
            }

            .room-edit-btn{
                width: 100%;
            }
        }
    </style>

    <div class="room-edit-page">
        <div class="room-edit-shell">
            <div class="room-edit-hero">
                <div class="room-edit-hero-inner">
                    <div>
                        <h1 class="room-edit-title">Edit Room</h1>
                        <p class="room-edit-sub">
                            Update room details, capacity, type, and notes while keeping the room linked correctly to its parent structure.
                        </p>
                    </div>

                    <div class="room-edit-pill-row">
                        <span class="room-edit-pill">Structure: {{ $room->structure->name }}</span>
                        <span class="room-edit-pill">Room: {{ $room->name }}</span>
                    </div>
                </div>
            </div>

            <div class="room-edit-card">
                <div class="room-edit-card-head">
                    <h2 class="room-edit-card-title">Room Details</h2>
                    <div class="room-edit-card-sub">Review and update the room information below, then save your changes.</div>
                </div>

                <div class="room-edit-card-body">
                    @if ($errors->any())
                        <div class="room-edit-error">
                            <div class="room-edit-error-title">Please correct the following before saving:</div>
                            <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (session('success'))
                        <div class="room-edit-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('rooms.update', $room) }}">
                        @csrf
                        @method('PUT')

                        <div class="room-edit-grid">
                            <div class="room-edit-field">
                                <label class="room-edit-label">Name <span class="text-red-500">*</span></label>
                                <input
                                    type="text"
                                    name="name"
                                    value="{{ old('name', $room->name) }}"
                                    class="room-edit-input"
                                    required
                                >
                                @error('name')
                                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="room-edit-field">
                                <label class="room-edit-label">Code <span class="text-red-500">*</span></label>
                                <input
                                    type="text"
                                    name="code"
                                    value="{{ old('code', $room->code) }}"
                                    class="room-edit-input"
                                    required
                                >
                                @error('code')
                                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                                <div class="room-edit-help">Must be unique within this structure.</div>
                            </div>

                            <div class="room-edit-field">
                                <label class="room-edit-label">Capacity (optional)</label>
                                <input
                                    type="number"
                                    name="capacity"
                                    min="0"
                                    value="{{ old('capacity', $room->capacity) }}"
                                    class="room-edit-input"
                                    placeholder="Leave empty for unlimited"
                                >
                                @error('capacity')
                                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="room-edit-field">
                                <label class="room-edit-label">Type <span class="text-red-500">*</span></label>
                                <select name="type" class="room-edit-select" required>
                                    <option value="general" {{ old('type', $room->type) == 'general' ? 'selected' : '' }}>General/Breeding</option>
                                    <option value="maternity" {{ old('type', $room->type) == 'maternity' ? 'selected' : '' }}>Maternity</option>
                                    <option value="gestation" {{ old('type', $room->type) == 'gestation' ? 'selected' : '' }}>Gestation</option>
                                    <option value="weaning" {{ old('type', $room->type) == 'weaning' ? 'selected' : '' }}>Weaning</option>
                                    <option value="finisher" {{ old('type', $room->type) == 'finisher' ? 'selected' : '' }}>Finisher</option>
                                    <option value="quarantine" {{ old('type', $room->type) == 'quarantine' ? 'selected' : '' }}>Quarantine</option>
                                </select>
                                @error('type')
                                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="room-edit-field full">
                                <label class="room-edit-label">Notes (optional)</label>
                                <textarea
                                    name="notes"
                                    rows="4"
                                    class="room-edit-textarea"
                                    placeholder="Special requirements, status, or any additional information about this room"
                                >{{ old('notes', $room->notes) }}</textarea>
                                @error('notes')
                                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                                <div class="room-edit-help">Use notes for room restrictions, special handling, or extra guidance for staff.</div>
                            </div>
                        </div>

                        <div class="room-edit-meta">
                            <div class="room-edit-meta-title">Current room snapshot</div>
                            <ul class="room-edit-meta-list">
                                <li><strong>Structure:</strong> {{ $room->structure->name }}</li>
                                <li><strong>Room Name:</strong> {{ $room->name }}</li>
                                <li><strong>Code:</strong> {{ $room->code }}</li>
                                <li><strong>Type:</strong> {{ ucfirst($room->type) }}</li>
                                <li><strong>Capacity:</strong> {{ $room->capacity !== null && $room->capacity !== '' ? $room->capacity : 'Unlimited' }}</li>
                            </ul>
                        </div>

                        <div class="room-edit-actions">
                            <a href="{{ route('rooms.show', $room) }}" class="room-edit-btn cancel">
                                Cancel
                            </a>
                            <button type="submit" class="room-edit-btn save">
                                Update Room
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>