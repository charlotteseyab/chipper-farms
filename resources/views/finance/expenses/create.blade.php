<x-app-layout>
    <main class="min-h-screen bg-slate-50/70 py-4">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

            <h1 class="text-2xl font-semibold text-slate-900">Record an expense</h1>
            <p class="mt-1 text-sm text-slate-600">
                What did the farm pay for, and where did the money come from?
            </p>

            @if ($errors->any())
                <div class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('finance.expenses.store') }}"
                  class="mt-5 space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                @csrf

                {{-- Minted per render so back-button-then-resubmit replays the
                     same token and the service returns the original expense. --}}
                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

                <div>
                    <label for="expense_date" class="block text-sm font-medium text-slate-700">Date</label>
                    <input type="date" id="expense_date" name="expense_date" required
                           value="{{ old('expense_date', now()->toDateString()) }}"
                           class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                </div>

                <div>
                    <label for="category_key" class="block text-sm font-medium text-slate-700">What was it for?</label>
                    <select id="category_key" name="category_key" required
                            class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        @foreach ($categories as $key => $category)
                            <option value="{{ $key }}" @selected(old('category_key') === $key)>
                                {{ $category['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-slate-700">Description</label>
                    <input type="text" id="description" name="description" required maxlength="500"
                           placeholder="e.g. ECG bill for June"
                           value="{{ old('description') }}"
                           class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                </div>

                <div>
                    <label for="amount" class="block text-sm font-medium text-slate-700">Amount (GHS)</label>
                    <input type="number" id="amount" name="amount" required step="0.01" min="0.01"
                           inputmode="decimal" value="{{ old('amount') }}"
                           class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                </div>

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

                <div>
                    <label for="payment_method_key" class="block text-sm font-medium text-slate-700">Paid from</label>
                    <select id="payment_method_key" name="payment_method_key"
                            class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        @foreach ($paymentAccounts as $key => $account)
                            <option value="{{ $key }}" @selected(old('payment_method_key', 'cash') === $key)>
                                {{ $account['label'] }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">
                        Bank and mobile money payments need their transaction reference below, so they
                        can be matched against the statement later.
                    </p>
                </div>

                <div>
                    <label for="external_reference" class="block text-sm font-medium text-slate-700">
                        Transaction reference
                    </label>
                    <input type="text" id="external_reference" name="external_reference" maxlength="120"
                           value="{{ old('external_reference') }}"
                           class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                </div>

                <div>
                    <label for="supplier_id" class="block text-sm font-medium text-slate-700">
                        Supplier <span class="font-normal text-slate-500">(required if unpaid)</span>
                    </label>
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

                <div>
                    <label for="notes" class="block text-sm font-medium text-slate-700">
                        Notes <span class="font-normal text-slate-500">(optional)</span>
                    </label>
                    <textarea id="notes" name="notes" rows="2" maxlength="2000"
                              class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">{{ old('notes') }}</textarea>
                </div>

                <div class="flex flex-col gap-2 pt-1 sm:flex-row-reverse">
                    <button type="submit"
                            class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-slate-800">
                        Save expense
                    </button>
                    <a href="{{ route('finance.expenses.index') }}"
                       class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-center text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </main>
</x-app-layout>
