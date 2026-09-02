<x-app-layout>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Record Medication</h1>
        @if(isset($pig))
        <p class="text-gray-500">{{ $pig->code ?? $pig->ear_tag_number }} • {{ $pig->breed }}</p>
        @else
        <p class="text-gray-500">Select a pig to record medication</p>
        @endif
    </div>

    <div class="bg-white shadow-sm rounded-lg p-6">
        <form method="post" action="{{ isset($pig) ? route('medications.storeForPig', $pig) : route('medications.store') }}" class="space-y-6">
            @csrf
            @if(!isset($pig))
            <div>
                <label class="block text-sm font-medium text-gray-700">Select animal</label>
                <select name="pig_id" data-searchable-select data-search-placeholder="Search by ear tag, code, breed, or gender..." class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" required>
                    <option value="">Search for an animal...</option>
                    @foreach($pigs as $p)
                    <option value="{{ $p->id }}" @selected(old('pig_id')==$p->id)>Tag {{ $p->ear_tag_number }}{{ $p->code ? ' • '.$p->code : '' }} • {{ $p->breed }} • {{ $p->gender === 'M' ? 'Male' : 'Female' }}</option>
                    @endforeach
                </select>
                <p class="mt-2 text-xs text-gray-500">Only active animals in this farm can be selected. The medication appears in the log after it is saved.</p>
                @error('pig_id')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            @endif
            <div>
                <label class="block text-sm font-medium text-gray-700">Administration Date</label>
                <input type="date" name="administration_date" value="{{ old('administration_date', now()->toDateString()) }}" class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" required />
                @error('administration_date')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Medication Name</label>
                <input type="text" name="medication_name" value="{{ old('medication_name') }}" class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" required />
                @error('medication_name')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Dosage</label>
                <input type="text" name="dosage" value="{{ old('dosage') }}" class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" required />
                @error('dosage')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Purpose</label>
                <input type="text" name="purpose" value="{{ old('purpose') }}" class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                @error('purpose')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Route</label>
                <input type="text" name="route" value="{{ old('route') }}" placeholder="e.g., oral, injection" class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
            </div>

            {{--
                IMP-090 — the withdrawal period, as printed on the bottle.

                This is the input Phase 11's enforcement was waiting on: a pig
                inside its withdrawal period cannot be pushed to the shop, and
                until this field existed every treatment read as "no withdrawal"
                so the guard could never fire.

                Left blank when the medicine carries none, which is most of
                them — guessing a default would block legal sales and teach
                people to ignore the warning.
            --}}
            <div>
                <label class="block text-sm font-medium text-gray-700">
                    Withdrawal period (days)
                </label>
                <input type="number" name="withdrawal_period_days" min="0" max="365"
                       value="{{ old('withdrawal_period_days') }}"
                       placeholder="e.g. 28 — leave blank if none"
                       class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                <p class="mt-1 text-xs text-gray-500">
                    How long before this animal may be sold for meat. Check the label.
                    While the period is running the pig cannot be listed on the shop.
                </p>
                @error('route')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Notes</label>
                <textarea name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                @error('notes')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ isset($pig) ? route('health.show', $pig) : route('health.index') }}" class="px-4 py-2 rounded-md border border-gray-300 text-gray-700">Cancel</a>
                <button type="submit" class="px-4 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700">Save medication record</button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>

