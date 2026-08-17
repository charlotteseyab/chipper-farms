<x-app-layout>
    <main class="min-h-screen bg-slate-50/70 py-4">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

            <h1 class="text-2xl font-semibold text-slate-900">Record a purchase</h1>
            <p class="mt-1 text-sm text-slate-600">
                What did you buy, from whom, and has it been paid for?
            </p>

            @if ($errors->any())
                <div class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('finance.purchases.store') }}"
                  x-data="{ lines: [{ kind: 'stock' }] }"
                  class="mt-5 space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="purchase_date" class="block text-sm font-medium text-slate-700">Date</label>
                        <input type="date" id="purchase_date" name="purchase_date" required
                               value="{{ old('purchase_date', now()->toDateString()) }}"
                               class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    </div>
                    <div>
                        <label for="supplier_id" class="block text-sm font-medium text-slate-700">Supplier</label>
                        <select id="supplier_id" name="supplier_id"
                                class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            <option value="">Not recorded</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((int) old('supplier_id') === $supplier->id)>
                                    {{ $supplier->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Lines. Progressive disclosure: one line to start, add more as
                     needed, rather than a ten-row grid on a phone. --}}
                <fieldset class="space-y-4">
                    <legend class="text-sm font-medium text-slate-700">What did you buy?</legend>

                    <template x-for="(line, i) in lines" :key="i">
                        <div class="space-y-3 rounded-2xl border border-slate-200 p-4">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium uppercase tracking-wide text-slate-500"
                                      x-text="'Item ' + (i + 1)"></span>
                                <button type="button" x-show="lines.length > 1" @click="lines.splice(i, 1)"
                                        class="text-xs text-rose-600 hover:text-rose-700">Remove</button>
                            </div>

                            <div>
                                <label class="block text-sm text-slate-700">Type</label>
                                <select :name="`lines[${i}][line_kind]`" x-model="line.kind"
                                        class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm">
                                    <option value="stock">Goods for stock (feed, medicine, supplies)</option>
                                    <option value="expense">A cost, not stock (delivery, service, fee)</option>
                                </select>
                            </div>

                            <div x-show="line.kind === 'stock'">
                                <label class="block text-sm text-slate-700">Item</label>
                                <select :name="`lines[${i}][raw_material_id]`"
                                        class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm">
                                    <option value="">Choose an item…</option>
                                    @foreach ($materials as $material)
                                        <option value="{{ $material->id }}">
                                            {{ $material->name }}@if ($material->unit) ({{ $material->unit }})@endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div x-show="line.kind === 'expense'">
                                <label class="block text-sm text-slate-700">Category</label>
                                <select :name="`lines[${i}][category_key]`"
                                        class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm">
                                    @foreach ($categories as $key => $category)
                                        <option value="{{ $key }}">{{ $category['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm text-slate-700">Description</label>
                                <input type="text" :name="`lines[${i}][description]`" required maxlength="500"
                                       class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm">
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm text-slate-700">Quantity</label>
                                    <input type="number" :name="`lines[${i}][quantity]`" required
                                           step="0.0001" min="0.0001" inputmode="decimal" value="1"
                                           class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-sm text-slate-700">Unit cost (GHS)</label>
                                    <input type="number" :name="`lines[${i}][unit_cost]`" required
                                           step="0.0001" min="0" inputmode="decimal"
                                           class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm">
                                </div>
                            </div>
                        </div>
                    </template>

                    <button type="button" @click="lines.push({ kind: 'stock' })"
                            class="w-full rounded-xl border border-dashed border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                        Add another item
                    </button>
                </fieldset>

                <fieldset>
                    <legend class="text-sm font-medium text-slate-700">Has it been paid?</legend>
                    <div class="mt-2 space-y-2">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="radio" name="settlement" value="paid_now" required
                                   @checked(old('settlement', 'paid_now') === 'paid_now')
                                   class="border-slate-300 text-slate-900 focus:ring-slate-500">
                            Yes, paid now
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="radio" name="settlement" value="pay_later"
                                   @checked(old('settlement') === 'pay_later')
                                   class="border-slate-300 text-slate-900 focus:ring-slate-500">
                            No, we owe the supplier
                        </label>
                    </div>
                </fieldset>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="money_account_id" class="block text-sm font-medium text-slate-700">Paid from</label>
                        <select id="money_account_id" name="money_account_id"
                                class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm">
                            <option value="">Choose a money account...</option>
                            @foreach ($moneyAccounts as $account)
                                <option value="{{ $account->id }}" @selected((int) old('money_account_id') === $account->id)>
                                    {{ $account->displayName() }} · {{ $account->account?->code }}
                                </option>
                            @endforeach
                        </select>
                        @if ($moneyAccounts->isEmpty())
                            <p class="mt-1 text-xs text-slate-500">Set up a money account under Cash, Bank & MoMo before recording paid-now purchases.</p>
                        @endif
                    </div>
                    <div>
                        <label for="supplier_invoice_reference" class="block text-sm font-medium text-slate-700">
                            Supplier invoice no.
                        </label>
                        <input type="text" id="supplier_invoice_reference" name="supplier_invoice_reference"
                               maxlength="120" value="{{ old('supplier_invoice_reference') }}"
                               class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm">
                    </div>
                </div>

                <div>
                    <label for="notes" class="block text-sm font-medium text-slate-700">
                        Notes <span class="font-normal text-slate-500">(optional)</span>
                    </label>
                    <textarea id="notes" name="notes" rows="2" maxlength="2000"
                              class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm">{{ old('notes') }}</textarea>
                </div>

                <div class="flex flex-col gap-2 pt-1 sm:flex-row-reverse">
                    <button type="submit"
                            class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-slate-800">
                        Save purchase
                    </button>
                    <a href="{{ route('finance.purchases.index') }}"
                       class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-center text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </main>
</x-app-layout>
