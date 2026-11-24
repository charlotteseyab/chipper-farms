<x-app-layout>
    <x-feed-hub hub="mill" />
    <div class="max-w-7xl mx-auto py-6 px-4"
         x-data="{
            openModal: null,
            selectedFeedTypeId: '',
            recipeItems: [{ raw_material_id: '', quantity_per_100kg: '' }]
         }">

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Mix &amp; batches</h1>
            <p class="text-sm text-gray-500">Confirm planned mixes, record a batch made without a plan, and see every batch made.</p>
            <p class="mt-1 text-sm text-gray-500">Feed types and recipes are kept in <a href="{{ route('feed-setup.index') }}#step-recipes" class="font-semibold text-emerald-700 underline">Feed Setup</a>.</p>
        </div>

        <x-glance :cards="$glance" class="mb-6" />

        @if(session('mix_error'))
            <div role="alert" class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-5 shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-700">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-red-900">Batch not created</h2>
                        <p class="mt-1 text-sm font-medium leading-6 text-red-800">{{ session('mix_error') }}</p>
                        <p class="mt-1 text-xs text-red-700">No raw-material quantities were changed. Receive or adjust the required stock, then try again.</p>
                    </div>
                </div>
            </div>
        @endif

        @if($errors->any())<ul class="mb-4 rounded bg-red-50 p-4 text-red-700">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
        @if(session('success'))<p class="mb-4 rounded bg-green-50 p-4 text-green-800">{{ session('success') }}</p>@endif
    <section class="space-y-3 mb-8">
        <h2 class="text-xl font-bold">1. Plans to confirm</h2>
        <p class="text-sm text-slate-500">When a planned mix is made, enter what was actually used and confirm it. That takes the ingredients from stock and adds the finished feed.</p>
        @forelse($waitingPlans as $plan)
            @include('feeds.feed-production.partials.plan', ['open' => $loop->first])
        @empty
            <p class="rounded-xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">No plans waiting. Plans are made from <a href="{{ route('mill.plan') }}" class="font-semibold text-emerald-700 underline">Mill → Plan</a> when finished feed will run short.</p>
        @endforelse
        <x-pager :paginator="$waitingPlans" label="plans" />
        @if($earlierPlans->total() > 0)
            <details class="rounded-xl border bg-white p-3" @if(request()->has('earlier_plans_page')) open @endif><summary class="cursor-pointer text-sm font-semibold text-slate-600">Earlier plans ({{ $earlierPlans->total() }})</summary>
                <div class="mt-3 space-y-2">@foreach($earlierPlans as $plan)@include('feeds.feed-production.partials.plan', ['open' => false])@endforeach</div>
                <x-pager :paginator="$earlierPlans" label="plans" />
            </details>
        @endif
    </section>
        <section class="mb-8 max-w-2xl">
            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-xl font-bold text-gray-800">2. Mix without a plan</h2>
                    <p class="text-sm text-gray-500 mt-1">For a batch made without a plan. Confirming takes the recipe's ingredients from stock and adds the finished feed straight away.</p>
                </div>

                @if(auth()->user()->hasPermission('manage_feed_production'))
                <form action="{{ route('feed-production.mix') }}" method="POST" class="p-6 space-y-5">
                    <input type="hidden" name="submission_key" value="{{ old('submission_key', (string) \Illuminate\Support\Str::uuid()) }}">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Feed Type</label>
                        <select name="feed_type_id"
                                class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                                required>
                            <option value="">Select feed type</option>
                            @foreach($feedTypes as $feedType)
                                <option value="{{ $feedType->id }}" @selected((string) old('feed_type_id') === (string) $feedType->id)>{{ $feedType->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Batch Date</label>
                        <input type="date"
                               name="batch_date"
                               value="{{ old('batch_date', now()->toDateString()) }}"
                               class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                               required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Batch Size (kg)</label>
                        <input type="number"
                               step="0.01"
                               min="0.01"
                               name="batch_size_kg"
                               value="{{ old('batch_size_kg') }}"
                               placeholder="e.g. 100"
                               class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                               required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                        <textarea name="notes"
                                  rows="4"
                                  class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                                  placeholder="Optional notes for this batch">{{ old('notes') }}</textarea>
                    </div>

                    <button type="submit"
                            class="w-full inline-flex items-center justify-center rounded-xl bg-green-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-green-700 transition">
                        Confirm batch and update stock
                    </button>
                </form>
                @endif
            </div>
        </section>

        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-xl font-bold text-gray-800">3. Batches made</h2>
                <p class="text-sm text-gray-500 mt-1">Review mixed batches, feed type used, batch size, cost, and ingredients.</p>
            </div>

            <div class="overflow-x-auto overflow-y-auto max-h-[650px]">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Batch Date</th>
                            <th>Feed Type</th>
                            <th>Batch Size</th>
                            <th>Total Cost</th>
                            <th>Mixed By</th>
                            <th>Ingredients Used</th>
                            <th><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($feedBatches as $batch)
                            <tr class="hover:bg-gray-50 transition {{ $batch->isReversed() ? 'opacity-60' : '' }}">
                                <td class="text-sm text-gray-700">
                                    {{ optional($batch->batch_date)->format('M d, Y') }}
                                    @if($batch->isReversed())<span class="mt-1 block w-fit rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">Reversed</span>@endif
                                </td>

                                <td>
                                    <div class="font-semibold text-gray-800">{{ $batch->feedType->name ?? '—' }}</div>
                                    @if($batch->notes)
                                        <div class="text-xs text-gray-400 mt-1 line-clamp-1">{{ $batch->notes }}</div>
                                    @endif
                                </td>

                                <td class="text-sm font-semibold text-gray-800">
                                    {{ number_format($batch->batch_size_kg, 2) }} kg
                                    @if($batch->recipe_snapshot)<p class="text-xs text-slate-500">Planned {{ \App\Support\Qty::kg($batch->planned_quantity_kg) }} kg · Recipe {{ $batch->recipe_snapshot['version_id'] ?? 'legacy snapshot' }}</p><details><summary>Recipe used / expected ingredients</summary>@foreach($batch->recipe_snapshot['ingredients'] as $ingredient)<p>{{ $ingredient['name'] }}: {{ $ingredient['quantity_per_100kg'] }}% · expected {{ \App\Support\Qty::kg($batch->planned_quantity_kg * $ingredient['quantity_per_100kg']/100) }} kg</p>@endforeach</details>@else<p class="text-xs">Legacy batch — recorded actual ingredients retained.</p>@endif
                                </td>

                                <td class="text-sm font-semibold text-gray-800">
                                    {{ number_format($batch->total_cost, 2) }}
                                </td>

                                <td class="text-sm text-gray-700">
                                    {{ trim(($batch->mixer->first_name ?? '') . ' ' . ($batch->mixer->last_name ?? '')) ?: ($batch->mixer->name ?? 'System') }}
                                </td>

                                <td>
                                    <div class="space-y-1">
                                        @foreach($batch->items as $item)
                                            <div class="text-xs text-gray-600">
                                                <span class="font-medium text-gray-800">{{ $item->rawMaterial->name ?? '—' }}</span>
                                                — {{ number_format($item->quantity_used, 2) }} {{ $item->rawMaterial->unit ?? 'kg' }}
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="align-top">
                                    @if($batch->isReversed())
                                        <p class="max-w-[14rem] text-xs text-slate-500">{{ $batch->reversed_at->format('j M Y') }} by {{ $batch->reverser?->name ?? '—' }}: {{ $batch->reversal_reason }}</p>
                                    @elseif(auth()->user()->hasPermission('manage_feed_production'))
                                        <details class="text-sm">
                                            <summary class="cursor-pointer font-semibold text-red-700">Reverse</summary>
                                            <form method="POST" action="{{ route('feed-production.batches.reverse', $batch) }}" class="mt-2 w-56 space-y-2">@csrf
                                                <p class="text-xs text-slate-500">Puts the ingredients back and removes the finished feed. Only possible before any of it is fed or sold.</p>
                                                <input name="reason" required minlength="5" maxlength="2000" placeholder="Reason" class="block w-full rounded border-slate-300 text-sm">
                                                <button class="rounded bg-red-600 px-3 py-1.5 text-xs font-semibold text-white">Reverse batch</button>
                                            </form>
                                        </details>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-6m3 6V7m3 10v-4m4 6H5a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v12a2 2 0 01-2 2z" />
                                            </svg>
                                        </div>
                                        <h3 class="text-base font-semibold text-gray-700">No production batches found</h3>
                                        <p class="mt-1 text-sm text-gray-500">Once you mix feed, batch history will appear here.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4"><x-pager :paginator="$feedBatches" label="batches" /></div>

    </div>

    <style>
        [x-cloak] { display: none !important; }
    </style>

</x-app-layout>
