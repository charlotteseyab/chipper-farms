<x-app-layout>
    <style>
        .weight-create-page{
            min-height: 100vh;
            padding: 18px 0 40px;
        }

        .weight-create-shell{
            max-width: 1380px;
            margin: 0 auto;
            padding: 0 10px;
        }

        .weight-hero{
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 20px 18px;
            margin-bottom: 18px;
            background: linear-gradient(135deg, #0f766e 0%, #16a34a 50%, #15803d 100%);
            color: #fff;
            box-shadow: 0 24px 54px rgba(21,128,61,.18);
        }

        .weight-hero::before{
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,.16), transparent 28%),
                radial-gradient(circle at bottom left, rgba(255,255,255,.10), transparent 24%);
            pointer-events: none;
        }

        .weight-hero-inner{
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .weight-hero-title{
            margin: 0;
            font-size: clamp(1.85rem, 3vw, 2.7rem);
            line-height: 1.02;
            font-weight: 900;
            letter-spacing: -.04em;
        }

        .weight-hero-sub{
            margin: 10px 0 0;
            max-width: 760px;
            color: rgba(255,255,255,.90);
            font-size: .95rem;
            line-height: 1.75;
        }

        .weight-hero-actions{
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
            color: #ecfdf5;
        }

        .weight-top-btn-muted:hover{
            background: rgba(255,255,255,.16);
        }

        .weight-panel{
            background: rgba(255,255,255,.96);
            border: 1px solid #dbe7f0;
            border-radius: 28px;
            box-shadow: 0 14px 34px rgba(15,23,42,.06);
            overflow: visible;
        }

        .weight-panel-body{
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
            position: relative;
            background: rgba(255,255,255,.97);
            border: 1px solid #dde6ee;
            border-radius: 24px;
            box-shadow: 0 10px 28px rgba(15,23,42,.05);
            overflow: hidden;
        }

        .weight-card:focus-within{
            z-index: 30;
            overflow: visible;
        }

        .weight-card .searchable-select{
            position: relative;
            z-index: 2;
        }

        .weight-card .searchable-select.is-open{
            z-index: 40;
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

        .weight-banner-green{
            background: linear-gradient(180deg, #ecfdf5 0%, #f0fdf4 100%);
            border-color: #bbf7d0;
        }

        .weight-banner-blue{
            background: linear-gradient(180deg, #eff6ff 0%, #f8fbff 100%);
            border-color: #bfdbfe;
        }

        .weight-banner-title{
            font-size: .9rem;
            font-weight: 900;
            color: #14532d;
            margin-bottom: 4px;
        }

        .weight-banner-blue .weight-banner-title{
            color: #1d4ed8;
        }

        .weight-banner-text{
            font-size: .82rem;
            line-height: 1.7;
            color: #166534;
        }

        .weight-banner-blue .weight-banner-text{
            color: #1e40af;
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
            border-color: #16a34a;
            box-shadow: 0 0 0 4px rgba(22,163,74,.10);
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
            background: linear-gradient(135deg, #16a34a, #15803d);
            box-shadow: 0 18px 34px rgba(22,163,74,.20);
            transition: .22s ease;
        }

        .weight-submit:hover{
            transform: translateY(-1px);
            box-shadow: 0 20px 36px rgba(22,163,74,.25);
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

        .weight-placeholder{
            text-align: center;
            padding: 36px 18px;
            color: #64748b;
        }

        .weight-placeholder h3{
            margin: 10px 0 8px;
            font-size: 1rem;
            font-weight: 900;
            color: #0f172a;
        }

        @media (max-width: 1100px){
            .weight-layout{
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px){
            .weight-create-shell{
                padding: 0 12px;
            }

            .weight-hero{
                padding: 22px 18px;
                border-radius: 24px;
            }

            .weight-panel-body,
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

            .weight-hero-actions{
                width: 100%;
            }

            .weight-top-btn{
                width: 100%;
            }
        }
    </style>

    <div class="weight-create-page">
        <div class="weight-create-shell">
            <div class="weight-hero">
                <div class="weight-hero-inner">
                    <div>
                        <h1 class="weight-hero-title">Weekly Weight Recording</h1>
                        <p class="weight-hero-sub">
                            Record pig weights with live reference to expected standards, previous measurements, weekly targets,
                            and basic condition notes. The page is designed to stay simple for everyday farm use.
                        </p>
                    </div>

                    <div class="weight-hero-actions">
                        <button type="button" onclick="toggleBatchMode()" id="batch-toggle" class="weight-top-btn weight-top-btn-primary">
                            Batch Mode
                        </button>
                        <a href="{{ route('weights.index') }}" class="weight-top-btn weight-top-btn-muted">
                            ← Back to Records
                        </a>
                    </div>
                </div>
            </div>

            <div class="weight-panel">
                <div class="weight-panel-body">
                    <div id="single-mode">
                        <form method="POST" action="{{ route('weights.store') }}">
                            @csrf

                            <div class="weight-layout">
                                <div class="weight-stack">
                                    <div class="weight-card">
                                        <div class="weight-card-head">
                                            <h3 class="weight-card-title">Select Pig</h3>
                                            <div class="weight-card-sub">Choose the pig and review its basic profile before recording a new weight.</div>
                                        </div>

                                        <div class="weight-card-body">
                                            <div class="weight-banner weight-banner-green" style="margin-bottom:16px;">
                                                <div class="weight-banner-title">Weekly Weight Update</div>
                                                <div class="weight-banner-text">
                                                    Record weekly weights with automatic comparison against expected standards.
                                                </div>
                                            </div>

                                            <div class="weight-field">
                                                <label class="weight-label">Pig</label>
                                                <select name="pig_id" id="pig_id" required class="weight-select" data-searchable-select data-search-placeholder="Search by ear tag, local ID, code, breed, gender, or stock type...">
                                                    <option value="" disabled selected>-- Select Pig --</option>
                                                    @foreach($pigs as $pig)
                                                        @php
                                                            $genderLabel = match (strtolower((string) $pig->gender)) {
                                                                'm', 'male' => 'Male',
                                                                'f', 'female' => 'Female',
                                                                default => ucfirst((string) ($pig->gender ?: 'unknown')),
                                                            };
                                                        @endphp
                                                        <option value="{{ $pig->id }}"
                                                            data-ear-tag="{{ $pig->ear_tag_number }}"
                                                            data-code="{{ $pig->code }}"
                                                            data-local-id="{{ $pig->local_id }}"
                                                            data-breed="{{ $pig->breed }}"
                                                            data-gender="{{ $pig->gender }}"
                                                            data-age="{{ $pig->current_age_in_days }}"
                                                            data-birth-date="{{ $pig->date_of_birth ? $pig->date_of_birth->toDateString() : '' }}"
                                                            data-stock-type="{{ $pig->stock_type }}"
                                                            {{ ($selectedPig && $selectedPig->id == $pig->id) || old('pig_id') == $pig->id ? 'selected' : '' }}>
                                                            {{ $pig->ear_tag_number ?: ('Pig #' . $pig->id) }}
                                                            @if($pig->local_id)
                                                                / Local {{ $pig->local_id }}
                                                            @endif
                                                            - {{ $pig->code }} ({{ $pig->breed }}, {{ $genderLabel }}, {{ ucfirst((string) $pig->stock_type) }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @if($pigs->isEmpty())
                                                    <div class="weight-help" style="color:#b45309;">
                                                        No active pigs are available for weight recording. Sold or deceased pigs are excluded.
                                                    </div>
                                                @endif
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
                                            <div class="weight-card-sub">Capture the new measurement and the reason for the weighing.</div>
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
                                                            value="{{ old('weight_kg') }}"
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
                                                        <span class="text-gray-400">• Weekly updates recommended</span>
                                                    </div>
                                                </div>

                                                <div class="weight-field">
                                                    <label class="weight-label">Purpose</label>
                                                    <select name="purpose" id="purpose" required class="weight-select" data-searchable-select>
                                                        <option value="" disabled selected>-- Select Purpose --</option>
                                                        <option value="routine" {{ old('purpose') == 'routine' ? 'selected' : '' }}>Monthly Routine</option>
                                                        <option value="breeding" {{ old('purpose') == 'breeding' ? 'selected' : '' }}>Breeding Assessment</option>
                                                        <option value="health" {{ old('purpose') == 'health' ? 'selected' : '' }}>Health Check</option>
                                                        <option value="sale" {{ old('purpose') == 'sale' ? 'selected' : '' }}>Pre-Sale</option>
                                                        <option value="birth" {{ old('purpose') == 'birth' ? 'selected' : '' }}>Birth Weight</option>
                                                        <option value="weaning" {{ old('purpose') == 'weaning' ? 'selected' : '' }}>Weaning Weight</option>
                                                        <option value="maturity" {{ old('purpose') == 'maturity' ? 'selected' : '' }}>Maturity Weight</option>
                                                        <option value="other" {{ old('purpose') == 'other' ? 'selected' : '' }}>Other</option>
                                                    </select>

                                                    <div id="purpose-other" class="hidden" style="margin-top:10px;">
                                                        <input
                                                            type="text"
                                                            name="purpose_other"
                                                            placeholder="Please specify the purpose..."
                                                            class="weight-input"
                                                        >
                                                    </div>

                                                    <div class="weight-help">Most common: Weekly routine for growth tracking.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="weight-card">
                                        <div class="weight-card-head">
                                            <h3 class="weight-card-title">Weekly Tracking</h3>
                                            <div class="weight-card-sub">Track the target week, weighing date, and actual timing against the pig’s age.</div>
                                        </div>

                                        <div class="weight-card-body">
                                            <div class="weight-grid-3">
                                                <div class="weight-field">
                                                    <label class="weight-label">Target Week</label>
                                                    <select name="target_week" id="target_week" required class="weight-select" data-searchable-select data-search-placeholder="Search weeks...">
                                                        <option value="" disabled selected>-- Select Week --</option>
                                                        @for($week = 1; $week <= 200; $week++)
                                                            <option value="{{ $week }}" {{ old('target_week') == $week ? 'selected' : '' }}>
                                                                Week {{ $week }} ({{ $week * 7 }} days)
                                                            </option>
                                                        @endfor
                                                    </select>
                                                    <div class="weight-help">Which week are you recording for?</div>
                                                </div>

                                                <div class="weight-field">
                                                    <label class="weight-label">Weighing Date</label>
                                                    <input
                                                        type="date"
                                                        name="weigh_date"
                                                        id="weigh_date"
                                                        required
                                                        max="{{ now()->toDateString() }}"
                                                        value="{{ old('weigh_date', now()->toDateString()) }}"
                                                        class="weight-input"
                                                    >
                                                    <div class="weight-help">Actual date weighed.</div>
                                                </div>

                                                <div class="weight-field">
                                                    <label class="weight-label">Weighing Time (optional)</label>
                                                    <input
                                                        type="time"
                                                        name="weigh_time"
                                                        id="weigh_time"
                                                        value="{{ old('weigh_time', now()->format('H:i')) }}"
                                                        class="weight-input"
                                                    >
                                                    <div class="weight-help">Auto-filled with current time.</div>
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
                                            <div class="weight-card-sub">Optional details to support growth and health review later.</div>
                                        </div>

                                        <div class="weight-card-body">
                                            <div class="weight-grid-2">
                                                <div class="weight-field">
                                                    <label class="weight-label">Body Condition Score (optional)</label>
                                                    <select name="body_condition_score" class="weight-select" data-searchable-select>
                                                        <option value="" disabled selected>-- Not Assessed --</option>
                                                        <option value="1" {{ old('body_condition_score') == '1' ? 'selected' : '' }}>1 - Very Thin</option>
                                                        <option value="2" {{ old('body_condition_score') == '2' ? 'selected' : '' }}>2 - Thin</option>
                                                        <option value="3" {{ old('body_condition_score') == '3' ? 'selected' : '' }}>3 - Ideal</option>
                                                        <option value="4" {{ old('body_condition_score') == '4' ? 'selected' : '' }}>4 - Fat</option>
                                                        <option value="5" {{ old('body_condition_score') == '5' ? 'selected' : '' }}>5 - Very Fat</option>
                                                    </select>
                                                </div>

                                                <div class="weight-field">
                                                    <label class="weight-label">Health Status (optional)</label>
                                                    <select name="health_status" class="weight-select" data-searchable-select>
                                                        <option value="" disabled selected>-- Not Assessed --</option>
                                                        <option value="excellent" {{ old('health_status') == 'excellent' ? 'selected' : '' }}>Excellent</option>
                                                        <option value="good" {{ old('health_status') == 'good' ? 'selected' : '' }}>Good</option>
                                                        <option value="fair" {{ old('health_status') == 'fair' ? 'selected' : '' }}>Fair</option>
                                                        <option value="poor" {{ old('health_status') == 'poor' ? 'selected' : '' }}>Poor</option>
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
                                                >{{ old('notes') }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="weight-stack">
                                    <div class="weight-card">
                                        <div class="weight-card-head">
                                            <h3 class="weight-card-title">Expected Weight & History</h3>
                                            <div class="weight-card-sub">Live comparison based on standards and the pig’s previous recorded weight.</div>
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
                                            <h3 class="weight-card-title">Save Weight Record</h3>
                                            <div class="weight-card-sub">Once saved, the record will be available for growth tracking and alerts.</div>
                                        </div>

                                        <div class="weight-card-body">
                                            <div class="weight-actions">
                                                <button type="submit" class="weight-submit">Record Weight</button>
                                                <a href="{{ route('weights.index') }}" class="weight-back">← Back to Weight Records</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div id="batch-mode" class="hidden">
                        <div class="weight-card">
                            <div class="weight-card-head">
                                <h3 class="weight-card-title">Batch Weight Recording</h3>
                                <div class="weight-card-sub">This will allow multiple pigs to be updated in one session.</div>
                            </div>

                            <div class="weight-card-body">
                                <div class="weight-banner weight-banner-blue" style="margin-bottom:18px;">
                                    <div class="weight-banner-title">Batch mode</div>
                                    <div class="weight-banner-text">
                                        Record weights for multiple pigs at once — ideal for weekly weigh days and grouped updates.
                                    </div>
                                </div>

                                <div class="weight-placeholder">
                                    <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                    </svg>
                                    <h3>Batch Mode Coming Soon</h3>
                                    <p class="mb-5">
                                        Batch weight recording will allow you to update multiple pigs at once.<br>
                                        For now, use single mode for individual pig weight updates.
                                    </p>
                                    <button onclick="toggleBatchMode()" class="weight-top-btn weight-top-btn-muted" style="background:#475569; border:none; color:#fff;">
                                        ← Back to Single Mode
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleBatchMode() {
            const singleMode = document.getElementById('single-mode');
            const batchMode = document.getElementById('batch-mode');
            const toggleButton = document.getElementById('batch-toggle');

            if (singleMode.classList.contains('hidden')) {
                singleMode.classList.remove('hidden');
                batchMode.classList.add('hidden');
                toggleButton.textContent = 'Batch Mode';
            } else {
                singleMode.classList.add('hidden');
                batchMode.classList.remove('hidden');
                toggleButton.textContent = 'Notes Single Mode';
            }
        }

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

                if (selectedOption.value) {
                    pigInfo.classList.remove('hidden');
                    const rawGender = (selectedOption.dataset.gender || '').toLowerCase();
                    const genderLabel = rawGender === 'm' || rawGender === 'male'
                        ? 'Male'
                        : (rawGender === 'f' || rawGender === 'female' ? 'Female' : (selectedOption.dataset.gender || 'Unknown'));

                    document.getElementById('info-ear-tag').textContent = selectedOption.dataset.earTag;
                    document.getElementById('info-code').textContent = selectedOption.dataset.code;
                    document.getElementById('info-breed').textContent = selectedOption.dataset.breed;
                    document.getElementById('info-gender').textContent = genderLabel;
                    document.getElementById('info-age').textContent = selectedOption.dataset.age;
                    document.getElementById('info-age-weeks').textContent = Math.floor(selectedOption.dataset.age / 7);
                    document.getElementById('info-stock-type').textContent =
                        selectedOption.dataset.stockType.charAt(0).toUpperCase() + selectedOption.dataset.stockType.slice(1);

                    loadWeightStandards(selectedOption.value, weighDateInput.value, targetWeekSelect.value);
                    loadPreviousWeight(selectedOption.value);

                    weightFeedback.classList.add('hidden');
                    window.currentWeightStandard = null;
                    window.previousWeight = null;
                } else {
                    pigInfo.classList.add('hidden');
                    weightFeedback.classList.add('hidden');
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

                if (!selectedOption.value || !targetWeek || !weighDate) {
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

                if (Math.abs(weekDiff) > 1) {
                    weekVariance.innerHTML += `<br><button type="button" onclick="setTargetWeek(${actualWeeks})" class="text-xs text-blue-600 hover:underline mt-1">Use Week ${actualWeeks} instead?</button>`;
                }
            }

            function setTargetWeek(week) {
                targetWeekSelect.value = week;
                updateWeekComparison();
                updateWeightFeedback();
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

            pigSelect.addEventListener('change', function() {
                updatePigInfo();
                autoSuggestWeek();
                updateWeekComparison();
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
                autoSuggestWeek();
                updateWeekComparison();
                if (pigSelect.value) {
                    loadWeightStandards(pigSelect.value, weighDateInput.value, targetWeekSelect.value);
                }
                updateWeightFeedback();
            });

            function autoSuggestWeek() {
                const selectedOption = pigSelect.options[pigSelect.selectedIndex];
                const weighDate = new Date(weighDateInput.value);

                if (selectedOption.value && !targetWeekSelect.value && weighDate) {
                    const birthDateStr = selectedOption.dataset.birthDate;
                    let suggestedWeek = 0;

                    if (birthDateStr) {
                        const birthDate = new Date(birthDateStr);
                        const ageInDays = Math.floor((weighDate - birthDate) / (1000 * 60 * 60 * 24));
                        suggestedWeek = Math.floor(ageInDays / 7);
                    } else {
                        const ageInDays = parseInt(selectedOption.dataset.age);
                        suggestedWeek = Math.floor(ageInDays / 7);
                    }

                    if (suggestedWeek > 0 && suggestedWeek <= 104) {
                        targetWeekSelect.value = suggestedWeek;
                        updateWeekComparison();
                    }
                }
            }

            if (pigSelect.value) {
                updatePigInfo();
                autoSuggestWeek();
                loadWeightStandards(pigSelect.value, weighDateInput.value, targetWeekSelect.value);
            }

            function setCurrentTime() {
                const now = new Date();
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                const currentTime = `${hours}:${minutes}`;

                const weighTimeInput = document.getElementById('weigh_time');
                if (weighTimeInput && !weighTimeInput.value) {
                    weighTimeInput.value = currentTime;
                }
            }

            setCurrentTime();

            function handleOtherDropdown(selectId, otherDivId) {
                const select = document.getElementById(selectId);
                const otherDiv = document.getElementById(otherDivId);

                if (select && otherDiv) {
                    select.addEventListener('change', function() {
                        if (this.value === 'other') {
                            otherDiv.classList.remove('hidden');
                            const input = otherDiv.querySelector('input');
                            if (input) {
                                input.required = true;
                                input.focus();
                            }
                        } else {
                            otherDiv.classList.add('hidden');
                            const input = otherDiv.querySelector('input');
                            if (input) {
                                input.required = false;
                                input.value = '';
                            }
                        }
                    });
                }
            }

            handleOtherDropdown('purpose', 'purpose-other');
        });
    </script>
</x-app-layout>
