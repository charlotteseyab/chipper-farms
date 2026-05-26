<x-app-layout>
    <style>
        .structure-edit-page{
            min-height: 100vh;
            padding: 18px 0 40px;
        }

        .structure-edit-shell{
            max-width: 1180px;
            margin: 0 auto;
            padding: 0 16px;
        }

        .structure-edit-hero{
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 26px 28px;
            background: linear-gradient(135deg, #14532d 0%, #166534 48%, #0f766e 100%);
            box-shadow: 0 24px 54px rgba(22,101,52,.16);
            color: #fff;
            margin-bottom: 18px;
        }

        .structure-edit-hero::before{
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,.16), transparent 28%),
                radial-gradient(circle at bottom left, rgba(255,255,255,.08), transparent 24%);
            pointer-events: none;
        }

        .structure-edit-hero-inner{
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .structure-edit-title{
            margin: 0;
            font-size: clamp(1.8rem, 3vw, 2.7rem);
            line-height: 1.02;
            font-weight: 900;
            letter-spacing: -.04em;
        }

        .structure-edit-sub{
            margin: 10px 0 0;
            max-width: 760px;
            color: rgba(255,255,255,.90);
            font-size: .95rem;
            line-height: 1.75;
        }

        .structure-edit-pill-row{
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .structure-edit-pill{
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

        .structure-edit-card{
            background: rgba(255,255,255,.96);
            border: 1px solid #dbe7f0;
            border-radius: 28px;
            box-shadow: 0 14px 34px rgba(15,23,42,.06);
            overflow: hidden;
        }

        .structure-edit-card-head{
            padding: 22px 24px 14px;
            border-bottom: 1px solid #edf2f7;
            background: linear-gradient(180deg, rgba(248,250,252,.95) 0%, rgba(255,255,255,.98) 100%);
        }

        .structure-edit-card-title{
            margin: 0;
            font-size: 1.04rem;
            font-weight: 900;
            color: #0f172a;
        }

        .structure-edit-card-sub{
            margin-top: 4px;
            font-size: .82rem;
            color: #64748b;
        }

        .structure-edit-card-body{
            padding: 22px 24px 24px;
        }

        .structure-edit-error{
            background: linear-gradient(180deg, #fff1f2 0%, #ffe4e6 100%);
            border: 1px solid #fecdd3;
            border-radius: 20px;
            padding: 14px 16px;
            margin-bottom: 18px;
            box-shadow: 0 10px 24px rgba(244,63,94,.08);
        }

        .structure-edit-error-title{
            font-size: .88rem;
            font-weight: 900;
            color: #be123c;
            margin-bottom: 8px;
        }

        .structure-edit-grid{
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .structure-edit-field{
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .structure-edit-field.full{
            grid-column: 1 / -1;
        }

        .structure-edit-label{
            font-size: .82rem;
            font-weight: 800;
            color: #334155;
        }

        .structure-edit-input,
        .structure-edit-select,
        .structure-edit-textarea{
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

        .structure-edit-textarea{
            min-height: 110px;
            resize: vertical;
        }

        .structure-edit-input:focus,
        .structure-edit-select:focus,
        .structure-edit-textarea:focus{
            border-color: #16a34a;
            box-shadow: 0 0 0 4px rgba(22,163,74,.10);
        }

        .structure-edit-help{
            font-size: .75rem;
            color: #64748b;
            line-height: 1.55;
        }

        .structure-edit-meta{
            margin-top: 18px;
            border-radius: 20px;
            padding: 16px 18px;
            background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
            border: 1px solid #e2e8f0;
        }

        .structure-edit-meta-title{
            font-size: .82rem;
            font-weight: 900;
            color: #334155;
            margin-bottom: 8px;
        }

        .structure-edit-meta-list{
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 6px;
        }

        .structure-edit-meta-list li{
            font-size: .83rem;
            color: #475569;
        }

        .structure-edit-actions{
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .structure-edit-btn{
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

        .structure-edit-btn.cancel{
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .structure-edit-btn.cancel:hover{
            background: #e2e8f0;
            color: #334155;
        }

        .structure-edit-btn.save{
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            box-shadow: 0 12px 28px rgba(37,99,235,.16);
        }

        .structure-edit-btn.save:hover{
            transform: translateY(-1px);
            box-shadow: 0 16px 30px rgba(37,99,235,.20);
        }

        @media (max-width: 768px){
            .structure-edit-shell{
                padding: 0 12px;
            }

            .structure-edit-hero{
                padding: 22px 18px;
                border-radius: 24px;
            }

            .structure-edit-card-head,
            .structure-edit-card-body{
                padding-left: 18px;
                padding-right: 18px;
            }

            .structure-edit-grid{
                grid-template-columns: 1fr;
            }

            .structure-edit-actions{
                justify-content: stretch;
            }

            .structure-edit-btn{
                width: 100%;
            }
        }
    </style>

    <div class="structure-edit-page">
        <div class="structure-edit-shell">
            <div class="structure-edit-hero">
                <div class="structure-edit-hero-inner">
                    <div>
                        <h1 class="structure-edit-title">Edit Structure</h1>
                        <p class="structure-edit-sub">
                            Update the structure details, capacity, and status without affecting the current room organization workflow.
                        </p>
                    </div>

                    <div class="structure-edit-pill-row">
                        <span class="structure-edit-pill">Current: {{ $structure->name }}</span>
                        <span class="structure-edit-pill">{{ ucfirst(str_replace('_', ' ', $structure->status)) }}</span>
                    </div>
                </div>
            </div>

            <div class="structure-edit-card">
                <div class="structure-edit-card-head">
                    <h2 class="structure-edit-card-title">Structure Details</h2>
                    <div class="structure-edit-card-sub">Review and update the details below, then save your changes.</div>
                </div>

                <div class="structure-edit-card-body">
                    @if ($errors->any())
                        <div class="structure-edit-error">
                            <div class="structure-edit-error-title">Please correct the following before saving:</div>
                            <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('structures.update', $structure) }}">
                        @csrf
                        @method('PUT')

                        <div class="structure-edit-grid">
                            <div class="structure-edit-field">
                                <label class="structure-edit-label">Name</label>
                                <input
                                    type="text"
                                    name="name"
                                    value="{{ old('name', $structure->name) }}"
                                    class="structure-edit-input"
                                    required
                                >
                                <div class="structure-edit-help">Use a clear name staff can recognize quickly.</div>
                            </div>

                            <div class="structure-edit-field">
                                <label class="structure-edit-label">Capacity (optional)</label>
                                <input
                                    type="number"
                                    name="capacity"
                                    min="0"
                                    value="{{ old('capacity', $structure->capacity) }}"
                                    class="structure-edit-input"
                                    placeholder="Leave empty for unlimited"
                                >
                                <div class="structure-edit-help">Leave empty if this structure should not have a room limit.</div>
                            </div>

                            <div class="structure-edit-field">
                                <label class="structure-edit-label">Status</label>
                                <select name="status" class="structure-edit-select" required>
                                    <option value="active" {{ old('status', $structure->status) == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ old('status', $structure->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                    <option value="under_maintenance" {{ old('status', $structure->status) == 'under_maintenance' ? 'selected' : '' }}>Under Maintenance</option>
                                </select>
                                <div class="structure-edit-help">Set maintenance when the structure should not be actively used.</div>
                            </div>

                            <div class="structure-edit-field full">
                                <label class="structure-edit-label">Description (optional)</label>
                                <textarea
                                    name="description"
                                    rows="4"
                                    class="structure-edit-textarea"
                                    placeholder="Describe the structure, its purpose, or any important details"
                                >{{ old('description', $structure->description) }}</textarea>
                                <div class="structure-edit-help">Helpful for recording location purpose, restrictions, or notes for staff.</div>
                            </div>
                        </div>

                        <div class="structure-edit-meta">
                            <div class="structure-edit-meta-title">Current structure snapshot</div>
                            <ul class="structure-edit-meta-list">
                                <li><strong>Name:</strong> {{ $structure->name }}</li>
                                <li><strong>Status:</strong> {{ ucfirst(str_replace('_', ' ', $structure->status)) }}</li>
                                <li><strong>Capacity:</strong> {{ $structure->capacity !== null && $structure->capacity !== '' ? $structure->capacity : 'Unlimited' }}</li>
                            </ul>
                        </div>

                        <div class="structure-edit-actions">
                            <a href="{{ route('structures.show', $structure) }}" class="structure-edit-btn cancel">
                                Cancel
                            </a>
                            <button type="submit" class="structure-edit-btn save">
                                Update Structure
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>