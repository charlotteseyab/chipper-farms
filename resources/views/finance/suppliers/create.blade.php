<x-app-layout>
    <div class="max-w-4xl mx-auto py-6 px-4">
        <div class="mb-4">
            <a href="{{ route('finance.suppliers.index') }}" class="text-sm text-slate-500 hover:underline">← Back to Suppliers</a>
        </div>

        <h1 class="text-2xl font-bold text-gray-800 mb-2">New Supplier</h1>
        <p class="text-sm text-gray-500 mb-6">Adds a vendor to the master. Code is auto-generated if left blank.</p>

        @if($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <ul class="list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
        @if(session('error'))<div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>@endif

        <form method="POST" action="{{ route('finance.suppliers.store') }}" class="space-y-4">
            @csrf

            {{-- Identity --}}
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Identity</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" required value="{{ old('name') }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Legal Name</label>
                        <input type="text" name="legal_name" value="{{ old('legal_name') }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Code</label>
                        <input type="text" name="code" value="{{ old('code') }}" placeholder="Auto-generated (VEND-2026-0001)" class="w-full rounded-xl border-slate-200 text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
                        <select name="type" class="w-full rounded-xl border-slate-200 text-sm">
                            <option value="supplier" @selected(old('type', 'supplier') === 'supplier')>Supplier only</option>
                            <option value="both" @selected(old('type') === 'both')>Both supplier and customer</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Tax ID</label>
                        <input type="text" name="tax_id" value="{{ old('tax_id') }}" class="w-full rounded-xl border-slate-200 text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Registration Number</label>
                        <input type="text" name="registration_number" value="{{ old('registration_number') }}" class="w-full rounded-xl border-slate-200 text-sm font-mono">
                    </div>
                </div>
            </div>

            {{-- Contact + address --}}
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Contact &amp; Address</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Address Line 1</label>
                        <input type="text" name="address_line_1" value="{{ old('address_line_1') }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">City</label>
                        <input type="text" name="city" value="{{ old('city') }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Region</label>
                        <input type="text" name="region" value="{{ old('region') }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                </div>
            </div>

            {{-- Finance --}}
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Finance Defaults</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                        <select name="supplier_category_id" class="w-full rounded-xl border-slate-200 text-sm">
                            <option value="">— Uncategorised —</option>
                            @foreach($categories as $c)
                                <option value="{{ $c->id }}" @selected(old('supplier_category_id') == $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-slate-500 mt-1">Provides default GL coding and payment terms.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Currency</label>
                        <input type="text" name="currency_code" value="{{ old('currency_code', 'GHS') }}" maxlength="3" class="w-full rounded-xl border-slate-200 text-sm uppercase">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Payment Terms (days)</label>
                        <input type="number" name="payment_terms_days" value="{{ old('payment_terms_days') }}" placeholder="From category default" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Default Expense GL Account</label>
                        <select name="default_expense_account_id" class="w-full rounded-xl border-slate-200 text-sm">
                            <option value="">— Use category default —</option>
                            @foreach($expenseAccounts as $a)
                                <option value="{{ $a->id }}" @selected(old('default_expense_account_id') == $a->id)>{{ $a->code }} — {{ $a->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Default Payment Method (AP)</label>
                        <select name="default_payment_method_for_ap_id" class="w-full rounded-xl border-slate-200 text-sm">
                            <option value="">— No default —</option>
                            @foreach($paymentMethods as $pm)
                                <option value="{{ $pm->id }}" @selected(old('default_payment_method_for_ap_id') == $pm->id)>{{ $pm->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Withholding %</label>
                            <input type="number" step="0.01" min="0" max="100" name="withholding_percent" value="{{ old('withholding_percent') }}" placeholder="From category" class="w-full rounded-xl border-slate-200 text-sm text-right">
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 text-sm text-slate-700 mb-1">
                                <input type="checkbox" name="withholding_exempt" value="1" @checked(old('withholding_exempt')) class="rounded border-slate-300">
                                Exempt
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Banking --}}
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Banking &amp; Mobile Money</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <h3 class="text-xs text-slate-500 uppercase tracking-wide font-semibold mb-2">Bank Account</h3>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Account Name</label>
                        <input type="text" name="bank_account_name" value="{{ old('bank_account_name') }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Account Number</label>
                        <input type="text" name="bank_account_number" value="{{ old('bank_account_number') }}" class="w-full rounded-xl border-slate-200 text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Bank</label>
                        <input type="text" name="bank_name" value="{{ old('bank_name') }}" placeholder="GCB, Ecobank, Zenith..." class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Branch</label>
                        <input type="text" name="bank_branch" value="{{ old('bank_branch') }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">SWIFT / BIC</label>
                        <input type="text" name="bank_swift" value="{{ old('bank_swift') }}" class="w-full rounded-xl border-slate-200 text-sm font-mono">
                    </div>

                    <div class="sm:col-span-2 mt-2">
                        <h3 class="text-xs text-slate-500 uppercase tracking-wide font-semibold mb-2">Mobile Money</h3>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Provider</label>
                        <select name="momo_provider" class="w-full rounded-xl border-slate-200 text-sm">
                            <option value="">—</option>
                            @foreach($momoProviders as $mp)
                                <option value="{{ $mp }}" @selected(old('momo_provider') === $mp)>{{ $mp }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Number</label>
                        <input type="text" name="momo_number" value="{{ old('momo_number') }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">MoMo Account Name</label>
                        <input type="text" name="momo_account_name" value="{{ old('momo_account_name') }}" placeholder="If different from supplier name" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                </div>
            </div>

            {{-- Notes --}}
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Notes</h2>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                    <textarea name="notes" rows="2" class="w-full rounded-xl border-slate-200 text-sm">{{ old('notes') }}</textarea>
                    <p class="text-xs text-slate-500 mt-1">May appear on supplier-facing documents.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Internal Notes</label>
                    <textarea name="internal_notes" rows="2" class="w-full rounded-xl border-slate-200 text-sm">{{ old('internal_notes') }}</textarea>
                    <p class="text-xs text-slate-500 mt-1">Operations only — never shared.</p>
                </div>
            </div>

            <div class="flex gap-3 justify-end">
                <a href="{{ route('finance.suppliers.index') }}" class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">Create Supplier</button>
            </div>
        </form>
    </div>
</x-app-layout>
