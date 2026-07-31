<x-app-layout>
    <style>
        .weight-edit-page{
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(14,165,233,.08), transparent 28%),
                radial-gradient(circle at top right, rgba(16,185,129,.08), transparent 24%),
                linear-gradient(180deg, #f8fafc 0%, #eef5f7 100%);
            padding: 18px 0 40px;
        }

        .weight-edit-shell{
            max-width: 1380px;
            margin: 0 auto;
            padding: 0 16px;
        }

        .weight-edit-hero{
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 26px 28px;
            margin-bottom: 18px;
            background: linear-gradient(135deg, #0f766e 0%, #0f766e 35%, #2563eb 100%);
            color: #fff;
            box-shadow: 0 24px 54px rgba(15,118,110,.18);
        }

        .weight-edit-hero::before{
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,.16), transparent 28%),
                radial-gradient(circle at bottom left, rgba(255,255,255,.10), transparent 24%);
            pointer-events: none;
        }

        .weight-edit-hero-inner{
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .weight-edit-title{
            margin: 0;
            font-size: clamp(1.85rem, 3vw, 2.7rem);
            line-height: 1.02;
            font-weight: 900;
            letter-spacing: -.04em;
        }

        .weight-edit-sub{
            margin: 10px 0 0;
            max-width: 760px;
            color: rgba(255,255,255,.90);
            font-size: .95rem;
            line-height: 1.75;
        }

        .weight-edit-actions{
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .weight-top-btn{
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

        .weight-top-btn-primary{
            background: rgba(255,255,255,.14);
            color: #fff;
        }

        .weight-top-btn-primary:hover{
            background: rgba(255,255,255,.20);
            transform: translateY(-1px);
        }

        .weight-top-btn-muted{
            background: rgba(255,255,255,.09);
            color: #ecfeff;
        }

        .weight-top-btn-muted:hover{
            background: rgba(255,255,255,.16);
        }

        .weight-edit-panel{
            background: rgba(255,255,255,.96);
            border: 1px solid #dbe7f0;
            border-radius: 28px;
            box-shadow: 0 14px 34px rgba(15,23,42,.06);
            overflow: hidden;
        }

        .weight-edit-panel-body{
            padding: 24px;
        }

        .weight-layout{
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(320px, .95fr);
            gap: 18px;
            align-items: start;
        }

        .weight-stack{
            display: grid;
            gap: 18px;
        }

        .weight-card{
            background: rgba(255,255,255,.97);
            border: 1px solid #dde6ee;
            border-radius: 24px;
            box-shadow: 0 10px 28px rgba(15,23,42,.05);
            overflow: hidden;
        }

        .weight-card-head{
            padding: 20px 22px 14px;
            border-bottom: 1px solid #edf2f7;
            background: linear-gradient(180deg, rgba(248,250,252,.95) 0%, rgba(255,255,255,.98) 100%);
        }

        .weight-card-title{
            margin: 0;
            font-size: 1.02rem;
            font-weight: 900;
            color: #0f172a;
        }

        .weight-card-sub{
            margin-top: 4px;
            font-size: .82rem;
            color: #64748b;
        }

        .weight-card-body{
            padding: 20px 22px 22px;
        }

        .weight-banner{
            border-radius: 20px;
            padding: 16px 18px;
            border: 1px solid transparent;
        }

        .weight-banner-blue{
            background: linear-gradient(180deg, #eff6ff 0%, #f8fbff 100%);
            border-color: #bfdbfe;
        }

        .weight-banner-emerald{
            background: linear-gradient(180deg, #ecfdf5 0%, #f0fdf4 100%);
            border-color: #bbf7d0;
        }

        .weight-banner-title{
            font-size: .9rem;
            font-weight: 900;
            margin-bottom: 4px;
        }

        .weight-banner-blue .weight-banner-title{
            color: #1d4ed8;
        }

        .weight-banner-blue .weight-banner-text{
            color: #1e40af;
        }

        .weight-banner-emerald .weight-banner-title{
            color: #166534;
        }

        .weight-banner-emerald .weight-banner-text{
            color: #166534;
        }

        .weight-banner-text{
            font-size: .82rem;
            line-height: 1.7;
        }

        .weight-grid-2{
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .weight-grid-3{
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .weight-field{
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .weight-label{
            font-size: .84rem;
            font-weight: 800;
            color: #334155;
        }

        .weight-input,
        .weight-select,
        .weight-textarea{
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

        .weight-textarea{
            min-height: 112px;
            resize: vertical;
        }

        .weight-input:focus,
        .weight-select:focus,
        .weight-textarea:focus{
            border-color: #0f766e;
            box-shadow: 0 0 0 4px rgba(15,118,110,.10);
        }

        .weight-help{
            font-size: .75rem;
            color: #64748b;
            line-height: 1.55;
        }

        .weight-pig-card{
            border-radius: 22px;
            background: linear-gradient(180deg, #eff6ff 0%, #f8fbff 100%);
            border: 1px solid #bfdbfe;
            padding: 16px 18px;
        }

        .weight-pig-card h4{
            margin: 0 0 12px;
            font-size: .88rem;
            font-weight: 900;
            color: #1e40af;
        }

        .weight-pig-grid{
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .weight-pig-meta{
            background: rgba(255,255,255,.92);
            border: 1px solid #dbeafe;
            border-radius: 16px;
            padding: 12px 14px;
        }

        .weight-pig-meta-label{
            font-size: .72rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #64748b;
            margin-bottom: 5px;
        }

        .weight-pig-meta-value{
            font-size: .88rem;
            font-weight: 800;
            color: #0f172a;
        }

        .weight-insight-grid{
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .weight-insight{
            border-radius: 20px;
            padding: 16px;
            border: 1px solid;
        }

        .weight-insight.green{
            background: #f0fdf4;
            border-color: #bbf7d0;
        }

        .weight-insight.yellow{
            background: #fefce8;
            border-color: #fde68a;
        }

        .weight-insight h5{
            margin: 0 0 10px;
            font-size: .85rem;
            font-weight: 900;
        }

        .weight-insight.green h5{
            color: #166534;
        }

        .weight-insight.yellow h5{
            color: #92400e;
        }

        .weight-insight .weight-mini{
            display: grid;
            gap: 6px;
            font-size: .82rem;
        }

        .weight-insight .weight-mini div{
            color: #374151;
        }

        .weight-insight .weight-mini strong{
            color: #0f172a;
        }

        .weight-feedback-box{
            margin-top: 10px;
            border-radius: 14px;
            padding: 10px 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .weight-week-box{
            margin-top: 14px;
            border-radius: 18px;
            padding: 14px 16px;
            background: #fff;
            border: 1px solid #fde68a;
        }

        .weight-week-row{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            font-size: .84rem;
        }

        .weight-week-row + .weight-week-row{
            margin-top: 8px;
        }

        .weight-actions{
            display: grid;
            gap: 12px;
        }

        .weight-submit{
            width: 100%;
            border: none;
            border-radius: 18px;
            padding: 15px 18px;
            color: #fff;
            font-size: .95rem;
            font-weight: 900;
            letter-spacing: .01em;
            background: linear-gradient(135deg, #0f766e, #2563eb);
            box-shadow: 0 18px 34px rgba(15,118,110,.20);
            transition: .22s ease;
        }

        .weight-submit:hover{
            transform: translateY(-1px);
            box-shadow: 0 20px 36px rgba(15,118,110,.25);
        }

        .weight-back{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            text-decoration: none;
            border-radius: 16px;
            padding: 12px 14px;
            font-size: .88rem;
            font-weight: 800;
            color: #475569;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            transition: .2s ease;
        }

        .weight-back:hover{
            background: #f1f5f9;
            color: #1e293b;
        }

        @media (max-width: 1100px){
            .weight-layout{
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px){
            .weight-edit-shell{
                padding: 0 12px;
            }

            .weight-edit-hero{
                padding: 22px 18px;
                border-radius: 24px;
            }

            .weight-edit-panel-body,
            .weight-card-body,
            .weight-card-head{
                padding-left: 18px;
                padding-right: 18px;
            }

            .weight-grid-2,
            .weight-grid-3,
            .weight-pig-grid,
            .weight-insight-grid{
                grid-template-columns: 1fr;
            }

            .weight-edit-actions{
                width: 100%;
            }

            .weight-top-btn{
                width: 100%;
            }
        }
    </style>

    <div class="weight-edit-page">
        <div class="weight-edit-shell">
            <div class="weight-edit-hero">
                <div class="weight-edit-hero-inner">
                    <div>
                        <h1 class="weight-edit-title">Edit Weight Record</h1>
                        <p class="weight-edit-sub">
                            Review and update this weight record while keeping the same smart comparison tools for expected range,
                            previous history, and week-based tracking.
                        </p>
                    </div>

                    <div class="weight-edit-actions">
                        <a href="{{ route('weights.audit', $weight) }}" class="weight-top-btn weight-top-btn-primary">
                            Change History
                        </a>
                        <a href="{{ route('weights.index') }}" class="weight-top-btn weight-top-btn-muted">
                            ← Back to Records
                        </a>
                    </div>
                </div>
            </div>

            <div class="weight-edit-panel">
                <div class="weight-edit-panel-body">
                    <form method="POST" action="{{ route('weights.update', $weight) }}">
                        @csrf
                        @method('PUT')

                        <div class="weight-layout">
                            <div class="weight-stack">
                                <div class="weight-card">
                                    <div class="weight-card-head">
                                        <h3 class="weight-card-title">Selected Pig</h3>
                                        <div class="weight-card-sub">Confirm the pig and review its profile while editing this record.</div>
                                    </div>

                                    <div class="weight-card-body">
                                        <div class="weight-banner weight-banner-blue" style="margin-bottom:16px;">
                                            <div class="weight-banner-title">Editing existing record</div>
                                            <div class="weight-banner-text">
                                                Update the recorded weight, date, target week, or notes without losing the live comparison tools.
                                            </div>
                                        </div>

                                        <div class="weight-field">
                                            <label class="weight-label">Pig</label>
                                            <select name="pig_id" id="pig_id" required class="weight-select" data-searchable-select data-search-placeholder="Search by ear tag, code, breed, or gender...">
                                                <option value="" disabled>-- Select Pig --</option>
                                                @foreach($pigs as $pig)
                                                    <option value="{{ $pig->id }}"
                                                        data-ear-tag="{{ $pig->ear_tag_number }}"
                                                        data-code="{{ $pig->code }}"
                                                        data-breed="{{ $pig->breed }}"
                                                        data-gender="{{ $pig->gender }}"
                                                        data-age="{{ $pig->current_age_in_days }}"
                                                        data-birth-date="{{ $pig->date_of_birth ? $pig->date_of_birth->toDateString() : '' }}"
                                                        data-stock-type="{{ $pig->stock_type }}"
                                                        {{ old('pig_id', $weight->pig_id) == $pig->id ? 'selected' : '' }}>
                                                        {{ $pig->ear_tag_number }} - {{ $pig->code }} ({{ $pig->breed }}, {{ $pig->gender === 'M' ? 'Male' : 'Female' }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div id="pig-info" class="hidden weight-pig-card" style="margin-top:16px;">
                                            <h4>Selected Pig Information</h4>

                                            <div class="weight-pig-grid">
                                                <div class="weight-pig-meta">
                                                    <div class="weight-pig-meta-label">Ear Tag</div>
                                                    <div id="info-ear-tag" class="weight-pig-meta-value"></div>
                                                </div>

                                                <div class="weight-pig-meta">
                                                    <div class="weight-pig-meta-label">Code</div>
                                                    <div id="info-code" class="weight-pig-meta-value"></div>
                                                </div>

                                                <div class="weight-pig-meta">
                                                    <div class="weight-pig-meta-label">Breed</div>
                                                    <div id="info-breed" class="weight-pig-meta-value"></div>
                                                </div>

                                                <div class="weight-pig-meta">
                                                    <div class="weight-pig-meta-label">Gender</div>
                                                    <div id="info-gender" class="weight-pig-meta-value"></div>
                                                </div>

                                                <div class="weight-pig-meta">
                                                    <div class="weight-pig-meta-label">Age</div>
                                                    <div class="weight-pig-meta-value">
                                                        <span id="info-age"></span> days
                                                        (<span id="info-age-weeks"></span> weeks)
                                                    </div>
                                                </div>

                                                <div class="weight-pig-meta">
                                                    <div class="weight-pig-meta-label">Stock Type</div>
                                                    <div id="info-stock-type" class="weight-pig-meta-value"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="weight-card">
                                    <div class="weight-card-head">
                                        <h3 class="weight-card-title">Weight Entry</h3>
                                        <div class="weight-card-sub">Update the measurement and record context for this weighing.</div>
                                    </div>

                                    <div class="weight-card-body">
                                        <div class="weight-grid-2">
                                            <div class="weight-field">
                                                <label class="weight-label">Weight (kg)</label>
                                                <div class="relative">
                                                    <input
                                                        type="number"
                                                        name="weight_kg"
                                                        id="weight_kg"
                                                        step="0.1"
                                                        min="0.1"
                                                        max="500"
                                                        required
                                                        value="{{ old('weight_kg', $weight->weight ?? $weight->weight_kg) }}"
                                                        class="weight-input"
                                                        placeholder="Enter weight"
                                                        style="padding-right:58px;"
                                                    >
                                                    <span style="position:absolute; right:14px; top:12px; font-size:.82rem; font-weight:800; color:#64748b;">kg</span>
                                                </div>

                                                <div id="weight-feedback" class="weight-feedback-box hidden">
                                                    <div id="weight-status" class="flex items-center gap-2 text-sm">
                                                        <span id="weight-icon"></span>
                                                        <span id="weight-message"></span>
                                                    </div>
                                                    <div id="weight-comparison" class="text-xs text-gray-600 mt-1"></div>
                                                </div>

                                                <div class="weight-help">
                                                    <span id="weight-lbs-display"></span>
                                                    <span class="text-gray-400">• Smart comparison remains active while editing</span>
                                                </div>
                                            </div>

                                            <div class="weight-field">
                                                <label class="weight-label">Purpose</label>
                                                <select name="purpose" id="purpose" required class="weight-select" data-searchable-select>
                                                    <option value="" disabled>-- Select Purpose --</option>
                                                    <option value="routine" {{ old('purpose', $weight->purpose) == 'routine' ? 'selected' : '' }}>Monthly Routine</option>
                                                    <option value="breeding" {{ old('purpose', $weight->purpose) == 'breeding' ? 'selected' : '' }}>Breeding Assessment</option>
                                                    <option value="health" {{ old('purpose', $weight->purpose) == 'health' ? 'selected' : '' }}>Health Check</option>
                                                    <option value="sale" {{ old('purpose', $weight->purpose) == 'sale' ? 'selected' : '' }}>Pre-Sale</option>
                                                    <option value="birth" {{ old('purpose', $weight->purpose) == 'birth' ? 'selected' : '' }}>Birth Weight</option>
                                                    <option value="weaning" {{ old('purpose', $weight->purpose) == 'weaning' ? 'selected' : '' }}>Weaning Weight</option>
                                                    <option value="maturity" {{ old('purpose', $weight->purpose) == 'maturity' ? 'selected' : '' }}>Maturity Weight</option>
                                                    <option value="other" {{ old('purpose', $weight->purpose) == 'other' ? 'selected' : '' }}>Other</option>
                                                </select>

                                                <div id="purpose-other" class="{{ old('purpose', $weight->purpose) === 'other' ? '' : 'hidden' }}" style="margin-top:10px;">
                                                    <input
                                                        type="text"
                                                        name="purpose_other"
                                                        value="{{ old('purpose_other', $weight->purpose_other ?? '') }}"
                                                        placeholder="Please specify the purpose..."
                                                        class="weight-input"
                                                    >
                                                </div>

                                                <div class="weight-help">Keep the original purpose or update it if this record was misclassified.</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="weight-card">
                                    <div class="weight-card-head">
                                        <h3 class="weight-card-title">Weekly Tracking</h3>
                                        <div class="weight-card-sub">Adjust target week, weighing date, and recorded time as needed.</div>
                                    </div>

                                    <div class="weight-card-body">
                                        <div class="weight-grid-3">
                                            <div class="weight-field">
                                                <label class="weight-label">Target Week</label>
                                                <select name="target_week" id="target_week" required class="weight-select" data-searchable-select data-search-placeholder="Search weeks...">
                                                    <option value="" disabled>-- Select Week --</option>
                                                    @for($weekNo = 1; $weekNo <= 200; $weekNo++)
                                                        <option value="{{ $weekNo }}" {{ old('target_week', $weight->target_week) == $weekNo ? 'selected' : '' }}>
                                                            Week {{ $weekNo }} ({{ $weekNo * 7 }} days)
                                                        </option>
                                                    @endfor
                                                </select>
                                                <div class="weight-help">Which week should this weight be evaluated against?</div>
                                            </div>

                                            <div class="weight-field">
                                                <label class="weight-label">Weighing Date</label>
                                                <input
                                                    type="date"
                                                    name="weigh_date"
                                                    id="weigh_date"
                                                    required
                                                    max="{{ now()->toDateString() }}"
                                                    value="{{ old('weigh_date', optional($weight->weigh_date)->format('Y-m-d')) }}"
                                                    class="weight-input"
                                                >
                                                <div class="weight-help">Date the weighing happened.</div>
                                            </div>

                                            <div class="weight-field">
                                                <label class="weight-label">Weighing Time (optional)</label>
                                                <input
                                                    type="time"
                                                    name="weigh_time"
                                                    id="weigh_time"
                                                    value="{{ old('weigh_time', isset($weight->weigh_time) ? substr((string) $weight->weigh_time, 0, 5) : now()->format('H:i')) }}"
                                                    class="weight-input"
                                                >
                                                <div class="weight-help">Adjust if the original time was recorded incorrectly.</div>
                                            </div>
                                        </div>

                                        <div id="week-comparison" class="weight-week-box hidden">
                                            <div class="weight-week-row">
                                                <div>
                                                    <span class="text-yellow-700 font-medium">Target Week:</span>
                                                    <span id="target-week-display" class="text-yellow-800 font-semibold">-</span>
                                                </div>
                                                <div>
                                                    <span class="text-yellow-700 font-medium">Pig's Actual Age:</span>
                                                    <span id="actual-age-display" class="text-yellow-800 font-semibold">-</span>
                                                </div>
                                            </div>
                                            <div class="weight-week-row">
                                                <div id="week-variance" class="text-xs"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="weight-card">
                                    <div class="weight-card-head">
                                        <h3 class="weight-card-title">Condition Notes</h3>
                                        <div class="weight-card-sub">Update supporting condition notes if they were omitted or entered wrongly.</div>
                                    </div>

                                    <div class="weight-card-body">
                                        <div class="weight-grid-2">
                                            <div class="weight-field">
                                                <label class="weight-label">Body Condition Score (optional)</label>
                                                <select name="body_condition_score" class="weight-select" data-searchable-select>
                                                    <option value="" {{ old('body_condition_score', $weight->body_condition_score) === null || old('body_condition_score', $weight->body_condition_score) === '' ? 'selected' : '' }}>-- Not Assessed --</option>
                                                    <option value="1" {{ old('body_condition_score', $weight->body_condition_score) == '1' ? 'selected' : '' }}>1 - Very Thin</option>
                                                    <option value="2" {{ old('body_condition_score', $weight->body_condition_score) == '2' ? 'selected' : '' }}>2 - Thin</option>
                                                    <option value="3" {{ old('body_condition_score', $weight->body_condition_score) == '3' ? 'selected' : '' }}>3 - Ideal</option>
                                                    <option value="4" {{ old('body_condition_score', $weight->body_condition_score) == '4' ? 'selected' : '' }}>4 - Fat</option>
                                                    <option value="5" {{ old('body_condition_score', $weight->body_condition_score) == '5' ? 'selected' : '' }}>5 - Very Fat</option>
                                                </select>
                                            </div>

                                            <div class="weight-field">
                                                <label class="weight-label">Health Status (optional)</label>
                                                <select name="health_status" class="weight-select" data-searchable-select>
                                                    <option value="" {{ old('health_status', $weight->health_status) === null || old('health_status', $weight->health_status) === '' ? 'selected' : '' }}>-- Not Assessed --</option>
                                                    <option value="excellent" {{ old('health_status', $weight->health_status) == 'excellent' ? 'selected' : '' }}>Excellent</option>
                                                    <option value="good" {{ old('health_status', $weight->health_status) == 'good' ? 'selected' : '' }}>Good</option>
                                                    <option value="fair" {{ old('health_status', $weight->health_status) == 'fair' ? 'selected' : '' }}>Fair</option>
                                                    <option value="poor" {{ old('health_status', $weight->health_status) == 'poor' ? 'selected' : '' }}>Poor</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="weight-field" style="margin-top:14px;">
                                            <label class="weight-label">Notes (optional)</label>
                                            <textarea
                                                name="notes"
                                                rows="4"
                                                class="weight-textarea"
                                                placeholder="Any additional observations or notes about the weighing..."
                                            >{{ old('notes', $weight->notes) }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="weight-stack">
                                <div class="weight-card">
                                    <div class="weight-card-head">
                                        <h3 class="weight-card-title">Expected Weight & History</h3>
                                        <div class="weight-card-sub">Review expected standards and previous trend while editing.</div>
                                    </div>

                                    <div class="weight-card-body">
                                        <div class="weight-insight-grid">
                                            <div id="expected-weight-info" class="weight-insight green">
                                                <h5>Expected Weight</h5>
                                                <div id="expected-weight-display" class="weight-mini">
                                                    <div>Range: <strong id="expected-range">-</strong></div>
                                                    <div>Optimal: <strong id="expected-optimal">-</strong></div>
                                                </div>
                                                <div id="no-standard-message" class="text-sm text-gray-500 hidden" style="margin-top:8px;">
                                                    No weight standard set for this pig type/age
                                                </div>
                                                <div id="standard-loading" class="text-sm text-green-600 hidden" style="margin-top:8px;">
                                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    Loading standards...
                                                </div>
                                            </div>

                                            <div id="previous-weight-info" class="weight-insight yellow">
                                                <h5>Trend up Previous Weight</h5>
                                                <div id="previous-weight-display" class="weight-mini">
                                                    <div>Last: <strong id="previous-weight">-</strong></div>
                                                    <div>Date: <strong id="previous-date">-</strong></div>
                                                    <div>Days Ago: <strong id="days-since">-</strong></div>
                                                </div>
                                                <div id="no-previous-message" class="text-sm text-gray-500 hidden" style="margin-top:8px;">
                                                    No previous weight recorded
                                                </div>
                                                <div id="previous-loading" class="text-sm text-yellow-600 hidden" style="margin-top:8px;">
                                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    Loading history...
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="weight-card">
                                    <div class="weight-card-head">
                                        <h3 class="weight-card-title">Save Changes</h3>
                                        <div class="weight-card-sub">Update this record and keep it available for alerts, growth tracking, and reports.</div>
                                    </div>

                                    <div class="weight-card-body">
                                        <div class="weight-actions">
                                            <button type="submit" class="weight-submit">Update Weight Record</button>
                                            <a href="{{ route('weights.index') }}" class="weight-back">← Back to Weight Records</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const pigSelect = document.getElementById('pig_id');
            const pigInfo = document.getElementById('pig-info');
            const weightInput = document.getElementById('weight_kg');
            const weightFeedback = document.getElementById('weight-feedback');
            const targetWeekSelect = document.getElementById('target_week');
            const weighDateInput = document.getElementById('weigh_date');
            const weekComparison = document.getElementById('week-comparison');

            function updatePigInfo() {
                const selectedOption = pigSelect.options[pigSelect.selectedIndex];

                if (selectedOption && selectedOption.value) {
                    pigInfo.classList.remove('hidden');

                    document.getElementById('info-ear-tag').textContent = selectedOption.dataset.earTag || '-';
                    document.getElementById('info-code').textContent = selectedOption.dataset.code || '-';
                    document.getElementById('info-breed').textContent = selectedOption.dataset.breed || '-';
                    document.getElementById('info-gender').textContent = selectedOption.dataset.gender === 'M' ? 'Male' : 'Female';
                    document.getElementById('info-age').textContent = selectedOption.dataset.age || '0';
                    document.getElementById('info-age-weeks').textContent = Math.floor((parseInt(selectedOption.dataset.age || 0)) / 7);
                    document.getElementById('info-stock-type').textContent =
                        selectedOption.dataset.stockType
                            ? selectedOption.dataset.stockType.charAt(0).toUpperCase() + selectedOption.dataset.stockType.slice(1)
                            : '-';

                    loadWeightStandards(selectedOption.value, weighDateInput.value, targetWeekSelect.value);
                    loadPreviousWeight(selectedOption.value);
                    updateWeekComparison();
                    updateWeightFeedback();
                } else {
                    pigInfo.classList.add('hidden');
                    weightFeedback.classList.add('hidden');
                    weekComparison.classList.add('hidden');
                }
            }

            function loadWeightStandards(pigId, weighDateValue, targetWeekValue) {
                document.getElementById('expected-range').textContent = 'Loading...';
                document.getElementById('expected-optimal').textContent = 'Loading...';
                document.getElementById('no-standard-message').classList.add('hidden');
                document.getElementById('standard-loading').classList.remove('hidden');

                const params = new URLSearchParams();
                if (weighDateValue) {
                    params.append('date', weighDateValue);
                }
                if (targetWeekValue) {
                    params.append('week', targetWeekValue);
                }

                const url = params.toString()
                    ? `/api/pigs/${pigId}/expected-weight?${params.toString()}`
                    : `/api/pigs/${pigId}/expected-weight`;

                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data) {
                            const standard = data.data;
                            document.getElementById('expected-range').textContent = `${standard.min_weight} - ${standard.max_weight} kg`;
                            document.getElementById('expected-optimal').textContent = `${standard.optimal_weight} kg`;
                            window.currentWeightStandard = standard;
                        } else {
                            document.getElementById('expected-range').textContent = 'N/A';
                            document.getElementById('expected-optimal').textContent = 'N/A';

                            const messageEl = document.getElementById('no-standard-message');
                            if (data.data && data.data.note) {
                                messageEl.textContent = data.data.note;
                            } else if (data.message) {
                                messageEl.textContent = data.message;
                            } else {
                                messageEl.textContent = 'No weight standard set for this pig type/age.';
                            }

                            messageEl.classList.remove('hidden');
                            window.currentWeightStandard = null;
                        }

                        document.getElementById('standard-loading').classList.add('hidden');
                    })
                    .catch(error => {
                        console.error('Error loading weight standards:', error);
                        document.getElementById('expected-range').textContent = 'N/A';
                        document.getElementById('expected-optimal').textContent = 'N/A';
                        window.currentWeightStandard = null;
                        document.getElementById('standard-loading').classList.add('hidden');
                    });
            }

            function loadPreviousWeight(pigId) {
                document.getElementById('previous-weight').textContent = 'Loading...';
                document.getElementById('previous-date').textContent = 'Loading...';
                document.getElementById('days-since').textContent = 'Loading...';
                document.getElementById('no-previous-message').classList.add('hidden');
                document.getElementById('previous-loading').classList.remove('hidden');

                fetch(`/api/pigs/${pigId}/previous-weight`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data) {
                            const previous = data.data;
                            document.getElementById('previous-weight').textContent = `${previous.weight} kg`;
                            document.getElementById('previous-date').textContent = previous.date;
                            document.getElementById('days-since').textContent = Math.floor(previous.days_since);
                            window.previousWeight = previous;
                        } else {
                            document.getElementById('previous-weight').textContent = 'N/A';
                            document.getElementById('previous-date').textContent = 'N/A';
                            document.getElementById('days-since').textContent = 'N/A';
                            document.getElementById('no-previous-message').classList.remove('hidden');
                            window.previousWeight = null;
                        }

                        document.getElementById('previous-loading').classList.add('hidden');
                    })
                    .catch(error => {
                        console.error('Error loading previous weight:', error);
                        document.getElementById('previous-weight').textContent = 'N/A';
                        document.getElementById('previous-date').textContent = 'N/A';
                        document.getElementById('days-since').textContent = 'N/A';
                        window.previousWeight = null;
                        document.getElementById('previous-loading').classList.add('hidden');
                    });
            }

            function updateWeekComparison() {
                const selectedOption = pigSelect.options[pigSelect.selectedIndex];
                const targetWeek = parseInt(targetWeekSelect.value);
                const weighDate = new Date(weighDateInput.value);

                if (!selectedOption || !selectedOption.value || !targetWeek || !weighDateInput.value) {
                    weekComparison.classList.add('hidden');
                    return;
                }

                const birthDateStr = selectedOption.dataset.birthDate;
                let actualWeeks = 0;
                let ageInDays = 0;

                if (birthDateStr) {
                    const birthDate = new Date(birthDateStr);
                    ageInDays = Math.floor((weighDate - birthDate) / (1000 * 60 * 60 * 24));
                    actualWeeks = Math.floor(ageInDays / 7);
                } else {
                    ageInDays = parseInt(selectedOption.dataset.age) || 0;
                    actualWeeks = Math.floor(ageInDays / 7);
                }

                weekComparison.classList.remove('hidden');
                document.getElementById('target-week-display').textContent = `Week ${targetWeek}`;
                document.getElementById('actual-age-display').textContent = `Week ${actualWeeks} (${ageInDays} days)`;

                const weekVariance = document.getElementById('week-variance');
                const weekDiff = actualWeeks - targetWeek;

                if (weekDiff === 0) {
                    weekVariance.innerHTML = '<span class="text-green-600">Perfect timing</span>';
                } else if (weekDiff > 0) {
                    weekVariance.innerHTML = `<span class="text-orange-600">Warning ${weekDiff} week(s) late</span>`;
                } else {
                    weekVariance.innerHTML = `<span class="text-blue-600">${Math.abs(weekDiff)} week(s) early</span>`;
                }
            }

            function updateWeightFeedback() {
                const weight = parseFloat(weightInput.value);
                const targetWeek = parseInt(targetWeekSelect.value);

                if (!weight || !pigSelect.value || !targetWeek) {
                    weightFeedback.classList.add('hidden');
                    return;
                }

                const weightLbs = (weight * 2.20462).toFixed(1);
                document.getElementById('weight-lbs-display').textContent = `≈ ${weightLbs} lbs`;

                weightFeedback.classList.remove('hidden');

                if (window.currentWeightStandard) {
                    const standard = window.currentWeightStandard;

                    if (weight < standard.min_weight) {
                        document.getElementById('weight-icon').textContent = 'Under';
                        document.getElementById('weight-message').textContent = `Under expected weight for Week ${standard.week_number}`;
                        document.getElementById('weight-message').className = 'text-blue-600 font-medium';
                        const deviation = ((standard.min_weight - weight) / standard.min_weight * 100).toFixed(1);
                        document.getElementById('weight-comparison').textContent = `${deviation}% below minimum (${standard.min_weight} kg)`;
                    } else if (weight > standard.max_weight) {
                        document.getElementById('weight-icon').textContent = 'Over';
                        document.getElementById('weight-message').textContent = `Over expected weight for Week ${standard.week_number}`;
                        document.getElementById('weight-message').className = 'text-orange-600 font-medium';
                        const deviation = ((weight - standard.max_weight) / standard.max_weight * 100).toFixed(1);
                        document.getElementById('weight-comparison').textContent = `${deviation}% above maximum (${standard.max_weight} kg)`;
                    } else {
                        document.getElementById('weight-icon').textContent = 'OK';
                        document.getElementById('weight-message').textContent = `Good weight for Week ${standard.week_number}`;
                        document.getElementById('weight-message').className = 'text-green-600 font-medium';
                        document.getElementById('weight-comparison').textContent = `Within expected range (${standard.min_weight}-${standard.max_weight} kg)`;
                    }

                    if (standard.note && standard.note.includes('closest')) {
                        document.getElementById('weight-comparison').textContent += ` - ${standard.note}`;
                    }
                } else {
                    document.getElementById('weight-icon').textContent = '?';
                    document.getElementById('weight-message').textContent = 'No weight standard available';
                    document.getElementById('weight-message').className = 'text-gray-600 font-medium';
                    document.getElementById('weight-comparison').textContent = 'Weight standard not set for this pig type/age';
                }

                if (window.previousWeight) {
                    const previous = window.previousWeight;
                    const weightChange = weight - previous.weight;
                    const changePercent = ((weightChange / previous.weight) * 100).toFixed(1);

                    let changeText = '';
                    if (weightChange > 0) {
                        changeText = `Trend up +${weightChange.toFixed(1)} kg (+${changePercent}%) since last weigh`;
                    } else if (weightChange < 0) {
                        changeText = `Trend down ${weightChange.toFixed(1)} kg (${changePercent}%) since last weigh`;
                    } else {
                        changeText = `No change since last weigh`;
                    }

                    document.getElementById('weight-comparison').textContent += ` | ${changeText}`;
                }
            }

            function handleOtherDropdown(selectId, otherDivId) {
                const select = document.getElementById(selectId);
                const otherDiv = document.getElementById(otherDivId);

                if (select && otherDiv) {
                    const toggle = () => {
                        if (select.value === 'other') {
                            otherDiv.classList.remove('hidden');
                            const input = otherDiv.querySelector('input');
                            if (input) {
                                input.required = true;
                            }
                        } else {
                            otherDiv.classList.add('hidden');
                            const input = otherDiv.querySelector('input');
                            if (input) {
                                input.required = false;
                                if (!input.dataset.preserveOnLoad) {
                                    input.value = '';
                                }
                            }
                        }
                    };

                    select.addEventListener('change', toggle);
                    toggle();
                }
            }

            pigSelect.addEventListener('change', function() {
                updatePigInfo();
            });

            weightInput.addEventListener('input', updateWeightFeedback);

            targetWeekSelect.addEventListener('change', function() {
                updateWeekComparison();
                updateWeightFeedback();
                if (pigSelect.value) {
                    loadWeightStandards(pigSelect.value, weighDateInput.value, targetWeekSelect.value);
                }
            });

            weighDateInput.addEventListener('change', function() {
                updateWeekComparison();
                if (pigSelect.value) {
                    loadWeightStandards(pigSelect.value, weighDateInput.value, targetWeekSelect.value);
                }
                updateWeightFeedback();
            });

            handleOtherDropdown('purpose', 'purpose-other');
            updatePigInfo();
        });
    </script>
</x-app-layout>
