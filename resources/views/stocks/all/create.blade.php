<x-app-layout>
    <style>
        .pig-create-page{
            min-height: 100vh;
            padding: 18px 0 42px;
        }

        .pig-create-shell{
            max-width: 1380px;
            margin: 0 auto;
            padding: 0 10px;
        }

        .pig-create-header{
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 20px 18px;
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 48%, #0f766e 100%);
            box-shadow: 0 22px 50px rgba(30,64,175,.18);
            color: #fff;
            margin-bottom: 18px;
        }

        .pig-create-header::before{
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,.16), transparent 26%),
                radial-gradient(circle at bottom left, rgba(255,255,255,.10), transparent 24%);
            pointer-events: none;
        }

        .pig-create-header-inner{
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .pig-create-title{
            margin: 0;
            font-size: clamp(1.8rem, 3vw, 2.65rem);
            line-height: 1.02;
            font-weight: 900;
            letter-spacing: -.04em;
        }

        .pig-create-subtitle{
            margin: 10px 0 0;
            max-width: 760px;
            color: rgba(255,255,255,.88);
            font-size: .95rem;
            line-height: 1.75;
        }

        .pig-create-pills{
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .pig-create-pill{
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

        .pig-create-error{
            background: linear-gradient(180deg, #fff1f2 0%, #ffe4e6 100%);
            border: 1px solid #fecdd3;
            border-radius: 22px;
            padding: 16px 18px;
            margin-bottom: 18px;
            box-shadow: 0 10px 24px rgba(244,63,94,.08);
        }

        .pig-create-error-title{
            font-size: .92rem;
            font-weight: 800;
            color: #be123c;
            margin-bottom: 8px;
        }

        .pig-create-form{
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
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37,99,235,.10);
        }

        .pig-input:disabled,
        .pig-select:disabled{
            background: #f8fafc;
            color: #94a3b8;
            cursor: not-allowed;
        }

        .pig-help{
            font-size: .75rem;
            color: #64748b;
            line-height: 1.55;
        }

        .stock-type-grid{
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .stock-type-option{
            position: relative;
            border: 1px solid #dbe4ee;
            border-radius: 22px;
            background: #fff;
            padding: 18px 16px;
            text-align: center;
            transition: .22s ease;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(15,23,42,.03);
        }

        .stock-type-option:hover{
            transform: translateY(-2px);
            box-shadow: 0 14px 24px rgba(15,23,42,.06);
        }

        .stock-type-option.selected-standard{
            border-color: #2563eb;
            background: linear-gradient(180deg, #eff6ff 0%, #ffffff 100%);
            box-shadow: 0 14px 24px rgba(37,99,235,.10);
        }

        .stock-type-option.selected-breeding{
            border-color: #16a34a;
            background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 100%);
            box-shadow: 0 14px 24px rgba(22,163,74,.10);
        }

        .stock-type-icon{
            font-size: 1.9rem;
            line-height: 1;
            margin-bottom: 8px;
        }

        .stock-type-name{
            font-size: .98rem;
            font-weight: 900;
            color: #0f172a;
        }

        .stock-type-text{
            margin-top: 4px;
            font-size: .76rem;
            color: #64748b;
        }

        .stock-type-badge{
            position: absolute;
            top: 12px;
            right: 12px;
            display: none;
            font-size: .65rem;
            font-weight: 800;
            border-radius: 999px;
            padding: 4px 8px;
            color: #fff;
        }

        .stock-type-option.selected-standard .stock-type-badge{
            display: inline-flex;
            background: #2563eb;
        }

        .stock-type-option.selected-breeding .stock-type-badge{
            display: inline-flex;
            background: #16a34a;
        }

        .pig-summary-box{
            background: linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%);
            border: 1px solid #dbeafe;
            border-radius: 20px;
            padding: 16px 18px;
        }

        .pig-summary-title{
            font-size: .83rem;
            font-weight: 900;
            color: #1d4ed8;
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
            color: #1e3a8a;
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
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            box-shadow: 0 18px 34px rgba(37,99,235,.20);
            transition: .22s ease;
        }

        .pig-submit:hover:not(:disabled){
            transform: translateY(-1px);
            box-shadow: 0 20px 36px rgba(37,99,235,.24);
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
            .pig-create-form{
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px){
            .pig-create-shell{
                padding: 0 12px;
            }

            .pig-create-header{
                padding: 22px 18px;
                border-radius: 24px;
            }

            .pig-card-head,
            .pig-card-body{
                padding-left: 18px;
                padding-right: 18px;
            }

            .pig-grid-2,
            .stock-type-grid{
                grid-template-columns: 1fr;
            }

            .pig-create-pills{
                width: 100%;
            }

            .pig-create-pill{
                width: 100%;
                justify-content: center;
            }
        }
    </style>

    <div class="pig-create-page">
        <div class="pig-create-shell">
            <div class="pig-create-header">
                <div class="pig-create-header-inner">
                    <div>
                        <h1 class="pig-create-title">Register New Pig</h1>
                        <p class="pig-create-subtitle">
                            Add a new pig into Chipper Farms with clear stock classification, housing assignment, and family details.
                            The form is designed to stay simple for daily farm use while still capturing the important records.
                        </p>
                    </div>

                    <div class="pig-create-pills">
                        <div class="pig-create-pill">Fast entry</div>
                        <div class="pig-create-pill">Error checks</div>
                        <div class="pig-create-pill">Responsive layout</div>
                    </div>
                </div>
            </div>

            @if ($errors->any())
                <div class="pig-create-error">
                    <div class="pig-create-error-title">Please correct the highlighted errors before saving.</div>
                    <ul style="margin:0; padding-left:18px; color:#be123c; font-size:.84rem; line-height:1.7;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('breeding-stocks.store') }}" id="pigForm" class="pig-create-form">
                @csrf

                <section class="pig-card">
                    <div class="pig-card-head">
                        <h3 class="pig-card-title">Basic Information</h3>
                        <div class="pig-card-sub">Core identity and stock details for the pig record.</div>
                    </div>

                    <div class="pig-card-body">
                        <div class="pig-section">
                            <div class="pig-section-title">Stock Type</div>

                            <div class="stock-type-grid">
                                <label class="relative block cursor-pointer">
                                    <input
                                        type="radio"
                                        name="stock_type"
                                        value="standard"
                                        class="absolute opacity-0 pointer-events-none"
                                        {{ old('stock_type') === 'standard' ? 'checked' : '' }}
                                        required
                                    >
                                    <div class="stock-type-option" data-type="standard">
                                        <span class="stock-type-badge">Selected</span>
                                        <!-- <div class="stock-type-icon">Pig</div> -->
                                        <div class="stock-type-name">Standard Stock</div>
                                        <div class="stock-type-text">For fattening and sale</div>
                                    </div>
                                </label>

                                <label class="relative block cursor-pointer">
                                    <input
                                        type="radio"
                                        name="stock_type"
                                        value="breeding"
                                        class="absolute opacity-0 pointer-events-none"
                                        {{ old('stock_type') === 'breeding' ? 'checked' : '' }}
                                        required
                                    >
                                    <div class="stock-type-option" data-type="breeding">
                                        <span class="stock-type-badge">Selected</span>
                                        <!-- <div class="stock-type-icon">Breeding</div> -->
                                        <div class="stock-type-name">Breeding Stock</div>
                                        <div class="stock-type-text">For reproduction and breeding plans</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="pig-section">
                            <div class="pig-grid-2">
                                <div class="pig-field">
                                    <label class="pig-label">Breed</label>
                                    <select name="breed" required class="pig-select">
                                        <option value="" {{ old('breed') ? '' : 'selected' }} disabled>Select breed</option>
                                        <option value="LW" {{ old('breed') === 'LW' ? 'selected' : '' }}>Large White</option>
                                        <option value="DR" {{ old('breed') === 'DR' ? 'selected' : '' }}>Duroc</option>
                                        <option value="LWDR" {{ old('breed') === 'LWDR' ? 'selected' : '' }}>Large White Duroc</option>
                                    </select>
                                </div>

                                <div class="pig-field">
                                    <label class="pig-label">Gender</label>
                                    <select name="gender" required class="pig-select">
                                        <option value="" {{ old('gender') ? '' : 'selected' }} disabled>Select gender</option>
                                        <option value="M" {{ old('gender') === 'M' ? 'selected' : '' }}>Male</option>
                                        <option value="F" {{ old('gender') === 'F' ? 'selected' : '' }}>Female</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="pig-section">
                            <div class="pig-field">
                                <label class="pig-label">Ear Tag Number</label>
                                <input
                                    type="text"
                                    name="ear_tag_number"
                                    value="{{ old('ear_tag_number') }}"
                                    placeholder="e.g. 1, 2, 3 or CF-001"
                                    class="pig-input"
                                    required
                                >
                                <div class="pig-help">This should be unique for each pig in the system.</div>
                            </div>
                        </div>

                        <div class="pig-section">
                            <div class="pig-field">
                                <label class="pig-label">Date of Birth</label>
                                <input
                                    type="date"
                                    name="date_of_birth"
                                    max="{{ now()->toDateString() }}"
                                    value="{{ old('date_of_birth') }}"
                                    class="pig-input"
                                    required
                                >
                            </div>
                        </div>

                        <div class="pig-summary-box">
                            <div class="pig-summary-title">Auto-generated after save</div>
                            <ul class="pig-summary-list">
                                <li>• Unique pig code will be generated automatically</li>
                                <li>• Age will be calculated from the date of birth</li>
                                <li>• Record will become available for dashboard and stock reports</li>
                            </ul>
                        </div>
                    </div>
                </section>

                <section class="pig-card">
                    <div class="pig-card-head">
                        <h3 class="pig-card-title">Location & Family</h3>
                        <div class="pig-card-sub">Housing assignment and parent details, where available.</div>
                    </div>

                    <div class="pig-card-body">
                        <div class="pig-section">
                            <div class="pig-section-title">Housing Housing Assignment</div>

                            <div class="pig-field" style="margin-bottom:14px;">
                                <label class="pig-label">Structure</label>
                                <select name="structure_id" id="structure_id" class="pig-select">
                                    <option value="" {{ old('structure_id') ? '' : 'selected' }} disabled>Select structure (optional)</option>
                                    @isset($structures)
                                        @foreach ($structures as $structure)
                                            <option value="{{ $structure->id }}" {{ old('structure_id') == $structure->id ? 'selected' : '' }}>
                                                {{ $structure->name }}
                                                @if($structure->capacity)
                                                    ({{ $structure->current_room_count }}/{{ $structure->capacity }} rooms)
                                                @endif
                                            </option>
                                        @endforeach
                                    @endisset
                                </select>
                            </div>

                            <div class="pig-field">
                                <label class="pig-label">Room</label>
                                <select name="room_id" id="room_id" class="pig-select" disabled>
                                    <option value="" disabled selected>Select structure first</option>
                                </select>
                                <div class="pig-help">Choose a structure first to load available rooms.</div>
                            </div>
                        </div>

                        <div class="pig-section">
                            <div class="pig-section-title">Parents Parent Information</div>

                            <div class="pig-field" style="margin-bottom:14px;">
                                <label class="pig-label">Father (optional)</label>
                                <select name="father_id" id="father_id" class="pig-select">
                                    <option value="" {{ old('father_id') ? '' : 'selected' }} disabled>Select father</option>
                                    @foreach ($males as $male)
                                        <option value="{{ $male->id }}" {{ old('father_id') == $male->id ? 'selected' : '' }}>
                                            {{ $male->code }} - {{ $male->ear_tag_number }} ({{ $male->stock_type === 'breeding' ? 'Breeding' : 'Standard' }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="pig-field">
                                <label class="pig-label">Mother (optional)</label>
                                <select name="mother_id" id="mother_id" class="pig-select">
                                    <option value="" {{ old('mother_id') ? '' : 'selected' }} disabled>Select mother</option>
                                    @foreach ($females as $female)
                                        <option value="{{ $female->id }}" {{ old('mother_id') == $female->id ? 'selected' : '' }}>
                                            {{ $female->code }} - {{ $female->ear_tag_number }} ({{ $female->stock_type === 'breeding' ? 'Breeding' : 'Standard' }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="pig-meta-box">
                            <div class="pig-meta-title">Good record practice</div>
                            <div class="pig-help">
                                Assign a room when possible and capture parent records only when you are confident they are correct.
                                Clean lineage and housing data improve breeding, weight, and health reporting later.
                            </div>
                        </div>

                        <div class="pig-actions">
                            <button type="submit" id="submit-button" class="pig-submit">
                                Register Pig
                            </button>

                            <a href="{{ \App\Helpers\NavigationHelper::getContextualBackUrl('all-stocks') }}" class="pig-back">
                                ← Back to Stock List
                            </a>
                        </div>
                    </div>
                </section>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const stockTypeRadios = document.querySelectorAll('input[name="stock_type"]');
            const stockTypeOptions = document.querySelectorAll('.stock-type-option');
            const fatherSelect = document.getElementById('father_id');
            const motherSelect = document.getElementById('mother_id');
            const structureSelect = document.getElementById('structure_id');
            const roomSelect = document.getElementById('room_id');
            const submitButton = document.getElementById('submit-button');
            const earTagInput = document.querySelector('input[name="ear_tag_number"]');

            const selectedRoomId = @json(old('room_id'));
            let earTagCheckTimeout;

            function removeFeedback(selector) {
                document.querySelectorAll(selector).forEach(el => el.remove());
            }

            function updateStockTypeSelection() {
                stockTypeOptions.forEach(option => {
                    option.classList.remove('selected-standard', 'selected-breeding');
                });

                const selectedType = document.querySelector('input[name="stock_type"]:checked');

                if (selectedType) {
                    const selectedOption = document.querySelector(`.stock-type-option[data-type="${selectedType.value}"]`);

                    if (selectedOption) {
                        if (selectedType.value === 'standard') {
                            selectedOption.classList.add('selected-standard');
                            submitButton.textContent = 'Register Standard Stock';
                            submitButton.style.background = 'linear-gradient(135deg, #2563eb, #1d4ed8)';
                        } else {
                            selectedOption.classList.add('selected-breeding');
                            submitButton.textContent = 'Register Breeding Stock';
                            submitButton.style.background = 'linear-gradient(135deg, #16a34a, #15803d)';
                        }
                    }
                } else {
                    submitButton.textContent = 'Register Pig';
                    submitButton.style.background = 'linear-gradient(135deg, #2563eb, #1d4ed8)';
                }

                updateSubmitButtonState();
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

            function checkEarTagAvailability() {
                const earTagValue = earTagInput.value.trim();

                removeFeedback('.ear-tag-warning');
                earTagInput.classList.remove('border-red-500', 'border-green-500');

                if (!earTagValue.length) {
                    updateSubmitButtonState();
                    return;
                }

                const loading = document.createElement('div');
                loading.className = 'ear-tag-warning inline-feedback info';
                loading.innerHTML = 'Search Checking ear tag availability...';
                earTagInput.parentNode.appendChild(loading);

                fetch('/api/check-ear-tag', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ ear_tag_number: earTagValue })
                })
                .then(response => response.json())
                .then(data => {
                    removeFeedback('.ear-tag-warning');

                    const feedback = document.createElement('div');
                    feedback.className = `ear-tag-warning inline-feedback ${data.exists ? 'error' : 'success'}`;
                    feedback.innerHTML = data.exists
                        ? 'This ear tag number already exists. Please choose a different one.'
                        : 'This ear tag number is available.';

                    earTagInput.parentNode.appendChild(feedback);

                    if (data.exists) {
                        earTagInput.classList.add('border-red-500');
                    } else {
                        earTagInput.classList.add('border-green-500');
                    }

                    updateSubmitButtonState();
                })
                .catch(() => {
                    removeFeedback('.ear-tag-warning');

                    const feedback = document.createElement('div');
                    feedback.className = 'ear-tag-warning inline-feedback info';
                    feedback.innerHTML = 'ℹ️ Could not verify ear tag right now. You can still continue if you are sure.';
                    earTagInput.parentNode.appendChild(feedback);

                    updateSubmitButtonState();
                });
            }

            function loadRooms(structureId, selected = null) {
                if (!structureId) {
                    roomSelect.disabled = true;
                    roomSelect.innerHTML = '<option value="" disabled selected>Select structure first</option>';
                    return;
                }

                roomSelect.disabled = true;
                roomSelect.innerHTML = '<option value="" disabled selected>Loading rooms...</option>';

                fetch(`/api/structures/${structureId}/rooms`)
                    .then(response => response.json())
                    .then(rooms => {
                        roomSelect.disabled = false;
                        roomSelect.innerHTML = '<option value="" disabled selected>Select room</option>';

                        rooms.forEach(room => {
                            const option = document.createElement('option');
                            option.value = room.id;

                            let text = `${room.name} (${room.code}) - ${room.type}`;
                            if (room.capacity) {
                                text += ` - ${room.capacity} capacity`;
                            }

                            option.textContent = text;

                            if (selected && String(selected) === String(room.id)) {
                                option.selected = true;
                            }

                            roomSelect.appendChild(option);
                        });
                    })
                    .catch(() => {
                        roomSelect.disabled = true;
                        roomSelect.innerHTML = '<option value="" disabled selected>Error loading rooms</option>';
                    });
            }

            function updateSubmitButtonState() {
                const hasInbreedingIssue = document.querySelector('.inbreeding-warning') !== null;
                const hasEarTagIssue = document.querySelector('.ear-tag-warning.error') !== null;
                const earTagValue = earTagInput.value.trim();
                const stockTypeSelected = document.querySelector('input[name="stock_type"]:checked') !== null;

                const disabled = hasInbreedingIssue || hasEarTagIssue || !earTagValue.length || !stockTypeSelected;

                submitButton.disabled = disabled;
            }

            stockTypeRadios.forEach(radio => {
                radio.addEventListener('change', updateStockTypeSelection);
            });

            fatherSelect.addEventListener('change', checkInbreeding);
            motherSelect.addEventListener('change', checkInbreeding);

            structureSelect.addEventListener('change', function () {
                loadRooms(this.value, null);
            });

            earTagInput.addEventListener('input', function () {
                clearTimeout(earTagCheckTimeout);
                earTagCheckTimeout = setTimeout(checkEarTagAvailability, 450);
            });

            updateStockTypeSelection();
            checkInbreeding();

            if (structureSelect.value) {
                loadRooms(structureSelect.value, selectedRoomId);
            }

            if (earTagInput.value.trim().length) {
                checkEarTagAvailability();
            } else {
                updateSubmitButtonState();
            }
        });
    </script>
</x-app-layout>