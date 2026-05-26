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
                <label class="block text-sm font-medium text-gray-700">Select Pig</label>
                <select name="pig_id" class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" required>
                    <option value="">Choose a pig...</option>
                    @foreach($pigs as $p)
                    <option value="{{ $p->id }}" @selected(old('pig_id')==$p->id)>{{ $p->code ?? $p->ear_tag_number }} • {{ $p->breed }}</option>
                    @endforeach
                </select>
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
                @error('route')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Notes</label>
                <textarea name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                @error('notes')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ isset($pig) ? route('health.show', $pig) : route('health.index') }}" class="px-4 py-2 rounded-md border border-gray-300 text-gray-700">Cancel</a>
                <button type="submit" class="px-4 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700">Save</button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>


