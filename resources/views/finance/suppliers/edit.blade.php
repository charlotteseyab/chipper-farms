<x-app-layout>
    <div class="max-w-4xl mx-auto py-6 px-4">
        <div class="mb-4">
            <a href="{{ route('finance.suppliers.show', $supplier) }}" class="text-sm text-slate-500 hover:underline">← Back to {{ $supplier->name }}</a>
        </div>

        <h1 class="text-2xl font-bold text-gray-800 mb-2">Edit Supplier</h1>
        <p class="text-sm text-slate-500 mb-1 font-mono">{{ $supplier->code }}</p>
        <p class="text-xs text-amber-700 mb-4 italic">The supplier code cannot be changed — historical records depend on it.</p>

        @if($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <ul class="list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
        @if(session('error'))<div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>@endif

        <form method="POST" action="{{ route('finance.suppliers.update', $supplier) }}" class="space-y-4">
            @csrf
            @method('PATCH')

            {{-- Identity --}}
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Identity</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" required value="{{ old('name', $supplier->name) }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Legal Name</label>
                        <input type="text" name="legal_name" value="{{ old('legal_name', $supplier->legal_name) }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
                        <select name="type" class="w-full rounded-xl border-slate-200 text-sm">
                            <option value="supplier" @selected(old('type', $supplier->type) === 'supplier')>Supplier only</option>
                            <option value="both" @selected(old('type', $supplier->type) === 'both')>Both supplier and customer</option>
                            <option value="customer" @selected(old('type', $supplier->type) === 'customer')>Customer only (drops supplier role)</option>
                        </select>
                        @if($supplier->hasBeenBilled())
                            <p class="text-xs text-amber-700 mt-1 italic">Supplier has historical purchases — switching to customer-only is blocked.</p>
                        @endif
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Tax ID</label>
                        <input type="text" name="tax_id" value="{{ old('tax_id', $supplier->tax_id) }}" class="w-full rounded-xl border-slate-200 text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Registration Number</label>
                        <input type="text" name="registration_number" value="{{ old('registration_number', $supplier->registration_number) }}" class="w-full rounded-xl border-slate-200 text-sm font-mono">
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $supplier->is_active)) class="rounded border-slate-300">
                    Active (uncheck to deactivate without using the deactivate workflow)
                </label>
            </div>

            {{-- Contact --}}
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Contact</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                        <input type="text" name="phone" value="{{ old('phone', $supplier->phone) }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                        <input type="email" name="email" value="{{ old('email', $supplier->email) }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                </div>
            </div>

            {{-- Finance defaults --}}
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Finance Defaults</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                        <select name="supplier_category_id" class="w-full rounded-xl border-slate-200 text-sm">
                            <option value="">— Uncategorised —</option>
                            @foreach($categories as $c)
                                <option value="{{ $c->id }}" @selected(old('supplier_category_id', $supplier->supplier_category_id) == $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Currency</label>
                        <input type="text" name="currency_code" value="{{ old('currency_code', $supplier->currency_code) }}" maxlength="3" class="w-full rounded-xl border-slate-200 text-sm uppercase">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Payment Terms (days)</label>
                        <input type="number" name="payment_terms_days" value="{{ old('payment_terms_days', $supplier->payment_terms_days) }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Default Expense GL</label>
                        <select name="default_expense_account_id" class="w-full rounded-xl border-slate-200 text-sm">
                            <option value="">— Use category default —</option>
                            @foreach($expenseAccounts as $a)
                                <option value="{{ $a->id }}" @selected(old('default_expense_account_id', $supplier->default_expense_account_id) == $a->id)>{{ $a->code }} — {{ $a->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Default AP Method</label>
                        <select name="default_payment_method_for_ap_id" class="w-full rounded-xl border-slate-200 text-sm">
                            <option value="">— No default —</option>
                            @foreach($paymentMethods as $pm)
                                <option value="{{ $pm->id }}" @selected(old('default_payment_method_for_ap_id', $supplier->default_payment_method_for_ap_id) == $pm->id)>{{ $pm->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Withholding %</label>
                            <input type="number" step="0.01" min="0" max="100" name="withholding_percent" value="{{ old('withholding_percent', $supplier->withholding_percent) }}" class="w-full rounded-xl border-slate-200 text-sm text-right">
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 text-sm text-slate-700 mb-1">
                                <input type="checkbox" name="withholding_exempt" value="1" @checked(old('withholding_exempt', $supplier->withholding_exempt)) class="rounded border-slate-300">
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
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Account Name</label>
                        <input type="text" name="bank_account_name" value="{{ old('bank_account_name', $supplier->bank_account_name) }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Account Number</label>
                        <input type="text" name="bank_account_number" value="{{ old('bank_account_number', $supplier->bank_account_number) }}" class="w-full rounded-xl border-slate-200 text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Bank</label>
                        <input type="text" name="bank_name" value="{{ old('bank_name', $supplier->bank_name) }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Branch</label>
                        <input type="text" name="bank_branch" value="{{ old('bank_branch', $supplier->bank_branch) }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">SWIFT</label>
                        <input type="text" name="bank_swift" value="{{ old('bank_swift', $supplier->bank_swift) }}" class="w-full rounded-xl border-slate-200 text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">MoMo Provider</label>
                        <select name="momo_provider" class="w-full rounded-xl border-slate-200 text-sm">
                            <option value="">—</option>
                            @foreach($momoProviders as $mp)
                                <option value="{{ $mp }}" @selected(old('momo_provider', $supplier->momo_provider) === $mp)>{{ $mp }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">MoMo Number</label>
                        <input type="text" name="momo_number" value="{{ old('momo_number', $supplier->momo_number) }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">MoMo Account Name</label>
                        <input type="text" name="momo_account_name" value="{{ old('momo_account_name', $supplier->momo_account_name) }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                </div>
            </div>

            {{-- Notes --}}
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Notes</h2>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                    <textarea name="notes" rows="2" class="w-full rounded-xl border-slate-200 text-sm">{{ old('notes', $supplier->notes) }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Internal Notes</label>
                    <textarea name="internal_notes" rows="2" class="w-full rounded-xl border-slate-200 text-sm">{{ old('internal_notes', $supplier->internal_notes) }}</textarea>
                </div>
            </div>

            <div class="flex gap-3 justify-end">
                <a href="{{ route('finance.suppliers.show', $supplier) }}" class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">Save Changes</button>
            </div>
        </form>
    </div>
</x-app-layout>
