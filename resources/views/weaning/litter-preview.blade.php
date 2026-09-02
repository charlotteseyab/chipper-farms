<x-app-layout>
    <main class="mx-auto max-w-7xl px-3 py-5 sm:px-6 lg:px-8">
        <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('weaning.dashboard') }}" class="text-sm font-bold text-emerald-700">← Back to weaning</a>
                <p class="mt-4 text-xs font-extrabold uppercase tracking-[.18em] text-emerald-700">Preview before stock entry</p>
                <h1 class="mt-1 text-2xl font-black text-slate-950 sm:text-3xl">Wean litter from sow {{ $farrowing->sow->ear_tag_number }}</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Review every surviving piglet, correct its proposed tag, gender, breed or weight, and choose its destination room. Nothing is added until you confirm.</p>
            </div>
            <span class="self-start rounded-full bg-emerald-100 px-4 py-2 text-sm font-black text-emerald-800">{{ $surviving }} surviving piglets</span>
        </div>

        @if($errors->any())
            <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800">
                <p class="font-black">Please correct the highlighted information.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('weaning.litter-wean', $farrowing) }}" class="space-y-5">
            @csrf
            <section class="grid gap-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2 lg:grid-cols-4 sm:p-6">
                <div><p class="text-xs font-bold uppercase text-slate-400">Mother</p><p class="mt-1 font-black text-slate-900">{{ $farrowing->sow->ear_tag_number }}</p><p class="text-xs text-slate-500">{{ $farrowing->sow->code }}</p></div>
                <div><p class="text-xs font-bold uppercase text-slate-400">Father</p><p class="mt-1 font-black text-slate-900">{{ $farrowing->boar?->ear_tag_number ?? 'Not recorded' }}</p></div>
                <div><p class="text-xs font-bold uppercase text-slate-400">Farrowed</p><p class="mt-1 font-black text-slate-900">{{ $farrowing->farrowing_date->format('d M Y') }}</p><p class="text-xs text-slate-500">{{ $currentAge }} days ago</p></div>
                <div><p class="text-xs font-bold uppercase text-slate-400">Litter outcome</p><p class="mt-1 font-black text-slate-900">{{ $farrowing->live_births }} live births</p><p class="text-xs text-slate-500">{{ $farrowing->pigletDeaths->sum('count') }} recorded deaths</p></div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="mb-5"><h2 class="text-lg font-black text-slate-950">Weaning and destination</h2><p class="mt-1 text-sm text-slate-500">These details apply to the full litter.</p></div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <label class="block"><span class="text-sm font-bold text-slate-700">Weaning date</span><input type="date" name="weaning_date" value="{{ old('weaning_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required class="mt-2 w-full rounded-xl border-slate-300 text-sm"></label>
                    <label class="block"><span class="text-sm font-bold text-slate-700">Method</span><select name="weaning_method" class="mt-2 w-full rounded-xl border-slate-300 text-sm"><option value="natural" @selected(old('weaning_method','natural') === 'natural')>Natural</option><option value="gradual" @selected(old('weaning_method') === 'gradual')>Gradual</option><option value="forced" @selected(old('weaning_method') === 'forced')>Forced</option></select></label>
                    <label class="block"><span class="text-sm font-bold text-slate-700">Reason</span><select name="weaning_reason" class="mt-2 w-full rounded-xl border-slate-300 text-sm"><option value="age_appropriate">Age appropriate</option><option value="health_issue">Health issue</option><option value="mother_issue">Mother issue</option><option value="management_decision">Management decision</option></select></label>
                    <label class="block"><span class="text-sm font-bold text-slate-700">Health after weaning</span><select name="post_weaning_status" class="mt-2 w-full rounded-xl border-slate-300 text-sm"><option value="healthy">Healthy</option><option value="monitoring">Monitoring</option><option value="stressed">Stressed</option><option value="sick">Sick</option></select></label>
                </div>
                <label class="mt-4 block"><span class="text-sm font-bold text-slate-700">Notes</span><textarea name="notes" rows="2" class="mt-2 w-full rounded-xl border-slate-300 text-sm" placeholder="Optional notes for this litter">{{ old('notes') }}</textarea></label>
            </section>

            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-5 sm:px-6"><h2 class="text-lg font-black text-slate-950">Piglet records</h2><p class="mt-1 text-sm text-slate-500">Tag suggestions continue from the latest animal in this farm. Each tag remains editable.</p></div>
                <div class="divide-y divide-slate-100">
                    @for($index = 0; $index < $surviving; $index++)
                        <div class="grid gap-4 px-5 py-5 sm:px-6 md:grid-cols-2 xl:grid-cols-[70px_minmax(130px,.8fr)_minmax(100px,.55fr)_minmax(160px,.8fr)_minmax(180px,1.2fr)_minmax(120px,.65fr)] xl:items-end">
                            <div><p class="text-xs font-bold uppercase text-slate-400">Animal</p><p class="mt-2 text-xl font-black text-slate-900">{{ $index + 1 }}</p></div>
                            <label><span class="text-xs font-bold uppercase text-slate-500">Ear tag</span><input name="animals[{{ $index }}][ear_tag_number]" value="{{ old("animals.$index.ear_tag_number", $proposedEarTags[$index] ?? '') }}" required maxlength="50" class="mt-2 w-full rounded-xl border-slate-300 text-sm font-bold"></label>
                            <label><span class="text-xs font-bold uppercase text-slate-500">Gender</span><select name="animals[{{ $index }}][gender]" class="mt-2 w-full rounded-xl border-slate-300 text-sm"><option value="M" @selected(old("animals.$index.gender", $suggestedGenders[$index]) === 'M')>Male</option><option value="F" @selected(old("animals.$index.gender", $suggestedGenders[$index]) === 'F')>Female</option></select></label>
                            <label><span class="text-xs font-bold uppercase text-slate-500">Breed</span><select name="animals[{{ $index }}][breed]" required class="mt-2 w-full rounded-xl border-slate-300 text-sm">@foreach($breeds as $code => $name)<option value="{{ $code }}" @selected(old("animals.$index.breed", $farrowing->sow->breed) === $code)>{{ $name }}</option>@endforeach</select></label>
                            <label><span class="text-xs font-bold uppercase text-slate-500">Destination room</span><select name="animals[{{ $index }}][room_id]" required class="mt-2 w-full rounded-xl border-slate-300 text-sm"><option value="">Select room</option>@foreach($rooms as $room)@php $available = ($room->capacity === null || (int)$room->capacity <= 0) ? null : max(0, (int)$room->capacity - (int)$room->active_pigs_count); @endphp<option value="{{ $room->id }}" @selected(old("animals.$index.room_id") == $room->id)>{{ $room->structure?->name }} / {{ $room->name }} — {{ $available === null ? 'Unlimited' : $available.' space'.($available === 1 ? '' : 's') }}</option>@endforeach</select></label>
                            <label><span class="text-xs font-bold uppercase text-slate-500">Weight (kg)</span><input type="number" step="0.01" min="0.1" max="500" name="animals[{{ $index }}][weaning_weight]" value="{{ old("animals.$index.weaning_weight") }}" placeholder="Optional" class="mt-2 w-full rounded-xl border-slate-300 text-sm"></label>
                        </div>
                    @endfor
                </div>
            </section>

            <div class="flex flex-col gap-4 rounded-2xl border border-emerald-200 bg-white p-4 shadow-sm sm:p-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <p class="text-sm font-black text-slate-900">Ready to add {{ $surviving }} {{ \Illuminate\Support\Str::plural('piglet', $surviving) }} to main stock?</p>
                    <p class="mt-1 text-sm font-medium leading-5 text-slate-600">This creates the individual stock and weaning records and cannot be submitted twice.</p>
                </div>
                <div class="grid shrink-0 gap-2 sm:grid-cols-[auto_1fr] lg:flex">
                    <a href="{{ route('weaning.dashboard') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50">Cancel</a>
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-700 px-6 py-3 text-center text-sm font-black text-white shadow-lg transition hover:bg-emerald-800 focus:outline-none focus:ring-4 focus:ring-emerald-200">Confirm and add to main stock</button>
                </div>
            </div>
        </form>
    </main>
</x-app-layout>
