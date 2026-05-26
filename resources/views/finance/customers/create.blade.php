<x-app-layout>
    <div class="max-w-4xl mx-auto py-6 px-4">
        <div class="mb-4">
            <a href="{{ route('finance.customers.index') }}" class="text-sm text-slate-500 hover:underline">← Back to Customers</a>
        </div>

        <h1 class="text-2xl font-bold text-gray-800 mb-6">New Customer</h1>

        @if($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <ul class="list-disc pl-4">
                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('finance.customers.store') }}" class="space-y-4">
            @csrf

            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Identity</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" required value="{{ old('name') }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Legal Name (if different)</label>
                        <input type="text" name="legal_name" value="{{ old('legal_name') }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Code</label>
                        <input type="text" name="code" value="{{ old('code') }}" placeholder="Leave blank to auto-generate" class="w-full rounded-xl border-slate-200 text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
                        <select name="type" class="w-full rounded-xl border-slate-200 text-sm">
                            <option value="customer" @selected(old('type', 'customer') === 'customer')>Customer only</option>
                            <option value="both" @selected(old('type') === 'both')>Customer + Supplier</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">TIN / Tax ID</label>
                        <input type="text" name="tax_id" value="{{ old('tax_id') }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Business Registration Number</label>
                        <input type="text" name="registration_number" value="{{ old('registration_number') }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Contact</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Address Line 1</label>
                    <input type="text" name="address_line_1" value="{{ old('address_line_1') }}" class="w-full rounded-xl border-slate-200 text-sm">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">City</label>
                        <input type="text" name="city" value="{{ old('city') }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Region</label>
                        <input type="text" name="region" value="{{ old('region') }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Country</label>
                        <input type="text" name="country" value="{{ old('country', 'GH') }}" maxlength="2" class="w-full rounded-xl border-slate-200 text-sm uppercase">
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Finance Defaults</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                        <select name="customer_category_id" class="w-full rounded-xl border-slate-200 text-sm">
                            <option value="">— Uncategorised —</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" @selected((int) old('customer_category_id') === (int) $cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-slate-400 mt-1">Determines default credit terms, discount, and revenue posting account.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Currency Code</label>
                        <input type="text" name="currency_code" value="{{ old('currency_code', 'GHS') }}" maxlength="3" class="w-full rounded-xl border-slate-200 text-sm uppercase">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Discount % (override)</label>
                        <input type="number" name="discount_percent" step="0.01" min="0" max="100" value="{{ old('discount_percent') }}"
                               class="w-full rounded-xl border-slate-200 text-sm" placeholder="0">
                        <p class="text-xs text-slate-400 mt-1">Blank → use category default</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Payment Terms (days, override)</label>
                        <input type="number" name="payment_terms_days" min="0" max="365" value="{{ old('payment_terms_days') }}"
                               class="w-full rounded-xl border-slate-200 text-sm" placeholder="—">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Credit Limit (override)</label>
                        <input type="number" name="credit_limit" step="0.01" min="0" value="{{ old('credit_limit') }}"
                               class="w-full rounded-xl border-slate-200 text-sm" placeholder="—">
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Notes</h2>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Customer-facing Notes</label>
                    <textarea name="notes" rows="2" class="w-full rounded-xl border-slate-200 text-sm">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="flex gap-3 justify-end pt-2">
                <a href="{{ route('finance.customers.index') }}" class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">Create Customer</button>
            </div>
        </form>
    </div>
</x-app-layout>
