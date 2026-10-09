@props([
    'name' => 'pig_ids',
    'selected' => [],
    'multiple' => true,
    'departed' => false,
    'rooms' => collect(),
    'label' => 'Animals',
])

{{-- Search by code or by tag colour and number; never loads every pig. A room
     button adds everyone currently in that room, saved as a fixed list. --}}
<div x-data="{
        picked: @js(collect(old($name) ? \App\Services\Health\HealthPigFinder::rowsFor((array) old($name)) : $selected)->values()),
        q: '', results: [], busy: false, multiple: @js($multiple),
        async search() {
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
    <div class="relative" x-show="multiple || !picked.length">
        <input id="{{ $name }}-search" type="search" x-model="q" @input.debounce.300ms="search()" autocomplete="off"
               placeholder="Search by code (CFPG-2607-9) or tag (green 21)" class="w-full rounded-lg border-slate-300 text-sm">
        <div x-show="results.length" x-cloak class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg">
            <template x-for="pig in results" :key="pig.id">
                <button type="button" @click="add(pig)" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-emerald-50">
                    <span><span class="font-semibold" x-text="pig.code"></span> <span class="text-slate-500" x-text="pig.label"></span></span>
                    <span class="text-xs text-slate-400" x-text="(pig.left ? 'left the farm · ' : '') + (pig.room ?? '')"></span>
                </button>
            </template>
        </div>
        <p x-show="busy" class="mt-1 text-xs text-slate-400">Searching…</p>
        <p x-show="!busy && q.length && !results.length" x-cloak class="mt-1 text-xs text-slate-500">No animal matches.</p>
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
