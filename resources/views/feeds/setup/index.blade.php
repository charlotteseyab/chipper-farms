<x-app-layout>
    <x-feed-hub hub="setup" />
    @php
        $canManage = auth()->user()->hasPermission('manage_feed_production');
        $doneCount = collect($steps)->filter()->count();
        $kg = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
        $stepMeta = [
            'materials'  => 'Ingredients',
            'feed_types' => 'Feeds',
            'recipes'    => 'Recipes',
            'standards'  => 'Daily rations',
        ];
        $recipeMaterials = $materials->where('usable', true)->values();
    @endphp

    <div class="max-w-6xl mx-auto px-4 pt-6 pb-24 space-y-6"
         x-data="{
             recipeFor: null,
             recipeName: '',
             items: [],
             mode: 'percent',
             materials: @js($recipeMaterials->keyBy('id')),
             editType: null,
             openRecipe(id, name, ingredients) {
                 this.recipeFor = id;
                 this.recipeName = name;
                 this.items = ingredients.length
                     ? ingredients.map(i => ({ raw_material_id: String(i.raw_material_id), quantity_per_100kg: String(parseFloat(i.quantity_per_100kg)), quantity_kg: '' }))
                     : [{ raw_material_id: '', quantity_per_100kg: '', quantity_kg: '' }];
                 this.mode = 'percent';
             },
             setMode(mode) {
                 if (mode === this.mode) return;
                 // Carry the numbers across: a 1,000 kg batch when switching to kg.
                 if (mode === 'batch') {
                     this.items.forEach(i => { const p = parseFloat(i.quantity_per_100kg); i.quantity_kg = isNaN(p) ? '' : String(+(p * 10).toFixed(4)); });
                 } else {
                     const t = this.batchTotal;
                     this.items.forEach(i => { const k = parseFloat(i.quantity_kg); i.quantity_per_100kg = (isNaN(k) || t <= 0) ? '' : String(+(k / t * 100).toFixed(4)); });
                 }
                 this.mode = mode;
             },
             get batchTotal() { return this.items.reduce((sum, i) => sum + (parseFloat(i.quantity_kg) || 0), 0); },
             share(i) { const t = this.batchTotal; const k = parseFloat(i.quantity_kg); return (t > 0 && ! isNaN(k)) ? k / t * 100 : 0; },
             get batchReady() { return this.batchTotal > 0 && this.items.every(i => parseFloat(i.quantity_kg) > 0); },
             get total() { return this.items.reduce((sum, i) => sum + (parseFloat(i.quantity_per_100kg) || 0), 0); },
             get costPerKg() {
                 return this.items.reduce((sum, i) => sum + (this.mode === 'batch' ? this.share(i) : (parseFloat(i.quantity_per_100kg) || 0)) / 100 * (parseFloat(this.materials[i.raw_material_id]?.cost_per_kg) || 0), 0);
             },
         }">

        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Setup</h1>
                <p class="mt-1 text-sm text-slate-500">Done once, then only when something changes. Go from top to bottom: after step 4 the system works out feeding, mixing and buying by itself.</p>
            </div>
        </header>

        @if(session('success'))<p role="status" class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</p>@endif
        @if($errors->any())
            <ul role="alert" class="rounded-xl bg-red-50 p-4 text-sm text-red-800 list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        @endif

        {{-- Progress --}}
        <nav aria-label="Setup steps" class="flex flex-wrap items-center gap-2 rounded-2xl border border-slate-100 bg-white p-3 shadow-sm">
            <span class="mr-1 text-sm font-bold {{ $doneCount === count($steps) ? 'text-emerald-700' : 'text-slate-700' }}">{{ $doneCount === count($steps) ? 'All set up' : $doneCount.' of '.count($steps).' done' }}</span>
            @foreach($stepMeta as $key => $title)
                <a href="#step-{{ $key }}" class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-sm font-semibold hover:bg-slate-50 {{ $steps[$key] ? 'border-emerald-200 text-emerald-800' : 'border-amber-300 bg-amber-50 text-amber-900' }}">
                    <span class="flex h-5 w-5 items-center justify-center rounded-full text-[11px] {{ $steps[$key] ? 'bg-emerald-600 text-white' : 'bg-amber-200 text-amber-900' }}">@if($steps[$key])<i class="fa-solid fa-check" aria-hidden="true"></i><span class="sr-only">Done:</span>@else{{ $loop->iteration }}@endif</span>
                    {{ $title }}
                </a>
            @endforeach
        </nav>

        {{-- 1. Raw materials --}}
        <section id="step-materials" class="scroll-mt-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">1. Ingredients</h2>
                    <p class="text-sm text-slate-500">Everything that goes into your feeds. Ones bought in bags need their kg per bag.</p>
                </div>
                <a href="{{ route('raw-materials.index') }}" class="text-sm font-semibold text-emerald-700 underline">Add or change ingredients on Stock</a>
            </div>
            @php $unusable = $materials->where('usable', false); @endphp
            @if($unusable->isNotEmpty())
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    <p class="font-semibold">Can't be used in recipes yet</p>
                    <p class="mt-1">{{ $unusable->map(fn ($m) => $m['name'].' ('.$m['unit'].')')->implode(', ') }}. Edit each one on Stock and enter its kg per {{ $unusable->first()['unit'] }}.</p>
                </div>
            @endif
            <div class="mt-4 flex flex-wrap gap-2">
                @forelse($materials->where('usable', true) as $material)
                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-sm">
                        <span class="font-semibold text-slate-800">{{ $material['name'] }}</span>
                        <span class="text-xs {{ $material['stock_kg'] > 0 ? 'text-slate-500' : 'text-red-600' }}">{{ $kg($material['stock_kg']) }} kg</span>
                    </span>
                @empty
                    <p class="text-sm text-slate-500">No ingredients yet. <a href="{{ route('raw-materials.index') }}" class="font-semibold text-emerald-700 underline">Add them on Stock</a>.</p>
                @endforelse
            </div>
        </section>

        {{-- 2. Feed types --}}
        <section id="step-feed_types" class="scroll-mt-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">2. Feeds</h2>
            <p class="text-sm text-slate-500">Each feed you mix, for example Piglet Starter, Grower, Sow. Switch off feeds you no longer make.</p>

            <div class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-100">
                @forelse($feedTypes as $type)
                    <div class="p-3">
                        <div class="flex flex-wrap items-center justify-between gap-3" x-show="editType !== {{ $type['id'] }}">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold {{ $type['is_active'] ? 'text-slate-900' : 'text-slate-400 line-through' }}">{{ $type['name'] }}</span>
                                @unless($type['is_active'])<span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-500">Switched off</span>@endunless
                                @if($type['duplicate'])<span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Same name as another feed — rename one</span>@endif
                                @if($type['notes'])<span class="text-xs text-slate-500">{{ $type['notes'] }}</span>@endif
                                @if($type['wastage_percent'] !== null)<span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600" title="Wastage allowance for this feed; overrides the farm-wide figure">Wastage {{ rtrim(rtrim(number_format($type['wastage_percent'], 2), '0'), '.') }}%</span>@endif
                            </div>
                            @if($canManage)
                                <button type="button" @click="editType = {{ $type['id'] }}" class="text-sm font-semibold text-emerald-700 hover:underline">Edit</button>
                            @endif
                        </div>
                        @if($canManage)
                            <form method="POST" action="{{ route('feed-production.feed-types.update', $type['id']) }}" x-show="editType === {{ $type['id'] }}" x-cloak
                                  class="grid gap-3 sm:grid-cols-[1fr_1fr_8rem_10rem_auto] sm:items-end">
                                @csrf @method('PUT')
                                <label class="text-sm font-medium text-slate-700">Name
                                    <input name="name" value="{{ $type['name'] }}" required maxlength="255" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                                </label>
                                <label class="text-sm font-medium text-slate-700">Notes
                                    <input name="notes" value="{{ $type['notes'] }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                                </label>
                                <label class="text-sm font-medium text-slate-700">Wastage %
                                    <input type="number" name="wastage_percent" min="0" max="50" step="0.01" value="{{ $type['wastage_percent'] }}" placeholder="Farm" title="Leave blank to use the farm-wide allowance" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                                </label>
                                <label class="text-sm font-medium text-slate-700">Status
                                    <select name="is_active" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                                        <option value="1" @selected($type['is_active'])>In use</option>
                                        <option value="0" @selected(! $type['is_active'])>Switched off</option>
                                    </select>
                                </label>
                                <div class="flex gap-2">
                                    <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Save</button>
                                    <button type="button" @click="editType = null" class="rounded-lg border px-4 py-2 text-sm font-semibold text-slate-600">Cancel</button>
                                </div>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="p-4 text-sm text-slate-500">No feed types yet.</p>
                @endforelse
            </div>

            @if($canManage)
                <form method="POST" action="{{ route('feed-production.feed-types.store') }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_8rem_auto] sm:items-end">
                    @csrf
                    <label class="text-sm font-medium text-slate-700">New feed name
                        <input name="name" required maxlength="255" placeholder="e.g. Piglet Starter" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Notes (optional)
                        <input name="notes" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Wastage % (optional)
                        <input type="number" name="wastage_percent" min="0" max="50" step="0.01" placeholder="Farm" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Add feed type</button>
                </form>
                @php $farmWastage = \App\Models\Setting::get(\App\Services\Feed\FeedPlanningService::WASTAGE_SETTING); @endphp
                <p class="mt-2 text-xs text-slate-500">Wastage % is added to this feed's planned production and ingredient purchases. Leave it blank to use the farm-wide allowance{{ is_numeric($farmWastage) && $farmWastage > 0 ? ' ('.rtrim(rtrim(number_format((float) $farmWastage, 2), '0'), '.').'%)' : ' (none set)' }}, set under Advanced at the bottom of this page.</p>
            @endif
        </section>

        {{-- 3. Recipes --}}
        <section id="step-recipes" class="scroll-mt-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">3. Recipes</h2>
            <p class="text-sm text-slate-500">What goes into each feed, as a % of the mix (or typed from a mixing sheet in kg). It must add up to 100%.</p>

            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                @forelse($feedTypes->where('is_active', true) as $type)
                    <article class="rounded-xl border p-4 {{ $type['recipe_ok'] ? 'border-slate-100' : 'border-amber-200 bg-amber-50/40' }}">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h3 class="font-bold text-slate-900">{{ $type['name'] }}</h3>
                                @if($type['recipe_ok'])
                                    <p class="text-xs font-semibold text-emerald-700">Ready to mix @if($type['cost_per_kg'] > 0)· about GH₵ {{ number_format($type['cost_per_kg'], 2) }} per kg @endif</p>
                                @else
                                    <p class="text-xs font-semibold text-amber-800">{{ $type['recipe_error'] }}</p>
                                @endif
                            </div>
                            @if($canManage)
                                <button type="button" @click="openRecipe({{ $type['id'] }}, @js($type['name']), @js($type['ingredients']))"
                                        class="rounded-lg {{ $type['recipe_ok'] ? 'border border-slate-200 text-slate-700' : 'bg-emerald-700 text-white' }} px-3 py-1.5 text-sm font-semibold">
                                    {{ $type['ingredients'] === [] ? 'Create recipe' : ($type['recipe_ok'] ? 'Change recipe' : 'Fix recipe') }}
                                </button>
                            @endif
                        </div>
                        @if($type['ingredients'] !== [])
                            <ul class="mt-3 space-y-1 text-sm">
                                @foreach($type['ingredients'] as $ingredient)
                                    <li class="flex justify-between gap-3">
                                        <span class="text-slate-700">{{ $ingredient['name'] ?? '—' }}</span>
                                        <span class="font-mono text-slate-900">{{ $kg($ingredient['quantity_per_100kg']) }}%</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </article>
                @empty
                    <p class="text-sm text-slate-500">Add a feed type first.</p>
                @endforelse
            </div>
        </section>

        {{-- 4. Daily rations --}}
        @php
            [$withAnimals, $without] = $stages->partition(fn ($stage) => $stage['animals'] > 0 || ($stage['suckling'] ?? 0) > 0 || $stage['current']);
        @endphp
        <section id="step-standards" class="scroll-mt-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm" x-data="{ editing: null }">
            <h2 class="text-lg font-bold text-slate-900">4. Daily rations</h2>
            <p class="text-sm text-slate-500">Which feed each stage eats, and how many kg per animal per day. Animals are counted from the pig records.</p>
            @php $ages = \App\Services\Feed\FeedStageResolver::limits(auth()->user()->organization_id); @endphp
            <p class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">
                Growing pigs move stage by age: <strong>piglet</strong> up to {{ $ages['piglet'] }} days, <strong>weaner</strong> to {{ $ages['weaner'] }}, <strong>grower</strong> to {{ $ages['grower'] }}, then <strong>finisher</strong>.
                {{ $ages['farm_set'] ? 'These are your farm\'s growth-stage ages.' : 'These are the standard ages; your farm has not set its own.' }}
                <a href="{{ route('settings.setup') }}#growth-stages" class="font-semibold text-emerald-700 underline">Change them in Settings</a>.
                Sows are staged by their mating and farrowing records.
            </p>

            <div class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-100">
                @foreach($withAnimals as $stage)
                    @include('feeds.setup.partials.ration-row', ['stage' => $stage])
                @endforeach
            </div>

            @if($without->isNotEmpty())
                <details class="mt-3">
                    <summary class="cursor-pointer text-sm font-semibold text-slate-600">Stages with no animals now ({{ $without->pluck('label')->implode(', ') }})</summary>
                    <div class="mt-2 divide-y divide-slate-100 rounded-xl border border-slate-100">
                        @foreach($without as $stage)
                            @include('feeds.setup.partials.ration-row', ['stage' => $stage])
                        @endforeach
                    </div>
                </details>
            @endif
        </section>

        {{-- Advanced: history and settings --}}
        <details id="settings" class="scroll-mt-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm" @if(request()->has('settings') || $errors->hasAny(['wastage_percent', 'variance_percent', 'cover_days', 'cover_urgent_days', 'feeding_due_by', 'pig_id', 'reason'])) open @endif>
            <summary class="cursor-pointer">
                <span class="text-lg font-bold text-slate-900">Advanced</span>
                <span class="block text-sm text-slate-500">History and settings you rarely need: every ration and recipe version, moving an animal to a different stage by hand, the farm-wide wastage allowance and the alert thresholds.</span>
            </summary>
            <div class="mt-4 space-y-4">
                @include('feeds.planning-reports.partials.setup', $settings)
            </div>
        </details>

        {{-- Recipe builder --}}
        @if($canManage)
            <div x-show="recipeFor !== null" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-0 sm:items-center sm:p-4" @keydown.escape.window="recipeFor = null">
                <div class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl" @click.outside="recipeFor = null" role="dialog" aria-modal="true" aria-labelledby="recipe-title">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 id="recipe-title" class="text-lg font-bold text-slate-900">Recipe: <span x-text="recipeName"></span></h2>
                            <p class="text-sm text-slate-500" x-text="mode === 'batch' ? 'Kg of each ingredient in one batch, as on your mixing sheet. Saved as a percentage of the mix.' : 'Kg of each ingredient in 100 kg of feed, which is the same as its percentage.'"></p>
                        </div>
                        <button type="button" @click="recipeFor = null" class="text-slate-400 hover:text-slate-600" aria-label="Close"><i class="fa-solid fa-xmark text-xl"></i></button>
                    </div>

                    <form method="POST" action="{{ route('feed-production.recipes.store') }}" class="mt-4 space-y-4">
                        @csrf
                        <input type="hidden" name="feed_type_id" :value="recipeFor">
                        <input type="hidden" name="entry_mode" :value="mode">
                        <div class="inline-flex rounded-xl bg-slate-100 p-1 text-sm font-semibold" role="radiogroup" aria-label="How to enter the quantities">
                            <button type="button" role="radio" :aria-checked="mode === 'percent'" @click="setMode('percent')" :class="mode === 'percent' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'" class="rounded-lg px-3 py-1.5">% of the mix</button>
                            <button type="button" role="radio" :aria-checked="mode === 'batch'" @click="setMode('batch')" :class="mode === 'batch' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'" class="rounded-lg px-3 py-1.5">Kg in a batch</button>
                        </div>
                        <template x-for="(item, index) in items" :key="index">
                            <div class="grid grid-cols-[1fr_8rem_auto] items-center gap-2" :class="mode === 'batch' && 'sm:grid-cols-[1fr_8rem_5rem_auto]'">
                                <select :name="`items[${index}][raw_material_id]`" x-model="item.raw_material_id" required class="rounded-lg border-slate-300 text-sm">
                                    <option value="">Choose ingredient</option>
                                    @foreach($recipeMaterials as $material)
                                        <option value="{{ $material['id'] }}">{{ $material['name'] }}</option>
                                    @endforeach
                                </select>
                                <div class="relative" x-show="mode === 'percent'">
                                    <input type="number" step="0.0001" min="0.0001" max="100" :name="mode === 'percent' ? `items[${index}][quantity_per_100kg]` : null" x-model="item.quantity_per_100kg" :required="mode === 'percent'" :disabled="mode !== 'percent'"
                                           aria-label="Kg in 100 kg" class="w-full rounded-lg border-slate-300 pr-9 text-right font-mono text-sm">
                                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">%</span>
                                </div>
                                <div class="relative" x-show="mode === 'batch'" x-cloak>
                                    <input type="number" step="0.0001" min="0.0001" :name="mode === 'batch' ? `items[${index}][quantity_kg]` : null" x-model="item.quantity_kg" :required="mode === 'batch'" :disabled="mode !== 'batch'"
                                           aria-label="Kg in the batch" class="w-full rounded-lg border-slate-300 pr-9 text-right font-mono text-sm">
                                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">kg</span>
                                </div>
                                <span x-show="mode === 'batch'" x-cloak class="hidden text-right font-mono text-xs text-slate-500 sm:block" x-text="share(item).toFixed(2) + '%'"></span>
                                <button type="button" @click="items.splice(index, 1)" x-show="items.length > 1" class="px-2 text-slate-400 hover:text-red-600" aria-label="Remove ingredient"><i class="fa-solid fa-trash-can"></i></button>
                            </div>
                        </template>
                        <button type="button" @click="items.push({ raw_material_id: '', quantity_per_100kg: '', quantity_kg: '' })" class="text-sm font-semibold text-emerald-700 hover:underline">+ Add ingredient</button>

                        <div x-show="mode === 'batch'" x-cloak class="rounded-xl p-3 text-sm" :class="batchReady ? 'bg-emerald-50 text-emerald-900' : 'bg-amber-50 text-amber-900'">
                            <div class="flex justify-between font-semibold">
                                <span>Batch size</span>
                                <span class="font-mono"><span x-text="batchTotal.toFixed(2)"></span> kg</span>
                            </div>
                            <p class="mt-1 text-xs">The batch is the total of the quantities above. Each ingredient is saved as its share of it, so the recipe always comes to 100%.</p>
                            <p x-show="costPerKg > 0" class="mt-1 text-xs">Ingredient cost about GH₵ <span x-text="costPerKg.toFixed(2)"></span> per kg of feed.</p>
                        </div>

                        <div x-show="mode === 'percent'" class="rounded-xl p-3 text-sm" :class="Math.abs(total - 100) < 0.0001 ? 'bg-emerald-50 text-emerald-900' : 'bg-amber-50 text-amber-900'">
                            <div class="flex justify-between font-semibold">
                                <span>Total</span>
                                <span class="font-mono"><span x-text="total.toFixed(2)"></span> / 100%</span>
                            </div>
                            <p x-show="Math.abs(total - 100) >= 0.0001" class="mt-1 text-xs">
                                <span x-text="total < 100 ? (100 - total).toFixed(2) + '% short' : (total - 100).toFixed(2) + '% over'"></span> — the recipe can't be saved until it adds up to 100%.
                            </p>
                            <p x-show="costPerKg > 0" class="mt-1 text-xs">Ingredient cost about GH₵ <span x-text="costPerKg.toFixed(2)"></span> per kg of feed.</p>
                        </div>

                        <label class="block text-sm font-medium text-slate-700">Use this recipe from
                            <input type="date" name="effective_from" min="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" required class="mt-1 block rounded-lg border-slate-300 text-sm">
                        </label>
                        <p class="text-xs text-slate-500">Batches already mixed keep the recipe they were made with.</p>

                        <div class="flex justify-end gap-2">
                            <button type="button" @click="recipeFor = null" class="rounded-lg border px-4 py-2 text-sm font-semibold text-slate-600">Cancel</button>
                            <button :disabled="mode === 'batch' ? ! batchReady : Math.abs(total - 100) >= 0.0001" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">Save recipe</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
