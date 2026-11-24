{{-- One stage's daily ration: what it is now, and a form that opens on "Change". --}}
@php $kgFmt = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.'); @endphp
<div class="p-3 {{ $stage['needs_standard'] ? 'bg-amber-50/60' : '' }}">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="font-semibold text-slate-900">{{ $stage['label'] }} <span class="text-sm font-normal text-slate-500">· {{ $stage['animals'] }} {{ \Illuminate\Support\Str::plural('animal', $stage['animals']) }}@if(($stage['suckling'] ?? 0) > 0) + {{ $stage['suckling'] }} still on their sows @endif</span></p>
            @if(($stage['suckling'] ?? 0) > 0 && ! $stage['current'])
                <p class="text-xs text-slate-500">Set a ration here only if you feed creep feed to piglets on the sow.</p>
            @endif
            @if($stage['current'])
                <p class="text-sm text-slate-600">{{ $stage['current']->feedType?->name }} · {{ $kgFmt($stage['current']->daily_kg) }} kg each a day
                    @if($stage['animals'] > 0)<span class="text-slate-500">({{ $kgFmt($stage['daily_kg']) }} kg a day in total)</span>@endif
                </p>
            @elseif($stage['needs_standard'])
                <p class="text-sm font-semibold text-amber-800">No ration yet, so these animals are left out of feeding and mixing.</p>
            @else
                <p class="text-sm text-slate-500">No ration.</p>
            @endif
            @if($stage['scheduled'])
                <p class="text-xs text-slate-500">From {{ $stage['scheduled']->effective_from->format('j M Y') }}: {{ $stage['scheduled']->feedType?->name }} · {{ $kgFmt($stage['scheduled']->daily_kg) }} kg</p>
            @endif
        </div>
        @if(auth()->user()->hasPermission('manage_feed_production'))
            <button type="button" @click="editing = editing === '{{ $stage['stage'] }}' ? null : '{{ $stage['stage'] }}'"
                    class="rounded-lg px-3 py-1.5 text-sm font-semibold {{ $stage['current'] ? 'border border-slate-200 text-slate-700 hover:bg-slate-50' : 'bg-emerald-700 text-white hover:bg-emerald-800' }}">
                {{ $stage['current'] ? 'Change' : 'Set ration' }}
            </button>
        @endif
    </div>
    @if(auth()->user()->hasPermission('manage_feed_production'))
        <form method="POST" action="{{ route('planning-reports.standards') }}" x-show="editing === '{{ $stage['stage'] }}'" x-cloak
              class="mt-3 grid gap-3 sm:grid-cols-[1fr_10rem_11rem_auto] sm:items-end">
            @csrf
            <input type="hidden" name="stage" value="{{ $stage['stage'] }}">
            <input type="hidden" name="is_active" value="1">
            <label class="text-xs font-semibold uppercase text-slate-500">Feed
                <select name="feed_type_id" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm font-normal normal-case text-slate-800">
                    <option value="">Choose feed</option>
                    @foreach($feedTypes->where('is_active', true) as $type)
                        <option value="{{ $type['id'] }}" @selected((int) $stage['current']?->feed_type_id === (int) $type['id'])>{{ $type['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-semibold uppercase text-slate-500">Kg each a day
                <input name="daily_kg" type="number" min="0.0001" step="0.0001" required value="{{ $stage['current'] ? (float) $stage['current']->daily_kg : '' }}"
                       class="mt-1 block w-full rounded-lg border-slate-300 text-sm font-normal text-slate-800">
            </label>
            <label class="text-xs font-semibold uppercase text-slate-500">From
                <input name="effective_from" type="date" min="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" required
                       class="mt-1 block w-full rounded-lg border-slate-300 text-sm font-normal text-slate-800">
            </label>
            <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Save</button>
        </form>
    @endif
</div>
