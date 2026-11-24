<x-app-layout>
    @php
        $correcting = $event !== null;
        $v = fn (string $key) => old($key, $prefill[$key] ?? '');
        $back = $correcting ? route('doses.show', $event) : ($pig ? route('health.show', $pig) : ($case ? route('health-cases.show', $case) : route('medications.index')));
        $action = $correcting ? route('doses.update', $event) : ($pig ? route('medications.storeForPig', $pig) : route('medications.store'));
    @endphp
    <div class="max-w-3xl mx-auto px-4 pt-6 pb-24">
        <a href="{{ $back }}" class="text-sm text-slate-500 hover:text-slate-700">← Back</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $correcting ? 'Correct Dose #'.$event->id : 'Record a Dose' }}</h1>
        <p class="text-sm text-slate-500">
            {{ $correcting
                ? 'Saving keeps the original, marked corrected. Its stock goes back to the batch and the corrected amount is taken instead.'
                : 'Record a dose that has been given. The medicine is taken from stock once for all the animals listed, and each animal’s withdrawal is worked out from the medicine’s label period.' }}
        </p>

        @if($errors->any())<ul role="alert" class="mt-4 list-disc list-inside rounded-xl bg-red-50 p-4 text-sm text-red-700">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
        @if(session('error'))<p role="alert" class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</p>@endif
        @if($medicines->isEmpty())
            <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                No medicines in the catalogue yet. Doses are recorded against a catalogue medicine so stock and withdrawal are right.
                @can('create', \App\Models\Medicine::class)<a class="font-semibold underline" href="{{ route('medicines.create') }}">Add a medicine</a>.@else Ask a farm manager to add it.@endcan
            </p>
        @endif
        @if($case)<p class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">For case: <a class="font-semibold underline" href="{{ route('health-cases.show', $case) }}">{{ $case->condition }}</a></p>@endif
        @if($scheduled->isNotEmpty())
            <p class="mt-3 rounded-lg bg-sky-50 p-3 text-sm text-sky-900">Giving {{ $scheduled->count() }} scheduled dose(s) due {{ $scheduled->first()->due_at->format('D j M H:i') }}. Remove an animal below if it was not dosed, then mark its dose missed on the course page.</p>
        @endif

        <form method="POST" action="{{ $action }}" class="mt-5 space-y-5"
              x-data="{ catalogue: @js($catalogue), medicine: @js((string) $v('medicine_id')), batch: @js((string) old('batch_id', '')),
                        basis: @js($v('amount_basis') ?: 'per_animal'), amount: @js((string) $v('dose_amount')), animals: {{ count($selected) }},
                        get m() { return this.catalogue[this.medicine] ?? null },
                        get stockNeeded() {
                            const a = parseFloat(this.amount); if (!this.m || !(a > 0) || !this.animals) return null;
                            const total = this.basis === 'total' ? a : a * this.animals;
                            return Math.round(total / parseFloat(this.m.factor) * 10000) / 10000;
                        },
                        get onHand() { return this.m ? this.m.batches.reduce((s, b) => s + parseFloat(b.on_hand), 0) : 0 } }"
              @pigs-changed="animals = $event.detail">
            @csrf
            @if($correcting) @method('PUT') @else <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}"> @endif
            @if($case)<input type="hidden" name="health_record_id" value="{{ $case->id }}">@endif
            @foreach((array) old('scheduled_dose_ids', $prefill['scheduled_dose_ids']) as $id)<input type="hidden" name="scheduled_dose_ids[]" value="{{ $id }}">@endforeach

            <div class="space-y-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                <x-health.pig-picker name="pig_ids" :selected="$selected" :rooms="$rooms" :departed="$mayRecordForDeparted" label="Animals dosed" />
                @if($pig && $hasLeft)
                    @include('health.partials.late-entry', ['hasLeft' => $hasLeft, 'mayRecordForDeparted' => $mayRecordForDeparted])
                @elseif(! $correcting && $mayRecordForDeparted)
                    <label class="block text-sm font-medium text-slate-700">Reason, if any animal has left the farm
                        <input name="late_entry_reason" value="{{ old('late_entry_reason') }}" maxlength="2000" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                @endif
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-700">Given at
                        <input type="datetime-local" name="administered_at" required max="{{ now()->format('Y-m-d\TH:i') }}" value="{{ $v('administered_at') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Given by
                        <select name="administered_by" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                            @foreach($staff as $person)<option value="{{ $person->id }}" @selected((string) $v('administered_by') === (string) $person->id)>{{ $person->name }}{{ $person->id === auth()->id() ? ' (you)' : '' }}</option>@endforeach
                        </select>
                    </label>
                </div>
            </div>

            <div class="space-y-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                <label class="block text-sm font-medium text-slate-700">Medicine
                    <select name="medicine_id" x-model="medicine" @change="batch = ''" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                        <option value="">Choose</option>
                        @foreach($medicines as $medicine)<option value="{{ $medicine->id }}">{{ $medicine->display_name }}</option>@endforeach
                    </select>
                </label>
                <template x-if="m">
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-start gap-4" x-show="m.photo || m.used_for || m.precautions">
                            <template x-if="m.photo"><img :src="m.photo" alt="Photo of the selected medicine" class="h-32 w-32 rounded-xl border border-slate-200 object-cover"></template>
                            <div class="min-w-0 flex-1 space-y-1 text-sm">
                                <p x-show="m.used_for"><span class="font-semibold text-slate-700">Used for:</span> <span x-text="m.used_for"></span></p>
                                <p x-show="m.precautions" class="text-amber-900"><span class="font-semibold">Precautions:</span> <span x-text="m.precautions"></span></p>
                            </div>
                        </div>
                        <p class="text-xs" :class="m.withdrawal == null ? 'text-amber-700' : 'text-slate-500'"
                           x-text="m.withdrawal == null ? 'No withdrawal period recorded for this medicine. Check the label before selling.' : 'Withdrawal: ' + m.withdrawal + ' days. Each animal can be sold from the day after.'"></p>
                        <fieldset class="text-sm">
                            <legend class="font-medium text-slate-700">Amount entered</legend>
                            <div class="mt-1 flex flex-wrap gap-4">
                                <label class="inline-flex items-center gap-2"><input type="radio" name="amount_basis" value="per_animal" x-model="basis"> Per animal</label>
                                <label class="inline-flex items-center gap-2"><input type="radio" name="amount_basis" value="total" x-model="basis"> Total for the group (e.g. in water or feed)</label>
                            </div>
                        </fieldset>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="text-sm font-medium text-slate-700"><span x-text="basis === 'total' ? 'Total given' : 'Dose per animal'"></span>
                                <div class="relative mt-1">
                                    <input name="dose_amount" x-model="amount" inputmode="decimal" required class="block w-full rounded-lg border-slate-300 pr-14 text-sm">
                                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-500" x-text="m.unit"></span>
                                </div>
                                <input type="hidden" name="dose_unit" :value="m.unit">
                            </label>
                            <label class="text-sm font-medium text-slate-700">Route
                                <input name="route" value="{{ $v('route') }}" list="dose-routes" maxlength="50" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                                <datalist id="dose-routes"><template x-for="r in m.routes"><option :value="r"></option></template></datalist>
                            </label>
                        </div>
                        <label class="block text-sm font-medium text-slate-700">Take from batch
                            <select name="batch_id" x-model="batch" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                                <option value="">Automatic: earliest expiry first</option>
                                <template x-for="b in m.batches" :key="b.id">
                                    <option :value="b.id" x-text="b.number + ' · expires ' + b.expiry + ' · ' + b.on_hand + ' ' + m.stock_unit + ' left'"></option>
                                </template>
                            </select>
                        </label>
                        <p class="rounded-lg p-3 text-sm" :class="stockNeeded !== null && stockNeeded > onHand ? 'bg-red-50 text-red-700' : 'bg-slate-50 text-slate-700'">
                            <template x-if="stockNeeded !== null"><span x-text="'Takes ' + stockNeeded + ' ' + m.stock_unit + ' from stock (' + onHand + ' ' + m.stock_unit + ' in usable batches).'"></span></template>
                            <template x-if="stockNeeded === null"><span x-text="m.batches.length ? onHand + ' ' + m.stock_unit + ' in usable batches.' : 'No usable stock. Receive a batch first.'"></span></template>
                        </p>
                    </div>
                </template>
                <label class="block text-sm font-medium text-slate-700">Purpose
                    <input name="purpose" value="{{ $v('purpose') }}" maxlength="255" placeholder="e.g. Treatment, Vaccination" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="block text-sm font-medium text-slate-700">Notes
                    <textarea name="notes" rows="2" maxlength="5000" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">{{ $v('notes') }}</textarea>
                </label>
            </div>

            @if($correcting)
                <label class="block text-sm font-medium text-slate-700">What was wrong with the original?
                    <input name="correction_reason" required minlength="5" maxlength="2000" value="{{ old('correction_reason') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                </label>
            @endif
            <div class="flex justify-end">
                <button class="rounded-lg bg-emerald-700 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-800">{{ $correcting ? 'Save correction' : 'Record dose' }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
