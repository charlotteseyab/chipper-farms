<x-app-layout>
    <main class="min-h-screen bg-slate-50 px-4 py-8 pb-28 sm:px-6">
        <div class="mx-auto max-w-3xl">
            <div class="mb-6"><a href="{{ route('pigs.show', $pig) }}" class="text-sm font-bold text-emerald-700">← Back to animal</a><h1 class="mt-3 text-3xl font-black text-slate-950">Replace physical ear tag</h1><p class="mt-2 text-slate-600">The animal remains {{ $pig->code }}. This closes the old assignment and creates a new one without moving its records.</p></div>
            @if($errors->any())<div class="mb-5 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <form method="POST" action="{{ route('pigs.ear-tag.update', $pig) }}" class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">@csrf @method('PATCH')
                <div class="rounded-2xl bg-slate-50 p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Current physical tag</p><p class="mt-2 text-xl font-black text-slate-950">{{ $pig->currentEarTagAssignment?->tag?->color?->name ?? 'Unspecified' }} {{ $pig->ear_tag_number }}</p><p class="mt-1 text-sm text-slate-500">Permanent animal code: {{ $pig->code }}</p></div>
                <div><label class="mb-2 block text-sm font-bold text-slate-700">What happened to the old tag?</label><select name="old_tag_status" required class="w-full rounded-xl border-slate-300"><option value="">Select outcome</option><option value="lost" @selected(old('old_tag_status') === 'lost')>Lost</option><option value="damaged" @selected(old('old_tag_status') === 'damaged')>Damaged</option><option value="retired" @selected(old('old_tag_status') === 'retired')>Retired</option><option value="available" @selected(old('old_tag_status') === 'available')>Removed and still reusable</option></select></div>
                <div class="grid gap-5 sm:grid-cols-2"><div><label class="mb-2 block text-sm font-bold text-slate-700">New tag colour</label><select id="tag-color" name="ear_tag_color_id" required class="w-full rounded-xl border-slate-300"><option value="">Select colour</option>@foreach($colors as $color)<option value="{{ $color->id }}" @selected((string) old('ear_tag_color_id') === (string) $color->id)>{{ $color->name }}</option>@endforeach</select></div><div><label class="mb-2 block text-sm font-bold text-slate-700">New tag number</label><input id="tag-number" name="ear_tag_number" value="{{ old('ear_tag_number') }}" required class="w-full rounded-xl border-slate-300" inputmode="numeric"><p id="tag-suggestion" class="mt-1 text-xs text-slate-500">Choose a colour to see the next eligible number.</p></div></div>
                <div><label class="mb-2 block text-sm font-bold text-slate-700">Reason for replacement</label><textarea name="reason" rows="3" required class="w-full rounded-xl border-slate-300" placeholder="Explain why this tag is being replaced">{{ old('reason') }}</textarea></div>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><a href="{{ route('pigs.show', $pig) }}" class="rounded-xl border border-slate-300 px-5 py-3 text-center text-sm font-bold text-slate-700">Cancel</a><button class="rounded-xl bg-emerald-700 px-5 py-3 text-sm font-black text-white">Replace tag</button></div>
            </form>
        </div>
    </main>
    <script>
        const suggestions = @json($suggestions);
        const color = document.getElementById('tag-color');
        const number = document.getElementById('tag-number');
        const hint = document.getElementById('tag-suggestion');
        color.addEventListener('change', () => {
            const choice = suggestions[color.value]?.[0];
            if (!choice) { hint.textContent = 'No eligible suggestion is available for this colour.'; return; }
            number.value = choice.tag_number;
            hint.textContent = choice.previously_used ? `Suggested recovered tag ${choice.tag_number}` : `Suggested new tag ${choice.tag_number}`;
        });
    </script>
</x-app-layout>
