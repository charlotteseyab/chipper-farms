<x-app-layout>
    <div class="max-w-3xl mx-auto py-6 px-4">
        <div class="mb-6">
            <a href="{{ route('finance.feed.issues.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Back to feed issues</a>
            <h1 class="text-2xl font-bold text-slate-800 mt-2">Issue Feed to Group</h1>
            <p class="text-sm text-slate-500">Records feed leaving the mill. Cost is captured at current finished-feed WAC and capitalised into Live Pig Inventory for the chosen group.</p>
        </div>

        @if($errors->any())
            <div class="mb-4 px-4 py-3 rounded-xl bg-red-50 text-red-700 text-sm">
                <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
        @if(session('error'))<div class="mb-4 px-4 py-3 rounded-xl bg-red-50 text-red-700 text-sm">{{ session('error') }}</div>@endif

        @if($feedTypes->isEmpty())
            <div class="rounded-2xl border border-amber-200 bg-amber-50 text-amber-900 p-6 text-sm">
                <p class="font-semibold mb-2">No finished feed in stock.</p>
                <p>Produce a feed batch first (or import opening balances for finished feed) before issuing.</p>
            </div>
        @else
            <form method="POST" action="{{ route('finance.feed.issues.store') }}" class="space-y-4"
                  x-data="{
                      qty: '{{ old('quantity_kg', 0) }}',
                      feedTypeId: '{{ old('feed_type_id', '') }}',
                      feeds: @js($feedTypes->all()),
                      get selected() {
                          return this.feeds.find(f => String(f.id) === String(this.feedTypeId)) ?? null;
                      },
                      get totalCost() {
                          if (!this.selected) return 0;
                          return (parseFloat(this.qty) || 0) * (parseFloat(this.selected.wac) || 0);
                      },
                  }">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Group</label>
                        <select name="group_id" required class="w-full rounded-lg border-slate-200">
                            <option value="">— pick a group —</option>
                            @foreach($groups as $g)
                                <option value="{{ $g->id }}" @selected(old('group_id') == $g->id)>
                                    {{ $g->name }} ({{ $g->type }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Feed type</label>
                        <select name="feed_type_id" x-model="feedTypeId" required class="w-full rounded-lg border-slate-200">
                            <option value="">— pick feed —</option>
                            @foreach($feedTypes as $ft)
                                <option value="{{ $ft['id'] }}" @selected(old('feed_type_id') == $ft['id'])>
                                    {{ $ft['name'] }} (on hand: {{ number_format((float) $ft['on_hand'], 2) }} kg @ {{ number_format((float) $ft['wac'], 4) }}/kg)
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Quantity (kg)</label>
                        <input type="number" step="0.0001" min="0.0001" required name="quantity_kg" x-model="qty"
                               class="w-full rounded-lg border-slate-200 font-mono text-right">
                        <p class="mt-1 text-xs text-slate-500" x-show="selected">
                            On hand: <span class="font-mono" x-text="parseFloat(selected.on_hand).toFixed(4)"></span> kg
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Issued at</label>
                        <input type="datetime-local" name="issued_at"
                               value="{{ old('issued_at', now()->format('Y-m-d\TH:i')) }}"
                               max="{{ now()->format('Y-m-d\TH:i') }}"
                               class="w-full rounded-lg border-slate-200">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Reference (e.g. delivery note no.)</label>
                        <input type="text" name="reference" value="{{ old('reference') }}" maxlength="200"
                               class="w-full rounded-lg border-slate-200">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Notes</label>
                        <textarea name="notes" rows="2" maxlength="2000" class="w-full rounded-lg border-slate-200">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 flex items-center justify-between" x-show="selected">
                    <span class="text-sm text-emerald-800">Estimated total cost at current WAC:</span>
                    <span class="text-lg font-bold text-emerald-900 font-mono">
                        GH₵ <span x-text="totalCost.toFixed(2)"></span>
                    </span>
                </div>

                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">
                    Record issue
                </button>
            </form>
        @endif
    </div>
</x-app-layout>
