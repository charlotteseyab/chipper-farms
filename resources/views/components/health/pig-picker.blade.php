@props([
    'name' => 'pig_ids',
    'selected' => [],
    'multiple' => true,
    'departed' => false,
    'rooms' => collect(),
    'label' => 'Animals',
])

{{-- A dropdown of the farm's animals that can also be searched: opening it
     lists the live animals (fetched then, not built into the page); typing
     searches by code or by tag colour and number ("green 21"), including
     animals that have left where the form allows. A room button adds everyone
     currently in that room, saved as a fixed list. --}}
<div x-data="{
        picked: @js(collect(old($name) ? \App\Services\Health\HealthPigFinder::rowsFor((array) old($name)) : $selected)->values()),
        q: '', results: [], all: null, open: false, busy: false, multiple: @js($multiple), browseLimit: @js(\App\Services\Health\HealthPigFinder::BROWSE_LIMIT),
        async openList() {
            this.open = true;
            if (this.all === null) {
                this.busy = true;
                try {
                    const r = await fetch(@js(route('health.pig-search')), { headers: { 'Accept': 'application/json' } });
                    this.all = r.ok ? await r.json() : [];
                } finally { this.busy = false; }
            }
        },
        get shown() {
            const list = this.q.trim() ? this.results : (this.all ?? []);
            return list.filter(p => !this.picked.some(x => x.id === p.id));
        },
        async search() {
            this.open = true;
            if (this.q.trim().length < 1) { this.results = []; return; }
            this.busy = true;
            try {
                const r = await fetch(@js(route('health.pig-search')) + '?departed={{ $departed ? 1 : 0 }}&q=' + encodeURIComponent(this.q), { headers: { 'Accept': 'application/json' } });
                this.results = r.ok ? await r.json() : [];
            } finally { this.busy = false; }
        },
        add(pig) {
            if (!this.multiple) { this.picked = [pig]; }
            else if (!this.picked.some(p => p.id === pig.id)) { this.picked.push(pig); }
            this.q = ''; this.results = [];
            if (!this.multiple) { this.open = false; }
        },
        async addRoom(id) {
            if (!id) return;
            const r = await fetch(@js(url('/health/rooms')) + '/' + id + '/pigs', { headers: { 'Accept': 'application/json' } });
            if (r.ok) { (await r.json()).forEach(p => this.add(p)); }
        },
        remove(id) { this.picked = this.picked.filter(p => p.id !== id); },
     }" x-effect="$dispatch('pigs-changed', picked.length)" class="space-y-2">
    <label class="block text-sm font-medium text-slate-700" for="{{ $name }}-search">{{ $label }}</label>
    <template x-for="pig in picked" :key="pig.id">
        <input type="hidden" name="{{ $multiple ? $name.'[]' : $name }}" :value="pig.id">
    </template>
    <div class="flex flex-wrap gap-2" x-show="picked.length">
        <template x-for="pig in picked" :key="pig.id">
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-1 text-sm text-emerald-900 ring-1 ring-emerald-200">
                <span class="font-semibold" x-text="pig.code"></span>
                <span class="text-xs text-emerald-700" x-text="pig.label !== pig.code ? pig.label : ''"></span>
                <button type="button" @click="remove(pig.id)" class="ml-1 text-emerald-700 hover:text-red-600" :aria-label="'Remove ' + pig.code">×</button>
            </span>
        </template>
    </div>
    <div class="relative" x-show="multiple || !picked.length" @click.outside="open = false" @keydown.escape="open = false">
        <div class="relative">
            <input id="{{ $name }}-search" type="search" x-model="q" @input.debounce.300ms="search()" @focus="openList()" @click="openList()" autocomplete="off"
                   role="combobox" :aria-expanded="open" aria-autocomplete="list"
                   placeholder="Choose an animal, or search by code (CFPG-2607-9) or tag (green 21)" class="w-full rounded-lg border-slate-300 pr-9 text-sm">
            <button type="button" @click="open ? open = false : openList()" class="absolute inset-y-0 right-0 flex w-9 items-center justify-center text-slate-400 hover:text-slate-600" aria-label="Show all animals">
                <svg class="h-4 w-4 transition" :class="open && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
            </button>
        </div>
        <div x-show="open && (shown.length || busy || q.length)" x-cloak class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg" role="listbox">
            <template x-for="pig in shown" :key="pig.id">
                <button type="button" @click="add(pig)" role="option" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-emerald-50">
                    <span><span class="font-semibold" x-text="pig.code"></span> <span class="text-slate-500" x-text="pig.label"></span></span>
                    <span class="text-xs text-slate-400" x-text="(pig.left ? 'left the farm · ' : '') + (pig.room ?? '')"></span>
                </button>
            </template>
            <p x-show="busy" class="px-3 py-2 text-xs text-slate-400" x-text="q.trim() ? 'Searching…' : 'Loading animals…'"></p>
            <p x-show="!busy && q.length && !results.length" class="px-3 py-2 text-xs text-slate-500">No animal matches.</p>
            <p x-show="!busy && !q.trim() && all && all.length >= browseLimit" class="border-t border-slate-100 px-3 py-2 text-xs text-slate-500">Showing the first <span x-text="browseLimit"></span> animals by code. Type to search them all.</p>
            <p x-show="!busy && !q.trim() && all && !all.length" class="px-3 py-2 text-xs text-slate-500">No animals on the farm yet.</p>
        </div>
    </div>
    @if($multiple && $rooms->isNotEmpty())
        <div class="flex items-center gap-2 text-sm">
            <label for="{{ $name }}-room" class="text-slate-500">Add everyone in</label>
            <select id="{{ $name }}-room" @change="addRoom($event.target.value); $event.target.value = ''" class="rounded-lg border-slate-300 text-sm">
                <option value="">a room…</option>
                @foreach($rooms as $room)<option value="{{ $room->id }}">{{ $room->name }}</option>@endforeach
            </select>
        </div>
    @endif
    @error($name)<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    @error($name.'.*')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
</div>
