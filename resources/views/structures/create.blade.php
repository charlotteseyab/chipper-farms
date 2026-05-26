<x-app-layout>
    <style>
        .structure-create-page{
            min-height: 100vh;
            padding: 18px 0 40px;
        }

        .structure-create-shell{
            max-width: 1180px;
            margin: 0 auto;
            padding: 0 16px;
        }

        .structure-create-hero{
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 26px 28px;
            background: linear-gradient(135deg, #14532d 0%, #166534 48%, #0f766e 100%);
            box-shadow: 0 24px 54px rgba(22,101,52,.16);
            color: #fff;
            margin-bottom: 18px;
        }

        .structure-create-hero::before{
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,.16), transparent 28%),
                radial-gradient(circle at bottom left, rgba(255,255,255,.08), transparent 24%);
            pointer-events: none;
        }

        .structure-create-hero-inner{
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .structure-create-title{
            margin: 0;
            font-size: clamp(1.8rem, 3vw, 2.7rem);
            line-height: 1.02;
            font-weight: 900;
            letter-spacing: -.04em;
        }

        .structure-create-sub{
            margin: 10px 0 0;
            max-width: 760px;
            color: rgba(255,255,255,.90);
            font-size: .95rem;
            line-height: 1.75;
        }

        .structure-create-pill-row{
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .structure-create-pill{
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

        .structure-create-card{
            background: rgba(255,255,255,.96);
            border: 1px solid #dbe7f0;
            border-radius: 28px;
            box-shadow: 0 14px 34px rgba(15,23,42,.06);
            overflow: hidden;
        }

        .structure-create-card-head{
            padding: 22px 24px 14px;
            border-bottom: 1px solid #edf2f7;
            background: linear-gradient(180deg, rgba(248,250,252,.95) 0%, rgba(255,255,255,.98) 100%);
        }

        .structure-create-card-title{
            margin: 0;
            font-size: 1.04rem;
            font-weight: 900;
            color: #0f172a;
        }

        .structure-create-card-sub{
            margin-top: 4px;
            font-size: .82rem;
            color: #64748b;
        }

        .structure-create-card-body{
            padding: 22px 24px 24px;
        }

        .structure-create-error{
            background: linear-gradient(180deg, #fff1f2 0%, #ffe4e6 100%);
            border: 1px solid #fecdd3;
            border-radius: 20px;
            padding: 14px 16px;
            margin-bottom: 18px;
            box-shadow: 0 10px 24px rgba(244,63,94,.08);
        }

        .structure-create-error-title{
            font-size: .88rem;
            font-weight: 900;
            color: #be123c;
            margin-bottom: 8px;
        }

        .structure-create-grid{
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .structure-create-field{
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .structure-create-field.full{
            grid-column: 1 / -1;
        }

        .structure-create-label{
            font-size: .82rem;
            font-weight: 800;
            color: #334155;
        }

        .structure-create-input,
        .structure-create-select,
        .structure-create-textarea{
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

        .structure-create-textarea{
            min-height: 110px;
            resize: vertical;
        }

        .structure-create-input:focus,
        .structure-create-select:focus,
        .structure-create-textarea:focus{
            border-color: #16a34a;
            box-shadow: 0 0 0 4px rgba(22,163,74,.10);
        }

        .structure-create-help{
            font-size: .75rem;
            color: #64748b;
            line-height: 1.55;
        }

        .structure-create-meta{
            margin-top: 18px;
            border-radius: 20px;
            padding: 16px 18px;
            background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 100%);
            border: 1px solid #bbf7d0;
        }

        .structure-create-meta-title{
            font-size: .82rem;
            font-weight: 900;
            color: #166534;
            margin-bottom: 8px;
        }

        .structure-create-meta-list{
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 6px;
        }

        .structure-create-meta-list li{
            font-size: .83rem;
            color: #166534;
        }

        .structure-create-actions{
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .structure-create-btn{
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

        .structure-create-btn.cancel{
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .structure-create-btn.cancel:hover{
            background: #e2e8f0;
            color: #334155;
        }

        .structure-create-btn.save{
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: #fff;
            box-shadow: 0 12px 28px rgba(22,163,74,.16);
        }

        .structure-create-btn.save:hover{
            transform: translateY(-1px);
            box-shadow: 0 16px 30px rgba(22,163,74,.20);
        }

        @media (max-width: 768px){
            .structure-create-shell{
                padding: 0 12px;
            }

            .structure-create-hero{
                padding: 22px 18px;
                border-radius: 24px;
            }

            .structure-create-card-head,
            .structure-create-card-body{
                padding-left: 18px;
                padding-right: 18px;
            }

            .structure-create-grid{
                grid-template-columns: 1fr;
            }

            .structure-create-actions{
                justify-content: stretch;
            }

            .structure-create-btn{
                width: 100%;
            }
        }
    </style>

    <div class="structure-create-page">
        <div class="structure-create-shell">
            <div class="structure-create-hero">
                <div class="structure-create-hero-inner">
                    <div>
                        <h1 class="structure-create-title">Create Structure</h1>
                        <p class="structure-create-sub">
                            Add a new farm structure to organize rooms, manage housing capacity, and improve pig location tracking across the system.
                        </p>
                    </div>

                    <div class="structure-create-pill-row">
                        <span class="structure-create-pill">Simple setup</span>
                        <span class="structure-create-pill">Capacity control</span>
                        <span class="structure-create-pill">Farm-ready structure records</span>
                    </div>
                </div>
            </div>

            <div class="structure-create-card">
                <div class="structure-create-card-head">
                    <h2 class="structure-create-card-title">Structure Details</h2>
                    <div class="structure-create-card-sub">Fill in the structure information below and save it to the system.</div>
                </div>

                <div class="structure-create-card-body">
                    @if ($errors->any())
                        <div class="structure-create-error">
                            <div class="structure-create-error-title">Please correct the following before creating the structure:</div>
                            <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('structures.store') }}">
                        @csrf

                        <div class="structure-create-grid">
                            <div class="structure-create-field">
                                <label class="structure-create-label">Name</label>
                                <input
                                    type="text"
                                    name="name"
                                    value="{{ old('name') }}"
                                    class="structure-create-input"
                                    required
                                >
                                <div class="structure-create-help">Use a name that is clear and easy for farm staff to recognize.</div>
                            </div>

                            <div class="structure-create-field">
                                <label class="structure-create-label">Capacity (optional)</label>
                                <input
                                    type="number"
                                    name="capacity"
                                    min="0"
                                    value="{{ old('capacity') }}"
                                    class="structure-create-input"
                                    placeholder="Leave empty for unlimited"
                                >
                                <div class="structure-create-help">Leave empty if this structure should not have a room limit.</div>
                            </div>

                            <div class="structure-create-field">
                                <label class="structure-create-label">Status</label>
                                <select name="status" class="structure-create-select" required>
                                    <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                    <option value="under_maintenance" {{ old('status') == 'under_maintenance' ? 'selected' : '' }}>Under Maintenance</option>
                                </select>
                                <div class="structure-create-help">Choose the current operational state of the structure.</div>
                            </div>

                            <div class="structure-create-field full">
                                <label class="structure-create-label">Description (optional)</label>
                                <textarea
                                    name="description"
                                    rows="4"
                                    class="structure-create-textarea"
                                    placeholder="Describe the structure, its purpose, or any important details"
                                >{{ old('description') }}</textarea>
                                <div class="structure-create-help">You can describe the structure purpose, restrictions, or other operational notes.</div>
                            </div>
                        </div>

                        <div class="structure-create-meta">
                            <div class="structure-create-meta-title">Before you save</div>
                            <ul class="structure-create-meta-list">
                                <li><strong>Name</strong> should be unique and easy to identify.</li>
                                <li><strong>Capacity</strong> can be left empty if the structure should not have a limit.</li>
                                <li><strong>Status</strong> helps staff understand whether the structure is usable right now.</li>
                            </ul>
                        </div>

                        <div class="structure-create-actions">
                            <a href="{{ route('structures.index') }}" class="structure-create-btn cancel">
                                Cancel
                            </a>
                            <button type="submit" class="structure-create-btn save">
                                Create Structure
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>