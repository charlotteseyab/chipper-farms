<x-app-layout>
    <x-feed-hub hub="setup" />
    @php
        $canManage = auth()->user()->hasPermission('manage_feed_production');
        $doneCount = collect($steps)->filter()->count();
        $kg = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
        $stepMeta = [
            'materials'  => ['Raw materials', 'At least one ingredient that recipes can use.'],
            'feed_types' => ['Feed types', 'The feeds you mix, each with its own name.'],
            'recipes'    => ['Recipes', 'Every active feed adds up to exactly 100 kg.'],
            'standards'  => ['Daily rations', 'Kg per animal per day for every stage that has animals.'],
            'stock'      => ['Feed in stock', 'Each feed in use has been mixed and is ready to feed.'],
        ];
        $recipeMaterials = $materials->where('usable', true)->values();
    @endphp

    <div class="max-w-6xl mx-auto px-4 pt-6 pb-24 space-y-6"
         x-data="{
             recipeFor: null,
             recipeName: '',
             items: [],
             materials: @js($recipeMaterials->keyBy('id')),
             editType: null,
             openRecipe(id, name, ingredients) {
                 this.recipeFor = id;
                 this.recipeName = name;
                 this.items = ingredients.length
                     ? ingredients.map(i => ({ raw_material_id: String(i.raw_material_id), quantity_per_100kg: String(parseFloat(i.quantity_per_100kg)) }))
                     : [{ raw_material_id: '', quantity_per_100kg: '' }];
             },
             get total() { return this.items.reduce((sum, i) => sum + (parseFloat(i.quantity_per_100kg) || 0), 0); },
             get costPerKg() {
                 return this.items.reduce((sum, i) => sum + (parseFloat(i.quantity_per_100kg) || 0) / 100 * (parseFloat(this.materials[i.raw_material_id]?.cost_per_kg) || 0), 0);
             },
         }">

        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Feed Setup</h1>
                <p class="mt-1 text-sm text-slate-500">Everything feed needs before mixing, feeding and planning can work. Finish each step from top to bottom.</p>
            </div>
        </header>

        @if(session('success'))<p role="status" class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</p>@endif
        @if($errors->any())
            <ul role="alert" class="rounded-xl bg-red-50 p-4 text-sm text-red-800 list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        @endif

        {{-- Progress --}}
        <section class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-bold text-slate-900">{{ $doneCount === count($steps) ? 'Feed is fully set up' : $doneCount.' of '.count($steps).' steps done' }}</h2>
                <span class="text-sm font-semibold {{ $doneCount === count($steps) ? 'text-emerald-700' : 'text-slate-500' }}">{{ (int) round($doneCount / count($steps) * 100) }}%</span>
            </div>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ $doneCount / count($steps) * 100 }}%"></div>
            </div>
            <ol class="mt-5 grid gap-3 sm:grid-cols-5">
                @foreach($stepMeta as $key => [$title, $hint])
                    <li>
                        <a href="#step-{{ $key }}" class="flex h-full gap-3 rounded-xl border p-3 hover:bg-slate-50 {{ $steps[$key] ? 'border-emerald-200' : 'border-amber-200' }}">
                            <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $steps[$key] ? 'bg-emerald-600 text-white' : 'bg-amber-100 text-amber-800' }}">
                                @if($steps[$key])<i class="fa-solid fa-check" aria-hidden="true"></i><span class="sr-only">Done</span> @else {{ $loop->iteration }} @endif
                            </span>
                            <span>
                                <span class="block text-sm font-bold text-slate-900">{{ $title }}</span>
                                <span class="block text-xs text-slate-500">{{ $hint }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- 1. Raw materials --}}
        <section id="step-materials" class="scroll-mt-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">1. Raw materials</h2>
                    <p class="text-sm text-slate-500">Recipes are written in kg. Materials stocked in bags need their kg per bag.</p>
                </div>
                <a href="{{ route('raw-materials.index') }}" class="text-sm font-semibold text-emerald-700 underline">Manage raw materials</a>
            </div>
            @php $unusable = $materials->where('usable', false); @endphp
            @if($unusable->isNotEmpty())
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    <p class="font-semibold">Can't be used in recipes yet</p>
                    <p class="mt-1">{{ $unusable->map(fn ($m) => $m['name'].' ('.$m['unit'].')')->implode(', ') }}. Edit each one on Raw Materials and enter its kg per {{ $unusable->first()['unit'] }}.</p>
                </div>
            @endif
            <div class="mt-4 flex flex-wrap gap-2">
                @forelse($materials->where('usable', true) as $material)
                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-sm">
                        <span class="font-semibold text-slate-800">{{ $material['name'] }}</span>
                        <span class="text-xs {{ $material['stock_kg'] > 0 ? 'text-slate-500' : 'text-red-600' }}">{{ $kg($material['stock_kg']) }} kg</span>
                    </span>
                @empty
                    <p class="text-sm text-slate-500">No usable raw materials yet.</p>
                @endforelse
            </div>
        </section>

        {{-- 2. Feed types --}}
        <section id="step-feed_types" class="scroll-mt-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">2. Feed types</h2>
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
                            </div>
                            @if($canManage)
                                <button type="button" @click="editType = {{ $type['id'] }}" class="text-sm font-semibold text-emerald-700 hover:underline">Edit</button>
                            @endif
                        </div>
                        @if($canManage)
                            <form method="POST" action="{{ route('feed-production.feed-types.update', $type['id']) }}" x-show="editType === {{ $type['id'] }}" x-cloak
                                  class="grid gap-3 sm:grid-cols-[1fr_1fr_10rem_auto] sm:items-end">
                                @csrf @method('PUT')
                                <label class="text-sm font-medium text-slate-700">Name
                                    <input name="name" value="{{ $type['name'] }}" required maxlength="255" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                                </label>
                                <label class="text-sm font-medium text-slate-700">Notes
                                    <input name="notes" value="{{ $type['notes'] }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
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
                <form method="POST" action="{{ route('feed-production.feed-types.store') }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                    @csrf
                    <label class="text-sm font-medium text-slate-700">New feed name
                        <input name="name" required maxlength="255" placeholder="e.g. Piglet Starter" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Notes (optional)
                        <input name="notes" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Add feed type</button>
                </form>
            @endif
        </section>

        {{-- 3. Recipes --}}
        <section id="step-recipes" class="scroll-mt-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">3. Recipes</h2>
            <p class="text-sm text-slate-500">Kg of each ingredient in 100 kg of finished feed. The total must be exactly 100 kg.</p>

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
                                        <span class="font-mono text-slate-900">{{ $kg($ingredient['quantity_per_100kg']) }} kg</span>
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
        <section id="step-standards" class="scroll-mt-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">4. Daily rations</h2>
            <p class="text-sm text-slate-500">Which feed each stage eats and how much per animal per day. A change applies from the date you pick; earlier days keep the old ration.</p>

            <div class="mt-4 space-y-3">
                @foreach($stages as $stage)
                    <div class="rounded-xl border p-4 {{ $stage['needs_standard'] ? 'border-amber-200 bg-amber-50/40' : 'border-slate-100' }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-bold text-slate-900">{{ $stage['label'] }} <span class="text-sm font-normal text-slate-500">· {{ $stage['animals'] }} {{ \Illuminate\Support\Str::plural('animal', $stage['animals']) }}</span></p>
                                @if($stage['current'])
                                    <p class="text-sm text-slate-600">{{ $stage['current']->feedType?->name }} · {{ $kg($stage['current']->daily_kg) }} kg per animal per day
                                        @if($stage['animals'] > 0)<span class="text-slate-500">({{ $kg($stage['daily_kg']) }} kg a day in total)</span>@endif
                                    </p>
                                @elseif($stage['needs_standard'])
                                    <p class="text-sm font-semibold text-amber-800">No ration set. Expected amounts for this stage will be blank.</p>
                                @else
                                    <p class="text-sm text-slate-500">No ration set.</p>
                                @endif
                                @if($stage['scheduled'])
                                    <p class="text-xs text-slate-500">From {{ $stage['scheduled']->effective_from->format('j M Y') }}: {{ $stage['scheduled']->feedType?->name }} · {{ $kg($stage['scheduled']->daily_kg) }} kg</p>
                                @endif
                            </div>
                        </div>
                        @if($canManage)
                            <form method="POST" action="{{ route('planning-reports.standards') }}" class="mt-3 grid gap-3 sm:grid-cols-[1fr_10rem_11rem_auto] sm:items-end">
                                @csrf
                                <input type="hidden" name="stage" value="{{ $stage['stage'] }}">
                                <input type="hidden" name="is_active" value="1">
                                <label class="text-xs font-semibold uppercase text-slate-500">Feed
                                    <select name="feed_type_id" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm normal-case font-normal text-slate-800">
                                        <option value="">Choose feed</option>
                                        @foreach($feedTypes->where('is_active', true) as $type)
                                            <option value="{{ $type['id'] }}" @selected((int) $stage['current']?->feed_type_id === (int) $type['id'])>{{ $type['name'] }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="text-xs font-semibold uppercase text-slate-500">Kg / animal / day
                                    <input name="daily_kg" type="number" min="0.0001" step="0.0001" required value="{{ $stage['current'] ? (float) $stage['current']->daily_kg : '' }}"
                                           class="mt-1 block w-full rounded-lg border-slate-300 text-sm font-normal text-slate-800">
                                </label>
                                <label class="text-xs font-semibold uppercase text-slate-500">Starting
                                    <input name="effective_from" type="date" min="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" required
                                           class="mt-1 block w-full rounded-lg border-slate-300 text-sm font-normal text-slate-800">
                                </label>
                                <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">{{ $stage['current'] ? 'Change' : 'Set ration' }}</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        {{-- 5. Feed in stock --}}
        <section id="step-stock" class="scroll-mt-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">5. Feed in stock</h2>
                    <p class="text-sm text-slate-500">Finished feed ready to give. Mix a batch to add stock.</p>
                </div>
                <a href="{{ route('feed-production.index') }}" class="text-sm font-semibold text-emerald-700 underline">Mix feed</a>
            </div>
            @php $feedsInUse = $stages->pluck('current')->filter()->pluck('feed_type_id')->unique(); @endphp
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($feedTypes->where('is_active', true) as $type)
                    <div class="rounded-xl border p-4 {{ $feedsInUse->contains($type['id']) && $type['stock_kg'] <= 0 ? 'border-red-200 bg-red-50/40' : 'border-slate-100' }}">
                        <p class="text-sm font-semibold text-slate-700">{{ $type['name'] }}</p>
                        <p class="mt-1 text-2xl font-bold {{ $type['stock_kg'] > 0 ? 'text-slate-900' : 'text-red-600' }}">{{ $kg($type['stock_kg']) }} <span class="text-sm font-semibold text-slate-500">kg</span></p>
                        @if($feedsInUse->contains($type['id']) && $type['stock_kg'] <= 0)<p class="text-xs font-semibold text-red-700">A stage eats this feed but there is none in stock.</p>@endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No feed types yet.</p>
                @endforelse
            </div>
        </section>

        {{-- Recipe builder --}}
        @if($canManage)
            <div x-show="recipeFor !== null" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-0 sm:items-center sm:p-4" @keydown.escape.window="recipeFor = null">
                <div class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl" @click.outside="recipeFor = null" role="dialog" aria-modal="true" aria-labelledby="recipe-title">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 id="recipe-title" class="text-lg font-bold text-slate-900">Recipe: <span x-text="recipeName"></span></h2>
                            <p class="text-sm text-slate-500">Kg of each ingredient in 100 kg of feed.</p>
                        </div>
                        <button type="button" @click="recipeFor = null" class="text-slate-400 hover:text-slate-600" aria-label="Close"><i class="fa-solid fa-xmark text-xl"></i></button>
                    </div>

                    <form method="POST" action="{{ route('feed-production.recipes.store') }}" class="mt-4 space-y-4">
                        @csrf
                        <input type="hidden" name="feed_type_id" :value="recipeFor">
                        <template x-for="(item, index) in items" :key="index">
                            <div class="grid grid-cols-[1fr_8rem_auto] items-center gap-2">
                                <select :name="`items[${index}][raw_material_id]`" x-model="item.raw_material_id" required class="rounded-lg border-slate-300 text-sm">
                                    <option value="">Choose ingredient</option>
                                    @foreach($recipeMaterials as $material)
                                        <option value="{{ $material['id'] }}">{{ $material['name'] }}</option>
                                    @endforeach
                                </select>
                                <div class="relative">
                                    <input type="number" step="0.0001" min="0.0001" max="100" :name="`items[${index}][quantity_per_100kg]`" x-model="item.quantity_per_100kg" required
                                           class="w-full rounded-lg border-slate-300 pr-9 text-right font-mono text-sm">
                                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">kg</span>
                                </div>
                                <button type="button" @click="items.splice(index, 1)" x-show="items.length > 1" class="px-2 text-slate-400 hover:text-red-600" aria-label="Remove ingredient"><i class="fa-solid fa-trash-can"></i></button>
                            </div>
                        </template>
                        <button type="button" @click="items.push({ raw_material_id: '', quantity_per_100kg: '' })" class="text-sm font-semibold text-emerald-700 hover:underline">+ Add ingredient</button>

                        <div class="rounded-xl p-3 text-sm" :class="Math.abs(total - 100) < 0.0001 ? 'bg-emerald-50 text-emerald-900' : 'bg-amber-50 text-amber-900'">
                            <div class="flex justify-between font-semibold">
                                <span>Total</span>
                                <span class="font-mono"><span x-text="total.toFixed(2)"></span> / 100 kg</span>
                            </div>
                            <p x-show="Math.abs(total - 100) >= 0.0001" class="mt-1 text-xs">
                                <span x-text="total < 100 ? (100 - total).toFixed(2) + ' kg short' : (total - 100).toFixed(2) + ' kg over'"></span> — the recipe can't be saved until it adds up to 100 kg.
                            </p>
                            <p x-show="costPerKg > 0" class="mt-1 text-xs">Ingredient cost about GH₵ <span x-text="costPerKg.toFixed(2)"></span> per kg of feed.</p>
                        </div>

                        <label class="block text-sm font-medium text-slate-700">Use this recipe from
                            <input type="date" name="effective_from" min="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" required class="mt-1 block rounded-lg border-slate-300 text-sm">
                        </label>
                        <p class="text-xs text-slate-500">Batches already mixed keep the recipe they were made with.</p>

                        <div class="flex justify-end gap-2">
                            <button type="button" @click="recipeFor = null" class="rounded-lg border px-4 py-2 text-sm font-semibold text-slate-600">Cancel</button>
                            <button :disabled="Math.abs(total - 100) >= 0.0001" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">Save recipe</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
