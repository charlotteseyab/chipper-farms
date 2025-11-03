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

            <div class="flex items-center justify-end gap-3">
                <a href="{{ isset($pig) ? route('health.show', $pig) : route('health.index') }}" class="px-4 py-2 rounded-md border border-gray-300 text-gray-700">Cancel</a>
                <button type="submit" class="px-4 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700">Save</button>
            </div>
        </form>
    </div>
</div>
<x-copyright-minimal />
</x-app-layout>


