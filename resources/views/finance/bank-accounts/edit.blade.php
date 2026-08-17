<x-app-layout>
    <div class="max-w-3xl mx-auto py-6 px-4">
        <div class="mb-6">
            <a href="{{ route('finance.bank-accounts.show', $bankAccount) }}" class="text-xs text-slate-500 hover:underline"><i class="fa-solid fa-chevron-left mr-1"></i> {{ $bankAccount->displayName() }}</a>
            <h1 class="text-2xl font-bold text-gray-800 mt-1">Edit Money Account</h1>
            <p class="text-sm text-slate-500">GL account, opening balance, and currency are not editable here. To change those, deactivate and re-create.</p>
        </div>

        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                <ul class="list-disc pl-5">
                @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('finance.bank-accounts.update', $bankAccount) }}" class="space-y-4"
              x-data="{ kind: @js(old('account_kind', $bankAccount->account_kind ?: 'bank')) }">
            @csrf
            @method('PATCH')

            <div class="rounded-2xl bg-white shadow-sm border border-slate-100 p-5 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Account type</label>
                    <select name="account_kind" x-model="kind" required class="w-full rounded-lg border-slate-200 text-sm">
                        @foreach ($kinds as $key => $label)
                            <option value="{{ $key }}" @selected(old('account_kind', $bankAccount->account_kind ?: 'bank') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Label</label>
                        <input name="label" type="text" maxlength="120" required value="{{ old('label', $bankAccount->label) }}" class="w-full rounded-lg border-slate-200 text-sm">
                    </div>
                    <div x-show="kind === 'bank'">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Bank Name</label>
                        <input name="bank_name" type="text" maxlength="100" value="{{ old('bank_name', $bankAccount->bank_name) }}" class="w-full rounded-lg border-slate-200 text-sm">
                    </div>
                    <div x-show="kind === 'momo' || kind === 'gateway'">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Provider</label>
                        <input name="provider" type="text" maxlength="80" value="{{ old('provider', $bankAccount->provider) }}"
                               placeholder="MTN, Telecel, AirtelTigo, Paystack"
                               class="w-full rounded-lg border-slate-200 text-sm">
                    </div>
                    <div x-show="kind === 'bank' || kind === 'momo'">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Account / Wallet Name</label>
                        <input name="account_name" type="text" maxlength="120" value="{{ old('account_name', $bankAccount->account_name) }}" class="w-full rounded-lg border-slate-200 text-sm">
                    </div>
                    <div x-show="kind === 'bank' || kind === 'gateway'">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Account Number</label>
                        <input name="account_number" type="text" maxlength="64" value="{{ old('account_number', $bankAccount->account_number) }}" class="w-full rounded-lg border-slate-200 text-sm">
                    </div>
                    <div x-show="kind === 'momo'">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Wallet Number</label>
                        <input name="wallet_number" type="text" maxlength="40" value="{{ old('wallet_number', $bankAccount->wallet_number) }}" class="w-full rounded-lg border-slate-200 text-sm">
                    </div>
                    <div x-show="kind === 'bank'">
                        <label class="block text-sm font-medium text-slate-700 mb-1">SWIFT Code</label>
                        <input name="swift_code" type="text" maxlength="20" value="{{ old('swift_code', $bankAccount->swift_code) }}" class="w-full rounded-lg border-slate-200 text-sm">
                    </div>
                    <div x-show="kind === 'bank' || kind === 'cash'">
                        <label class="block text-sm font-medium text-slate-700 mb-1" x-text="kind === 'cash' ? 'Cash location / custodian' : 'Branch'"></label>
                        <input name="branch" type="text" maxlength="100" value="{{ old('branch', $bankAccount->branch) }}" class="w-full rounded-lg border-slate-200 text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Storefront default</label>
                    <select name="storefront_payment_method" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Not used by storefront checkout</option>
                        <option value="cod" @selected(old('storefront_payment_method', $bankAccount->storefront_payment_method) === 'cod')>Cash on delivery / pickup</option>
                        <option value="paystack" @selected(old('storefront_payment_method', $bankAccount->storefront_payment_method) === 'paystack')>Paystack online checkout</option>
                        <option value="momo" @selected(old('storefront_payment_method', $bankAccount->storefront_payment_method) === 'momo')>Mobile money checkout</option>
                        <option value="bank_transfer" @selected(old('storefront_payment_method', $bankAccount->storefront_payment_method) === 'bank_transfer')>Bank transfer checkout</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Default CSV Format</label>
                    <select name="default_csv_format" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">— Auto-detect —</option>
                        @foreach ($parsers as $code => $label)
                            <option value="{{ $code }}" {{ old('default_csv_format', $bankAccount->default_csv_format) === $code ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" class="w-full rounded-lg border-slate-200 text-sm">{{ old('notes', $bankAccount->notes) }}</textarea>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('finance.bank-accounts.show', $bankAccount) }}" class="px-4 py-2 text-sm rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg">Save Changes</button>
            </div>
        </form>
    </div>
</x-app-layout>
