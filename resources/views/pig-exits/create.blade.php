<x-app-layout>
    @php
        $isCull = $type === \App\Models\PigExit::CULL;
        $tag = $pig->currentEarTagAssignment?->tag;
    @endphp
    <div class="max-w-3xl mx-auto px-4 pt-6 pb-24">
        <a href="{{ route('pigs.show', $pig->id) }}" class="text-sm text-slate-500 hover:text-slate-700">← Back to pig</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $isCull ? 'Cull' : 'Transfer Out' }}: {{ $pig->code }}</h1>
        <p class="text-sm text-slate-500">
            {{ $pig->identityAt() }} · {{ $pig->breed }} · {{ $pig->gender === 'M' ? 'Male' : 'Female' }}.
            {{ $isCull ? 'Removes the animal from the herd for poor performance or health.' : 'Moves the animal to another farm or owner without a sale.' }}
            Its history stays on record and its tag is released.
        </p>
        <div class="mt-3 flex gap-2 text-sm">
            <a href="{{ route('pig-exits.create', [$pig, 'type' => 'transfer_out']) }}" class="rounded-lg px-3 py-1.5 font-semibold {{ $isCull ? 'border text-slate-600' : 'bg-slate-900 text-white' }}">Transfer out</a>
            <a href="{{ route('pig-exits.create', [$pig, 'type' => 'cull']) }}" class="rounded-lg px-3 py-1.5 font-semibold {{ $isCull ? 'bg-slate-900 text-white' : 'border text-slate-600' }}">Cull</a>
        </div>

        @if($errors->any())
            <ul role="alert" class="mt-5 list-disc list-inside rounded-xl bg-red-50 p-4 text-sm text-red-800">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        @endif
        @if($withdrawal['restricted'])
            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <p class="font-semibold">Under medicine withdrawal until {{ $withdrawal['until']->format('d M Y') }}</p>
                <p class="mt-1">{{ $isCull ? 'A cull slaughtered for food is refused until then.' : 'Tell the receiving farm: this animal must not be slaughtered for food before ' . $withdrawal['until']->addDay()->format('d M Y') . '.' }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route('pig-exits.store', $pig) }}" class="mt-5 space-y-5 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            @csrf
            <input type="hidden" name="exit_type" value="{{ $type }}">

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-medium text-slate-700">Date
                    <input type="date" name="exit_date" required max="{{ now()->toDateString() }}" value="{{ old('exit_date', now()->toDateString()) }}" class="mt-1 block w-full rounded-lg border-slate-300">
                </label>
                <label class="text-sm font-medium text-slate-700">Final weight (kg, optional)
                    <input type="number" name="final_weight" step="0.01" min="0.01" value="{{ old('final_weight') }}" class="mt-1 block w-full rounded-lg border-slate-300">
                </label>
            </div>

            <div class="grid gap-4 sm:grid-cols-2" x-data="{ reason: @js(old('reason', '')) }">
                <label class="text-sm font-medium text-slate-700">Reason
                    <select name="reason" x-model="reason" required class="mt-1 block w-full rounded-lg border-slate-300">
                        <option value="">Choose a reason</option>
                        @foreach(\App\Models\PigExit::REASONS[$type] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700" x-show="reason === 'other'" x-cloak>Describe the reason
                    <input name="reason_other" value="{{ old('reason_other') }}" maxlength="255" class="mt-1 block w-full rounded-lg border-slate-300">
                </label>
            </div>

            @if($isCull)
                <label class="block text-sm font-medium text-slate-700">What happened to the animal
                    <select name="disposal" required class="mt-1 block w-full rounded-lg border-slate-300">
                        <option value="">Choose</option>
                        @foreach(\App\Models\PigExit::DISPOSALS as $value => $label)
                            <option value="{{ $value }}" @selected(old('disposal') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="mt-1 block text-xs text-slate-500">Selling the culled animal? Record it under Sales instead, with purpose "Slaughter".</span>
                </label>
            @else
                <label class="block text-sm font-medium text-slate-700">Going to (farm or owner)
                    <input name="destination" required maxlength="255" value="{{ old('destination') }}" placeholder="e.g. Kintampo farm, or Mr Mensah" class="mt-1 block w-full rounded-lg border-slate-300">
                </label>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-medium text-slate-700">Ear tag {{ $tag ? '('.$pig->identityAt().')' : '' }}
                    <select name="tag_outcome" required class="mt-1 block w-full rounded-lg border-slate-300">
                        @foreach([
                            \App\Models\EarTag::RETIRED => $isCull ? 'Destroyed with the animal' : 'Left on the animal (not coming back)',
                            \App\Models\EarTag::AVAILABLE => 'Removed and ready to reuse',
                            \App\Models\EarTag::PENDING_RECOVERY => 'To be recovered later',
                            \App\Models\EarTag::DAMAGED => 'Removed but damaged',
                            \App\Models\EarTag::LOST => 'Lost',
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected(old('tag_outcome', \App\Models\EarTag::RETIRED) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">Tag notes (optional)
                    <input name="tag_condition_notes" value="{{ old('tag_condition_notes') }}" maxlength="1000" class="mt-1 block w-full rounded-lg border-slate-300">
                </label>
            </div>

            <label class="block text-sm font-medium text-slate-700">Notes (optional)
                <textarea name="notes" rows="2" maxlength="2000" class="mt-1 block w-full rounded-lg border-slate-300">{{ old('notes') }}</textarea>
            </label>

            <div class="flex justify-end gap-3">
                <a href="{{ route('pigs.show', $pig->id) }}" class="rounded-lg border px-4 py-2 text-sm font-semibold text-slate-600">Cancel</a>
                <button class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
                        onclick="return confirm('Remove {{ $pig->code }} from the herd? This cannot be undone from this screen.')">
                    Record {{ $isCull ? 'cull' : 'transfer out' }}
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
