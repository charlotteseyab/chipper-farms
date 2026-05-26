<x-app-layout>
    <div class="max-w-2xl mx-auto py-6 px-4">
        <div class="mb-6">
            <a href="{{ route('finance.feed-sales.index') }}" class="text-sm text-blue-600 hover:underline">← Feed sales</a>
            <h1 class="text-2xl font-bold text-gray-800 mt-1">Record Feed Sale</h1>
        </div>

        @if($errors->any())
            <div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
        @if(session('error'))<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

        <form method="POST" action="{{ route('finance.feed-sales.store') }}" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
            @csrf
            <div>
                <label class="block text-xs text-slate-600 mb-1">Feed type</label>
                <select name="feed_type_id" required class="w-full rounded-xl border-slate-200 text-sm">
                    @foreach($feedTypes as $ft)<option value="{{ $ft->id }}">{{ $ft->name }}</option>@endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Quantity (kg)</label>
                    <input name="quantity_kg" type="number" step="0.0001" min="0.0001" value="{{ old('quantity_kg') }}" required class="w-full rounded-xl border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Unit price (GHS/kg)</label>
                    <input name="unit_price" type="number" step="0.0001" min="0" value="{{ old('unit_price') }}" required class="w-full rounded-xl border-slate-200 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Sale date</label>
                    <input name="sale_date" type="date" value="{{ old('sale_date') }}" class="w-full rounded-xl border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Payment status</label>
                    <select name="payment_status" class="w-full rounded-xl border-slate-200 text-sm">
                        <option value="paid">Paid (cash)</option>
                        <option value="pending">Pending (credit)</option>
                        <option value="partial">Partial</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Payment method (if paid)</label>
                    <input name="payment_method" value="{{ old('payment_method') }}" placeholder="cash / bank / mobile_money" class="w-full rounded-xl border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Buyer name</label>
                    <input name="buyer_name" value="{{ old('buyer_name') }}" class="w-full rounded-xl border-slate-200 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs text-slate-600 mb-1">Notes</label>
                <textarea name="notes" rows="2" class="w-full rounded-xl border-slate-200 text-sm">{{ old('notes') }}</textarea>
            </div>

            <div class="pt-2">
                <button class="px-5 py-2.5 bg-slate-800 text-white rounded-xl text-sm font-medium">Record sale</button>
                <p class="mt-2 text-xs text-slate-400">Revenue posts to feed sales revenue; COGS decrements finished-feed inventory at weighted-average cost.</p>
            </div>
        </form>
    </div>
</x-app-layout>
