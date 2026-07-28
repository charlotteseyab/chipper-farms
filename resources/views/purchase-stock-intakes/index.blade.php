<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Purchased Stock Intake</h1>
                <p class="text-sm text-slate-500 mt-1">External stock enters here before it affects active stock counts.</p>
            </div>
            <a href="{{ route('purchase-stock-intakes.create') }}" class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold" style="background:#14532d;color:#ffffff;border:1px solid #14532d;box-shadow:0 8px 18px rgba(20,83,45,.22);">
                Add Purchased Stock
            </a>
        </div>

        <div data-ajax-table>
            <form method="GET" action="{{ route('purchase-stock-intakes.index') }}" data-ajax-table-form class="bg-white border border-slate-200 rounded-xl p-4 mb-5 grid gap-3 md:grid-cols-4">
                <input name="q" value="{{ request('q') }}" class="rounded-lg border-slate-300 text-sm" placeholder="Batch, supplier, reference">
                <select name="status" class="rounded-lg border-slate-300 text-sm">
                    <option value="">All statuses</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="animal_category" class="rounded-lg border-slate-300 text-sm">
                    <option value="">All categories</option>
                    @foreach($categories as $value => $label)
                        <option value="{{ $value }}" @selected(request('animal_category') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="flex gap-2">
                    <button class="rounded-lg px-4 py-2 text-sm font-semibold" style="background:#0f172a;color:#ffffff;border:1px solid #0f172a;">Filter</button>
                    <a href="{{ route('purchase-stock-intakes.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Clear</a>
                </div>
            </form>

            <div data-ajax-table-loading hidden class="mb-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-600">
                Updating table...
            </div>

            <div data-ajax-table-error hidden class="mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700"></div>

            <div data-ajax-table-target>
                @include('purchase-stock-intakes._table', ['intakes' => $intakes, 'statuses' => $statuses, 'categories' => $categories])
            </div>
        </div>
    </div>
</x-app-layout>
