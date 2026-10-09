<x-app-layout>
    <style>
        .gestation-edit-page{
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(59,130,246,.07), transparent 28%),
                radial-gradient(circle at top right, rgba(16,185,129,.08), transparent 24%),
                linear-gradient(180deg, #f8fafc 0%, #eef6f3 100%);
            padding: 18px 0 40px;
        }

        .gestation-edit-shell{
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 16px;
        }

        .gestation-edit-hero{
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 26px 28px;
            background: linear-gradient(135deg, #14532d 0%, #166534 42%, #2563eb 100%);
            box-shadow: 0 24px 54px rgba(22,101,52,.16);
            color: #fff;
            margin-bottom: 18px;
        }

        .gestation-edit-hero::before{
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,.16), transparent 28%),
                radial-gradient(circle at bottom left, rgba(255,255,255,.08), transparent 24%);
            pointer-events: none;
        }

        .gestation-edit-hero-inner{
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .gestation-edit-title{
            margin: 0;
            font-size: clamp(1.9rem, 3vw, 2.9rem);
            line-height: 1.02;
            font-weight: 900;
            letter-spacing: -.04em;
        }

        .gestation-edit-sub{
            margin: 10px 0 0;
            max-width: 760px;
            color: rgba(255,255,255,.90);
            font-size: .95rem;
            line-height: 1.75;
        }

        .gestation-edit-pill-row{
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .gestation-edit-pill{
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

        .gestation-edit-stack{
            display: grid;
            gap: 18px;
        }

        .gestation-edit-card{
            background: rgba(255,255,255,.97);
            border: 1px solid #dbe7f0;
            border-radius: 28px;
            box-shadow: 0 14px 34px rgba(15,23,42,.06);
            overflow: hidden;
        }

        .gestation-edit-card-head{
            padding: 22px 24px 14px;
            border-bottom: 1px solid #edf2f7;
            background: linear-gradient(180deg, rgba(248,250,252,.95) 0%, rgba(255,255,255,.98) 100%);
        }

        .gestation-edit-card-title{
            margin: 0;
            font-size: 1.04rem;
            font-weight: 900;
            color: #0f172a;
        }

        .gestation-edit-card-sub{
            margin-top: 4px;
            font-size: .82rem;
            color: #64748b;
        }

        .gestation-edit-card-body{
            padding: 22px 24px 24px;
        }

        .gestation-edit-grid{
            display: grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap: 16px;
        }

        .gestation-edit-field{
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .gestation-edit-field.full{
            grid-column: 1 / -1;
        }

        .gestation-edit-label{
            font-size: .82rem;
            font-weight: 800;
            color: #334155;
        }

        .gestation-edit-input,
        .gestation-edit-select,
        .gestation-edit-textarea{
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

        .gestation-edit-textarea{
            min-height: 110px;
            resize: vertical;
        }

        .gestation-edit-input:focus,
        .gestation-edit-select:focus,
        .gestation-edit-textarea:focus{
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37,99,235,.10);
        }

        .gestation-edit-help{
            font-size: .75rem;
            color: #64748b;
            line-height: 1.55;
        }

        .gestation-edit-display{
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 14px 16px;
            min-height: 48px;
            display: flex;
            align-items: center;
        }

        .gestation-edit-animal{
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: #0f172a;
            font-size: .88rem;
            font-weight: 800;
        }

        .gestation-edit-animal svg{
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        .gestation-edit-animal.male svg{
            color: #2563eb;
        }

        .gestation-edit-animal.female svg{
            color: #db2777;
        }

        .gestation-edit-note{
            border-radius: 18px;
            padding: 14px 16px;
            background: linear-gradient(180deg, #eff6ff 0%, #f8fbff 100%);
            border: 1px solid #bfdbfe;
            color: #1e40af;
            font-size: .83rem;
            line-height: 1.65;
        }

        .gestation-edit-error{
            background: linear-gradient(180deg, #fff1f2 0%, #ffe4e6 100%);
            border: 1px solid #fecdd3;
            border-radius: 20px;
            padding: 14px 16px;
            box-shadow: 0 10px 24px rgba(244,63,94,.08);
        }

        .gestation-edit-error-title{
            font-size: .88rem;
            font-weight: 900;
            color: #be123c;
            margin-bottom: 8px;
        }

        .gestation-edit-actions{
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 4px;
        }

        .gestation-edit-btn{
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

        .gestation-edit-btn.cancel{
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .gestation-edit-btn.cancel:hover{
            background: #e2e8f0;
            color: #334155;
        }

        .gestation-edit-btn.save{
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            box-shadow: 0 12px 28px rgba(37,99,235,.16);
        }

        .gestation-edit-btn.save:hover{
            transform: translateY(-1px);
            box-shadow: 0 16px 30px rgba(37,99,235,.20);
        }

        @media (max-width: 768px){
            .gestation-edit-shell{
                padding: 0 12px;
            }

            .gestation-edit-hero{
                padding: 22px 18px;
                border-radius: 24px;
            }

            .gestation-edit-card-head,
            .gestation-edit-card-body{
                padding-left: 18px;
                padding-right: 18px;
            }

            .gestation-edit-grid{
                grid-template-columns: 1fr;
            }

            .gestation-edit-actions{
                justify-content: stretch;
            }

            .gestation-edit-btn{
                width: 100%;
            }
        }
    </style>

    <div class="gestation-edit-page">
        <div class="gestation-edit-shell">
            <div class="gestation-edit-hero">
                <div class="gestation-edit-hero-inner">
                    <div>
                        <h1 class="gestation-edit-title">Edit Gestation Record</h1>
                        <p class="gestation-edit-sub">
                            Update mating details, pregnancy status, farrowing expectations, and supporting notes while keeping the existing gestation workflow intact.
                        </p>
                    </div>

                    <div class="gestation-edit-pill-row">
                        <span class="gestation-edit-pill">{{ $mating->male?->identityAt($mating->actual_date ?? $mating->planned_date) }} × {{ $mating->female?->identityAt($mating->actual_date ?? $mating->planned_date) }}</span>
                        <span class="gestation-edit-pill">{{ ucfirst(str_replace('_', ' ', $mating->pregnancy_status)) }}</span>
                    </div>
                </div>
            </div>

            <main class="gestation-edit-stack">
                @if ($errors->any())
                    <div class="gestation-edit-error">
                        <div class="gestation-edit-error-title">Please correct the following before saving:</div>
                        <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('gestation.update', $mating) }}" class="gestation-edit-stack">
                    @csrf
                    @method('PUT')

                    <div class="gestation-edit-card">
                        <div class="gestation-edit-card-head">
                            <h2 class="gestation-edit-card-title">Mating Information</h2>
                            <div class="gestation-edit-card-sub">Review the breeding pair and update the key mating and farrowing dates.</div>
                        </div>

                        <div class="gestation-edit-card-body">
                            <div class="gestation-edit-grid">
                                <div class="gestation-edit-field">
                                    <label class="gestation-edit-label">Male (Boar)</label>
                                    <div class="gestation-edit-display">
                                        <span class="gestation-edit-animal male">
                                            <svg fill="currentColor" viewBox="0 0 24 24">
                                                <circle cx="9" cy="15" r="6" stroke="currentColor" stroke-width="2" fill="none"/>
                                                <path d="M15 9L21 3M21 3h-5M21 3v5" stroke="currentColor" stroke-width="2" fill="none"/>
                                            </svg>
                                            {{ $mating->male?->identityAt($mating->actual_date ?? $mating->planned_date) }} • {{ $mating->male->code }}
                                        </span>
                                    </div>
                                </div>

                                <div class="gestation-edit-field">
                                    <label class="gestation-edit-label">Female (Sow)</label>
                                    <div class="gestation-edit-display">
                                        <span class="gestation-edit-animal female">
                                            <svg fill="currentColor" viewBox="0 0 24 24">
                                                <circle cx="12" cy="8" r="5" stroke="currentColor" stroke-width="2" fill="none"/>
                                                <path d="M12 13v8M9 18h6" stroke="currentColor" stroke-width="2" fill="none"/>
                                            </svg>
                                            {{ $mating->female?->identityAt($mating->actual_date ?? $mating->planned_date) }} • {{ $mating->female->code }}
                                        </span>
                                    </div>
                                </div>

                                <div class="gestation-edit-field">
                                    <label for="actual_date" class="gestation-edit-label">Mating Date</label>
                                    <input
                                        type="date"
                                        name="actual_date"
                                        id="actual_date"
                                        required
                                        max="{{ now()->toDateString() }}"
                                        value="{{ old('actual_date', $mating->actual_date ? $mating->actual_date->toDateString() : '') }}"
                                        class="gestation-edit-input"
                                    >
                                    @error('actual_date')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="gestation-edit-field">
                                    <label for="expected_farrowing_date" class="gestation-edit-label">Expected Farrowing Date</label>
                                    <input
                                        type="date"
                                        name="expected_farrowing_date"
                                        id="expected_farrowing_date"
                                        required
                                        value="{{ old('expected_farrowing_date', $mating->expected_farrowing_date ? $mating->expected_farrowing_date->toDateString() : '') }}"
                                        class="gestation-edit-input"
                                    >
                                    @error('expected_farrowing_date')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="gestation-edit-note" style="margin-top: 18px;">
                                Keep the mating date and expected farrowing date accurate, because these fields affect gestation tracking and due-date monitoring across the breeding workflow.
                            </div>
                        </div>
                    </div>

                    <div class="gestation-edit-card">
                        <div class="gestation-edit-card-head">
                            <h2 class="gestation-edit-card-title">Pregnancy Status</h2>
                            <div class="gestation-edit-card-sub">Update pregnancy outcome, check details, and current confidence level.</div>
                        </div>

                        <div class="gestation-edit-card-body">
                            <div class="gestation-edit-grid">
                                <div class="gestation-edit-field">
                                    <label for="pregnancy_status" class="gestation-edit-label">Pregnancy Status</label>
                                    <select name="pregnancy_status" id="pregnancy_status" required class="gestation-edit-select">
                                        <option value="pending" {{ old('pregnancy_status', $mating->pregnancy_status) == 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="pregnant" {{ old('pregnancy_status', $mating->pregnancy_status) == 'pregnant' ? 'selected' : '' }}>Pregnant</option>
                                        <option value="not_pregnant" {{ old('pregnancy_status', $mating->pregnancy_status) == 'not_pregnant' ? 'selected' : '' }}>Not Pregnant</option>
                                        <option value="uncertain" {{ old('pregnancy_status', $mating->pregnancy_status) == 'uncertain' ? 'selected' : '' }}>Uncertain</option>
                                        <option value="lost_pregnancy" {{ old('pregnancy_status', $mating->pregnancy_status) == 'lost_pregnancy' ? 'selected' : '' }}>Lost Pregnancy</option>
                                        <option value="aborted" {{ old('pregnancy_status', $mating->pregnancy_status) == 'aborted' ? 'selected' : '' }}>Aborted</option>
                                        <option value="farrowed" {{ old('pregnancy_status', $mating->pregnancy_status) == 'farrowed' ? 'selected' : '' }}>Farrowed</option>
                                    </select>
                                    @error('pregnancy_status')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="gestation-edit-field">
                                    <label for="expected_piglets" class="gestation-edit-label">Expected Piglets</label>
                                    <input
                                        type="number"
                                        name="expected_piglets"
                                        id="expected_piglets"
                                        min="1"
                                        max="25"
                                        value="{{ old('expected_piglets', $mating->expected_piglets) }}"
                                        class="gestation-edit-input"
                                    >
                                    @error('expected_piglets')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="gestation-edit-field">
                                    <label for="pregnancy_check_date" class="gestation-edit-label">Pregnancy Check Date</label>
                                    <input
                                        type="date"
                                        name="pregnancy_check_date"
                                        id="pregnancy_check_date"
                                        max="{{ now()->toDateString() }}"
                                        value="{{ old('pregnancy_check_date', $mating->pregnancy_check_date ? $mating->pregnancy_check_date->toDateString() : '') }}"
                                        class="gestation-edit-input"
                                    >
                                    @error('pregnancy_check_date')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="gestation-edit-field">
                                    <label for="pregnancy_check_method" class="gestation-edit-label">Check Method</label>
                                    <select name="pregnancy_check_method" id="pregnancy_check_method" class="gestation-edit-select">
                                        <option value="">Select Method</option>
                                        <option value="ultrasound" {{ old('pregnancy_check_method', $mating->pregnancy_check_method) == 'ultrasound' ? 'selected' : '' }}>Ultrasound</option>
                                        <option value="manual" {{ old('pregnancy_check_method', $mating->pregnancy_check_method) == 'manual' ? 'selected' : '' }}>Manual</option>
                                        <option value="blood_test" {{ old('pregnancy_check_method', $mating->pregnancy_check_method) == 'blood_test' ? 'selected' : '' }}>Blood Test</option>
                                        <option value="other" {{ old('pregnancy_check_method', $mating->pregnancy_check_method) == 'other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                    @error('pregnancy_check_method')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="gestation-edit-field">
                                    <label for="pregnancy_confidence" class="gestation-edit-label">Confidence Level</label>
                                    <select name="pregnancy_confidence" id="pregnancy_confidence" class="gestation-edit-select">
                                        <option value="">Select Confidence</option>
                                        <option value="high" {{ old('pregnancy_confidence', $mating->pregnancy_confidence) == 'high' ? 'selected' : '' }}>High</option>
                                        <option value="medium" {{ old('pregnancy_confidence', $mating->pregnancy_confidence) == 'medium' ? 'selected' : '' }}>Medium</option>
                                        <option value="low" {{ old('pregnancy_confidence', $mating->pregnancy_confidence) == 'low' ? 'selected' : '' }}>Low</option>
                                    </select>
                                    @error('pregnancy_confidence')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="gestation-edit-field">
                                    <label for="pregnancy_status_date" class="gestation-edit-label">Status Update Date</label>
                                    <input
                                        type="date"
                                        name="pregnancy_status_date"
                                        id="pregnancy_status_date"
                                        max="{{ now()->toDateString() }}"
                                        value="{{ old('pregnancy_status_date', $mating->pregnancy_status_date ? $mating->pregnancy_status_date->toDateString() : '') }}"
                                        class="gestation-edit-input"
                                    >
                                    @error('pregnancy_status_date')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="gestation-edit-card">
                        <div class="gestation-edit-card-head">
                            <h2 class="gestation-edit-card-title">Notes and Additional Information</h2>
                            <div class="gestation-edit-card-sub">Capture supporting observations, outcome notes, and record history context.</div>
                        </div>

                        <div class="gestation-edit-card-body">
                            <div class="gestation-edit-grid">
                                <div class="gestation-edit-field full">
                                    <label for="pregnancy_check_notes" class="gestation-edit-label">Pregnancy Check Notes</label>
                                    <textarea
                                        name="pregnancy_check_notes"
                                        id="pregnancy_check_notes"
                                        rows="3"
                                        maxlength="1000"
                                        class="gestation-edit-textarea"
                                        placeholder="Notes from pregnancy check..."
                                    >{{ old('pregnancy_check_notes', $mating->pregnancy_check_notes) }}</textarea>
                                    @error('pregnancy_check_notes')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="gestation-edit-field full">
                                    <label for="pregnancy_status_notes" class="gestation-edit-label">Status Update Notes</label>
                                    <textarea
                                        name="pregnancy_status_notes"
                                        id="pregnancy_status_notes"
                                        rows="3"
                                        maxlength="1000"
                                        class="gestation-edit-textarea"
                                        placeholder="Notes about status changes..."
                                    >{{ old('pregnancy_status_notes', $mating->pregnancy_status_notes) }}</textarea>
                                    @error('pregnancy_status_notes')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="gestation-edit-field full">
                                    <label for="notes" class="gestation-edit-label">General Notes</label>
                                    <textarea
                                        name="notes"
                                        id="notes"
                                        rows="3"
                                        maxlength="1000"
                                        class="gestation-edit-textarea"
                                        placeholder="General notes about this mating..."
                                    >{{ old('notes', $mating->notes) }}</textarea>
                                    @error('notes')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="gestation-edit-field full">
                                    <label for="outcome_notes" class="gestation-edit-label">Outcome Notes</label>
                                    <textarea
                                        name="outcome_notes"
                                        id="outcome_notes"
                                        rows="3"
                                        maxlength="1000"
                                        class="gestation-edit-textarea"
                                        placeholder="Notes about the outcome..."
                                    >{{ old('outcome_notes', $mating->outcome_notes) }}</textarea>
                                    @error('outcome_notes')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="gestation-edit-actions">
                        <a href="{{ route('gestation.index') }}" class="gestation-edit-btn cancel">
                            Cancel
                        </a>
                        <button type="submit" class="gestation-edit-btn save">
                            Update Gestation Record
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>
</x-app-layout>