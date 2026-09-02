<x-app-layout>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Add Health Record</h1>
        @if(isset($pig))
        <p class="text-gray-500">{{ $pig->code ?? $pig->ear_tag_number }} • {{ $pig->breed }}</p>
        @else
        <p class="text-gray-500">Select a pig to add health record</p>
        @endif
    </div>

    <div class="bg-white shadow-sm rounded-lg p-6">
        <form method="post" action="{{ isset($pig) ? route('health.storeForPig', $pig) : route('health.store') }}" class="space-y-6">
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
                <p class="mt-2 text-xs text-gray-500">Only active animals in this farm can be selected. The animal appears in the health table after this record is saved.</p>
                @error('pig_id')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            @endif
            <div>
                <label class="block text-sm font-medium text-gray-700">Record Date</label>
                <input type="date" name="record_date" value="{{ old('record_date', now()->toDateString()) }}" class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" required />
                @error('record_date')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Condition</label>
                <input type="text" name="condition" value="{{ old('condition') }}" class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" required />
                @error('condition')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Severity</label>
                <select name="severity" class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Select severity</option>
                    <option value="low" @selected(old('severity')==='low')>Low</option>
                    <option value="medium" @selected(old('severity')==='medium')>Medium</option>
                    <option value="high" @selected(old('severity')==='high')>High</option>
                </select>
                @error('severity')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Symptoms</label>
                <textarea name="symptoms" rows="3" class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">{{ old('symptoms') }}</textarea>
                @error('symptoms')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Diagnosis</label>
                <textarea name="diagnosis" rows="3" class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">{{ old('diagnosis') }}</textarea>
                @error('diagnosis')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Treatment Plan</label>
                <textarea name="treatment_plan" rows="3" class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">{{ old('treatment_plan') }}</textarea>
                @error('treatment_plan')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Treatment Status</label>
                    <select name="treatment_status" class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select status</option>
                        <option value="planned" @selected(old('treatment_status')==='planned')>Planned</option>
                        <option value="ongoing" @selected(old('treatment_status')==='ongoing')>Ongoing</option>
                        <option value="completed" @selected(old('treatment_status')==='completed')>Completed</option>
                        <option value="stopped" @selected(old('treatment_status')==='stopped')>Stopped</option>
                    </select>
                    @error('treatment_status')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Outcome</label>
                    <select name="outcome" class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select outcome</option>
                        <option value="recovered" @selected(old('outcome')==='recovered')>Recovered</option>
                        <option value="improved" @selected(old('outcome')==='improved')>Improved</option>
                        <option value="unchanged" @selected(old('outcome')==='unchanged')>Unchanged</option>
                        <option value="worsened" @selected(old('outcome')==='worsened')>Worsened</option>
                    </select>
                    @error('outcome')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Resolved Date</label>
                <input type="date" name="resolved_at" value="{{ old('resolved_at') }}" class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                @error('resolved_at')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Resolution Notes</label>
                <textarea name="resolution_notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">{{ old('resolution_notes') }}</textarea>
                @error('resolution_notes')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ isset($pig) ? route('health.show', $pig) : route('health.index') }}" class="px-4 py-2 rounded-md border border-gray-300 text-gray-700">Cancel</a>
                <button type="submit" class="px-4 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700">Save health record</button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>

