<x-app-layout>
    <style>
        .mating-page{
            min-height: 100vh;
            padding: 18px 0 40px;
        }

        .mating-shell{
            max-width: 1380px;
            margin: 0 auto;
            padding: 0 10px;
        }

        .mating-hero{
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 20px 18px;
            background: linear-gradient(135deg,rgb(40, 19, 75) 100%);
            box-shadow: 0 24px 54px rgba(190,24,93,.18);
            color: #fff;
            margin-bottom: 18px;
        }

        .mating-hero::before{
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,.16), transparent 28%);
                
            pointer-events: none;
        }

        .mating-hero-inner{
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .mating-title{
            margin: 0;
            font-size: clamp(1.9rem, 3vw, 2.9rem);
            line-height: 1.02;
            font-weight: 900;
            letter-spacing: -.04em;
        }

        .mating-subtitle{
            margin: 10px 0 0;
            max-width: 760px;
            color: rgba(255,255,255,.90);
            font-size: .95rem;
            line-height: 1.75;
        }

        .mating-pill-row{
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .mating-pill{
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

        .mating-layout{
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(320px, .85fr);
            gap: 18px;
            align-items: start;
        }

        .mating-stack{
            display: grid;
            gap: 18px;
        }

        .mating-card{
            background: rgba(255,255,255,.97);
            border: 1px solid #e9d5e5;
            border-radius: 28px;
            box-shadow: 0 14px 34px rgba(15,23,42,.06);
            overflow: hidden;
        }

        .mating-card-head{
            padding: 22px 24px 14px;
            border-bottom: 1px solid #f1e7ee;
            background: linear-gradient(180deg, rgba(253,242,248,.85) 0%, rgba(255,255,255,.98) 100%);
        }

        .mating-card-title{
            margin: 0;
            font-size: 1.05rem;
            font-weight: 900;
            color: #0f172a;
        }

        .mating-card-sub{
            margin-top: 4px;
            font-size: .82rem;
            color: #64748b;
        }

        .mating-card-body{
            padding: 22px 24px 24px;
        }

        .mating-form{
            display: grid;
            gap: 18px;
        }

        .mating-field{
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .mating-label{
            font-size: .83rem;
            font-weight: 800;
            color: #334155;
        }

        .mating-input,
        .mating-select,
        .mating-textarea{
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 16px;
            background: #fff;
            padding: 12px 14px;
            font-size: .9rem;
            color: #0f172a;
            outline: none;
            transition: .2s ease;
            box-shadow: inset 0 1px 2px rgba(15,23,42,.02);
        }

        .mating-textarea{
            min-height: 110px;
            resize: vertical;
        }

        .mating-input:focus,
        .mating-select:focus,
        .mating-textarea:focus{
            border-color: #db2777;
            box-shadow: 0 0 0 4px rgba(219,39,119,.10);
        }

        .mating-help{
            font-size: .75rem;
            color: #64748b;
            line-height: 1.55;
        }

        .mating-submit{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            gap: 8px;
            border: none;
            border-radius: 18px;
            padding: 15px 18px;
            color: #fff;
            font-size: .95rem;
            font-weight: 900;
            letter-spacing: .01em;
            background: linear-gradient(135deg, #db2777, #be185d);
            box-shadow: 0 18px 34px rgba(219,39,119,.20);
            transition: .22s ease;
        }

        .mating-submit:hover{
            transform: translateY(-1px);
            box-shadow: 0 20px 36px rgba(219,39,119,.24);
        }

        .mating-pair-card{
            border-radius: 22px;
            background: linear-gradient(180deg, #faf5ff 0%, #fff 100%);
            border: 1px solid #eadcff;
            padding: 18px;
        }

        .mating-pair-title{
            margin: 0 0 14px;
            font-size: .95rem;
            font-weight: 900;
            color: #6b21a8;
        }

        .mating-pair-grid{
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .mating-animal-box{
            border-radius: 18px;
            padding: 16px;
            border: 1px solid;
        }

        .mating-animal-box.male{
            background: #eff6ff;
            border-color: #bfdbfe;
        }

        .mating-animal-box.female{
            background: #fdf2f8;
            border-color: #fbcfe8;
        }

        .mating-animal-box h4{
            margin: 0 0 10px;
            font-size: .88rem;
            font-weight: 900;
        }

        .mating-animal-box.male h4{ color: #1d4ed8; }
        .mating-animal-box.female h4{ color: #be185d; }

        .mating-side-list{
            display: grid;
            gap: 12px;
        }

        .mating-guide-row{
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 16px;
            background: #f8fafc;
            border: 1px solid #eef2f7;
            font-size: .85rem;
            color: #475569;
            line-height: 1.65;
        }

        .mating-mini-grid{
            display: grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap: 12px;
        }

        .mating-mini{
            border-radius: 18px;
            padding: 16px;
            border: 1px solid #eef2f7;
            background: #f8fafc;
        }

        .mating-mini-label{
            font-size: .75rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #94a3b8;
            margin-bottom: 8px;
        }

        .mating-mini-value{
            font-size: 1.45rem;
            font-weight: 900;
            line-height: 1;
        }

        .mating-mini-sub{
            margin-top: 8px;
            font-size: .76rem;
            color: #64748b;
        }

        .mating-status-grid{
            display: grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap: 10px;
            margin-top: 14px;
        }

        .mating-status-box{
            border-radius: 16px;
            padding: 12px 14px;
            background: #fff;
            border: 1px solid #eef2f7;
        }

        .mating-status-title{
            font-size: .76rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #94a3b8;
            margin-bottom: 6px;
        }

        .mating-status-row{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            font-size: .83rem;
            color: #475569;
        }

        .mating-status-row + .mating-status-row{
            margin-top: 6px;
        }

        @media (max-width: 1100px){
            .mating-layout{
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px){
            .mating-shell{
                padding: 0 12px;
            }

            .mating-hero{
                padding: 22px 18px;
                border-radius: 24px;
            }

            .mating-card-head,
            .mating-card-body{
                padding-left: 18px;
                padding-right: 18px;
            }

            .mating-pair-grid,
            .mating-mini-grid,
            .mating-status-grid{
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="mating-page">
        <div class="mating-shell">
            <div class="mating-hero">
                <div class="mating-hero-inner">
                    <div>
                        <h1 class="mating-title">Mating Planner</h1>
                        <p class="mating-subtitle">
                            Plan safe and genetically diverse breeding pairs with live compatibility checking, stock availability guidance,
                            and a simple planning workflow built for daily farm use.
                        </p>
                    </div>

                    <div class="mating-pill-row">
                        <span class="mating-pill">Compatibility checks</span>
                        <span class="mating-pill">Availability aware</span>
                        <span class="mating-pill">Breeding cycle guided</span>
                    </div>
                </div>
            </div>

            <div class="mating-layout">
                <div class="mating-stack">
                    <div class="mating-card">
                        <div class="mating-card-head">
                            <h3 class="mating-card-title">Plan a Mating</h3>
                            <div class="mating-card-sub">Choose a male and female, confirm the planned date, and record any useful notes.</div>
                        </div>

                        <div class="mating-card-body">
                            <form method="POST" action="{{ route('mating.plan') }}" class="mating-form">
                                @csrf

                                <div class="mating-field">
                                    <label class="mating-label">Select Male</label>
                                    <select name="male_id" id="male_id" required class="mating-select">
                                        <option value="" disabled selected>-- Choose Male --</option>
                                        @foreach($allMales as $male)
                                            @php $availability = $male->availability_status; @endphp
                                            <option value="{{ $male->id }}" {{ old('male_id') == $male->id ? 'selected' : '' }}
                                                    @if(!$availability['available']) disabled class="text-gray-400"
                                                    @elseif($availability['warning'] ?? false) class="text-orange-600 font-medium" @endif>
                                                {{ $availability['icon'] }} {{ $male->ear_tag_number }} - {{ $male->code }}
                                                ({{ $male->breed }}, {{ $male->current_age_in_days }} days)
                                                @if(!$availability['available'])
                                                    - {{ $availability['reason'] }}
                                                @elseif($availability['warning'] ?? false)
                                                    - {{ $availability['reason'] }}
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="mating-help">Only available or caution-flagged males should be selected for planning.</div>
                                </div>

                                <div class="mating-field">
                                    <label class="mating-label">Select Female</label>
                                    <select name="female_id" id="female_id" required class="mating-select">
                                        <option value="" disabled selected>-- Choose Female --</option>
                                        @foreach($allFemales as $female)
                                            @php $availability = $female->availability_status; @endphp
                                            <option value="{{ $female->id }}" {{ old('female_id') == $female->id ? 'selected' : '' }}
                                                    @if(!$availability['available']) disabled class="text-gray-400"
                                                    @elseif($availability['warning'] ?? false) class="text-orange-600 font-medium" @endif>
                                                {{ $availability['icon'] }} {{ $female->ear_tag_number }} - {{ $female->code }}
                                                ({{ $female->breed }}, {{ $female->current_age_in_days }} days)
                                                @if(!$availability['available'])
                                                    - {{ $availability['reason'] }}
                                                @elseif($availability['warning'] ?? false)
                                                    - {{ $availability['reason'] }}
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="mating-help">Females already occupied or unavailable remain disabled in the list.</div>
                                </div>

                                <x-inbreeding-alert id="mating-alert" />

                                <div class="mating-field">
                                    <label class="mating-label">Planned Mating Date</label>
                                    <input
                                        type="date"
                                        name="planned_date"
                                        value="{{ old('planned_date', now()->toDateString()) }}"
                                        required
                                        class="mating-input"
                                    >
                                    <div class="mating-help">You can select past dates to backdate planned matings when needed.</div>
                                </div>

                                <div class="mating-field">
                                    <label class="mating-label">Notes (optional)</label>
                                    <textarea
                                        name="notes"
                                        rows="4"
                                        placeholder="Any special considerations, compatibility notes, or breeding goals..."
                                        class="mating-textarea"
                                    >{{ old('notes') }}</textarea>
                                </div>

                                <button type="submit" id="submit-button" class="mating-submit">
                                    Plan This Mating
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="mating-stack">
                    <div id="pair-info" class="mating-pair-card hidden">
                        <h3 class="mating-pair-title">Selected Breeding Pair</h3>
                        <div class="mating-pair-grid">
                            <div id="male-info" class="mating-animal-box male">
                                <h4>♂ Male</h4>
                                <div id="male-details" class="text-sm text-blue-700"></div>
                            </div>
                            <div id="female-info" class="mating-animal-box female">
                                <h4>♀ Female</h4>
                                <div id="female-details" class="text-sm text-pink-700"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mating-card">
                        <div class="mating-card-head">
                            <h3 class="mating-card-title">Breeding Guidelines</h3>
                            <div class="mating-card-sub">Use these cues to understand compatibility and breeding safety levels.</div>
                        </div>
                        <div class="mating-card-body">
                            <div class="mating-side-list">
                                <div class="mating-guide-row">
                                    <span class="text-green-500 font-bold">✓</span>
                                    <span><strong>Safe:</strong> No close relationships - promotes genetic diversity</span>
                                </div>
                                <div class="mating-guide-row">
                                    <span class="text-blue-500 font-bold">ℹ</span>
                                    <span><strong>Caution:</strong> Distant relatives - acceptable with monitoring</span>
                                </div>
                                <div class="mating-guide-row">
                                    <span class="text-yellow-500 font-bold">⚠</span>
                                    <span><strong>Warning:</strong> Close relatives - requires careful consideration</span>
                                </div>
                                <div class="mating-guide-row">
                                    <span class="text-red-500 font-bold">🚫</span>
                                    <span><strong>Critical:</strong> Direct relatives - not recommended</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mating-card">
                        <div class="mating-card-head">
                            <h3 class="mating-card-title">Breeding Cycle Info</h3>
                            <div class="mating-card-sub">Reference points used across the breeding workflow.</div>
                        </div>
                        <div class="mating-card-body">
                            <div class="mating-mini-grid">
                                <div class="mating-mini">
                                    <div class="mating-mini-label">Gestation</div>
                                    <div class="mating-mini-value text-pink-700">{{ config('breeding.cycle.gestation_days') }}</div>
                                    <div class="mating-mini-sub">days</div>
                                </div>
                                <div class="mating-mini">
                                    <div class="mating-mini-label">Pregnancy Check</div>
                                    <div class="mating-mini-value text-blue-700">{{ config('breeding.cycle.pregnancy_check_days') }}</div>
                                    <div class="mating-mini-sub">days</div>
                                </div>
                                <div class="mating-mini">
                                    <div class="mating-mini-label">Weaning Age</div>
                                    <div class="mating-mini-value text-green-700">{{ config('breeding.cycle.birth_to_weaning_days') }}</div>
                                    <div class="mating-mini-sub">days</div>
                                </div>
                                <div class="mating-mini">
                                    <div class="mating-mini-label">Breeding Age</div>
                                    <div class="mating-mini-value text-purple-700">{{ config('breeding.cycle.birth_to_maturity_days') }}</div>
                                    <div class="mating-mini-sub">days</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mating-card">
                        <div class="mating-card-head">
                            <h3 class="mating-card-title">Available Stock For Mating</h3>
                            <div class="mating-card-sub">A quick operational view of what is currently available or blocked.</div>
                        </div>
                        <div class="mating-card-body">
                            <div class="mating-mini-grid">
                                <div class="mating-mini" style="background:#eff6ff; border-color:#bfdbfe;">
                                    <div class="mating-mini-label">Males Available</div>
                                    <div class="mating-mini-value text-blue-700">{{ count($availableMales) }}</div>
                                    <div class="mating-mini-sub">{{ count($allMales) }} total males</div>
                                </div>
                                <div class="mating-mini" style="background:#fdf2f8; border-color:#fbcfe8;">
                                    <div class="mating-mini-label">Females Available</div>
                                    <div class="mating-mini-value text-pink-700">{{ count($availableFemales) }}</div>
                                    <div class="mating-mini-sub">{{ count($allFemales) }} total females</div>
                                </div>
                            </div>

                            <div class="mating-status-grid">
                                <div class="mating-status-box">
                                    <div class="mating-status-title">Male Status Breakdown</div>
                                    @php
                                        $maleStatuses = $allMales->groupBy(function($pig) {
                                            return $pig->availability_status['status'];
                                        });
                                    @endphp
                                    @foreach($maleStatuses as $status => $pigs)
                                        <div class="mating-status-row">
                                            <span class="capitalize">{{ str_replace('_', ' ', $status) }}</span>
                                            <strong>{{ count($pigs) }}</strong>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="mating-status-box">
                                    <div class="mating-status-title">Female Status Breakdown</div>
                                    @php
                                        $femaleStatuses = $allFemales->groupBy(function($pig) {
                                            return $pig->availability_status['status'];
                                        });
                                    @endphp
                                    @foreach($femaleStatuses as $status => $pigs)
                                        <div class="mating-status-row">
                                            <span class="capitalize">{{ str_replace('_', ' ', $status) }}</span>
                                            <strong>{{ count($pigs) }}</strong>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const maleSelect = document.getElementById('male_id');
            const femaleSelect = document.getElementById('female_id');
            const inbreedingAlert = new InbreedingAlert('mating-alert');
            const pairInfo = document.getElementById('pair-info');
            const maleDetails = document.getElementById('male-details');
            const femaleDetails = document.getElementById('female-details');
            const breedingTimeline = document.getElementById('breeding-timeline');
            const plannedDateInput = document.querySelector('input[name="planned_date"]');
            let checkTimeout;

            const males = @json($males);
            const females = @json($females);

            function getMaleReadinessBadge(readiness) {
                const status = readiness.age_status;
                let badgeClass = '';
                let icon = '';
                let text = '';

                switch(status) {
                    case 'optimal':
                        badgeClass = 'bg-green-100 text-green-800';
                        icon = '✓';
                        text = 'Optimal';
                        break;
                    case 'minimum_met':
                        badgeClass = 'bg-yellow-100 text-yellow-800';
                        icon = '⚠';
                        text = 'Ready';
                        break;
                    case 'too_young':
                        badgeClass = 'bg-red-100 text-red-800';
                        icon = '✗';
                        text = 'Too Young';
                        break;
                    case 'too_old':
                        badgeClass = 'bg-gray-100 text-gray-800';
                        icon = '⚠';
                        text = 'Too Old';
                        break;
                    default:
                        badgeClass = 'bg-gray-100 text-gray-800';
                        icon = '?';
                        text = 'Unknown';
                }

                return `<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${badgeClass}">${icon} ${text}</span>`;
            }

            function getFemaleReadinessBadge(readiness) {
                return getMaleReadinessBadge(readiness);
            }

            const breedingConfig = {
                gestationDays: {{ config('breeding.cycle.gestation_days') }},
                pregnancyCheckDays: {{ config('breeding.cycle.pregnancy_check_days') }},
                weaningDays: {{ config('breeding.cycle.birth_to_weaning_days') }},
                restDays: {{ config('breeding.parameters.weaning_to_mating_rest') }}
            };

            function updatePairInfo() {
                const selectedMale = males.find(m => m.id == maleSelect.value);
                const selectedFemale = females.find(f => f.id == femaleSelect.value);

                if (selectedMale && selectedFemale) {
                    const maleReadinessBadge = getMaleReadinessBadge(selectedMale.breeding_readiness);
                    const femaleReadinessBadge = getFemaleReadinessBadge(selectedFemale.breeding_readiness);

                    const maleWeightWarning = selectedMale.breeding_readiness.factors?.weight?.status === 'too_light'
                        ? `<div class="mt-2 p-2 bg-orange-100 border border-orange-200 rounded text-xs text-orange-700">
                            ⚠️ Weight Warning: ${selectedMale.breeding_readiness.factors.weight.message}
                        </div>` : '';

                    maleDetails.innerHTML = `
                        <div class="flex items-center justify-between mb-2">
                            <div><strong>${selectedMale.ear_tag_number}</strong> - ${selectedMale.code}</div>
                            ${maleReadinessBadge}
                        </div>
                        <div class="text-sm text-gray-600">Breed: ${selectedMale.breed}</div>
                        <div class="text-sm text-gray-600">Age: ${Math.floor(selectedMale.current_age_in_days / 30)} months (${selectedMale.current_age_in_days} days)</div>
                        <div class="text-sm text-gray-600">Type: ${selectedMale.stock_type}</div>
                        ${selectedMale.breeding_readiness.age_status !== 'optimal' ? `<div class="text-xs text-gray-500 mt-2">${selectedMale.readiness_message}</div>` : ''}
                        ${maleWeightWarning}
                    `;

                    const femaleWeightWarning = selectedFemale.breeding_readiness.factors?.weight?.status === 'too_light'
                        ? `<div class="mt-2 p-2 bg-orange-100 border border-orange-200 rounded text-xs text-orange-700">
                            ⚠️ Weight Warning: ${selectedFemale.breeding_readiness.factors.weight.message}
                        </div>` : '';

                    femaleDetails.innerHTML = `
                        <div class="flex items-center justify-between mb-2">
                            <div><strong>${selectedFemale.ear_tag_number}</strong> - ${selectedFemale.code}</div>
                            ${femaleReadinessBadge}
                        </div>
                        <div class="text-sm text-gray-600">Breed: ${selectedFemale.breed}</div>
                        <div class="text-sm text-gray-600">Age: ${Math.floor(selectedFemale.current_age_in_days / 30)} months (${selectedFemale.current_age_in_days} days)</div>
                        <div class="text-sm text-gray-600">Type: ${selectedFemale.stock_type}</div>
                        ${selectedFemale.breeding_readiness.age_status !== 'optimal' ? `<div class="text-xs text-gray-500 mt-2">${selectedFemale.readiness_message}</div>` : ''}
                        ${femaleWeightWarning}
                    `;

                    pairInfo.classList.remove('hidden');
                } else {
                    pairInfo.classList.add('hidden');
                }
            }

            function updateBreedingTimeline() {
                const plannedDate = plannedDateInput.value;

                if (!plannedDate || !breedingTimeline) {
                    if (breedingTimeline) breedingTimeline.classList.add('hidden');
                    return;
                }

                const matingDate = new Date(plannedDate);
                const pregnancyCheckDate = new Date(matingDate);
                pregnancyCheckDate.setDate(matingDate.getDate() + breedingConfig.pregnancyCheckDays);

                const farrowingDate = new Date(matingDate);
                farrowingDate.setDate(matingDate.getDate() + breedingConfig.gestationDays);

                const weaningDate = new Date(farrowingDate);
                weaningDate.setDate(farrowingDate.getDate() + breedingConfig.weaningDays);

                const nextMatingDate = new Date(weaningDate);
                nextMatingDate.setDate(weaningDate.getDate() + breedingConfig.restDays);

                const cycleLengthDays = Math.ceil((nextMatingDate - matingDate) / (1000 * 60 * 60 * 24));

                const formatDate = (date) => {
                    return date.toLocaleDateString('en-US', {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric'
                    });
                };

                document.getElementById('timeline-mating').textContent = formatDate(matingDate);
                document.getElementById('timeline-pregnancy').textContent = formatDate(pregnancyCheckDate);
                document.getElementById('timeline-farrowing').textContent = formatDate(farrowingDate);
                document.getElementById('timeline-weaning').textContent = formatDate(weaningDate);
                document.getElementById('timeline-next').textContent = formatDate(nextMatingDate);
                document.getElementById('timeline-cycle').textContent = `${cycleLengthDays} days`;

                breedingTimeline.classList.remove('hidden');
            }

            function checkMatingCompatibility() {
                const maleId = maleSelect.value;
                const femaleId = femaleSelect.value;

                clearTimeout(checkTimeout);

                if (!maleId || !femaleId) {
                    inbreedingAlert.hide();
                    return;
                }

                checkTimeout = setTimeout(() => {
                    fetch('{{ route("check-relationship") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            father_id: maleId,
                            mother_id: femaleId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.risk_level && data.risk_level !== 'none') {
                            inbreedingAlert.show(data);
                        } else {
                            inbreedingAlert.show({
                                risk_level: 'success',
                                message: 'Excellent breeding pair! No close relationships detected. This mating will promote genetic diversity.'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error checking compatibility:', error);
                        inbreedingAlert.hide();
                    });
                }, 300);
            }

            maleSelect.addEventListener('change', function() {
                updatePairInfo();
                checkMatingCompatibility();
            });

            femaleSelect.addEventListener('change', function() {
                updatePairInfo();
                checkMatingCompatibility();
            });

            plannedDateInput.addEventListener('change', updateBreedingTimeline);

            if (maleSelect.value && femaleSelect.value) {
                updatePairInfo();
                checkMatingCompatibility();
            }

            if (plannedDateInput.value) {
                updateBreedingTimeline();
            }
        });
    </script>
</x-app-layout>