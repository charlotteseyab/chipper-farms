<x-app-layout>
    <x-feed-hub hub="today" />
    <div class="max-w-5xl mx-auto pt-6 pb-24 px-4">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-800">Today</h1>
            <p class="text-sm text-slate-500">{{ now()->format('l j F') }} · feed the animals. Fill in what each stage was given and save; the feed comes out of stock.</p>
        </div>

        @include('feeds.partials.needs', ['needs' => $needs])
        <div class="mb-6"></div>

        @if($errors->any())
            <div role="alert" class="mb-4 px-4 py-3 rounded-xl bg-red-50 text-red-700 text-sm">
                <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
        @if(session('error'))<div role="alert" class="mb-4 px-4 py-3 rounded-xl bg-red-50 text-red-700 text-sm">{{ session('error') }}</div>@endif

        @if($feedTypes->isEmpty())
            <div class="rounded-2xl border border-amber-200 bg-amber-50 text-amber-900 p-6 text-sm">
                <p class="font-semibold mb-2">No finished feed in stock.</p>
                <p>Mix a feed batch first (or import opening balances for finished feed) before recording a feeding.</p>
                @if(auth()->user()->hasPermission('view_feed_production'))
                    <a href="{{ route('feed-production.index') }}" class="mt-3 inline-block font-semibold underline">Go to feed production</a>
                @endif
            </div>
        @else
            @unless($demandComplete)
                <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <p class="font-semibold">Some expected amounts are missing</p>
                    <p class="mt-1">Some animals have no feeding standard or no known stage, so expected amounts may be low or blank. You can still record what was fed.</p>
                    @if(auth()->user()->hasPermission('view_feed_production'))
                        <a href="{{ route('feed-setup.index').'#step-standards' }}" class="mt-1 inline-block font-semibold underline">Set feeding standards</a>
                    @endif
                </div>
            @endunless

            @php
                $oldEntries = collect(old('entries', []))->keyBy('stage');
            @endphp

            <form id="feeding" method="POST" action="{{ route('feed.issues.store') }}" class="space-y-5"
                  x-data="{
                      feeds: @js($feedTypes->keyBy('id')->all()),
                      rows: @js($stages->map(fn ($s) => [
                          'stage' => $s['stage'],
                          'expected' => $s['expected_kg'],
                          'feed' => (string) ($oldEntries[$s['stage']]['feed_type_id'] ?? ($feedTypes->contains('id', $s['feed_type_id']) ? $s['feed_type_id'] : '')),
                          'qty' => (string) ($oldEntries[$s['stage']]['quantity_kg'] ?? ''),
                      ])->values()),
                      get totals() {
                          const byFeed = {};
                          for (const row of this.rows) {
                              const qty = parseFloat(row.qty) || 0;
                              if (!row.feed || qty <= 0) continue;
                              byFeed[row.feed] = (byFeed[row.feed] || 0) + qty;
                          }
                          return Object.entries(byFeed).map(([id, qty]) => ({
                              name: this.feeds[id]?.name ?? 'Feed',
                              qty,
                              onHand: parseFloat(this.feeds[id]?.on_hand) || 0,
                              cost: qty * (parseFloat(this.feeds[id]?.wac) || 0),
                          }));
                      },
                      get totalCost() { return this.totals.reduce((sum, t) => sum + t.cost, 0); },
                      get fillable() { return this.rows.filter(r => r.expected !== null && r.expected > 0 && r.feed).length; },
                      fillAll() { this.rows.forEach(r => { if (r.expected !== null && r.expected > 0 && r.feed) r.qty = String(r.expected); }); },
                      clearAll() { this.rows.forEach(r => r.qty = ''); },
                  }">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <div>
                        <label for="issued_at" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Fed at</label>
                        <input id="issued_at" type="datetime-local" name="issued_at"
                               value="{{ old('issued_at', now()->format('Y-m-d\TH:i')) }}"
                               max="{{ now()->format('Y-m-d\TH:i') }}"
                               class="w-full rounded-lg border-slate-200">
                    </div>
                    <div>
                        <label for="reference" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Reference (optional)</label>
                        <input id="reference" type="text" name="reference" value="{{ old('reference') }}" maxlength="200"
                               placeholder="e.g. Morning feeding"
                               class="w-full rounded-lg border-slate-200">
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3" x-show="fillable > 0">
                    <p class="text-sm text-emerald-900">Fed as the ration says? Fill every stage at once, then change only the ones fed differently.</p>
                    <div class="flex gap-2">
                        <button type="button" @click="fillAll()" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Fill all with today's ration</button>
                        <button type="button" @click="clearAll()" class="rounded-lg border border-emerald-200 bg-white px-3 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-100">Clear</button>
                    </div>
                </div>

                <div class="space-y-3">
                    @foreach($stages as $i => $stage)
                        {{-- Opened from a stage's Record button on Feed Today: that stage is marked and its kg field takes the cursor. --}}
                        @php $chosen = request('stage') === $stage['stage']; @endphp
                        <div id="stage-{{ $stage['stage'] }}" class="scroll-mt-24 rounded-2xl border bg-white p-4 shadow-sm transition {{ $chosen ? 'ring-2 ring-emerald-500' : '' }}"
                             :class="(parseFloat(rows[{{ $i }}].qty) || 0) > 0 ? 'border-emerald-300 ring-1 ring-emerald-100' : 'border-slate-100'">
                            <input type="hidden" name="entries[{{ $i }}][stage]" value="{{ $stage['stage'] }}">
                            <input type="hidden" name="entries[{{ $i }}][submission_key]" value="{{ $oldEntries[$stage['stage']]['submission_key'] ?? $stage['submission_key'] }}">

                            <div class="flex flex-col gap-4 md:flex-row md:items-center">
                                <div class="md:w-56 shrink-0">
                                    <p class="font-bold text-slate-900">{{ $stage['label'] }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $stage['animal_count'] }} {{ \Illuminate\Support\Str::plural('animal', $stage['animal_count']) }}
                                        @if($stage['expected_kg'] !== null)
                                            · expected <span class="font-semibold text-slate-700">{{ rtrim(rtrim(number_format($stage['expected_kg'], 2), '0'), '.') }} kg</span>/day
                                        @endif
                                    </p>
                                </div>

                                <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-[1fr_11rem]">
                                    <div>
                                        <label for="feed-{{ $i }}" class="sr-only">Feed for {{ $stage['label'] }}</label>
                                        <select id="feed-{{ $i }}" name="entries[{{ $i }}][feed_type_id]" x-model="rows[{{ $i }}].feed"
                                                class="w-full rounded-lg border-slate-200 text-sm">
                                            <option value="">— choose feed —</option>
                                            @foreach($feedTypes as $ft)
                                                <option value="{{ $ft['id'] }}">{{ $ft['name'] }} ({{ number_format((float) $ft['on_hand'], 2) }} kg in stock)</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="relative">
                                        <label for="qty-{{ $i }}" class="sr-only">Kg fed to {{ $stage['label'] }}</label>
                                        <input id="qty-{{ $i }}" type="number" step="0.0001" min="0.0001" inputmode="decimal" @if($chosen) autofocus @endif
                                               name="entries[{{ $i }}][quantity_kg]" x-model="rows[{{ $i }}].qty"
                                               placeholder="{{ $stage['expected_kg'] !== null ? rtrim(rtrim(number_format($stage['expected_kg'], 2), '0'), '.') : '0' }}"
                                               class="w-full rounded-lg border-slate-200 pr-10 text-right font-mono">
                                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">kg</span>
                                    </div>
                                    @if($rooms->isNotEmpty())
                                        @php
                                            [$here, $elsewhere] = $rooms->partition(fn ($room) => in_array($room['id'], $stage['room_ids'], true));
                                            $pickedRoom = (string) ($oldEntries[$stage['stage']]['room_id'] ?? '');
                                        @endphp
                                        <div class="sm:col-span-2">
                                            <label for="room-{{ $i }}" class="sr-only">Pen or room for {{ $stage['label'] }}</label>
                                            <select id="room-{{ $i }}" name="entries[{{ $i }}][room_id]" class="w-full rounded-lg border-slate-200 text-sm text-slate-600">
                                                <option value="">Whole stage (all pens)</option>
                                                @if($here->isNotEmpty())
                                                    <optgroup label="Where {{ strtolower($stage['label']) }}s are">
                                                        @foreach($here as $room)<option value="{{ $room['id'] }}" @selected($pickedRoom === (string) $room['id'])>{{ $room['name'] }}</option>@endforeach
                                                    </optgroup>
                                                @endif
                                                <optgroup label="{{ $here->isNotEmpty() ? 'Other pens' : 'Pens' }}">
                                                    @foreach($elsewhere as $room)<option value="{{ $room['id'] }}" @selected($pickedRoom === (string) $room['id'])>{{ $room['name'] }}</option>@endforeach
                                                </optgroup>
                                            </select>
                                        </div>
                                    @endif
                                </div>

                                @if($stage['expected_kg'] !== null)
                                    <button type="button" @click="rows[{{ $i }}].qty = String(rows[{{ $i }}].expected)"
                                            class="shrink-0 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                        Use expected
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div>
                    <label for="notes" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Notes (optional)</label>
                    <textarea id="notes" name="notes" rows="2" maxlength="2000" class="w-full rounded-lg border-slate-200">{{ old('notes') }}</textarea>
                </div>

                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4" x-show="totals.length" x-cloak>
                    <p class="text-xs font-semibold uppercase text-emerald-800">This feeding</p>
                    <template x-for="t in totals" :key="t.name">
                        <div class="mt-2 flex items-center justify-between text-sm">
                            <span class="text-emerald-900"><span x-text="t.name"></span>: <span class="font-mono" x-text="t.qty.toFixed(2)"></span> kg</span>
                            <span x-show="t.qty > t.onHand" class="font-semibold text-red-700">Only <span x-text="t.onHand.toFixed(2)"></span> kg in stock</span>
                        </div>
                    </template>
                    @if(auth()->user()->hasPermission('view_finance'))
                    <div class="mt-3 flex items-center justify-between border-t border-emerald-200 pt-3">
                        <span class="text-sm text-emerald-800">Estimated feed cost</span>
                        <span class="text-lg font-bold text-emerald-900 font-mono">GH₵ <span x-text="totalCost.toFixed(2)"></span></span>
                    </div>
                    @endif
                </div>

                <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">
                    Record feeding
                </button>
            </form>
        @endif

        <section class="mt-8 space-y-3" aria-labelledby="today-feedings">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <h2 id="today-feedings" class="text-lg font-bold text-slate-900">Recorded today</h2>
                <span class="flex flex-wrap gap-4 text-sm font-semibold">
                    <a href="{{ route('feed.issues.index') }}" class="text-emerald-700 hover:underline">All feedings and corrections</a>
                    @if(auth()->user()->hasPermission('view_feed_production'))<a href="{{ route('feed.planned-vs-actual') }}" class="text-emerald-700 hover:underline">Reports</a>@endif
                </span>
            </div>
            <div class="divide-y divide-slate-100 rounded-2xl border border-slate-100 bg-white shadow-sm">
                @forelse($todayIssues as $issue)
                    <a href="{{ route('feed.issues.show', $issue) }}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50 {{ $issue->isReversed() ? 'opacity-60' : '' }}">
                        <span class="min-w-0">
                            <span class="block font-semibold text-slate-900 {{ $issue->isReversed() ? 'line-through' : '' }}">{{ $issue->where_label }} · {{ $issue->feedType?->name }}</span>
                            <span class="block text-xs text-slate-500">{{ $issue->issued_at->format('H:i') }} · {{ $issue->issuedBy?->name ?? '—' }}@if($issue->isReversed()) · reversed @endif</span>
                        </span>
                        <span class="whitespace-nowrap font-mono text-sm font-semibold text-slate-900">{{ rtrim(rtrim(number_format((float) $issue->quantity_kg, 2), '0'), '.') }} kg</span>
                    </a>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-slate-500">Nothing recorded yet today.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
