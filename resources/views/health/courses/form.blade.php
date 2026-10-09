<x-app-layout>
    @php
        $amending = $course !== null;
        $lines = old('medicines', $amending
            ? $course->medicines->map(fn ($m) => ['medicine_id' => $m->medicine_id, 'dose_amount' => rtrim(rtrim((string) $m->dose_amount, '0'), '.'), 'dose_unit' => $m->dose_unit,
                'route' => $m->route, 'frequency' => $m->frequency, 'interval' => $m->interval, 'number_of_doses' => $m->number_of_doses,
                'start_at' => now()->addHour()->startOfHour()->format('Y-m-d\TH:i'), 'instructions' => $m->instructions])->values()->all()
            : [['medicine_id' => '', 'dose_amount' => '', 'dose_unit' => '', 'route' => '', 'frequency' => 'daily', 'interval' => '', 'number_of_doses' => 3,
                'start_at' => now()->addHour()->startOfHour()->format('Y-m-d\TH:i'), 'instructions' => '']]);
        $catalogue = $medicines->mapWithKeys(fn ($m) => [$m->id => ['unit' => $m->dose_unit, 'routes' => $m->allowed_routes ?? [], 'withdrawal' => $m->meat_withdrawal_days]]);
    @endphp
    <div class="max-w-4xl mx-auto px-4 pt-6 pb-24">
        <a href="{{ $case ? route('health-cases.show', $case) : route('health.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Back</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $amending ? 'Amend Course #'.$course->id : 'Prescribe a Course' }}</h1>
        <p class="text-sm text-slate-500">
            {{ $amending ? 'Saving creates a new version. The current one stops and its remaining doses are cancelled; doses already given stay on record.' : 'A treatment plan, or a vaccination or deworming course. Doses are scheduled from the frequency you set; nothing is taken from stock until a dose is recorded.' }}
        </p>
        @if($case)<p class="mt-2 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">For case: <strong>{{ $case->condition }}</strong> ({{ \App\Models\HealthRecord::CASE_STATUSES[$case->case_status ?? 'open'] ?? '' }})</p>@endif

        @if($errors->any())<ul role="alert" class="mt-4 list-disc list-inside rounded-xl bg-red-50 p-4 text-sm text-red-700">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
        @if(session('error'))<p role="alert" class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</p>@endif
        @if($medicines->isEmpty())
            <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">The medicine catalogue is empty. <a class="font-semibold underline" href="{{ route('medicines.create') }}">Add a medicine</a> first.</p>
        @endif

        <form method="POST" action="{{ $amending ? route('treatment-courses.update', $course) : route('treatment-courses.store') }}" class="mt-5 space-y-5"
              x-data="{ lines: @js($lines), catalogue: @js($catalogue),
                        blank() { return { medicine_id: '', dose_amount: '', dose_unit: '', route: '', frequency: 'daily', interval: '', number_of_doses: 3, start_at: @js(now()->addHour()->startOfHour()->format('Y-m-d\TH:i')), instructions: '' } } }">
            @csrf
            @if($amending) @method('PUT') @endif
            @if($case)<input type="hidden" name="health_record_id" value="{{ $case->id }}">@endif

            <div class="space-y-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                @unless($amending)
                    <label class="block text-sm font-medium text-slate-700">Purpose
                        <select name="purpose" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                            @foreach(\App\Models\TreatmentCourse::PURPOSES as $value => $label)<option value="{{ $value }}" @selected(old('purpose', $case ? 'treatment' : 'vaccination') === $value)>{{ $label }}</option>@endforeach
                        </select>
                    </label>
                @endunless
                <x-health.pig-picker name="pig_ids" :selected="$selected" :rooms="$rooms" label="Animals on this course" />
                <div class="grid gap-4 sm:grid-cols-3">
                    <label class="text-sm font-medium text-slate-700">Prescribed by
                        <input name="prescriber_name" value="{{ old('prescriber_name', $course?->prescriber_name) }}" maxlength="255" placeholder="e.g. Dr Mensah" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Responsible person
                        <select name="responsible_user_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                            <option value="">—</option>
                            @foreach($staff as $person)<option value="{{ $person->id }}" @selected((string) old('responsible_user_id', $course?->responsible_user_id) === (string) $person->id)>{{ $person->name }}</option>@endforeach
                        </select>
                    </label>
                    <label class="text-sm font-medium text-slate-700">Follow up on
                        <input type="date" name="follow_up_on" value="{{ old('follow_up_on', $course?->follow_up_on?->toDateString()) }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                </div>
            </div>

            <template x-for="(line, i) in lines" :key="i">
                <div class="space-y-3 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="font-bold text-slate-900" x-text="'Medicine ' + (i + 1)"></h2>
                        <button type="button" x-show="lines.length > 1" @click="lines.splice(i, 1)" class="text-sm text-red-600">Remove</button>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-[2fr_1fr_1fr]">
                        <label class="text-sm font-medium text-slate-700">Medicine
                            <select :name="`medicines[${i}][medicine_id]`" x-model="line.medicine_id" @change="line.dose_unit = catalogue[line.medicine_id]?.unit ?? ''" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                                <option value="">Choose</option>
                                @foreach($medicines as $medicine)<option value="{{ $medicine->id }}">{{ $medicine->display_name }}</option>@endforeach
                            </select>
                        </label>
                        <label class="text-sm font-medium text-slate-700">Dose per animal
                            <div class="relative mt-1">
                                <input type="number" step="0.0001" min="0.0001" :name="`medicines[${i}][dose_amount]`" x-model="line.dose_amount" required class="block w-full rounded-lg border-slate-300 pr-14 text-sm">
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-500" x-text="catalogue[line.medicine_id]?.unit ?? ''"></span>
                            </div>
                            <input type="hidden" :name="`medicines[${i}][dose_unit]`" :value="catalogue[line.medicine_id]?.unit ?? line.dose_unit">
                        </label>
                        <label class="text-sm font-medium text-slate-700">Route
                            <input :name="`medicines[${i}][route]`" x-model="line.route" :list="`routes-${i}`" maxlength="50" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                            <datalist :id="`routes-${i}`"><template x-for="r in (catalogue[line.medicine_id]?.routes ?? [])"><option :value="r"></option></template></datalist>
                        </label>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-4">
                        <label class="text-sm font-medium text-slate-700">How often
                            <select :name="`medicines[${i}][frequency]`" x-model="line.frequency" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                                @foreach(\App\Models\TreatmentCourseMedicine::FREQUENCIES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                            </select>
                        </label>
                        <label class="text-sm font-medium text-slate-700" x-show="line.frequency === 'every_n_hours' || line.frequency === 'every_n_days'">Every
                            <input type="number" min="1" :name="`medicines[${i}][interval]`" x-model="line.interval" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" :placeholder="line.frequency === 'every_n_hours' ? 'hours' : 'days'">
                        </label>
                        <label class="text-sm font-medium text-slate-700" x-show="line.frequency !== 'once'">Number of doses
                            <input type="number" min="1" max="200" :name="`medicines[${i}][number_of_doses]`" x-model="line.number_of_doses" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                        </label>
                        <label class="text-sm font-medium text-slate-700">First dose
                            <input type="datetime-local" :name="`medicines[${i}][start_at]`" x-model="line.start_at" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                        </label>
                    </div>
                    <input :name="`medicines[${i}][instructions]`" x-model="line.instructions" maxlength="2000" placeholder="Instructions (optional)" class="block w-full rounded-lg border-slate-300 text-sm">
                    <p class="text-xs" :class="catalogue[line.medicine_id]?.withdrawal == null ? 'text-amber-700' : 'text-slate-500'"
                       x-show="line.medicine_id" x-text="catalogue[line.medicine_id]?.withdrawal == null ? 'No withdrawal period recorded for this medicine. Check the label.' : 'Withdrawal: ' + catalogue[line.medicine_id].withdrawal + ' days after the last dose.'"></p>
                </div>
            </template>
            <button type="button" @click="lines.push(blank())" class="text-sm font-semibold text-emerald-700">+ Add another medicine</button>

            <label class="block text-sm font-medium text-slate-700">Notes
                <textarea name="notes" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">{{ old('notes', $course?->notes) }}</textarea>
            </label>
            @if($amending)
                <label class="block text-sm font-medium text-slate-700">Why is the course changing?
                    <input name="amendment_reason" required minlength="5" maxlength="2000" value="{{ old('amendment_reason') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                </label>
            @endif
            <div class="flex justify-end">
                <button class="rounded-lg bg-emerald-700 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-800">{{ $amending ? 'Save new version' : 'Prescribe course' }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
