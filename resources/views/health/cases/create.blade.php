<x-app-layout>
    <div class="max-w-3xl mx-auto px-4 pt-6 pb-24">
        <a href="{{ $pig ? route('health.show', $pig) : route('health.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Back</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Open a Health Case</h1>
        <p class="text-sm text-slate-500">Record what was seen. For several animals with the same problem, add them all: the list is saved as it is now and later pen moves won't change it.</p>

        @if($errors->any())
            <ul role="alert" class="mt-4 list-disc list-inside rounded-xl bg-red-50 p-4 text-sm text-red-700">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        @endif

        <form method="POST" action="{{ $pig ? route('health.storeForPig', $pig) : route('health.store') }}" class="mt-5 space-y-5 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            @csrf
            @if($pig)
                <div>
                    <p class="text-sm font-medium text-slate-700">Animal</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $pig->code }} <span class="font-normal text-slate-500">{{ $pig->identityAt() }}</span></p>
                </div>
                @include('health.partials.late-entry', ['hasLeft' => $hasLeft, 'mayRecordForDeparted' => $mayRecordForDeparted])
            @else
                <x-health.pig-picker name="pig_ids" :rooms="$rooms" :departed="$mayRecordForDeparted" label="Animal or animals" />
                <label class="block text-sm font-medium text-slate-700">Group name (optional, for several animals)
                    <input name="group_label" value="{{ old('group_label') }}" maxlength="255" placeholder="e.g. Weaner pen 2" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="block text-sm font-medium text-slate-700">Reason, if any animal has left the farm (managers only)
                    <input name="late_entry_reason" value="{{ old('late_entry_reason') }}" maxlength="2000" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                </label>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-medium text-slate-700">Seen on
                    <input type="datetime-local" name="observed_at" required max="{{ now()->format('Y-m-d\TH:i') }}" value="{{ old('observed_at', now()->format('Y-m-d\TH:i')) }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-sm font-medium text-slate-700">Severity
                    <select name="severity" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                        <option value="">Not assessed</option>
                        @foreach(\App\Models\HealthRecord::SEVERITIES as $value => $label)<option value="{{ $value }}" @selected(old('severity') === $value)>{{ $label }}</option>@endforeach
                    </select>
                </label>
            </div>
            <label class="block text-sm font-medium text-slate-700">Condition
                <input name="condition" required maxlength="255" value="{{ old('condition') }}" placeholder="e.g. Diarrhoea, lameness, cough" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
            </label>
            <label class="block text-sm font-medium text-slate-700">Symptoms (what you saw)
                <textarea name="symptoms" rows="3" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">{{ old('symptoms') }}</textarea>
            </label>
            <div class="grid gap-4 sm:grid-cols-[1fr_12rem]">
                <label class="text-sm font-medium text-slate-700">Diagnosis, if known
                    <input name="diagnosis" value="{{ old('diagnosis') }}" maxlength="5000" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-sm font-medium text-slate-700">Diagnosis is
                    <select name="diagnosis_status" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                        <option value="">—</option>
                        @foreach(\App\Models\HealthRecord::DIAGNOSIS_STATUSES as $value => $label)<option value="{{ $value }}" @selected(old('diagnosis_status') === $value)>{{ $label }}</option>@endforeach
                    </select>
                </label>
            </div>
            <label class="block text-sm font-medium text-slate-700">Responsible person
                <select name="responsible_user_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    <option value="">—</option>
                    @foreach($staff as $person)<option value="{{ $person->id }}" @selected((string) old('responsible_user_id') === (string) $person->id)>{{ $person->name }}</option>@endforeach
                </select>
            </label>
            <div class="flex justify-end gap-3">
                <a href="{{ $pig ? route('health.show', $pig) : route('health.index') }}" class="rounded-lg border px-4 py-2 text-sm font-semibold text-slate-600">Cancel</a>
                <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Open case</button>
            </div>
        </form>
    </div>
</x-app-layout>
