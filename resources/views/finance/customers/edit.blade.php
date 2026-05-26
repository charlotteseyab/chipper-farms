<x-app-layout>
    <div class="max-w-4xl mx-auto py-6 px-4">
        <div class="mb-4">
            <a href="{{ route('finance.customers.show', $customer) }}" class="text-sm text-slate-500 hover:underline">← Back to Customer</a>
        </div>

        <h1 class="text-2xl font-bold text-gray-800 mb-2">Edit Customer</h1>
        <p class="text-sm text-slate-500 mb-6 font-mono">{{ $customer->code }}</p>
        <p class="text-xs text-amber-700 mb-4 italic">The customer code cannot be changed once assigned — historical records depend on it.</p>

        @if($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <ul class="list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('finance.customers.update', $customer) }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Identity</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                        <input type="text" name="name" required value="{{ old('name', $customer->name) }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Legal Name</label>
                        <input type="text" name="legal_name" value="{{ old('legal_name', $customer->legal_name) }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">TIN</label>
                        <input type="text" name="tax_id" value="{{ old('tax_id', $customer->tax_id) }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Reg No.</label>
                        <input type="text" name="registration_number" value="{{ old('registration_number', $customer->registration_number) }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Contact</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                        <input type="text" name="phone" value="{{ old('phone', $customer->phone) }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                        <input type="email" name="email" value="{{ old('email', $customer->email) }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Address Line 1</label>
                    <input type="text" name="address_line_1" value="{{ old('address_line_1', $customer->address_line_1) }}" class="w-full rounded-xl border-slate-200 text-sm">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">City</label>
                        <input type="text" name="city" value="{{ old('city', $customer->city) }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Region</label>
                        <input type="text" name="region" value="{{ old('region', $customer->region) }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Country</label>
                        <input type="text" name="country" value="{{ old('country', $customer->country) }}" maxlength="2" class="w-full rounded-xl border-slate-200 text-sm uppercase">
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
                                <option value="{{ $cat->id }}" @selected((int) old('customer_category_id', $customer->customer_category_id) === (int) $cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Currency Code</label>
                        <input type="text" name="currency_code" value="{{ old('currency_code', $customer->currency_code) }}" maxlength="3" class="w-full rounded-xl border-slate-200 text-sm uppercase">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Discount %</label>
                        <input type="number" name="discount_percent" step="0.01" min="0" max="100" value="{{ old('discount_percent', $customer->discount_percent) }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Payment Terms (days)</label>
                        <input type="number" name="payment_terms_days" min="0" max="365" value="{{ old('payment_terms_days', $customer->payment_terms_days) }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Credit Limit</label>
                        <input type="number" name="credit_limit" step="0.01" min="0" value="{{ old('credit_limit', $customer->credit_limit) }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                </div>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $customer->is_active)) class="rounded border-slate-300">
                    Active
                </label>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Notes</h2>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Customer-facing</label>
                    <textarea name="notes" rows="2" class="w-full rounded-xl border-slate-200 text-sm">{{ old('notes', $customer->notes) }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Internal</label>
                    <textarea name="internal_notes" rows="2" class="w-full rounded-xl border-slate-200 text-sm">{{ old('internal_notes', $customer->internal_notes) }}</textarea>
                </div>
            </div>

            <div class="flex gap-3 justify-end pt-2">
                <a href="{{ route('finance.customers.show', $customer) }}" class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">Save Changes</button>
            </div>
        </form>
    </div>
</x-app-layout>
