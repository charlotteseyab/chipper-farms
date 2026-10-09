<x-app-layout>
    <style>
        .pig-edit-page{
            min-height: 100vh;
            padding: 18px 0 42px;
        }

        .pig-edit-shell{
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 16px;
        }

        .pig-edit-header{
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 26px 28px;
            background: linear-gradient(135deg, #14532d 0%, #166534 48%, #15803d 100%);
            box-shadow: 0 22px 50px rgba(22,101,52,.18);
            color: #fff;
            margin-bottom: 18px;
        }

        .pig-edit-header::before{
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,.16), transparent 26%),
                radial-gradient(circle at bottom left, rgba(255,255,255,.10), transparent 24%);
            pointer-events: none;
        }

        .pig-edit-header-inner{
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .pig-edit-title{
            margin: 0;
            font-size: clamp(1.8rem, 3vw, 2.6rem);
            line-height: 1.02;
            font-weight: 900;
            letter-spacing: -.04em;
        }

        .pig-edit-subtitle{
            margin: 10px 0 0;
            max-width: 760px;
            color: rgba(255,255,255,.90);
            font-size: .95rem;
            line-height: 1.75;
        }

        .pig-edit-pills{
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .pig-edit-pill{
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.16);
            color: #eff6ff;
            font-size: .78rem;
            font-weight: 800;
        }

        .pig-edit-error{
            background: linear-gradient(180deg, #fff1f2 0%, #ffe4e6 100%);
            border: 1px solid #fecdd3;
            border-radius: 22px;
            padding: 16px 18px;
            margin-bottom: 18px;
            box-shadow: 0 10px 24px rgba(244,63,94,.08);
        }

        .pig-edit-error-title{
            font-size: .92rem;
            font-weight: 800;
            color: #be123c;
            margin-bottom: 8px;
        }

        .pig-edit-layout{
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            align-items: start;
        }

        .pig-card{
            background: rgba(255,255,255,.96);
            border: 1px solid #dbe7f3;
            border-radius: 28px;
            box-shadow: 0 14px 34px rgba(15,23,42,.06);
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .pig-card-head{
            padding: 22px 24px 14px;
            border-bottom: 1px solid #edf2f7;
            background: linear-gradient(180deg, rgba(248,250,252,.9) 0%, rgba(255,255,255,.95) 100%);
        }

        .pig-card-title{
            margin: 0;
            font-size: 1.08rem;
            font-weight: 900;
            color: #0f172a;
        }

        .pig-card-sub{
            margin-top: 5px;
            font-size: .83rem;
            color: #64748b;
        }

        .pig-card-body{
            padding: 22px 24px 24px;
        }

        .pig-section{
            margin-bottom: 22px;
        }

        .pig-section:last-child{
            margin-bottom: 0;
        }

        .pig-section-title{
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: .88rem;
            font-weight: 900;
            letter-spacing: .02em;
            color: #334155;
            margin-bottom: 12px;
        }

        .pig-grid-2{
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .pig-field{
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .pig-label{
            font-size: .84rem;
            font-weight: 800;
            color: #334155;
        }

        .pig-input,
        .pig-select{
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

        .pig-input:focus,
        .pig-select:focus{
            border-color: #16a34a;
            box-shadow: 0 0 0 4px rgba(22,163,74,.10);
        }

        .pig-help{
            font-size: .75rem;
            color: #64748b;
            line-height: 1.55;
        }

        .pig-summary-box{
            background: linear-gradient(180deg, #f0fdf4 0%, #f8fffb 100%);
            border: 1px solid #bbf7d0;
            border-radius: 20px;
            padding: 16px 18px;
        }

        .pig-summary-title{
            font-size: .83rem;
            font-weight: 900;
            color: #166534;
            margin-bottom: 8px;
        }

        .pig-summary-list{
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 7px;
        }

        .pig-summary-list li{
            font-size: .84rem;
            color: #166534;
        }

        .pig-meta-box{
            background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 16px 18px;
        }

        .pig-meta-title{
            font-size: .82rem;
            font-weight: 900;
            color: #334155;
            margin-bottom: 6px;
        }

        .pig-actions{
            margin-top: 18px;
            display: grid;
            gap: 12px;
        }

        .pig-submit{
            width: 100%;
            border: none;
            border-radius: 18px;
            padding: 15px 18px;
            color: #fff;
            font-size: .96rem;
            font-weight: 900;
            letter-spacing: .01em;
            background: linear-gradient(135deg, #16a34a, #15803d);
            box-shadow: 0 18px 34px rgba(22,163,74,.20);
            transition: .22s ease;
        }

        .pig-submit:hover:not(:disabled){
            transform: translateY(-1px);
            box-shadow: 0 20px 36px rgba(22,163,74,.24);
        }

        .pig-submit.standard-mode{
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            box-shadow: 0 18px 34px rgba(37,99,235,.20);
        }

        .pig-submit:disabled{
            opacity: .55;
            cursor: not-allowed;
            box-shadow: none;
        }

        .pig-back{
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

        .pig-back:hover{
            background: #f1f5f9;
            color: #1e293b;
        }

        .inline-feedback{
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: .76rem;
            font-weight: 700;
            margin-top: 6px;
        }

        .inline-feedback.success{
            color: #15803d;
        }

        .inline-feedback.error{
            color: #b91c1c;
        }

        .inline-feedback.info{
            color: #475569;
        }

        .inline-warning{
            margin-top: 12px;
            border-radius: 16px;
            padding: 12px 14px;
            font-size: .8rem;
            font-weight: 700;
            background: #fff7ed;
            border: 1px solid #fdba74;
            color: #9a3412;
        }

        @media (max-width: 1100px){
            .pig-edit-layout{
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px){
            .pig-edit-shell{
                padding: 0 12px;
            }

            .pig-edit-header{
                padding: 22px 18px;
                border-radius: 24px;
            }

            .pig-card-head,
            .pig-card-body{
                padding-left: 18px;
                padding-right: 18px;
            }

            .pig-grid-2{
                grid-template-columns: 1fr;
            }

            .pig-edit-pills{
                width: 100%;
            }

            .pig-edit-pill{
                width: 100%;
                justify-content: center;
            }
        }
    </style>

    <div class="pig-edit-page">
        <div class="pig-edit-shell">
            <div class="pig-edit-header">
                <div class="pig-edit-header-inner">
                    <div>
                        <h1 class="pig-edit-title">Edit Pig Record</h1>
                        <p class="pig-edit-subtitle">
                            Update stock details, date of birth, room assignment, and parent records without losing the clean structure
                            needed for breeding, stock, and reporting workflows.
                        </p>
                    </div>

                    <div class="pig-edit-pills">
                        <div class="pig-edit-pill">Safe updates</div>
                        <div class="pig-edit-pill">Ear tag validation</div>
                        <div class="pig-edit-pill">Responsive layout</div>
                    </div>
                </div>
            </div>

            @if ($errors->any())
                <div class="pig-edit-error">
                    <div class="pig-edit-error-title">Please correct the highlighted errors before updating this pig.</div>
                    <ul style="margin:0; padding-left:18px; color:#be123c; font-size:.84rem; line-height:1.7;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('breeding-stocks.update', $breedingStock) }}" class="pig-edit-layout">
                @csrf
                @method('PUT')

                <section class="pig-card">
                    <div class="pig-card-head">
                        <h3 class="pig-card-title">Basic Information</h3>
                        <div class="pig-card-sub">Core identity and stock details for this pig record.</div>
                    </div>

                    <div class="pig-card-body">
                        <div class="pig-section">
                            <div class="pig-section-title">Pig Stock & Identity</div>

                            <div class="pig-grid-2">
                                <div class="pig-field">
                                    <label class="pig-label">Stock Type</label>
                                    <select name="stock_type" required class="pig-select">
                                        <option value="breeding" @if(old('stock_type', $breedingStock->stock_type) === 'breeding') selected @endif>Breeding Stock</option>
                                        <option value="standard" @if(old('stock_type', $breedingStock->stock_type) === 'standard') selected @endif>Standard Stock</option>
                                    </select>
                                </div>

                                <div class="pig-field">
                                    <label class="pig-label">Breed</label>
                                    <select name="breed" required class="pig-select" data-searchable-select>
                                        @foreach($animalBreeds as $code => $name)
                                            <option value="{{ $code }}" @selected(old('breed', $breedingStock->breed) === $code)>{{ $name }} ({{ $code }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="pig-section">
                            <div class="pig-grid-2">
                                <div class="pig-field">
                                    <label class="pig-label">Gender</label>
                                    <select name="gender" required class="pig-select">
                                        <option value="M" @if(old('gender', $breedingStock->gender) === 'M') selected @endif>Male</option>
                                        <option value="F" @if(old('gender', $breedingStock->gender) === 'F') selected @endif>Female</option>
                                    </select>
                                </div>

                                <div class="pig-field">
                                    <label class="pig-label">Date of Birth</label>
                                    <input
                                        type="date"
                                        name="date_of_birth"
                                        required
                                        max="{{ now()->toDateString() }}"
                                        value="{{ old('date_of_birth', optional($breedingStock->date_of_birth)->format('Y-m-d')) }}"
                                        class="pig-input"
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="pig-section">
                            @php
                                $currentTag = $breedingStock->currentEarTagAssignment?->tag;
                                $tagColour = $currentTag?->color;
                                $confirmedColour = $tagColour && $tagColour->normalized_name !== 'unspecified';
                                $tagDot = $confirmedColour && preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $tagColour->hex_color)
                                    ? $tagColour->hex_color : '#CBD5E1';
                            @endphp
                            <div class="pig-section-title">Physical Ear Tag</div>
                            <div class="pig-grid-2">
                                <div class="pig-field">
                                    <label class="pig-label">Tag Colour</label>
                                    <div class="pig-input flex items-center gap-3 bg-slate-100 text-slate-700">
                                        <span class="h-4 w-4 shrink-0 rounded-full border border-slate-300" style="background-color: {{ $tagDot }}"></span>
                                        <span class="font-semibold">{{ $confirmedColour ? $tagColour->name : 'Colour not confirmed' }}</span>
                                    </div>
                                </div>
                                <div class="pig-field">
                                    <label class="pig-label">Tag Number</label>
                                    <input type="text" name="ear_tag_number" required readonly
                                           value="{{ old('ear_tag_number', $breedingStock->ear_tag_number) }}"
                                           class="pig-input bg-slate-100 text-slate-700">
                                    @error('ear_tag_number')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                                </div>
                            </div>
                            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <p class="text-xs leading-5 text-slate-600">Tag identity cannot be overwritten during an ordinary animal edit. This protects the animal’s tag history.</p>
                                @if($breedingStock->currentEarTagAssignment)
                                    @if($confirmedColour)
                                        <a href="{{ route('pigs.ear-tag.replace', $breedingStock) }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-800">Replace physical tag</a>
                                    @else
                                        <a href="{{ route('settings.ear-tags.reconciliation', ['q' => $breedingStock->ear_tag_number]) }}" class="rounded-lg bg-amber-600 px-4 py-2 text-xs font-bold text-white hover:bg-amber-700">Confirm tag colour</a>
                                    @endif
                                @else
                                    <a href="{{ route('settings.ear-tags.reconciliation', ['q' => $breedingStock->ear_tag_number]) }}" class="rounded-lg bg-amber-600 px-4 py-2 text-xs font-bold text-white hover:bg-amber-700">Reconcile tag</a>
                                @endif
                            </div>
                        </div>

                        <div class="pig-summary-box">
                            <div class="pig-summary-title">Current record summary</div>
                            <ul class="pig-summary-list">
                                <li>• Code: <strong>{{ $breedingStock->code ?? 'Auto-generated' }}</strong></li>
                                <li>• Current stock type: <strong>{{ ucfirst($breedingStock->stock_type) }}</strong></li>
                                <li>• Current ear tag: <strong>{{ $confirmedColour ? $tagColour->name.' ' : '' }}{{ $breedingStock->ear_tag_number }}</strong></li>
                            </ul>
                        </div>
                    </div>
                </section>

                <section class="pig-card">
                    <div class="pig-card-head">
                        <h3 class="pig-card-title">Location & Family</h3>
                        <div class="pig-card-sub">Update the room and parent details where needed.</div>
                    </div>

                    <div class="pig-card-body">
                        <div class="pig-section">
                            <div class="pig-section-title">Housing Housing Assignment</div>

                            <div class="pig-field">
                                <label class="pig-label">Room (optional)</label>
                                <select name="room_id" class="pig-select">
                                    <option value="" selected>-- No Room --</option>
                                    @isset($rooms)
                                        @foreach ($rooms as $room)
                                            @php
                                                $label = ($room->structure?->name ? $room->structure->name . ' / ' : '') . $room->name;
                                                if ($room->capacity) {
                                                    $label .= " (" . ($room->pigs()->count()) . "/" . $room->capacity . ")";
                                                }
                                            @endphp
                                            <option value="{{ $room->id }}" @if(old('room_id', $breedingStock->room_id) == $room->id) selected @endif>{{ $label }}</option>
                                        @endforeach
                                    @endisset
                                </select>
                                <div class="pig-help">Capacity limits are still enforced when you update the room.</div>
                                @error('room_id')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="pig-section">
                            <div class="pig-section-title">Parents Parent Information</div>

                            <div class="pig-field" style="margin-bottom:14px;">
                                <label class="pig-label">Father (optional)</label>
                                <select name="father_id" id="father_id" class="pig-select">
                                    <option value="" selected>-- None --</option>
                                    @foreach ($males as $male)
                                        <option value="{{ $male->id }}" @if(old('father_id', $breedingStock->father_id) == $male->id) selected @endif>
                                            {{ $male->ear_tag_number }} - {{ $male->code }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="pig-field">
                                <label class="pig-label">Mother (optional)</label>
                                <select name="mother_id" id="mother_id" class="pig-select">
                                    <option value="" selected>-- None --</option>
                                    @foreach ($females as $female)
                                        <option value="{{ $female->id }}" @if(old('mother_id', $breedingStock->mother_id) == $female->id) selected @endif>
                                            {{ $female->ear_tag_number }} - {{ $female->code }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="pig-meta-box">
                            <div class="pig-meta-title">Editing guidance</div>
                            <div class="pig-help">
                                Change stock type only if the pig is really moving between breeding and standard workflows.
                                Parent changes should be made carefully because they affect lineage and reporting.
                            </div>
                        </div>

                        <div class="pig-actions">
                            <button type="submit" id="submit-button" class="pig-submit">
                                Update Pig Record
                            </button>

                            <a href="{{ route('pigs.show', $breedingStock->id) }}" class="pig-back">
                                ← Back to Pig Details
                            </a>
                        </div>
                    </div>
                </section>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fatherSelect = document.getElementById('father_id');
            const motherSelect = document.getElementById('mother_id');
            const submitButton = document.getElementById('submit-button');
            const stockTypeSelect = document.querySelector('select[name="stock_type"]');

            function removeFeedback(selector) {
                document.querySelectorAll(selector).forEach(el => el.remove());
            }

            function checkInbreeding() {
                removeFeedback('.inbreeding-warning');

                const fatherId = fatherSelect.value;
                const motherId = motherSelect.value;

                if (fatherId && motherId && fatherId === motherId) {
                    const warning = document.createElement('div');
                    warning.className = 'inbreeding-warning inline-warning';
                    warning.innerHTML = 'The same pig cannot be selected as both father and mother.';
                    motherSelect.parentNode.appendChild(warning);
                }

                updateSubmitButtonState();
            }

            function updateFormTheme() {
                const stockType = stockTypeSelect.value;

                if (stockType === 'standard') {
                    submitButton.classList.add('standard-mode');
                    submitButton.textContent = 'Update Standard Stock';
                } else {
                    submitButton.classList.remove('standard-mode');
                    submitButton.textContent = 'Update Breeding Stock';
                }
            }

            function updateSubmitButtonState() {
                const hasInbreedingIssue = document.querySelector('.inbreeding-warning') !== null;
                if (hasInbreedingIssue) {
                    submitButton.disabled = true;
                    submitButton.classList.add('opacity-50', 'cursor-not-allowed');
                } else {
                    submitButton.disabled = false;
                    submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            }

            fatherSelect.addEventListener('change', checkInbreeding);
            motherSelect.addEventListener('change', checkInbreeding);
            stockTypeSelect.addEventListener('change', updateFormTheme);

            checkInbreeding();
            updateFormTheme();
            updateSubmitButtonState();
        });
    </script>
</x-app-layout>
